"""
Unit & Integration Tests for Webhook Dispatcher & HMAC-SHA256 Signing (app/webhooks.py)
"""

import pytest
import respx
import httpx
import hmac
import hashlib
import json
from unittest.mock import AsyncMock
from app.webhooks import WebhookDispatcher, WebhookDeliveryResult


@pytest.mark.asyncio
@respx.mock
async def test_webhook_successful_delivery(test_webhook_url, test_webhook_secret):
    route = respx.post(test_webhook_url).mock(return_value=httpx.Response(200, json={"status": "received"}))

    dispatcher = WebhookDispatcher(
        endpoint_url=test_webhook_url,
        secret_key=test_webhook_secret,
        max_retries=3,
        base_delay_s=0.01
    )

    payload = {"room_code": "ATD-101", "duration_seconds": 300}
    res = await dispatcher.dispatch("session.ended", "room-101", payload)

    assert res.success is True
    assert res.status_code == 200
    assert res.attempts == 1
    assert route.called

    # Verify cryptographic signature
    request = route.calls.last.request
    expected_sig = hmac.new(test_webhook_secret.encode("utf-8"), request.content, hashlib.sha256).hexdigest()
    assert request.headers["X-Signature"] == f"sha256={expected_sig}"
    assert request.headers["X-Signature-SHA256"] == expected_sig

    await dispatcher.close()


@pytest.mark.asyncio
@respx.mock
async def test_webhook_retry_on_500_then_succeed(test_webhook_url, test_webhook_secret):
    route = respx.post(test_webhook_url)
    route.side_effect = [
        httpx.Response(500, text="Internal Error"),
        httpx.Response(503, text="Service Unavailable"),
        httpx.Response(200, json={"status": "ok"})
    ]

    dispatcher = WebhookDispatcher(
        endpoint_url=test_webhook_url,
        secret_key=test_webhook_secret,
        max_retries=3,
        base_delay_s=0.01,
        max_delay_s=0.02
    )

    res = await dispatcher.dispatch("session.started", "room-123", {"user_id": 1})
    assert res.success is True
    assert res.attempts == 3
    assert route.call_count == 3

    await dispatcher.close()


@pytest.mark.asyncio
@respx.mock
async def test_webhook_non_retryable_400_error(test_webhook_url, test_webhook_secret):
    route = respx.post(test_webhook_url).mock(return_value=httpx.Response(400, text="Bad Request"))

    dispatcher = WebhookDispatcher(
        endpoint_url=test_webhook_url,
        secret_key=test_webhook_secret,
        max_retries=4,
        base_delay_s=0.01
    )

    res = await dispatcher.dispatch("session.started", "room-400", {})
    assert res.success is False
    assert res.attempts == 1  # Bails immediately on 400
    assert route.call_count == 1

    await dispatcher.close()


@pytest.mark.asyncio
@respx.mock
async def test_webhook_exhaustion_persists_to_dlq(test_webhook_url, test_webhook_secret, mock_redis):
    respx.post(test_webhook_url).mock(return_value=httpx.Response(500, text="Fatal Server Error"))

    dispatcher = WebhookDispatcher(
        endpoint_url=test_webhook_url,
        secret_key=test_webhook_secret,
        max_retries=2,
        base_delay_s=0.01,
        redis_client=mock_redis
    )

    res = await dispatcher.dispatch("session.ended", "room-dlq-test", {"key": "value"})
    assert res.success is False
    assert res.attempts == 2
    assert mock_redis.rpush.called

    # Check DLQ key
    call_args = mock_redis.rpush.call_args[0]
    assert call_args[0] == "webrtc:webhook_dlq"
    dlq_data = json.loads(call_args[1])
    assert dlq_data["event"] == "session.ended"
    assert dlq_data["room_id"] == "room-dlq-test"

    await dispatcher.close()
