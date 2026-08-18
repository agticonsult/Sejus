# Scope: Milestone M4 — Python FastAPI WebRTC Signaling & Telemetry Microservice

## Architecture
- Root Directory for service: `d:\Agile\projeto dia 18\webrtc_service\`
- Framework: Python 3.11+ FastAPI (asynchronous, Starlette WebSocket, Pydantic v2)
- Redis: Redis Pub/Sub async engine (aioredis / redis-py async) for multi-node room synchronization, queue state, and telemetry pub/sub.
- WebRTC Signaling:
  - WebSocket `/ws/signaling/{room_id}`: Room join/leave, SDP offer/answer exchange, ICE candidate trickle, peer state.
  - Role-based signaling: Attendee (Egresso / Citizen), Host/Technician (Servidor / Psicólogo / Assistente Social), Observer (Defensoria / Juiz).
- Queue / Waiting Room Management:
  - WebSocket `/ws/queue/{unit_id}`: Waiting room status, real-time queue position calculation, technician push notifications, attendee admission into specific virtual rooms.
- Telemetry & Quality Engine:
  - Ingestion of client-side WebRTC `getStats()` reports (jitter, RTT, packet loss, audio/video bitrates, frames per second, resolution).
  - ITU-T G.107 / E-Model derived MOS (Mean Opinion Score) estimation (1.0 to 5.0).
  - Continuous quality scoring, degradation alerts, and aggregated session summary on room teardown.
- Webhook Dispatcher:
  - Async HTTP client (httpx) posting signed events to Laravel API (`/api/webhooks/webrtc`).
  - HMAC-SHA256 signature header (`X-Signature` or `X-Hub-Signature-256`) with shared secret.
  - Events: `session.started`, `session.ended` (with duration, participant count, MOS summary, packet loss metrics), `session.error`, `attendee.joined_queue`, `attendee.admitted`.
- Test Suite:
  - Full Pytest test suite with pytest-asyncio, FastAPI TestClient / WebSocket TestClient, fake/mock Redis pubsub fixture, webhook receiver mock, and telemetry edge cases.

## Milestones & Work Items
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M4.1 Exploration & Spec | Requirements, interface specs, Redis channel schemas, MOS algorithm, webhook schema | None | IN_PROGRESS |
| 2 | M4.2 Implementation & Test Suite | Complete `webrtc_service/` app, modules, requirements.txt, tests, runner | M4.1 | PLANNED |
| 3 | M4.3 Verification & Stress Test | Reviewers + Challengers (WebSocket load, Redis failover, packet loss simulation) | M4.2 | PLANNED |
| 4 | M4.4 Forensic Audit & Gate | Forensic integrity check, zero stubs verification, Gate pass & handoff | M4.3 | PLANNED |

## Interface Contracts
### Microservice ↔ Laravel Backend
- Webhook endpoint: `POST /api/webhooks/webrtc`
- Header: `X-Signature: sha256=<hex_hmac>`, `Content-Type: application/json`
- Payload structure: `{ "event": "<event_type>", "timestamp": "<ISO-8601>", "room_id": "<uuid>", "payload": { ... } }`

### Client ↔ WebSocket Signaling Endpoint
- Path: `/ws/signaling/{room_id}?token=<jwt_or_ticket>&user_id=<id>&role=<role>`
- Message types:
  - `join`, `joined`, `peer_joined`, `peer_left`
  - `offer`, `answer`, `ice_candidate`
  - `mute_audio`, `mute_video`, `screen_share_start`, `screen_share_stop`
  - `telemetry_report`
  - `room_terminated`

### Client ↔ WebSocket Queue Endpoint
- Path: `/ws/queue/{unit_id}?user_id=<id>&ticket_id=<ticket>`
- Message types:
  - `queue_status`, `position_update`, `call_attendee`, `admit_to_room`, `leave_queue`
