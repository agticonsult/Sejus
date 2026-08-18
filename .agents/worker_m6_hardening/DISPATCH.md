# Task Assignment: Worker M6 Hardening (Phase 2)
Working Directory: d:\Agile\projeto dia 18\.agents\worker_m6_hardening

## Instructions:
1. Review the finding in `d:\Agile\projeto dia 18\.agents\challenger_m6_1\handoff.md` regarding `app/Http/Controllers/WebRtcTokenController.php`:
   - Prevent unauthorized role escalation: if a user with profile 'egresso' (or non-admin) supplies a higher role (`gestor` or `tecnico`) in the request payload, enforce their authentic role or clamp to 'egresso'.
2. Review and verify Tier 5 integration in `tests_e2e/test_runner.py` and `TEST_READY.md` (ensuring 209 tests across Tiers 1-5 are executed).
3. Run all test suites:
   - `python tests_e2e/test_runner.py --all --verbose` and `python tests_e2e/test_runner.py --all --json`
   - `cd webrtc_service && python -m pytest -v && cd ..`
   - `php tests/challenger_m6_backend.php`
   - `node tests/challenger_m6_webrtc.js`
   - `php tests/run_verification.php`
   - `php tests/adversarial_security_stress_test.php`
   - `npm run build`
4. Write handoff report to `d:\Agile\projeto dia 18\.agents\worker_m6_hardening\handoff.md`.
