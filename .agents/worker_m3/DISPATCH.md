## 2026-08-17T17:26:04Z
You are the Worker implementing Milestone M3: Backend Business APIs, RBAC & Webhooks.

Your working directory is: d:\Agile\projeto dia 18\.agents\worker_m3
Project root: d:\Agile\projeto dia 18

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Mandatory Reading Before Coding:
- d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md
- d:\Agile\projeto dia 18\PROJECT.md
- d:\Agile\projeto dia 18\TEST_INFRA.md
- d:\Agile\projeto dia 18\.agents\sub_orch_m3_backend\SCOPE.md
- d:\Agile\projeto dia 18\.agents\explorer_m3_1\analysis.md
- d:\Agile\projeto dia 18\.agents\explorer_m3_2\analysis.md
- d:\Agile\projeto dia 18\.agents\explorer_m3_3\analysis.md

Scope of Work to Implement:
1. **Authentication & RBAC Services & Controllers**:
   - `app/Services/GovBrAuthService.php`: Simulated OIDC / Gov.br / Acesso Cidadão login provider with claim mapping, level verification (Bronze, Silver, Gold), session/Sanctum token generation, fail-secure fallback to Egresso.
   - `app/Http/Controllers/AuthController.php`: Login via simulated Gov.br/Acesso Cidadão (`POST /api/auth/govbr/login`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`).
2. **Middleware & Policies**:
   - `app/Http/Middleware/CheckRole.php`: Verifies user authenticated and has active required role (`gestor`, `tecnico`, `egresso`, `familiar`). Returns 401 for unauthenticated, 403 for unauthorized.
   - `app/Http/Middleware/AuditAccessLog.php`: Intercepts sensitive requests and calls `AuditService::log()` with user ID, action, resource, IP address, user-agent, timestamp.
   - Register middleware aliases in `bootstrap/app.php` (`'role' => \App\Http\Middleware\CheckRole::class`, `'audit.log' => \App\Http\Middleware\AuditAccessLog::class`).
   - `app/Policies/ProntuarioPolicy.php`: Gestor full read/write, Técnico read/write, Egresso read-only self restricted (without internal technical notes).
   - `app/Policies/CarteiraPolicy.php`, `app/Policies/VagaEmpregoPolicy.php`, `app/Policies/VideoRoomPolicy.php`.
3. **Prontuário Único & Timeline Controllers**:
   - `app/Http/Controllers/ProntuarioController.php`: Full CRUD (`index`, `store`, `show`, `update`, `destroy`) with automatic LGPD `AuditService::log()` on every read/write, pagination clamping (1..100), sequential ID generation (`PRT-2026-XXXXXX`), blind-index search by CPF.
   - `app/Http/Controllers/ProntuarioTimelineController.php`: Timeline events listing (`index`), event creation (`store`, `storeEvolucao`), strict boundary checks: 64KB max payload (413), empty note rejection (422), XSS entity escaping, author ID binding to authenticated user, RBAC check (403 for egresso).
4. **Vagas de Emprego & Cursos de Capacitação Controllers**:
   - `app/Http/Controllers/VagaEmpregoController.php`: Jobs listing with filters (`municipio`, `categoria`, `afirmativa_egresso`, `salario_min` clamped >= 0, accent-insensitive search), job details, and `candidatar` action that creates an `encaminhamento_vaga` timeline event on the atendido's Prontuario.
   - `app/Http/Controllers/CursoCapacitacaoController.php`: Courses listing with filters, course details, and `inscrever` action that creates an `inscricao_curso` timeline event on the atendido's Prontuario.
   - `app/Http/Controllers/CandidaturaController.php`: Candidaturas list and tracking.
5. **Territorial Mapping & Rede de Apoio Controllers**:
   - `app/Http/Controllers/TerritorioController.php`: 78 ES municipalities listing, IBGE 7-digit starting with 32 validation, WGS84 bounding box validation, regional summary (Metropolitana, Norte, Sul, Central), physical office indicator (`tem_escritorio_fisico`).
   - `app/Http/Controllers/RedeApoioController.php`: CRAS, CREAS, SINE, CAPS listing with fallback to host municipality centroid GPS if facility GPS is null.
6. **Management KPIs & Analytics Controller**:
   - `app/Http/Controllers/KpiDashboardController.php`: Executive KPI aggregation (`total_atendimentos`, `taxa_remoto_pct`, `taxa_empregabilidade_pct`, `taxa_sucesso_nao_reincidencia_pct`, `meta_populacional_egressos_es: 108000`), regional distribution across 4 macro-regions, time-series trends, and WebRTC telemetry MOS stats.
7. **WebRTC JWT Token Generation & Webhook Ingest**:
   - `app/Services/WebRtcJwtService.php`: RFC 7519 HS256 JWT encoder, decoder, validator using `config('services.webrtc.jwt_secret')`, and Coturn STUN/TURN ICE server generator.
   - `app/Http/Controllers/WebRtcTokenController.php`: `POST /api/webrtc/token` endpoint validating user/room authorization, returning signed JWT and ICE servers.
   - `app/Http/Controllers/WebRtcWebhookController.php`: `POST /api/webhooks/webrtc` endpoint verifying HMAC-SHA256 signature (`X-Signature: sha256=...`), processing lifecycle events (`session.started`, `session.ended`, `recording.ready`, `session.quality_alert`), updating `VideoRoom` status, inserting `VideoAttendee` telemetry metrics (MOS, packet loss, RTT), automatically inserting `ProntuarioTimeline` event (`tipo_evento: acolhimento_video`), and logging immutable audit trail via `AuditService::log()`.
8. **Routing Registration**:
   - Complete `routes/api.php` and `routes/web.php` with all M3 endpoints.
9. **Automated Test Suite & Verification**:
   - Create comprehensive tests in `tests/Feature/` and `tests/Unit/`.
   - Create standalone PHP verification runner `tests/run_m3_verification.php` that executes assertions for all M3 features.
   - Run:
     1) `php tests/run_verification.php`
     2) `php tests/run_m3_verification.php`
     3) `python tests_e2e/test_runner.py`
   - Ensure all tests pass with 100% success rate.
