"""
Unit & Integration Tests for WebRTC Signaling WebSocket Router (app/signaling.py)
"""

import pytest
import asyncio
from app.signaling import signaling_websocket_endpoint
from app.schemas import ClientRole


@pytest.mark.asyncio
async def test_signaling_join_with_query_token(token_factory, ws_session_factory):
    room_id = "sala-test-01"
    token = token_factory(user_id=101, role="tecnico", room_id=room_id)
    ws = ws_session_factory()

    task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=token))
    try:
        ack = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert ack["type"] == "joined"
        assert ack["room_id"] == room_id
        assert ack["user_id"] == 101
        assert ack["role"] == "technician"
        assert ack["polite"] is False  # Technician is impolite
        assert "ice_servers" in ack
    finally:
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except (asyncio.TimeoutError, Exception):
            pass


@pytest.mark.asyncio
async def test_signaling_join_via_message(token_factory, ws_session_factory):
    room_id = "sala-test-02"
    token = token_factory(user_id=502, name="Lucas", role="egresso", room_id=room_id)
    ws = ws_session_factory()

    task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id))
    try:
        # Send join message via WebSocket
        await ws.inbox.put({
            "type": "join",
            "token": token,
            "media_state": {"audio_muted": False, "video_muted": True}
        })

        ack = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert ack["type"] == "joined"
        assert ack["user_id"] == 502
        assert ack["polite"] is True  # Attendee is polite
    finally:
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except (asyncio.TimeoutError, Exception):
            pass


@pytest.mark.asyncio
async def test_signaling_peer_joined_and_sdp_offer_answer_flow(token_factory, ws_session_factory):
    room_id = "sala-sdp-test"
    token_tech = token_factory(user_id=101, name="Dra. Marcia", role="tecnico", room_id=room_id)
    token_att = token_factory(user_id=502, name="Lucas", role="egresso", room_id=room_id)

    ws_tech = ws_session_factory()
    ws_att = ws_session_factory()

    task_tech = asyncio.create_task(signaling_websocket_endpoint(ws_tech, room_id=room_id, token=token_tech))
    try:
        ack_tech = await asyncio.wait_for(ws_tech.outbox.get(), timeout=2.0)
        cid_tech = ack_tech["client_id"]

        task_att = asyncio.create_task(signaling_websocket_endpoint(ws_att, room_id=room_id, token=token_att))
        try:
            ack_att = await asyncio.wait_for(ws_att.outbox.get(), timeout=2.0)
            cid_att = ack_att["client_id"]

            # Tech receives peer_joined notification
            peer_joined_tech = await asyncio.wait_for(ws_tech.outbox.get(), timeout=2.0)
            assert peer_joined_tech["type"] == "peer_joined"
            assert peer_joined_tech["peer"]["user_id"] == 502

            # Tech sends SDP Offer to Attendee
            fake_offer_sdp = "v=0\r\no=alice 2890844526 2890844526 IN IP4 host.example.com\r\ns=-\r\n"
            await ws_tech.inbox.put({
                "type": "offer",
                "target_client_id": cid_att,
                "sdp": fake_offer_sdp
            })

            offer_received = await asyncio.wait_for(ws_att.outbox.get(), timeout=2.0)
            assert offer_received["type"] == "offer"
            assert offer_received["sender_client_id"] == cid_tech
            assert offer_received["sdp"] == fake_offer_sdp

            # Attendee sends SDP Answer to Tech
            fake_answer_sdp = "v=0\r\no=bob 2890844527 2890844527 IN IP4 host.example.com\r\ns=-\r\n"
            await ws_att.inbox.put({
                "type": "answer",
                "target_client_id": cid_tech,
                "sdp": fake_answer_sdp
            })

            answer_received = await asyncio.wait_for(ws_tech.outbox.get(), timeout=2.0)
            assert answer_received["type"] == "answer"
            assert answer_received["sender_client_id"] == cid_att
            assert answer_received["sdp"] == fake_answer_sdp

        finally:
            await ws_att.close()
            try:
                await asyncio.wait_for(task_att, timeout=1.0)
            except Exception:
                pass
    finally:
        await ws_tech.close()
        try:
            await asyncio.wait_for(task_tech, timeout=1.0)
        except Exception:
            pass


@pytest.mark.asyncio
async def test_signaling_trickle_ice_routing(token_factory, ws_session_factory):
    room_id = "sala-ice-test"
    token1 = token_factory(user_id=1, role="tecnico", room_id=room_id)
    token2 = token_factory(user_id=2, role="egresso", room_id=room_id)

    ws1 = ws_session_factory()
    ws2 = ws_session_factory()

    task1 = asyncio.create_task(signaling_websocket_endpoint(ws1, room_id=room_id, token=token1))
    try:
        ack1 = await asyncio.wait_for(ws1.outbox.get(), timeout=2.0)
        cid1 = ack1["client_id"]

        task2 = asyncio.create_task(signaling_websocket_endpoint(ws2, room_id=room_id, token=token2))
        try:
            ack2 = await asyncio.wait_for(ws2.outbox.get(), timeout=2.0)
            cid2 = ack2["client_id"]
            _ = await asyncio.wait_for(ws1.outbox.get(), timeout=2.0)  # peer_joined

            # Send ICE candidate
            candidate_payload = {
                "candidate": "candidate:842163049 1 udp 1677729535 192.168.1.2 54321 typ host",
                "sdpMid": "0",
                "sdpMLineIndex": 0
            }
            await ws1.inbox.put({
                "type": "ice_candidate",
                "target_client_id": cid2,
                "candidate": candidate_payload
            })

            ice_recv = await asyncio.wait_for(ws2.outbox.get(), timeout=2.0)
            assert ice_recv["type"] == "ice_candidate"
            assert ice_recv["sender_client_id"] == cid1
            assert ice_recv["candidate"]["candidate"] == candidate_payload["candidate"]

        finally:
            await ws2.close()
            try:
                await asyncio.wait_for(task2, timeout=1.0)
            except Exception:
                pass
    finally:
        await ws1.close()
        try:
            await asyncio.wait_for(task1, timeout=1.0)
        except Exception:
            pass


@pytest.mark.asyncio
async def test_signaling_media_state_broadcast(token_factory, ws_session_factory):
    room_id = "sala-media-test"
    token1 = token_factory(user_id=1, role="tecnico", room_id=room_id)
    token2 = token_factory(user_id=2, role="egresso", room_id=room_id)

    ws1 = ws_session_factory()
    ws2 = ws_session_factory()

    task1 = asyncio.create_task(signaling_websocket_endpoint(ws1, room_id=room_id, token=token1))
    try:
        _ = await asyncio.wait_for(ws1.outbox.get(), timeout=2.0)

        task2 = asyncio.create_task(signaling_websocket_endpoint(ws2, room_id=room_id, token=token2))
        try:
            _ = await asyncio.wait_for(ws2.outbox.get(), timeout=2.0)
            _ = await asyncio.wait_for(ws1.outbox.get(), timeout=2.0)  # peer_joined

            # Client 2 mutes audio and starts screen sharing
            await ws2.inbox.put({
                "type": "media_state",
                "audio_muted": True,
                "video_muted": False,
                "screen_sharing": True
            })

            update_recv = await asyncio.wait_for(ws1.outbox.get(), timeout=2.0)
            assert update_recv["type"] == "peer_media_updated"
            assert update_recv["user_id"] == 2
            assert update_recv["media_state"]["audio_muted"] is True
            assert update_recv["media_state"]["screen_sharing"] is True

        finally:
            await ws2.close()
            try:
                await asyncio.wait_for(task2, timeout=1.0)
            except Exception:
                pass
    finally:
        await ws1.close()
        try:
            await asyncio.wait_for(task1, timeout=1.0)
        except Exception:
            pass


@pytest.mark.asyncio
async def test_signaling_telemetry_and_alert_handling(token_factory, ws_session_factory, sample_degraded_report):
    room_id = "sala-telemetry-test"
    token = token_factory(user_id=502, role="egresso", room_id=room_id)
    ws = ws_session_factory()

    task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=token))
    try:
        _ = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)  # joined

        # Send degraded telemetry
        await ws.inbox.put({
            "type": "telemetry",
            **sample_degraded_report
        })

        ack = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert ack["type"] == "telemetry_ack"
        assert "mos" in ack
        assert ack["mos"] < 3.2

        # Receive quality alert
        alert = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert alert["type"] == "quality_alert"
        assert alert["level"] in ["poor", "critical"]
        assert alert["suggestion"] == "disable_video"
    finally:
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except Exception:
            pass


@pytest.mark.asyncio
async def test_signaling_ping_pong(token_factory, ws_session_factory):
    room_id = "sala-ping-test"
    token = token_factory(user_id=1, role="tecnico", room_id=room_id)
    ws = ws_session_factory()

    task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=token))
    try:
        _ = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)

        await ws.inbox.put({"type": "ping", "timestamp": 123456789})
        pong = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert pong["type"] == "pong"
        assert pong["timestamp"] == 123456789
        assert "server_time" in pong
    finally:
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except Exception:
            pass
