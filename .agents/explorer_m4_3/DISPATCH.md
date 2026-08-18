## 2026-08-17T12:18:37Z
You are Explorer 3 (Telemetry & Webhook Dispatcher) for Milestone M4 (WebRTC Microservice) of CONECTA EGRESSO.
Your working directory is: d:\Agile\projeto dia 18\.agents\explorer_m4_3

MANDATORY INPUTS:
- Read `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- Read `d:\Agile\projeto dia 18\PROJECT.md`
- Read `d:\Agile\projeto dia 18\.agents\sub_orch_m4_webrtc\SCOPE.md`

OBJECTIVE:
Design the Telemetry Processing Engine, MOS Scoring, and Webhook Dispatcher for `webrtc_service/`:
1. Telemetry ingestion schema for client-side `getStats()` (round-trip time, jitter, packet loss percentage, audio/video bitrates, frame rates, resolution changes).
2. ITU-T G.107 / E-Model derived MOS (Mean Opinion Score) algorithm implementation details (converting RTT, jitter, and packet loss into an effective R-factor and subsequent MOS rating from 1.0 to 5.0).
3. Aggregation & Session Summary metrics (average MOS, min MOS, total packet loss, duration, quality distribution, poor network alerts).
4. Reliable HMAC-SHA256 Webhook Dispatcher: async HTTP client (`httpx`), signature generation (`X-Signature: sha256=...`), retry mechanism with exponential backoff for failed webhook deliveries, and event logging.
5. Testing Strategy: Comprehensive Pytest fixtures, mock Redis, mock WebSockets, simulated network degradation metrics, and test coverage plan.

Write your detailed technical specification to `d:\Agile\projeto dia 18\.agents\explorer_m4_3\analysis.md` and `handoff.md`.
Send a completion message when done.
