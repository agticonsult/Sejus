# Task Assignment: Challenger 1 (Milestone M6 Phase 2 - Adversarial Coverage Hardening)
Working Directory: d:\Agile\projeto dia 18\.agents\challenger_m6_1

## Objective:
Perform adversarial white-box testing of:
1. Laravel backend services & controllers (`app/Services/`, `app/Http/Controllers/`, `app/Models/`).
2. Cryptographic operations: AES-256-CBC with IV tampering, HMAC-SHA256 integrity, SHA-256 audit log blockchain tampering (detecting altered middle blocks, genesis block tampering, invalid signatures).
3. PostGIS & Geo-fencing: 78 ES Municipalities coordinate boundary checks, multi-polygon spatial intersections, coordinates outside ES or invalid GPS lat/long formats.
4. Concurrency & Race conditions: Rapid simultaneous check-ins, double certificate validation, token exhaustion, multi-role privilege escalation attempts.
5. Create executable adversarial tests in `tests_e2e/tier5_adversarial/test_adversarial_backend_crypto.py` and `tests/challenger_m6_backend.php`.
6. Run tests, document all findings, and write handoff report to `d:\Agile\projeto dia 18\.agents\challenger_m6_1\handoff.md`.
