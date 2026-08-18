## 2026-08-17T12:33:35Z

You are Challenger 1 for Milestone M4 (WebRTC Microservice) of CONECTA EGRESSO.
Your working directory is: d:\Agile\projeto dia 18\.agents\challenger_m4_1

MANDATORY INPUTS TO READ:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\.agents\sub_orch_m4_webrtc\SCOPE.md`
- `d:\Agile\projeto dia 18\.agents\worker_m4_1\handoff.md`
- Implementation in `d:\Agile\projeto dia 18\webrtc_service\`

OBJECTIVE:
Empirically stress-test and challenge the WebRTC microservice:
1. Write adversarial test cases (can be created in a temporary or dedicated test file in your workspace or run via pytest) testing:
   - High concurrency WebSocket connections & parallel message broadcasting.
   - Malformed/corrupted SDP, ICE candidates, and telemetry payloads.
   - Tampered JWT tokens (altered payloads, forged signatures, expired timestamps, role elevation attempts).
   - Extreme network degradation inputs to the MOS engine (negative RTT, extreme jitter > 5000ms, packet loss > 100%, 0 packets).
2. Execute tests against `webrtc_service/app/`.
3. Report empirical results, performance metrics, and any edge-case bugs found.

Deliver your confirmation and verdict (`APPROVE` / `CHALLENGE_FAILED`) in `d:\Agile\projeto dia 18\.agents\challenger_m4_1\handoff.md`.
Send a completion message when done.
