## 2026-08-17T12:21:38Z
You are Worker 1 (WebRTC Microservice Developer) for Milestone M4 of CONECTA EGRESSO.
Your working directory is: d:\Agile\projeto dia 18\.agents\worker_m4_1
You have EXCLUSIVE write ownership of: `d:\Agile\projeto dia 18\webrtc_service\`

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

MANDATORY INPUTS TO READ FIRST:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\.agents\sub_orch_m4_webrtc\SCOPE.md`
- `d:\Agile\projeto dia 18\.agents\explorer_m4_1\analysis.md`
- `d:\Agile\projeto dia 18\.agents\explorer_m4_2\analysis.md`
- `d:\Agile\projeto dia 18\.agents\explorer_m4_3\analysis.md`

OBJECTIVES & REQUIREMENTS:
1. Create all files for the asynchronous Python FastAPI WebRTC microservice in `d:\Agile\projeto dia 18\webrtc_service\`:
   - `requirements.txt`
   - `pytest.ini`
   - `.env.example`
   - `app/__init__.py`
   - `app/config.py` (Pydantic Settings: JWT secrets, Redis URL, Coturn STUN/TURN, Webhook URL & HMAC secret, CORS origins)
   - `app/schemas.py` (Pydantic v2 schemas: WebSocket messages, Telemetry report, Webhook payloads for session.started, session.ended, session.error, attendee.joined_queue, attendee.admitted, Queue tickets, Error models)
   - `app/auth.py` (JWT decoding, HS256 verification, room_id/unit_id scope matching, role validation: technician, attendee, observer)
   - `app/redis_bus.py` (Async Redis Pub/Sub client, channel subscriptions: room:{room_id}:events, queue:{unit_id}:events, message serialization, loopback prevention via origin_worker_id, graceful mock fallback when Redis unavailable)
   - `app/room_manager.py` (Room state machine: created, waiting, in_progress, reconnecting, ended, expired; per-connection async SendLock to prevent Starlette concurrency errors; participant tracking; disconnect grace period; cleanup daemon)
   - `app/queue_manager.py` (Waiting room queue for 78 ES municipalities, Redis ZSET priority scoring with FIFO ordering, atomic Lua script for technician ticket claiming, position broadcasting)
   - `app/signaling.py` (FastAPI WebSocket router for `/ws/signaling/{room_id}`, W3C Perfect Negotiation, SDP offer/answer exchange, trickle ICE routing, media state toggles, room termination)
   - `app/telemetry.py` (Ingestion of getStats(), ITU-T G.107 E-Model MOS scoring formula tuned for Opus codec with delay and packet loss impairments, sliding window stats, quality tier distribution, degradation alerts)
   - `app/webhooks.py` (Async HTTP client via httpx, HMAC-SHA256 signature generator X-Signature: sha256=..., retry mechanism with exponential backoff and jitter, Redis DLQ fallback for zero event loss)
   - `app/main.py` (FastAPI app factory, lifespan startup/shutdown for Redis bus & cleanup tasks, CORS middleware, healthcheck /health endpoint, WebSocket route mounting)

2. Create a comprehensive Pytest test suite in `webrtc_service/tests/`:
   - `tests/__init__.py`
   - `tests/conftest.py` (Fixtures: AsyncMock Redis, FastAPI TestClient, WebSocket TestClient, JWT helper functions for all roles)
   - `tests/test_auth.py` (Token validation, expiration, bad signatures, mismatched rooms/units, role permissions)
   - `tests/test_signaling.py` (WebSocket signaling flow, SDP offer/answer relay, trickle ICE routing, media state broadcast, peer disconnect handling)
   - `tests/test_queue.py` (Queue entry, priority ordering, atomic Lua ticket claiming, position updates, attendee departure)
   - `tests/test_telemetry.py` (ITU-T G.107 MOS calibration against benchmark vectors, delay & packet loss impairment, session summary calculation)
   - `tests/test_webhooks.py` (HMAC-SHA256 signature verification, successful delivery, retry backoff on 500 error, DLQ persistence)
   - `tests/test_room_lifecycle.py` (State machine transitions, disconnect grace period, auto-expiration and cleanup daemon)
   - `tests/test_e2e_integration.py` (Full end-to-end simulated session: queue -> admission -> room connect -> SDP/ICE negotiation -> telemetry streaming -> session end -> signed webhook verification)

3. Execute the tests using pytest. Run `pytest -v` and ensure all tests pass (100% success). Document test outputs.

4. Write your detailed handoff report to `d:\Agile\projeto dia 18\.agents\worker_m4_1\handoff.md` and send a completion message.
