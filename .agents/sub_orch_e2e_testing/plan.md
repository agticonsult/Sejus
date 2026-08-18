# Execution Plan: E2E Testing Track

## Objective
Design, implement, and verify the full opaque-box E2E testing framework in `d:\Agile\projeto dia 18\tests_e2e\` covering all 50 features from PROJECT.md, boundary cases, cross-feature pairwise interactions, and 4 real-world operational scenarios.

## Milestones & Steps

### Step 1: Initialize Test Infrastructure & Harness (M_E2E_1)
- Create `tests_e2e/test_runner.py` with CLI flags (`--tier`, `--all`, `--verbose`, `--json`), colorized reporting, timing, test discovery, and proper exit codes.
- Create common test utilities (`tests_e2e/utils.py` or `tests_e2e/conftest_e2e.py`) for HTTP client, WebSocket client, mock data generators, assertion helpers, and crypto verification (HMAC-SHA256, blind index validation).

### Step 2: Implement Tier 1 Feature Tests (M_E2E_2)
- Implement >= 50 isolated feature tests across F01-F50 in `tests_e2e/tier1_features/`.
- Verify every feature from PROJECT.md Feature Inventory has dedicated verification.

### Step 3: Implement Tier 2 Boundary & Negative Tests (M_E2E_3)
- Implement >= 50 boundary/edge-case/negative tests in `tests_e2e/tier2_boundaries/`.
- Cover invalid tokens, tampered signatures, SQLi/XSS payloads, missing headers, empty strings, extreme coordinates, packet loss extremes, malformed WebSockets, etc.

### Step 4: Implement Tier 3 Pairwise & Cross-Feature Tests (M_E2E_4)
- Implement >= 15 cross-module integration tests in `tests_e2e/tier3_combinations/`.
- Cover RBAC × Prontuário, WebRTC Webhook × Timeline Event, Carteira PDF × QR Verification, 78 Municipalities × Jobs Filter, High Contrast × Simplified Language view state, OIDC Login × Role claim assignment.

### Step 5: Implement Tier 4 Real-World Application Scenarios (M_E2E_5)
- Implement 4 complete real-world user journeys in `tests_e2e/tier4_scenarios/`:
  1. `scenario_gestor_audit_kpis.py`: Gestor SEJUS Global Audit & Analytics
  2. `scenario_egresso_onboarding_wallet.py`: Egresso Digital Onboarding & Credential Issuance
  3. `scenario_video_attendance_prontuario.py`: Remote Video Social Attendance & Prontuário Auto-Log
  4. `scenario_interior_job_application.py`: Interior Territorial Job Application in Linhares

### Step 6: Review, Verification & Publication (M_E2E_6)
- Review tests with Reviewer subagent for execution correctness, syntax, assertion validity, and coverage.
- Publish `d:\Agile\projeto dia 18\TEST_READY.md`.
- Send completion handoff report to parent orchestrator.
