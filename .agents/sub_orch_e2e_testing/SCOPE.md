# Scope: E2E Testing Track (CONECTA EGRESSO)

## Architecture & Layout
```
d:\Agile\projeto dia 18\tests_e2e\
├── test_runner.py                      # Multi-tier CLI test harness with exit codes & reporting
├── e2e_utils.py                        # Common test clients, mock responders, HMAC/crypto helpers
├── tier1_features\                     # Tier 1 Feature coverage tests (70 tests)
│   ├── test_f01_f05_docker_infra.py
│   ├── test_f06_f09_db_lgpd.py
│   ├── test_f10_f12_carteira_qr.py
│   ├── test_f13_f16_rbac_auth.py
│   ├── test_f17_f18_prontuario_timeline.py
│   ├── test_f19_f21_vagas_territorio.py
│   ├── test_f22_kpis_gestao.py
│   ├── test_f23_f25_webrtc_webhooks.py
│   ├── test_f26_f33_python_webrtc.py
│   ├── test_f34_f47_frontend_views.py
│   ├── test_f48_f50_e2e_meta.py
│   └── test_harness_core.py
├── tier2_boundaries\                   # Tier 2 Boundary & Corner case tests (61 tests)
│   ├── test_auth_boundaries.py
│   ├── test_crypto_tampering.py
│   ├── test_prontuario_boundaries.py
│   ├── test_webrtc_network_limits.py
│   ├── test_territory_payload_limits.py
│   └── test_frontend_a11y_limits.py
├── tier3_combinations\                 # Tier 3 Cross-feature pairwise tests (23 tests)
│   ├── test_rbac_prontuario_matrix.py
│   ├── test_webrtc_webhook_timeline.py
│   ├── test_pdf_qr_validation_chain.py
│   ├── test_territory_jobs_filter.py
│   ├── test_a11y_multimode_states.py
│   └── test_oidc_claims_authorization.py
└── tier4_scenarios\                    # Tier 4 Real-world application scenarios (4 scenarios / 21 tests)
    ├── scenario_gestor_audit_kpis.py
    ├── scenario_egresso_onboarding_wallet.py
    ├── scenario_video_attendance_prontuario.py
    └── scenario_interior_job_application.py
```

## Feature Inventory Mapping
| # | Feature | Milestone Assignment | Test Suite Location | Status |
|---|---------|----------------------|---------------------|:------:|
| 1 | F01-F05 Docker Multi-Service | M_E2E_2 | `tier1_features/test_f01_f05_docker_infra.py` | DONE |
| 2 | F06-F09 DB, Migrations, LGPD & Audit | M_E2E_2 | `tier1_features/test_f06_f09_db_lgpd.py` | DONE |
| 3 | F10-F12 Carteira Digital & QR Code | M_E2E_2 | `tier1_features/test_f10_f12_carteira_qr.py` | DONE |
| 4 | F13-F16 RBAC & OIDC Auth | M_E2E_2 | `tier1_features/test_f13_f16_rbac_auth.py` | DONE |
| 5 | F17-F18 Prontuário Único & Timeline | M_E2E_2 | `tier1_features/test_f17_f18_prontuario_timeline.py` | DONE |
| 6 | F19-F21 Vagas, Cursos & Território | M_E2E_2 | `tier1_features/test_f19_f21_vagas_territorio.py` | DONE |
| 7 | F22 Gestão KPIs & Relatórios | M_E2E_2 | `tier1_features/test_f22_kpis_gestao.py` | DONE |
| 8 | F23-F25 WebRTC Token & Webhooks | M_E2E_2 | `tier1_features/test_f23_f25_webrtc_webhooks.py` | DONE |
| 9 | F26-F33 Python FastAPI WebRTC Service | M_E2E_2 | `tier1_features/test_f26_f33_python_webrtc.py` | DONE |
| 10 | F34-F47 Frontend Views & Acessibilidade | M_E2E_2 | `tier1_features/test_f34_f47_frontend_views.py` | DONE |
| 11 | F48-F50 E2E Integration & Audit | M_E2E_2 | `tier1_features/test_f48_f50_e2e_meta.py` | DONE |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|:------:|
| 1 | M_E2E_1: Test Harness & Utilities | `test_runner.py`, `e2e_utils.py` | none | **DONE** |
| 2 | M_E2E_2: Tier 1 Feature Suite (F01-F50) | 12 test modules covering all 50 features (70 tests) | M_E2E_1 | **DONE** |
| 3 | M_E2E_3: Tier 2 Boundary & Negative Suite | 6 test modules covering boundary/edge/negative tests (61 tests) | M_E2E_1 | **DONE** |
| 4 | M_E2E_4: Tier 3 Combinatorial Suite | 6 test modules covering pairwise integration tests (23 tests) | M_E2E_1 | **DONE** |
| 5 | M_E2E_5: Tier 4 Real-World Scenarios | 4 complete end-to-end user journey workflows (21 tests) | M_E2E_1 | **DONE** |
| 6 | M_E2E_6: Test Suite Verification & Publication | Execute all 175 tests, review APPROVED, publish `TEST_READY.md` | M_E2E_2..5 | **DONE** |
