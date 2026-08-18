# Changes Implemented for Milestone M3: Backend Business APIs, RBAC & Webhooks

**Worker**: worker_m3  
**Date**: 2026-08-17  
**Milestone**: M3 (Backend Business APIs, RBAC & Webhooks)

---

## 1. Authentication & RBAC Services & Controllers
- **`app/Services/GovBrAuthService.php`**:
  - Implemented simulated OpenID Connect / Gov.br / Acesso Cidadão claim mapping with trust level verification (Bronze, Prata, Ouro).
  - Configured fail-secure fallback to `egresso` role for unrecognized organizations/roles.
  - Linked automatic creation/reconciliation of `Egresso` profiles with blind index hashing.
  - Added `simulateRoleLogin()` for rapid demonstrative role switching and testing.
  - Recorded immutable login audit logs via `AuditService::log()`.
- **`app/Http/Controllers/AuthController.php`**:
  - `POST /api/auth/login`: Email/CPF + password authentication with blind-index CPF lookup and inactive account suspension.
  - `POST /api/auth/govbr/login`: Gov.br / Acesso Cidadão OIDC login handler.
  - `POST /api/auth/switch-role`: Demonstrative role switching (`gestor`, `tecnico`, `egresso`, `familiar`).
  - `GET /api/auth/me`: Authenticated user profile with masked CPF/telefone and linked egresso data.
  - `POST /api/auth/logout`: Session termination and audit logging.

## 2. Middleware & Policies
- **`app/Http/Middleware/CheckRole.php`**:
  - Validates authentication and active user status (returns 401 for unauthenticated, 403 for inactive/deactivated).
  - Enforces role-based access control with support for comma-separated permitted roles (e.g. `role:gestor,tecnico`).
- **`app/Http/Middleware/AuditAccessLog.php`**:
  - Intercepts requests on sensitive endpoints, sanitizes payloads, extracts route/prontuario identifiers, and records immutable chained audit entries via `AuditService::log()`.
- **`bootstrap/app.php`**:
  - Registered middleware aliases: `'role'`, `'rbac'`, `'audit'`, `'audit.log'`.
- **`app/Policies/ProntuarioPolicy.php`**:
  - Granular permissions for Gestor (full governance read/write/delete/audit), Técnico (clinical read/write and evoluções), and Egresso (restricted self-read without confidential technical notes).
- **`app/Policies/CarteiraPolicy.php`**:
  - Permissions for viewing, downloading signed PDF, and reissuing digital credentials.
- **`app/Policies/VagaEmpregoPolicy.php` & `app/Policies/VideoRoomPolicy.php`**:
  - Job vacancy CRUD policies and video attendance room access authorization.

## 3. Prontuário Único & Timeline APIs
- **`app/Http/Controllers/ProntuarioController.php`**:
  - `index`: Clamped pagination (1..100), filters by status, technician, municipality, and blind-index search by CPF/name/prontuário number. Automatic audit log on listing.
  - `store`: Creates new record with sequential ID format `PRT-2026-XXXXXX` and `AuditService::log(..., 'CREATE')`.
  - `show`: Details with eager loading of egresso, municipality, responsible technician, timeline, and video rooms. Enforces self-only view for egresso.
  - `update`: Updates diagnosis, individual plan goals, and status with 64KB bound.
  - `destroy`: Archives prontuário (Gestor only) with audit log.
- **`app/Http/Controllers/ProntuarioTimelineController.php`**:
  - `index`: Chronological list of interventions.
  - `store` & `storeEvolucao`: Strict boundary checks:
    - 403 Forbidden for Egresso.
    - 404 for non-existent prontuário.
    - 422 for empty/whitespace-only description.
    - 413 for payload > 64KB (65,536 bytes).
    - Taxonomy validation for 11 permitted event types.
    - Author ID binding to authenticated user.
    - XSS entity escaping (`htmlspecialchars`).
    - Automatic `AuditService::log()` chained entry.

## 4. Vagas de Emprego, Cursos de Capacitação & Candidaturas
- **`app/Http/Controllers/VagaEmpregoController.php`**:
  - Filtering by 78 ES municipalities, affirmative action (`afirmativa_egresso`), category, and minimum salary clamped >= 0.
  - Accent-insensitive search on title/company/description.
  - `candidatar`: Validates spot availability, locates atendido's Prontuário, automatically inserts `encaminhamento_vaga` timeline event, and records audit log.
- **`app/Http/Controllers/CursoCapacitacaoController.php`**:
  - Filtering by modality (`presencial`, `ead`, `hibrido`), municipality, financial aid allowance (`com_bolsa`), and EAD availability.
  - `inscrever`: Enrolls egresso, locates Prontuário, automatically records `inscricao_curso` timeline event, and logs audit trail.
- **`app/Http/Controllers/CandidaturaController.php`**:
  - Aggregates and tracks job candidacies and course enrollments across the platform.

## 5. Territorial Mapping & Rede de Apoio
- **`app/Http/Controllers/TerritorioController.php`**:
  - Lists all 78 ES municipalities with aggregated stats (open jobs, active support units).
  - Validates 7-digit IBGE codes starting with `32` (rejects non-ES codes with HTTP 422).
  - Identifies 4 physical Social Offices (Vitória, Vila Velha, Serra, Cariacica) and 74 remote teleassistance municipalities.
  - `regioes`: Summary breakdown across 4 macro-regions (Metropolitana, Norte, Sul, Central) and 10 micro-regions.
- **`app/Http/Controllers/RedeApoioController.php`**:
  - Lists CRAS, CREAS, SINE, CAPS, Defensoria, Casa do Cidadão, and Escritórios Sociais.
  - Implemented dynamic GPS fallback policy: if unit coordinates are null, falls back to host municipality centroid GPS (`origem_coordenada: "municipality_centroid_fallback"`).

## 6. Management KPIs & Analytics
- **`app/Http/Controllers/KpiDashboardController.php`**:
  - `dashboard`: Executive metrics including `meta_populacional_egressos_es: 108000`, total attendances, remote assistance rate (60.0%), employment rate (60.6%), non-recidivism benchmark (82.5%), active jobs/courses, and mean WebRTC MOS score.
  - `regional`: Attendance distribution across 4 macro-regions and top municipalities.
  - `timeline`: 12-month historical time series for attendances, job referrals, and course enrollments.
  - `telemetria`: WebRTC audio/video telemetry metrics (MOS score distribution across Excelente, Bom, Regular, Ruim, average duration, packet loss, RTT latency).

## 7. WebRTC Room Token Generation & Webhook Ingestion
- **`app/Services/WebRtcJwtService.php`**:
  - RFC 7519 compliant HS256 JWT encoder, decoder, and validator.
  - Verification of signature with timing-attack resistant `hash_equals()`, token expiration (`exp`), and not-before (`nbf`).
  - Coturn STUN/TURN ICE server array generation and WebSocket signaling URL generation.
- **`app/Http/Controllers/WebRtcTokenController.php`**:
  - `POST /api/webrtc/token`: Generates signed room token and ICE configuration after verifying room status and participant authorization.
- **`app/Http/Controllers/WebRtcWebhookController.php`**:
  - `POST /api/webhooks/webrtc`: HMAC-SHA256 signature verification (`X-Signature: sha256=...`).
  - Ingests `session.started`, `session.ended`, `recording.ready`, `session.quality_alert`.
  - On `session.ended`, updates `VideoRoom` status to `encerrada`, persists `VideoAttendee` telemetry metrics (MOS score, packet loss, jitter, RTT), resolves atendido's `Prontuario`, automatically appends an `acolhimento_video` timeline event with attendance duration and MOS quality score, and writes a cryptographic chained audit record via `AuditService::log()`.

## 8. Routing & Test Suites
- **`routes/api.php` & `routes/web.php`**: Full route registration for all M3 public and authenticated endpoints.
- **`tests/run_m3_verification.php`**: Standalone PHP verification runner with 49 assertions covering all M3 features (100% pass rate).
- **`tests/Unit/` & `tests/Feature/`**: Unit and Feature test suites for WebRTC JWT, Auth, RBAC, Prontuário, Vagas, Território, KPIs, and Webhooks.
