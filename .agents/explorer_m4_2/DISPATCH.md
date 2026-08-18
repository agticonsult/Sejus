## 2026-08-17T12:18:37Z
You are Explorer 2 (Signaling & Realtime Architecture) for Milestone M4 (WebRTC Microservice) of CONECTA EGRESSO.
Your working directory is: d:\Agile\projeto dia 18\.agents\explorer_m4_2

MANDATORY INPUTS:
- Read `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- Read `d:\Agile\projeto dia 18\PROJECT.md`
- Read `d:\Agile\projeto dia 18\.agents\sub_orch_m4_webrtc\SCOPE.md`

OBJECTIVE:
Design the complete asynchronous WebSocket signaling and Redis Pub/Sub architecture for `webrtc_service/`:
1. WebSocket connection manager with async locks, client tracking, and heartbeat/ping-pong mechanisms.
2. WebRTC Signaling Protocol: SDP offer/answer exchange, ICE candidate trickling, media state toggle (audio/video/screen), peer leave/disconnect handling, graceful degradation.
3. Multi-instance Room Synchronization via Redis Pub/Sub: Channel naming conventions (`room:{room_id}:events`, `queue:{unit_id}:events`), message serialization, pub/sub listener background task lifecycle.
4. Real-time Queue / Waiting Room manager: queue positioning, FIFO / priority admission, technician broadcast notifications, attendee transfer into active video rooms.
5. Error handling, reconnection tolerance, and room teardown cleanup procedures.

Write your detailed architectural specification to `d:\Agile\projeto dia 18\.agents\explorer_m4_2\analysis.md` and `handoff.md`.
Send a completion message when done.
