# TEST_INFRA.md — Comprehensive E2E Testing Infrastructure & Specification

**Project**: CONECTA EGRESSO (SEJUS/ES)  
**Track**: Full Authentication, Agile Support User, PDF Microservice, Toasts & Route Audit  
**Authoritative Source**: `ORIGINAL_REQUEST.md`, `PROJECT.md`  
**Date**: 2026-08-18  

---

## 1. Executive Summary & Architecture Overview

The **Conecta Egresso Testing Infrastructure** establishes an exhaustive, multi-tiered verification framework designed to guarantee the functional correctness, cryptographic integrity, resilience, and zero-defect quality across all 18 core features.

The test suite is structured into **4 Primary Operational Tiers** (plus Tier 5 Adversarial Hardening) executed via a unified, zero-dependency Python test runner (`tests_e2e/test_runner.py`) and Laravel PHPUnit test suite (`tests/`):

```
+-------------------------------------------------------------------------------+
|                      CONECTA EGRESSO TEST ARCHITECTURE                        |
+-------------------------------------------------------------------------------+
|  Tier 1: Feature Coverage (>=5 tests per major feature, 18 features covered)  |
|  Tier 2: Boundary, Negative & Corner Cases (Offline, Invalid, Tampering)      |
|  Tier 3: Pairwise Combinatorial & Cross-Feature Integration Chains            |
|  Tier 4: Real-World Workload Scenarios (Support, Tech, Egresso, Manager)      |
|  Tier 5: Adversarial Cryptographic & Security Hardening Suite                |
+-------------------------------------------------------------------------------+
|  PHPUnit Feature & Unit Suites: Controllers, Services, RBAC, Cryptography     |
+-------------------------------------------------------------------------------+
```

---

## 2. Comprehensive 18-Feature Matrix Across All 4 Tiers

| # | Feature Name | Description | Tier 1 (Feature) | Tier 2 (Boundary) | Tier 3 (Combination) | Tier 4 (Workload) |
|---|--------------|-------------|------------------|-------------------|----------------------|-------------------|
| **F01** | `useToast.js` Reactive Composable | Singleton reactive store supporting success, error, warning, info, auto-dismiss, manual dismiss, custom durations | `test_f01_f04_toasts.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_technician_workflow.py` |
| **F02** | `<ToastContainer />` Component | Fixed top-right UI component, Lucide icons, high-contrast support, smooth transitions | `test_f01_f04_toasts.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_technician_workflow.py` |
| **F03** | Native `alert()` Elimination | Removal of 5 `alert()` calls in `Atendimento.vue`, `Carteira.vue`, `Oportunidades.vue`, `Relatorios.vue`, `SegurancaLgpd.vue` | `test_f01_f04_toasts.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_technician_workflow.py` |
| **F04** | Additional Toast Touchpoints | Toasts in `Prontuario.vue` (save/edit) and `AppLayout.vue` role change flash listener | `test_f01_f04_toasts.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_technician_workflow.py` |
| **F05** | Document Generator API Integration | `CarteiraPdfService.php` POST to `http://localhost:8080` with API Key `token-secreto-dev`, format A4, portrait | `test_f05_f08_pdf_generator.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_egresso_workflow.py` |
| **F06** | Graceful Dompdf Fallback | Seamless automatic fallback to local Dompdf / PDF stream on network timeout, offline service, or 500 error | `test_f05_f08_pdf_generator.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_egresso_workflow.py` |
| **F07** | Carteira Digital PDF Route | `GET /carteira/pdf` in `routes/web.php` returning PDF binary stream (`application/pdf`, `inline; filename="carteira-digital-sejus.pdf"`) | `test_f05_f08_pdf_generator.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_egresso_workflow.py` |
| **F08** | Unauthenticated/Demo PDF Fallback | In `CarteiraPdfController`, fallback to first Egresso when user is unauthenticated or in demo mode | `test_f05_f08_pdf_generator.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_egresso_workflow.py` |
| **F09** | Gov.br / Acesso Cidadão Login Page | `Login.vue` with Gov.br (`#1351b4`) & ES state design (`#003366`, `#e63946`), dual login (Gov.br SSO + credentials), quick-fill demo bar | `test_f09_f11_auth_govbr.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_support_lifecycle.py` |
| **F10** | Route Protection & `GET /login` Route | `GET /login` registered in `routes/web.php`, `HandleInertiaRequests` middleware sharing `auth.user`, unauth redirect | `test_f09_f11_auth_govbr.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_support_lifecycle.py` |
| **F11** | Secure Logout Action | Logout button in header and sidebar of `AppLayout.vue` posting to `/logout`, invalidating session, redirecting to `/login` | `test_f09_f11_auth_govbr.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_support_lifecycle.py` |
| **F12** | Suporte Profile & Permissions | `suporte` profile (id 5, full admin permissions) in `PerfilSeeder.php`, `isSuporte()` helper in `User.php` | `test_f12_f16_user_mgmt_suporte.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_support_lifecycle.py` |
| **F13** | Agile Support User Seeder | Seed `suporte.agile@sejus.es.gov.br` (password `secret123`, role `suporte`) in `UserSeeder.php` | `test_f12_f16_user_mgmt_suporte.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_support_lifecycle.py` |
| **F14** | User Management Controller & API | `UserController.php` with listing, creation, editing, deletion/toggle, CPF encryption, municipality selection, audit logging | `test_f12_f16_user_mgmt_suporte.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_support_lifecycle.py` |
| **F15** | User Management Interface | `Usuarios.vue` page with responsive table, filters, and modal for create/edit profiles (Gestor, Técnico, Egresso, Familiar, Suporte) | `test_f12_f16_user_mgmt_suporte.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_manager_governance.py` |
| **F16** | User Management Navigation | "Gerenciamento de Usuários" link in `AppLayout.vue` visible for Gestor and Suporte roles only | `test_f12_f16_user_mgmt_suporte.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_manager_governance.py` |
| **F17** | Route & Link 404 Audit | Audit and eliminate all 404 errors across frontend navigation links, Inertia web routes, and backend API endpoints | `test_f17_f18_audit_404_e2e.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_manager_governance.py` |
| **F18** | E2E Testing & Verification Suite | Opaque-box and unit testing covering all features, zero-404 audit, and forensic integrity verification | `test_f17_f18_audit_404_e2e.py` | `test_boundaries_m6_features.py` | `test_combinations_m6_flows.py` | `scenario_manager_governance.py` |

---

## 3. Tier Specifications & Test Requirements

### Tier 1: Feature Coverage (>=5 Tests per Major Feature Area)
- **Toast Notifications (F01–F04)**:
  1. `test_toast_composable_initialization_and_types`: Validates reactive singleton store, success/error/warning/info dispatch.
  2. `test_toast_auto_dismiss_timer`: Tests automatic timeout dismiss with custom duration.
  3. `test_toast_manual_dismiss`: Tests manual dismiss by toast ID.
  4. `test_toast_container_lucide_icons_and_classes`: Tests CSS styles, Lucide icon mappings, and top-right positioning.
  5. `test_vue_files_zero_native_alert_calls`: Audits `Atendimento.vue`, `Carteira.vue`, `Oportunidades.vue`, `Relatorios.vue`, `SegurancaLgpd.vue` confirming 0 occurrences of `alert(`.
  6. `test_toast_touchpoints_prontuario_and_layout`: Tests integration in `Prontuario.vue` and `AppLayout.vue` flash listener.

- **PDF Generation & Fallback (F05–F08)**:
  1. `test_document_generator_api_payload_and_headers`: Tests POST to `http://localhost:8080` with `X-API-Key: token-secreto-dev`.
  2. `test_document_generator_graceful_fallback_on_timeout`: Tests fallback to Dompdf when microservice times out.
  3. `test_document_generator_graceful_fallback_on_500`: Tests fallback when microservice returns HTTP 500.
  4. `test_carteira_pdf_route_headers_and_stream`: Tests `GET /carteira/pdf` returns `application/pdf` and `inline; filename="carteira-digital-sejus.pdf"`.
  5. `test_carteira_pdf_unauthenticated_fallback`: Tests unauthenticated/demo mode fallback to first egresso.
  6. `test_carteira_pdf_html_structure`: Validates state coat of arms, SEJUS header, masked CPF, and QR code SVG.

- **Gov.br / Acesso Cidadão Authentication (F09–F11)**:
  1. `test_login_page_govbr_and_es_styling`: Validates official Gov.br (`#1351b4`) and ES state colors and identity.
  2. `test_login_standard_credentials_success`: Tests login with valid email/CPF and password.
  3. `test_login_govbr_sso_simulation`: Tests simulated OIDC callback with claims.
  4. `test_login_route_registered_and_inertia_shared_user`: Tests `GET /login` and `HandleInertiaRequests` sharing `auth.user`.
  5. `test_logout_session_invalidation_and_audit`: Tests `POST /logout` invalidates session, regenerates CSRF token, and logs audit event.

- **Suporte Profile & Agile User (F12–F13)**:
  1. `test_suporte_profile_seeded_with_admin_permissions`: Validates profile id 5, slug `suporte`, full administrative permissions.
  2. `test_suporte_user_helper_method`: Tests `$user->isSuporte()` and role checking.
  3. `test_agile_support_user_credentials`: Tests `suporte.agile@sejus.es.gov.br` seeded with `secret123`.
  4. `test_suporte_user_can_access_administrative_endpoints`: Verifies full permission grants for suporte user.
  5. `test_suporte_role_in_switch_role_endpoint`: Tests quick-switch capability for support role.

- **User Management CRUD & UI (F14–F16)**:
  1. `test_user_management_listing_with_filters`: Tests `GET /usuarios` with role and municipality filters.
  2. `test_user_management_creation_with_cpf_encryption`: Tests `POST /usuarios` encrypts CPF, creates blind index, and hashes password.
  3. `test_user_management_update_profile`: Tests `PUT /usuarios/{id}` updates name, email, role, and municipality.
  4. `test_user_management_toggle_active_status`: Tests `DELETE /usuarios/{id}` toggles active state with audit log.
  5. `test_usuarios_vue_table_and_modal_structure`: Validates Vue page structure and modal for all 5 roles.
  6. `test_user_management_nav_link_visibility`: Validates navigation item in `AppLayout.vue` visible only to Gestor and Suporte.

- **Route & Link 404 Audit (F17–F18)**:
  1. `test_all_web_routes_return_valid_inertia_or_response`: Audits all 10+ web routes for zero 404 errors.
  2. `test_all_api_endpoints_return_valid_responses`: Tests all API routes for correct status codes.
  3. `test_frontend_navigation_links_consistency`: Verifies that all sidebar and navbar links match registered Laravel routes.
  4. `test_public_carteira_validation_routes`: Verifies `/validar-carteira` and `/validar-carteira/{token}` return 200.
  5. `test_e2e_meta_runner_verification`: Tests the E2E verification harness and test runner integrity.

---

### Tier 2: Boundary & Corner Cases
1. **Microservice Offline & Network Errors**: Tests complete socket disconnect, connection refusal, 502/503/504 errors, and malformed non-PDF payloads falling back to Dompdf.
2. **Invalid Authentication Credentials**: Tests incorrect password, non-existent user identifier, malformed email string, invalid CPF digits, and empty password.
3. **Deactivated Account Access**: Tests that users with `ativo = false` receive HTTP 403 `ACCOUNT_DEACTIVATED`.
4. **Duplicate User Registration Collisions**: Tests collision handling on duplicate email and duplicate CPF blind index.
5. **Input Validation Limits**: Tests empty name, weak password (< 8 chars), invalid 11-digit CPF checksum, non-existent municipality IBGE code, invalid perfil ID.
6. **Unauthenticated Access & Route Protection**: Tests unauthorized access to `/usuarios` redirected or returning 401/403.
7. **Privilege Escalation Guards**: Tests egresso and familiar roles attempting to perform admin CRUD actions.
8. **Extreme Payload & Sanitization Limits**: Tests 64KB boundary limits, XSS script injection tags in user fields, and SQL injection fuzzing.

---

### Tier 3: Cross-Feature Combinations
1. **Cross-Flow 1: Support Provisioning to Role Switch**:
   Login as `suporte.agile` -> Provision new Gestor user -> Edit Gestor municipality -> Switch active role to new Gestor -> Verify permissions and immutable audit logs.
2. **Cross-Flow 2: Digital Wallet Issuance to Verification Chain**:
   Login as Técnico -> Issue Digital Wallet -> Generate PDF via Document Generator API -> Trigger fallback on simulated timeout -> Extract QR code HMAC-SHA256 token -> Validate at `/validar-carteira/{token}` -> Verify VALID_DOCUMENT status.
3. **Cross-Flow 3: User Management Mutations to Toast Feedback**:
   Create new user -> Verify success Toast structure -> Edit user -> Verify update Toast -> Switch role -> Verify flash Toast across Inertia page navigation.
4. **Cross-Flow 4: Fallback PDF to Gov.br Authenticated Transition**:
   Access `/carteira/pdf` unauthenticated (returns fallback Egresso PDF) -> Login via Gov.br SSO (`nivel_confianca = Ouro`) -> Access `/carteira/pdf` authenticated (returns personalized Egresso PDF).
5. **Cross-Flow 5: User Management CRUD & Cryptographic Hash Chaining**:
   Sequential user creation, update, and deactivation -> Verify unbroken SHA-256 hash chaining across `prontuario_audit_logs` / system audit entries.

---

### Tier 4: Real-World Workload Scenarios
1. **Support Administrator Lifecycle (`scenario_support_lifecycle.py`)**:
   End-to-end administration: login as `suporte.agile@sejus.es.gov.br`, inspect system seeders, batch provision social assistance technicians across ES microregions (Vitória, Linhares, Colatina, Cachoeiro), audit hash chain integrity.
2. **Social Technician Case Management & Wallet Issuance (`scenario_technician_workflow.py`)**:
   Technician conducts intake for egresso citizen, adds evolucao notes with Toast feedback, issues new digital wallet with signed QR code, downloads PDF via Document Generator.
3. **Egresso Self-Service & Opportunity Referral (`scenario_egresso_workflow.py`)**:
   Egresso authenticates via Gov.br / Acesso Cidadão, views digital wallet, downloads carteira PDF, browses affirmative action job vacancies and EAD courses, applies with Toast confirmation.
4. **Statewide Manager Governance & Route 404 Sweep (`scenario_manager_governance.py`)**:
   Statewide manager reviews 78 ES municipalities KPI dashboard, filters by macroregion, manages user access, performs exhaustive zero-404 route sweep across all frontend links and backend endpoints.

---

## 4. Test Execution Guide

### Running E2E Test Suite (Python)
```bash
# Run all Tiers (Tier 1 to 5)
python tests_e2e/test_runner.py --all

# Run specific Tier
python tests_e2e/test_runner.py --tier 1
python tests_e2e/test_runner.py --tier 2
python tests_e2e/test_runner.py --tier 3
python tests_e2e/test_runner.py --tier 4

# Run with filter pattern
python tests_e2e/test_runner.py --filter toast
python tests_e2e/test_runner.py --filter pdf
python tests_e2e/test_runner.py --filter suporte
python tests_e2e/test_runner.py --filter user_mgmt

# Output JSON report
python tests_e2e/test_runner.py --all --json --output e2e_results.json
```

### Running Backend PHPUnit Tests
```bash
# Run all PHPUnit tests
php artisan test

# Run specific Feature suite
php artisan test --filter AuthControllerTest
php artisan test --filter UserControllerTest
php artisan test --filter CarteiraPdfRouteTest
```

### Running Standalone Verification Scripts
```bash
php tests/run_verification.php
php tests/run_m3_verification.php
```
