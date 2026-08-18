"""
Full End-to-End Integration Test: Queue -> Admission -> Signaling -> Telemetry -> Webhook
"""

import pytest
import respx
import httpx
import hmac
import hashlib
import json
import asyncio
from app.config import settings
from app.queue_manager import queue_websocket_endpoint
from app.signaling import signaling_websocket_endpoint, webhook_dispatcher


@pytest.mark.asyncio
@respx.mock
async def test_full_consultation_e2e_lifecycle(token_factory, ws_session_factory, sample_cellular_report):
    # Mock Laravel Webhook Receiver
    webhook_route = respx.post(settings.LARAVEL_WEBHOOK_URL).mock(
        return_value=httpx.Response(200, json={"status": "recorded", "timeline_id": 999})
    )

    unit_id = "3205002"  # São Mateus
    room_id = "sala-e2e-sm-101"
    prontuario_id = "550e8400-e29b-41d4-a716-446655440000"

    token_tech = token_factory(
        user_id=101,
        name="Dra. Marcia Oliveira",
        role="tecnico",
        room_id=room_id,
        unit_id=unit_id,
        prontuario_id=prontuario_id
    )
    token_att = token_factory(
        user_id=502,
        name="Lucas Santos",
        role="egresso",
        room_id=room_id,
        unit_id=unit_id,
        prontuario_id=prontuario_id
    )

    # -------------------------------------------------------------
    # Step 1 & 2: Waiting Room Queue & Admission
    # -------------------------------------------------------------
    ws_queue_att = ws_session_factory()
    task_q_att = asyncio.create_task(queue_websocket_endpoint(ws_queue_att, unit_id=unit_id, token=token_att))

    # Attendee joins queue
    await ws_queue_att.inbox.put({
        "type": "join_queue",
        "name": "Lucas Santos",
        "municipio": "São Mateus",
        "prioridade": "urgente"
    })
    queue_joined = await asyncio.wait_for(ws_queue_att.outbox.get(), timeout=2.0)
    assert queue_joined["type"] == "queue_joined"
    ticket_id = queue_joined["ticket_id"]

    ws_queue_tech = ws_session_factory()
    task_q_tech = asyncio.create_task(queue_websocket_endpoint(ws_queue_tech, unit_id=unit_id, token=token_tech))
    _ = await asyncio.wait_for(ws_queue_tech.outbox.get(), timeout=2.0)  # queue_status

    # Tech admits attendee
    await ws_queue_tech.inbox.put({
        "type": "admit_attendee",
        "ticket_id": ticket_id,
        "room_id": room_id
    })

    # Attendee receives call notification
    call_msg = await asyncio.wait_for(ws_queue_att.outbox.get(), timeout=2.0)
    assert call_msg["type"] == "call_attendee"
    assert call_msg["room_id"] == room_id

    # Cleanup queue sockets
    await ws_queue_att.close()
    await ws_queue_tech.close()
    await asyncio.gather(task_q_att, task_q_tech, return_exceptions=True)

    # -------------------------------------------------------------
    # Step 3, 4 & 5: Video Room Signaling & Perfect Negotiation
    # -------------------------------------------------------------
    ws_sig_tech = ws_session_factory()
    task_sig_tech = asyncio.create_task(signaling_websocket_endpoint(ws_sig_tech, room_id=room_id, token=token_tech))
    ack_tech = await asyncio.wait_for(ws_sig_tech.outbox.get(), timeout=2.0)
    assert ack_tech["type"] == "joined"
    assert ack_tech["polite"] is False  # Impolite Host
    cid_tech = ack_tech["client_id"]

    ws_sig_att = ws_session_factory()
    task_sig_att = asyncio.create_task(signaling_websocket_endpoint(ws_sig_att, room_id=room_id, token=token_att))
    ack_att = await asyncio.wait_for(ws_sig_att.outbox.get(), timeout=2.0)
    assert ack_att["type"] == "joined"
    assert ack_att["polite"] is True  # Polite Attendee
    cid_att = ack_att["client_id"]

    # Tech receives peer_joined
    peer_joined_msg = await asyncio.wait_for(ws_sig_tech.outbox.get(), timeout=2.0)
    assert peer_joined_msg["type"] == "peer_joined"

    # -------------------------------------------------------------
    # Step 6: SDP Offer & Answer Exchange
    # -------------------------------------------------------------
    offer_sdp = "v=0\r\no=- 12345 2 IN IP4 127.0.0.1\r\ns=E2E Consultation\r\n"
    await ws_sig_tech.inbox.put({
        "type": "offer",
        "target_client_id": cid_att,
        "sdp": offer_sdp
    })

    offer_recv = await asyncio.wait_for(ws_sig_att.outbox.get(), timeout=2.0)
    assert offer_recv["type"] == "offer"
    assert offer_recv["sdp"] == offer_sdp

    answer_sdp = "v=0\r\no=- 12346 2 IN IP4 127.0.0.1\r\ns=E2E Answer\r\n"
    await ws_sig_att.inbox.put({
        "type": "answer",
        "target_client_id": cid_tech,
        "sdp": answer_sdp
    })

    answer_recv = await asyncio.wait_for(ws_sig_tech.outbox.get(), timeout=2.0)
    assert answer_recv["type"] == "answer"
    assert answer_recv["sdp"] == answer_sdp

    # -------------------------------------------------------------
    # Step 7: Trickle ICE Exchange
    # -------------------------------------------------------------
    candidate_tech = {"candidate": "candidate:1 1 UDP 2130706431 10.0.0.1 50000 typ host"}
    await ws_sig_tech.inbox.put({
        "type": "ice_candidate",
        "target_client_id": cid_att,
        "candidate": candidate_tech
    })
    ice_recv = await asyncio.wait_for(ws_sig_att.outbox.get(), timeout=2.0)
    assert ice_recv["type"] == "ice_candidate"

    # -------------------------------------------------------------
    # Step 8: Telemetry Streaming & MOS Calculation
    # -------------------------------------------------------------
    await ws_sig_att.inbox.put({
        "type": "telemetry",
        **sample_cellular_report
    })
    telemetry_ack = await asyncio.wait_for(ws_sig_att.outbox.get(), timeout=2.0)
    assert telemetry_ack["type"] == "telemetry_ack"
    assert telemetry_ack["mos"] >= 4.0

    # -------------------------------------------------------------
    # Step 9: Consultation Conclusion & Teardown
    # -------------------------------------------------------------
    await ws_sig_tech.inbox.put({
        "type": "terminate_room",
        "reason": "attendance_completed"
    })

    # Both receive room_terminated
    term_att = await asyncio.wait_for(ws_sig_att.outbox.get(), timeout=2.0)
    assert term_att["type"] == "room_terminated"

    await ws_sig_att.close()
    await ws_sig_tech.close()
    await asyncio.gather(task_sig_att, task_sig_tech, return_exceptions=True)

    # -------------------------------------------------------------
    # Step 10: Webhook Verification with HMAC-SHA256
    # -------------------------------------------------------------
    # Allow background webhook tasks to complete
    await asyncio.sleep(0.2)

    assert webhook_route.called
    session_ended_call = None
    for call in webhook_route.calls:
        body = json.loads(call.request.content.decode("utf-8"))
        if body.get("event") == "session.ended":
            session_ended_call = call
            break

    assert session_ended_call is not None
    ended_body = json.loads(session_ended_call.request.content.decode("utf-8"))
    assert ended_body["event"] == "session.ended"
    assert ended_body["room_id"] == room_id
    assert "summary_telemetry" in ended_body["data"]

    # Verify HMAC Header
    signature_header = session_ended_call.request.headers.get("X-Signature")
    assert signature_header.startswith("sha256=")
    expected_hmac = hmac.new(
        settings.WEBHOOK_SECRET.encode("utf-8"),
        session_ended_call.request.content,
        hashlib.sha256
    ).hexdigest()
    assert signature_header == f"sha256={expected_hmac}"
