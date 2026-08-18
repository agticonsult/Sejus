"""
Asynchronous HMAC-SHA256 Signed Webhook Dispatcher with Exponential Backoff and Redis DLQ Fallback
"""

import hmac
import hashlib
import json
import asyncio
import random
import logging
from typing import Dict, Any, Optional
from datetime import datetime
import httpx
from pydantic import BaseModel
from .config import settings

logger = logging.getLogger("webrtc_service.webhooks")


class WebhookDeliveryResult(BaseModel):
    success: bool
    event: str
    room_id: Optional[str] = None
    status_code: Optional[int] = None
    attempts: int
    duration_ms: float
    error_message: Optional[str] = None


class WebhookDispatcher:
    """
    Reliable webhook dispatcher using HTTPX with HMAC-SHA256 signature,
    exponential backoff with jitter, and fallback to Redis DLQ.
    """
    def __init__(
        self,
        endpoint_url: Optional[str] = None,
        secret_key: Optional[str] = None,
        max_retries: Optional[int] = None,
        base_delay_s: Optional[float] = None,
        max_delay_s: Optional[float] = None,
        timeout_s: Optional[float] = None,
        redis_client: Optional[Any] = None
    ):
        self.endpoint_url = endpoint_url or settings.LARAVEL_WEBHOOK_URL
        self.secret_key = (secret_key or settings.WEBHOOK_SECRET).encode("utf-8")
        self.max_retries = max_retries if max_retries is not None else settings.WEBHOOK_MAX_RETRIES
        self.base_delay_s = base_delay_s if base_delay_s is not None else settings.WEBHOOK_BASE_DELAY_SECONDS
        self.max_delay_s = max_delay_s if max_delay_s is not None else settings.WEBHOOK_MAX_DELAY_SECONDS
        self.timeout_s = timeout_s if timeout_s is not None else settings.WEBHOOK_TIMEOUT_SECONDS
        self.redis_client = redis_client
        self._http_client: Optional[httpx.AsyncClient] = None

    async def get_client(self) -> httpx.AsyncClient:
        if self._http_client is None or self._http_client.is_closed:
            self._http_client = httpx.AsyncClient(
                timeout=httpx.Timeout(self.timeout_s, connect=3.0),
                limits=httpx.Limits(max_keepalive_connections=20, max_connections=100)
            )
        return self._http_client

    async def close(self):
        if self._http_client and not self._http_client.is_closed:
            await self._http_client.aclose()

    def generate_signature(self, payload_bytes: bytes) -> str:
        """Computes HMAC-SHA256 hex digest for canonical request body."""
        return hmac.new(self.secret_key, payload_bytes, hashlib.sha256).hexdigest()

    async def dispatch(
        self,
        event_type: str,
        room_id: Optional[str],
        payload_data: Dict[str, Any]
    ) -> WebhookDeliveryResult:
        """
        Dispatches a signed webhook payload asynchronously with retries.
        """
        loop = asyncio.get_event_loop()
        start_time = loop.time()

        envelope = {
            "event": event_type,
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "room_id": room_id,
            "data": payload_data
        }

        body_bytes = json.dumps(envelope, ensure_ascii=False, separators=(',', ':')).encode("utf-8")
        signature_hex = self.generate_signature(body_bytes)

        headers = {
            "Content-Type": "application/json",
            "X-Signature": f"sha256={signature_hex}",
            "X-Signature-SHA256": signature_hex,
            "X-Webhook-Timestamp": str(int(datetime.utcnow().timestamp())),
            "User-Agent": "ConectaEgresso-WebRTC-Dispatcher/1.0"
        }

        client = await self.get_client()
        last_error = None
        last_status = None

        for attempt in range(1, self.max_retries + 1):
            try:
                logger.info(f"Dispatching webhook '{event_type}' (Attempt {attempt}/{self.max_retries})")
                response = await client.post(self.endpoint_url, content=body_bytes, headers=headers)
                last_status = response.status_code

                if response.is_success:
                    elapsed = (loop.time() - start_time) * 1000.0
                    logger.info(f"Webhook '{event_type}' delivered (HTTP {response.status_code}, {elapsed:.1f}ms)")
                    return WebhookDeliveryResult(
                        success=True,
                        event=event_type,
                        room_id=room_id,
                        status_code=response.status_code,
                        attempts=attempt,
                        duration_ms=round(elapsed, 2)
                    )

                # Client errors (4xx except 429) are non-retryable
                if 400 <= response.status_code < 500 and response.status_code != 429:
                    last_error = f"Non-retryable HTTP {response.status_code}: {response.text[:200]}"
                    logger.error(last_error)
                    break

                last_error = f"HTTP {response.status_code}: {response.text[:200]}"

            except (httpx.ConnectError, httpx.TimeoutException, httpx.NetworkError) as exc:
                last_error = f"{type(exc).__name__}: {str(exc)}"
                logger.warning(f"Network error on attempt {attempt}: {last_error}")

            # Sleep with exponential backoff + jitter if retry attempts remain
            if attempt < self.max_retries:
                base = min(self.max_delay_s, self.base_delay_s * (2 ** (attempt - 1)))
                jitter = random.uniform(-0.2 * base, 0.2 * base)
                sleep_delay = max(0.01, base + jitter)
                logger.info(f"Waiting {sleep_delay:.2f}s before retry attempt {attempt + 1}")
                await asyncio.sleep(sleep_delay)

        elapsed = (loop.time() - start_time) * 1000.0
        logger.error(f"All {self.max_retries} attempts failed for webhook '{event_type}'. Escalating to DLQ.")

        # Persist to Redis Dead-Letter Queue (DLQ) if Redis client is available
        if self.redis_client:
            dlq_entry = {
                "event": event_type,
                "room_id": room_id,
                "url": self.endpoint_url,
                "envelope": envelope,
                "headers": headers,
                "failed_at": datetime.utcnow().isoformat(),
                "attempts": self.max_retries,
                "last_status": last_status,
                "error": last_error
            }
            try:
                await self.redis_client.rpush("webrtc:webhook_dlq", json.dumps(dlq_entry))
                logger.info("Persisted failed webhook into Redis DLQ (key: webrtc:webhook_dlq)")
            except Exception as redis_exc:
                logger.critical(f"Failed to persist webhook to Redis DLQ: {redis_exc}")

        attempts_made = attempt if 'attempt' in locals() else self.max_retries
        return WebhookDeliveryResult(
            success=False,
            event=event_type,
            room_id=room_id,
            status_code=last_status,
            attempts=attempts_made,
            duration_ms=round(elapsed, 2),
            error_message=last_error
        )



# Global webhook dispatcher instance
webhook_dispatcher = WebhookDispatcher()
