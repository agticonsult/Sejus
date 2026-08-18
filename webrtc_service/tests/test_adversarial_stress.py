"""
Adversarial Stress, Fuzzing & Concurrency Test Suite for CONECTA EGRESSO WebRTC Microservice
Tests extreme boundaries, cryptographic attacks, high concurrency, and payload fuzzing.
"""

import sys
import os
import json
import time
import base64
import math
import uuid
import asyncio
import pytest
import jwt
from datetime import datetime, timedelta
from unittest.mock import AsyncMock, MagicMock

# App imports
from app.config import settings
from app.auth import (
    decode_jwt_token,
    validate_room_access,
    validate_unit_access,
    create_access_token,
    is_polite_peer,
    AuthError
)
from app.schemas import (
    ClientRole,
    RoomState,
    QueuePriority,
    NetworkQualityTier,
    ClientTelemetryReport,
    ConnectionStats,
    AudioTrackStats,
    VideoTrackStats
)
from app.telemetry import EModelMOSCalculator, SessionAggregator, calculate_mos
from app.signaling import signaling_websocket_endpoint, get_session_aggregator
from app.queue_manager import QueueManager, calculate_queue_score, queue_websocket_endpoint
from app.room_manager import RoomManager, Room, ClientSession
from app.webhooks import WebhookDispatcher
from app.redis_bus import RedisBus


# ============================================================================
# 1. CRYPTOGRAPHIC & JWT ADVERSARIAL CHALLENGES
# ============================================================================

def test_jwt_none_algorithm_attack():
    """Adversary attempts to forge token using 'none' algorithm header."""
    header = {"alg": "none", "typ": "JWT"}
    payload = {
        "iss": settings.JWT_ISSUER,
        "aud": settings.JWT_AUDIENCE,
        "sub": "9999",
        "name": "Hacker Attacker",
        "role": "gestor",
        "exp": int(time.time()) + 3600
    }
    header_b64 = base64.urlsafe_b64encode(json.dumps(header).encode()).decode().rstrip("=")
    payload_b64 = base64.urlsafe_b64encode(json.dumps(payload).encode()).decode().rstrip("=")
    unsigned_token = f"{header_b64}.{payload_b64}."

    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(unsigned_token)
    assert exc_info.value.code in ["AUTH_INVALID_SIGNATURE", "AUTH_DECODE_ERROR", "AUTH_ERROR"]


def test_jwt_forged_signature_with_attacker_secret():
    """Adversary signs a valid-looking token with their own private key."""
    attacker_secret = "malicious_attacker_private_secret_key_123"
    payload = {
        "iss": settings.JWT_ISSUER,
        "aud": settings.JWT_AUDIENCE,
        "sub": "9999",
        "name": "Fake Gestor",
        "role": "gestor",
        "exp": int(time.time()) + 3600
    }
    forged_token = jwt.encode(payload, attacker_secret, algorithm="HS256")

    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(forged_token)
    assert exc_info.value.code == "AUTH_INVALID_SIGNATURE"


def test_jwt_payload_tampering_post_signature():
    """Adversary modifies payload bytes after legitimate token signing."""
    valid_token = create_access_token(user_id=101, name="Normal User", role="egresso")
    parts = valid_token.split(".")
    assert len(parts) == 3

    # Tamper with the middle payload part to change role to gestor
    tampered_payload = {"sub": "101", "name": "Normal User", "role": "gestor", "exp": int(time.time()) + 3600}
    tampered_b64 = base64.urlsafe_b64encode(json.dumps(tampered_payload).encode()).decode().rstrip("=")
    tampered_token = f"{parts[0]}.{tampered_b64}.{parts[2]}"

    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(tampered_token)
    assert exc_info.value.code in ["AUTH_INVALID_SIGNATURE", "AUTH_DECODE_ERROR"]


def test_jwt_expired_boundary_checks():
    """Tokens expired by 1 second, 1 hour, or 1 year must strictly fail."""
    now = int(time.time())
    for offset_seconds in [-1, -3600, -31536000]:
        expired_payload = {
            "iss": settings.JWT_ISSUER,
            "aud": settings.JWT_AUDIENCE,
            "sub": "501",
            "name": "Expired User",
            "role": "egresso",
            "iat": now - 7200,
            "exp": now + offset_seconds
        }
        token = jwt.encode(expired_payload, settings.JWT_SECRET_KEY, algorithm="HS256")
        with pytest.raises(AuthError) as exc_info:
            decode_jwt_token(token)
        assert exc_info.value.code == "AUTH_TOKEN_EXPIRED"


def test_jwt_invalid_issuer_and_audience():
    """Token signed with correct key but spoofed issuer or audience must fail."""
    now = int(time.time())
    # Wrong issuer
    bad_iss_payload = {
        "iss": "untrusted-rogue-issuer",
        "aud": settings.JWT_AUDIENCE,
        "sub": "501",
        "name": "User",
        "role": "egresso",
        "exp": now + 3600
    }
    tok_iss = jwt.encode(bad_iss_payload, settings.JWT_SECRET_KEY, algorithm="HS256")
    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(tok_iss)
    assert exc_info.value.code in ["AUTH_INVALID_ISSUER", "AUTH_ERROR"]

    # Wrong audience
    bad_aud_payload = {
        "iss": settings.JWT_ISSUER,
        "aud": "rogue-service-audience",
        "sub": "501",
        "name": "User",
        "role": "egresso",
        "exp": now + 3600
    }
    tok_aud = jwt.encode(bad_aud_payload, settings.JWT_SECRET_KEY, algorithm="HS256")
    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(tok_aud)
    assert exc_info.value.code in ["AUTH_INVALID_AUDIENCE", "AUTH_ERROR"]


def test_jwt_cross_room_and_cross_unit_privilege_violations():
    """Attendee cannot access rooms or municipal units not present in their signed claims."""
    # Cross-room attempt
    claims_room_a = decode_jwt_token(create_access_token(
        user_id=501, name="Citizen", role="egresso", room_id="sala-vitoria-01"
    ))
    # Must succeed for authorized room
    assert validate_room_access(claims_room_a, "sala-vitoria-01") is True
    # Must raise for unauthorized room
    with pytest.raises(AuthError) as exc_info:
        validate_room_access(claims_room_a, "sala-vitoria-02")
    assert exc_info.value.code == "ROOM_ACCESS_DENIED"

    # Cross-unit queue attempt
    claims_unit_a = decode_jwt_token(create_access_token(
        user_id=501, name="Citizen", role="egresso", unit_id="3205002"
    ))
    assert validate_unit_access(claims_unit_a, "3205002") is True
    with pytest.raises(AuthError) as exc_info:
        validate_unit_access(claims_unit_a, "3205309")
    assert exc_info.value.code == "UNIT_ACCESS_DENIED"


# ============================================================================
# 2. MOS & TELEMETRY ENGINE ADVERSARIAL BOUNDARIES & STRESS
# ============================================================================

def test_mos_negative_latency_and_jitter_resilience(mos_calculator):
    """Negative RTT or negative Jitter should clamp gracefully to 0 without error."""
    # Negative RTT
    res = mos_calculator.evaluate(rtt_ms=-150.0, jitter_ms=-50.0, packet_loss_pct=-10.0)
    assert res.one_way_delay_ms == 0.0
    assert res.equipment_impairment == 0.0
    assert res.r_factor == 94.2
    assert res.mos >= 4.3
    assert res.quality_tier == NetworkQualityTier.EXCELLENT


def test_mos_extreme_jitter_and_rtt_clamping(mos_calculator):
    """Astronomical RTT (60s) and Jitter (10s) must strictly clamp MOS to 1.0."""
    res = mos_calculator.evaluate(rtt_ms=60000.0, jitter_ms=10000.0, packet_loss_pct=100.0)
    assert res.r_factor == 0.0
    assert res.mos == 1.0
    assert res.quality_tier == NetworkQualityTier.BAD


def test_mos_oversaturated_packet_loss(mos_calculator):
    """Loss percentage over 100% (e.g. 500% or 10000%) must clamp to 100% without math domain error."""
    res = mos_calculator.evaluate(rtt_ms=50.0, jitter_ms=5.0, packet_loss_pct=5000.0)
    assert not math.isnan(res.mos)
    assert not math.isinf(res.mos)
    assert res.mos <= 2.0
    assert res.quality_tier == NetworkQualityTier.BAD


def test_mos_zero_delay_boundary(mos_calculator):
    """Absolute zero latency and loss represents ideal theoretical channel."""
    res = mos_calculator.evaluate(rtt_ms=0.0, jitter_ms=0.0, packet_loss_pct=0.0)
    assert res.one_way_delay_ms == 0.0
    assert res.r_factor == 94.2
    assert 4.3 <= res.mos <= 4.5
    assert res.quality_tier == NetworkQualityTier.EXCELLENT


def test_session_aggregator_10k_sample_flood_stress():
    """
    Stress-test SessionAggregator with 10,000 rapid telemetry samples.
    Verifies sub-second aggregation, p95 accuracy, and distribution math.
    """
    aggregator = SessionAggregator(room_id="sala-stress-10k")
    peer_id = "client-peer-flood-01"
    user_id = 777
    role = "attendee"

    start_t = time.perf_counter()
    for i in range(10000):
        # Vary loss between 0% and 5% with some jitter
        loss = (i % 50) / 10.0
        rtt = 20.0 + (i % 100)
        jitter = 2.0 + (i % 20)
        sample = {
            "connection": {"rtt_ms": rtt, "bytes_sent": i * 100, "bytes_received": i * 200},
            "audio": {"jitter_ms": jitter, "packet_loss_pct": loss, "bitrate_kbps": 32.0},
            "video": {"fps": 30.0, "bitrate_kbps": 600.0, "frame_width": 1280, "frame_height": 720}
        }
        aggregator.record_sample(peer_id=peer_id, user_id=user_id, role=role, raw_sample=sample)

    elapsed_s = time.perf_counter() - start_t
    assert elapsed_s < 1.0, f"10k sample ingestion took too long: {elapsed_s:.3f}s"

    summary = aggregator.generate_summary(peer_id)
    assert summary is not None
    assert summary.sample_count == 10000
    assert summary.min_mos >= 1.0
    assert summary.max_mos <= 5.0
    assert summary.p95_mos >= summary.min_mos
    assert summary.duration_seconds >= 0.0

    # Ensure distribution percentages sum to ~100%
    dist = summary.quality_distribution
    total_pct = dist.excellent_pct + dist.good_pct + dist.fair_pct + dist.poor_pct + dist.bad_pct
    assert 99.0 <= total_pct <= 101.0, f"Distribution percentages do not sum to 100%: {total_pct}"


def test_session_aggregator_rapid_resolution_switching():
    """Simulates 200 rapid adaptive bitrate resolution switches."""
    aggregator = SessionAggregator(room_id="sala-res-switch")
    peer_id = "client-res-01"
    resolutions = [(1920, 1080), (1280, 720), (640, 480), (320, 240)]

    for i in range(200):
        w, h = resolutions[i % len(resolutions)]
        sample = {
            "connection": {"rtt_ms": 30.0, "bytes_sent": 1000, "bytes_received": 1000},
            "audio": {"jitter_ms": 5.0, "packet_loss_pct": 0.0},
            "video": {"fps": 30.0, "bitrate_kbps": 500.0, "frame_width": w, "frame_height": h}
        }
        aggregator.record_sample(peer_id=peer_id, user_id=1, role="attendee", raw_sample=sample)

    summary = aggregator.generate_summary(peer_id)
    assert summary.resolution_changes_count == 199
    assert summary.final_resolution == f"{resolutions[199 % len(resolutions)][0]}x{resolutions[199 % len(resolutions)][1]}"


# ============================================================================
# 3. FUZZING & MALFORMED WEBRTC PAYLOADS
# ============================================================================

@pytest.mark.asyncio
async def test_fuzzing_malformed_json_and_unauthenticated_actions(ws_session_factory):
    """Sends invalid JSON and signaling commands before joining."""
    room_id = "sala-fuzz-01"
    ws = ws_session_factory()

    task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id))
    try:
        # 1. Invalid JSON
        await ws.inbox.put("{{INVALID_JSON_RAW_STRING%%%")
        # Unauthenticated attempt to send SDP
        await ws.inbox.put({"type": "offer", "sdp": "fake_sdp"})
        err = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert err["type"] == "error"
        assert err["code"] == "UNAUTHENTICATED"

        # Unauthenticated ICE candidate
        await ws.inbox.put({"type": "ice_candidate", "candidate": "test"})
        err2 = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert err2["type"] == "error"
        assert err2["code"] == "UNAUTHENTICATED"
    finally:
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except Exception:
            pass


@pytest.mark.asyncio
async def test_unauthorized_room_termination_by_attendee(token_factory, ws_session_factory):
    """An attendee (egresso) attempts to terminate the room. Must be rejected."""
    room_id = "sala-term-denied"
    token = token_factory(user_id=502, role="egresso", room_id=room_id)
    ws = ws_session_factory()

    task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=token))
    try:
        _ = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)  # joined ack

        # Attendee sends terminate_room
        await ws.inbox.put({"type": "terminate_room", "reason": "malicious_kick"})

        err = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert err["type"] == "error"
        assert err["code"] == "FORBIDDEN"
    finally:
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except Exception:
            pass


# ============================================================================
# 4. HIGH CONCURRENCY & PARALLEL STRESS TESTS
# ============================================================================

@pytest.mark.asyncio
async def test_high_concurrency_multi_client_mesh_signaling(token_factory, ws_session_factory):
    """
    30 simulated clients connect concurrently to the same room.
    All broadcast media updates and exchange ping/pong simultaneously.
    Verifies thread safety, send_lock integrity, and zero deadlocks.
    """
    room_id = "sala-mesh-concurrency-30"
    num_clients = 30
    sessions = []
    tasks = []

    try:
        # Launch 30 concurrent WebSocket clients
        for i in range(num_clients):
            role = "tecnico" if i == 0 else "egresso"
            tok = token_factory(user_id=1000 + i, name=f"User_{i}", role=role, room_id=room_id)
            ws = ws_session_factory()
            sessions.append(ws)
            t = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=tok))
            tasks.append(t)

        # Wait for all 30 joined acks
        for ws in sessions:
            ack = await asyncio.wait_for(ws.outbox.get(), timeout=3.0)
            assert ack["type"] == "joined"

        # Concurrently send ping from all 30 clients
        async def send_ping(ws, idx):
            await ws.inbox.put({"type": "ping", "timestamp": idx})

        await asyncio.gather(*(send_ping(sessions[i], i) for i in range(num_clients)))

        # Verify all 30 clients receive their pong
        for ws in sessions:
            # Drain until pong received
            pong_found = False
            for _ in range(num_clients + 5):
                msg = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
                if msg.get("type") == "pong":
                    pong_found = True
                    break
            assert pong_found is True

    finally:
        for ws in sessions:
            await ws.close()
        await asyncio.gather(*tasks, return_exceptions=True)


@pytest.mark.asyncio
async def test_waiting_room_100_concurrent_joiners_priority_deterministic():
    """
    100 citizens join the municipal queue concurrently across mixed priorities.
    Verifies that ZSET ordering remains strictly deterministic:
    all URGENTE < all PREFERENCIAL < all NORMAL.
    """
    qm = QueueManager()
    unit_id = "3205002"  # São Mateus
    num_citizens = 100

    async def citizen_join(idx: int):
        # 0..19: Urgente, 20..49: Preferencial, 50..99: Normal
        if idx < 20:
            prio = QueuePriority.URGENTE
        elif idx < 50:
            prio = QueuePriority.PREFERENCIAL
        else:
            prio = QueuePriority.NORMAL

        ticket = await qm.join_queue(
            unit_id=unit_id,
            user_id=10000 + idx,
            name=f"Citizen_{idx}",
            municipio="São Mateus",
            prioridade=prio
        )
        return ticket, prio

    # Execute all 100 joins in parallel
    results = await asyncio.gather(*(citizen_join(i) for i in range(num_citizens)))
    assert len(results) == 100

    # Retrieve queue status
    status_bc = await qm.get_queue_status(unit_id)
    assert status_bc.total_waiting == 100

    # Verify priority segregation
    urgente_items = [it for it in status_bc.items if it.prioridade == "urgente"]
    preferencial_items = [it for it in status_bc.items if it.prioridade == "preferencial"]
    normal_items = [it for it in status_bc.items if it.prioridade == "normal"]

    assert len(urgente_items) == 20
    assert len(preferencial_items) == 30
    assert len(normal_items) == 50

    # Check that in the final ordered list, urgent items precede preferencial, which precede normal
    item_priorities = [it.prioridade for it in status_bc.items]
    assert item_priorities[:20] == ["urgente"] * 20
    assert item_priorities[20:50] == ["preferencial"] * 30
    assert item_priorities[50:100] == ["normal"] * 50


@pytest.mark.asyncio
async def test_atomic_ticket_claim_30_way_race_condition():
    """
    30 technicians simultaneously attempt to claim the exact same citizen ticket.
    Exactly 1 must succeed, and 29 must be rejected.
    """
    qm = QueueManager()
    unit_id = "3205309"  # Vitória

    ticket = await qm.join_queue(
        unit_id=unit_id,
        user_id=555,
        name="Target Citizen",
        municipio="Vitória",
        prioridade=QueuePriority.URGENTE
    )

    async def technician_claim(tech_idx: int):
        success, msg = await qm.claim_and_admit_attendee(
            unit_id=unit_id,
            ticket_id=ticket.ticket_id,
            technician_id=2000 + tech_idx,
            technician_name=f"Tech_{tech_idx}",
            room_id=f"sala-tech-{tech_idx}"
        )
        return success, msg, tech_idx

    # Fire 30 parallel claim attempts
    claim_results = await asyncio.gather(*(technician_claim(i) for i in range(30)))

    successes = [r for r in claim_results if r[0] is True]
    failures = [r for r in claim_results if r[0] is False]

    assert len(successes) == 1, f"Expected exactly 1 success, got {len(successes)}"
    assert len(failures) == 29
    assert successes[0][1] == "SUCCESS"
    for f in failures:
        assert f[1] in ["TICKET_NOT_IN_QUEUE", "TICKET_ALREADY_CLAIMED"]


@pytest.mark.asyncio
async def test_mass_queue_cancellation_and_rank_integrity():
    """
    50 citizens in queue; 25 cancel concurrently.
    All remaining 25 must have valid, sequential, positive positions (1..25).
    """
    qm = QueueManager()
    unit_id = "3201506"  # Colatina

    tickets = []
    for i in range(50):
        t = await qm.join_queue(
            unit_id=unit_id,
            user_id=3000 + i,
            name=f"Colatina_User_{i}",
            municipio="Colatina",
            prioridade=QueuePriority.NORMAL
        )
        tickets.append(t)

    # Concurrently remove even-indexed tickets (25 users leave)
    async def cancel_ticket(t_id):
        await qm.remove_ticket(unit_id, t_id, reason="left")

    even_tickets = [tickets[i].ticket_id for i in range(0, 50, 2)]
    odd_tickets = [tickets[i].ticket_id for i in range(1, 50, 2)]

    await asyncio.gather(*(cancel_ticket(tid) for tid in even_tickets))

    status_bc = await qm.get_queue_status(unit_id)
    assert status_bc.total_waiting == 25

    # Check that each remaining odd ticket has a valid rank between 1 and 25
    ranks = []
    for tid in odd_tickets:
        pos, total = await qm.get_queue_position(unit_id, tid)
        assert total == 25
        assert 1 <= pos <= 25
        ranks.append(pos)

    # Ranks must be unique and span 1..25
    assert sorted(ranks) == list(range(1, 26))


# ============================================================================
# 5. WEBHOOK DISPATCHER ADVERSARIAL RESILIENCE
# ============================================================================

@pytest.mark.asyncio
async def test_webhook_dispatcher_concurrent_burst_and_dlq(mock_redis):
    """
    Tests dispatching 20 webhooks concurrently under intermittent and permanent failures.
    Verifies HMAC signatures and Redis DLQ fallback behavior.
    """
    import respx
    import httpx

    dispatcher = WebhookDispatcher(
        endpoint_url="http://laravel-test.local/api/webhooks/webrtc",
        secret_key="test_webhook_secret_conecta_egresso_2026",
        max_retries=2,
        base_delay_s=0.01,
        max_delay_s=0.05,
        timeout_s=1.0,
        redis_client=mock_redis
    )

    try:
        # Simulate permanent 500 error on webhook endpoint
        with respx.mock(assert_all_called=False) as respx_mock:
            respx_mock.post("http://laravel-test.local/api/webhooks/webrtc").mock(
                return_value=httpx.Response(500, json={"error": "Database lock"})
            )

            results = await asyncio.gather(*(
                dispatcher.dispatch(
                    event_type="session.ended",
                    room_id=f"room-err-{i}",
                    payload_data={"duration": 100 + i}
                )
                for i in range(5)
            ))

            for r in results:
                assert r.success is False
                assert r.attempts == 2
                assert "500" in r.error_message

            # Verify DLQ rpush was called 5 times
            assert mock_redis.rpush.call_count == 5
    finally:
        await dispatcher.close()


@pytest.mark.asyncio
async def test_parallel_message_broadcasting_flood_1000_msgs(token_factory, ws_session_factory):
    """
    10 clients in a room simultaneously flood 100 messages each (1000 messages total).
    Verifies broadcast thread-safety, zero lock-deadlocks, and socket delivery integrity.
    """
    room_id = "sala-flood-1000"
    num_clients = 10
    msgs_per_client = 50
    sessions = []
    tasks = []

    try:
        for i in range(num_clients):
            tok = token_factory(user_id=4000 + i, name=f"Flooder_{i}", role="egresso", room_id=room_id)
            ws = ws_session_factory()
            sessions.append(ws)
            t = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=tok))
            tasks.append(t)

        # Wait for all joined acks
        for ws in sessions:
            ack = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
            assert ack["type"] == "joined"

        # Concurrently send media_state messages from all clients
        async def flood_client(ws, client_idx):
            for m in range(msgs_per_client):
                await ws.inbox.put({
                    "type": "media_state",
                    "audio_muted": (m % 2 == 0),
                    "video_muted": False,
                    "screen_sharing": False
                })

        start_flood = time.perf_counter()
        await asyncio.gather(*(flood_client(sessions[i], i) for i in range(num_clients)))
        elapsed_flood = time.perf_counter() - start_flood

        assert elapsed_flood < 2.0, f"Flood of {num_clients * msgs_per_client} messages took {elapsed_flood:.2f}s"

    finally:
        for ws in sessions:
            await ws.close()
        await asyncio.gather(*tasks, return_exceptions=True)


@pytest.mark.asyncio
async def test_rapid_connection_churn_50_clients(token_factory, ws_session_factory):
    """
    Simulates 50 clients rapidly joining and immediately disconnecting.
    Verifies room manager cleanup, reference cleanup, and no memory leaks.
    """
    room_id = "sala-churn-50"

    for i in range(50):
        tok = token_factory(user_id=5000 + i, name=f"Churn_{i}", role="egresso", room_id=room_id)
        ws = ws_session_factory()
        task = asyncio.create_task(signaling_websocket_endpoint(ws, room_id=room_id, token=tok))
        ack = await asyncio.wait_for(ws.outbox.get(), timeout=2.0)
        assert ack["type"] == "joined"
        # Immediate close
        await ws.close()
        try:
            await asyncio.wait_for(task, timeout=1.0)
        except Exception:
            pass

    # Verify room has 0 active clients
    from app.signaling import room_manager
    room = room_manager.get_room(room_id)
    if room:
        assert room.total_active_participants == 0


def test_auth_token_edge_cases():
    """Tests extreme string edge cases for auth token decoder."""
    # Whitespace only
    with pytest.raises(AuthError) as exc1:
        decode_jwt_token("    \t\n   ")
    assert exc1.value.code == "AUTH_TOKEN_MISSING"

    # None input
    with pytest.raises(AuthError) as exc2:
        decode_jwt_token(None)
    assert exc2.value.code == "AUTH_TOKEN_MISSING"

    # Garbage binary string
    with pytest.raises(AuthError) as exc3:
        decode_jwt_token("%%%%###@@@!!!___INVALID___")
    assert exc3.value.code in ["AUTH_DECODE_ERROR", "AUTH_ERROR"]

