## 2026-08-17T17:40:08Z
You are the Sub-orchestrator for Milestone M6: E2E Full Integration, Verification & Adversarial Coverage Hardening (CONECTA EGRESSO SEJUS/ES).

Your working directory is: d:\Agile\projeto dia 18\.agents\sub_orch_m6_e2e_integration
Project root: d:\Agile\projeto dia 18
Your Parent Conversation ID: 9285f12b-64c2-4188-ba61-bc8ba009b89b

Mandatory Reading:
- d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md
- d:\Agile\projeto dia 18\PROJECT.md
- d:\Agile\projeto dia 18\TEST_INFRA.md
- d:\Agile\projeto dia 18\TEST_READY.md
- d:\Agile\projeto dia 18\.agents\sub_orch_m1_m2\handoff.md
- d:\Agile\projeto dia 18\.agents\sub_orch_m3_backend\handoff.md
- d:\Agile\projeto dia 18\.agents\worker_m4_1\handoff.md
- d:\Agile\projeto dia 18\.agents\sub_orch_m5_frontend\handoff.md

Your Mission & Scope:
1. **Phase 1 — E2E Test Suite Execution (Tiers 1-4)**:
   - Run and verify the full opaque-box E2E test suite across all 4 tiers using the test runner: `python tests_e2e/test_runner.py --all --verbose` (or `--json`).
   - Verify 100% pass across all 175 test cases:
     - Tier 1: Feature Coverage (70 tests covering F01 to F50)
     - Tier 2: Boundary & Corner Cases (61 tests)
     - Tier 3: Cross-Feature Combinations (23 pairwise tests)
     - Tier 4: Real-World Application Scenarios (4 scenarios / 21 tests)
   - Also run Laravel unit/feature tests and Python WebRTC tests: `python -m pytest -v` in `webrtc_service/`.
   - If any test fails, run the iteration loop (Explorer -> Worker -> Reviewer) to debug and resolve.

2. **Phase 2 — Adversarial Coverage Hardening (Tier 5)**:
   - Spawn 2 Challengers (`teamwork_preview_challenger`) to perform white-box code & test analysis. Find any untested code paths, edge cases, race conditions, or potential vulnerabilities across Laravel, FastAPI WebRTC, Vue 3 frontend, and crypto services.
   - Challengers create adversarial stress tests in `tests_e2e/tier5_adversarial/` or `tests/`.
   - Spawn Worker to integrate tests and fix any discovered bugs.
   - Spawn 2 Reviewers to verify all fixes and tests.

3. **Phase 3 — Forensic Integrity Audit**:
   - Spawn a Forensic Auditor (`teamwork_preview_auditor`) to perform systematic integrity verification:
     - Verify zero mock/dummy/hardcoded values in production code.
     - Verify genuine ITU-T G.107 E-model calculations in Python & JS.
     - Verify genuine AES-256-CBC, HMAC-SHA256, and SHA-256 hash chaining.
     - Verify genuine PostGIS/pgcrypto integration, 78 ES municipalities, Dompdf and QR code generation.
     - Verify genuine Vue 3 + Inertia frontend and WCAG 2.1 AAA accessibility implementations.

4. **Gate Evaluation & Final Report**:
   - Verify all pass criteria:
     1. Build and all test suites pass 100% (Tiers 1-4 and Tier 5).
     2. Every Reviewer verdict is APPROVE.
     3. Every Challenger confirms correctness.
     4. Forensic Auditor verdict is CLEAN.
   - Update `PROJECT.md` marking M1-M6 as DONE.
   - Write comprehensive handoff report to `d:\Agile\projeto dia 18\.agents\sub_orch_m6_e2e_integration\handoff.md`.
   - Send completion message to parent.
