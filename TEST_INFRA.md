# E2E Test Infra: CONECTA EGRESSO (SEJUS/ES)

## Test Philosophy
- Opaque-box, requirement-driven, derived strictly from `ORIGINAL_REQUEST.md`, `PROJECT.md`, and the official SEJUS Edital specifications.
- Methodology: Category-Partition + Boundary Value Analysis + Pairwise Combinatorial Testing + Real-World Workload Testing.
- Strict isolation: tests execute against exposed API/Web endpoints, WebSockets, and CLI runners.

---

## Feature Inventory Coverage Map

| # | Feature | Requirement Source | Tier 1 (Count) | Tier 2 (Count) | Tier 3 (Count) | Tier 4 Scenario |
|---|---------|-------------------|:--------------:|:--------------:|:--------------:|:---------------:|
| 1 | F01-F05 Docker Multi-Service | ORIGINAL_REQUEST R4 | 5 | 5 | ✓ | Scenario 1 |
| 2 | F06-F09 Database & LGPD Audit | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | Scenario 1, 2 |
| 3 | F10-F12 Carteira Digital & QR Code | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | Scenario 2, 4 |
| 4 | F13-F16 RBAC Auth & OIDC | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | Scenario 1, 3 |
| 5 | F17-F18 Prontuário Único & Timeline | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | Scenario 1, 2, 3 |
| 6 | F19-F21 Vagas, Cursos & 78 Municípios | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | Scenario 4 |
| 7 | F22 Management KPIs & Reports | ORIGINAL_REQUEST R1 | 5 | 5 | ✓ | Scenario 1 |
| 8 | F23-F25 WebRTC Token & Webhooks | ORIGINAL_REQUEST R2 | 5 | 5 | ✓ | Scenario 3 |
| 9 | F26-F33 Python WebRTC Microservice | ORIGINAL_REQUEST R2 | 5 | 5 | ✓ | Scenario 3 |
| 10 | F34-F47 Frontend Vue 3 & Accessibility | ORIGINAL_REQUEST R3 | 5 | 5 | ✓ | Scenario 1, 2, 3, 4 |

---

## Test Architecture
- **Test Runner**: `python tests_e2e/test_runner.py` (executes all tiers sequentially with deterministic assertions, color-coded summaries, and nonzero exit codes on failure).
- **Directory Layout**:
  - `tests_e2e/tier1_features/`: Isolated happy-path feature verification.
  - `tests_e2e/tier2_boundaries/`: Edge cases, negative inputs, invalid tokens, tampered hashes, rate limits, empty municipality payloads.
  - `tests_e2e/tier3_combinations/`: Cross-feature matrix (RBAC × Prontuário, WebRTC Webhook × Timeline, PDF Generation × QR Verification, 78 Municipalities × Jobs Filter).
  - `tests_e2e/tier4_scenarios/`: Real-world end-to-end user journeys (SEJUS Manager, Social Office Technician, Egresso/Family Member).

---

## Real-World Application Scenarios (Tier 4)

| # | Scenario Name | Features Exercised | Target Profile & Workflow |
|---|---------------|--------------------|---------------------------|
| 1 | **Gestor SEJUS Global Audit & Analytics** | F14, F15, F16, F21, F22, F45, F46 | Gestor logs in via Gov.br, verifies 78 municipalities KPI statistics, checks tamper-proof audit chain, inspects system telemetry. |
| 2 | **Egresso Digital Onboarding & Credential Issuance** | F08, F10, F11, F12, F17, F42, F47 | Egresso accesses portal, view profile, downloads signed PDF Digital Wallet, validates QR Code cryptographic HMAC on public portal. |
| 3 | **Remote Video Social Attendance & Prontuário Auto-Log** | F17, F18, F23, F24, F25, F26, F27, F28, F30, F32, F40, F44 | Técnico initiates attendance queue, Egresso enters video room via WebSockets, conducts session with simulated 4G packet loss, call concludes, FastAPI sends HMAC webhook, Laravel automatically registers duration and metadata on Prontuário timeline. |
| 4 | **Interior Territorial Job Application in Linhares** | F07, F19, F20, F21, F41, F43 | Egresso in Linhares activates Simplified Language mode, filters jobs in Linhares, views local CRAS/SINE details, and applies for affirmative vacancy. |

---

## Acceptance Thresholds
- **Tier 1 (Feature Coverage)**: >= 50 test cases.
- **Tier 2 (Boundary & Corner Cases)**: >= 50 test cases.
- **Tier 3 (Cross-Feature Combinations)**: >= 15 pairwise integration test cases.
- **Tier 4 (Real-World Scenarios)**: 4 complete E2E scenario workflows.
- **Overall**: 100% test pass rate required before project completion.
