# Handoff Report: Milestone M3 Backend Business APIs, RBAC & Analytics

**Explorer**: explorer_m3_2  
**Milestone**: M3 (Backend Business APIs, RBAC & Webhooks)  
**Date**: 2026-08-17  
**Type**: Hard Handoff (Investigation Complete)  
**Reference Document**: `d:\Agile\projeto dia 18\.agents\explorer_m3_2\analysis.md`

---

## 1. Observation

### 1.1 Existing Database Models & Migrations
Direct inspection of the codebase revealed the following database architecture and models:
- `app/Models/Prontuario.php` (lines 11-86): Eloquent model with 1:1 `egresso()`, N:1 `tecnicoResponsavel()`, 1:N `timeline()`, 1:N `auditLogs()`, and 1:N `videoRooms()`.
- `app/Models/ProntuarioTimeline.php` (lines 10-62): Model managing chronological events with `tipo_evento`, `titulo`, `descricao`, `metadata` (JSON), `responsavel_id`, and `data_evento`.
- `app/Models/ProntuarioAuditLog.php` (lines 10-74): Model for cryptographic audit trail (`previous_hash`, `current_hash`, `acao`, `details`, `timestamp`).
- `app/Models/Egresso.php` (lines 13-228): Handles LGPD blind index (`hash_cpf`) and encrypted fields (`cpf_encrypted`, `rg_encrypted`, `filiacao_mae_encrypted`, `endereco_encrypted`, `telefone_encrypted`) via accessors/mutators.
- `app/Models/VagaEmprego.php` (lines 10-81): Model with `empresa`, `titulo`, `categoria`, `municipio_id`, `salario`, `afirmativa_egresso`, `vagas_totais`, `vagas_preenchidas`, `status`.
- `app/Models/CursoCapacitacao.php` (lines 10-67): Model with `instituicao`, `titulo`, `categoria`, `municipio_id` (nullable for EAD), `carga_horaria`, `modalidade`, `bolsa_auxilio`, `vagas_disponiveis`, `status`.
- `app/Models/MunicipioEs.php` (lines 10-107): Fully seeded with all 78 ES municipalities, official IBGE codes, lat/long centroids, and `tem_escritorio_fisico` flag.
- `app/Models/RedeApoio.php` (lines 10-100): Socio-assistive support units (`CRAS`, `CREAS`, `SINE`, `CAPS`, `DEFENSORIA`, `CASA_CIDADAO`).
- `app/Services/AuditService.php` (lines 10-155): Implements SHA-256 hash chaining via `calculateRecordHash()`, `log()`, and `verifyChainIntegrity()`.

### 1.2 Existing Routes & Controllers
- `routes/api.php` currently has:
  ```php
  Route::get('/health', ...);
  Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validarApi']);
  ```
- `app/Http/Controllers/` currently only contains `CarteiraValidationController.php` and `Controller.php`.
- The business controllers (`ProntuarioController`, `ProntuarioTimelineController`, `VagaEmpregoController`, `CursoCapacitacaoController`, `TerritorioController`, `RedeApoioController`, `KpiDashboardController`) need to be implemented.

### 1.3 Authoritative Test Requirements
- `tests_e2e/tier1_features/test_f17_f18_prontuario_timeline.py` (lines 24-175): Verifies Prontuário CRUD triggering audit logs (`CREATE`, `READ`, `UPDATE`) and timeline event recording with taxonomy verification.
- `tests_e2e/tier1_features/test_f19_f21_vagas_territorio.py` (lines 23-128): Verifies job vacancies filtering by municipality and affirmative action tag (`afirmativa_egresso: true`), courses listing, and territorial mapping for 78 municipalities.
- `tests_e2e/tier1_features/test_f22_kpis_gestao.py` (lines 21-87): Verifies executive dashboard metrics aggregation (`total_atendimentos`, `taxa_remoto_pct`, `taxa_empregabilidade_pct`, `taxa_sucesso_nao_reincidencia_pct`, `meta_populacional_egressos_es: 108000`) and regional breakdown across ES.
- `tests_e2e/tier2_boundaries/test_prontuario_boundaries.py` (lines 27-400): Enforces boundaries:
  - Empty evolution text rejection (HTTP 422 `validation_error_empty_description`).
  - Maximum payload size 64KB (65,536 bytes; > 64KB returns HTTP 413).
  - Non-existent ID returns HTTP 404.
  - Invalid non-integer ID returns HTTP 400.
  - Malformed timestamp returns HTTP 422.
  - XSS script tags sanitized/escaped before database storage.
  - Author mismatch forged payload ID overridden by authenticated user ID.
  - Egresso role writing evolution note rejected with HTTP 403 Forbidden.
- `tests_e2e/tier2_boundaries/test_territory_payload_limits.py` (lines 24-340): Enforces boundaries:
  - IBGE codes must be 7 digits starting with `32` (ES prefix).
  - Coordinates bounded by ES WGS84 bounding box (Lat `-21.31` to `-17.88`, Lon `-41.88` to `-39.66`).
  - Missing CRAS/SINE coordinates fallback to municipality centroid coordinates.
  - Pagination DoS limit clamped strictly between 1 and 100.
  - Negative salary clamped to 0.0.
  - Accent-insensitive search matching ("Vitória" matches "vitoria").
  - Exact count of 78 municipalities in Espírito Santo.

---

## 2. Logic Chain

1. **Prontuário Único & Timeline**:
   - Observations show `Prontuario` and `ProntuarioTimeline` models already exist with correct relationships and schema.
   - To satisfy LGPD and security requirements, `ProntuarioController` and `ProntuarioTimelineController` must intercept all requests and call `AuditService::log()` on every read and write operation.
   - For evolution notes, boundary constraints from `test_prontuario_boundaries.py` require strict validation: reject empty notes with 422, reject payloads > 64KB with 413, sanitize HTML entities, bind `responsavel_id` to `Auth::id()`, and enforce RBAC where only `tecnico` and `gestor` roles can create evoluções.

2. **Vagas de Emprego & Cursos de Capacitação**:
   - `VagaEmprego` and `CursoCapacitacao` models are linked to `MunicipioEs`.
   - `VagaEmpregoController` must provide filtering by `municipio` (accent-insensitive), `afirmativa_egresso`, `categoria`, and `salario_min` (clamped >= 0).
   - Candidatura/Inscrição endpoints (`POST /api/vagas/{id}/candidatar`, `POST /api/cursos/{id}/inscrever`) must automatically insert an `encaminhamento_vaga` or `inscricao_curso` timeline event into the egresso's `Prontuario`, ensuring full business workflow continuity.

3. **Territorial Mapping & Rede de Apoio**:
   - `MunicipioEs` has all 78 ES municipalities seeded with IBGE codes and coordinates.
   - `TerritorioController` and `RedeApoioController` must validate that IBGE codes start with `32` and coordinates fall within the ES bounding box (`[-21.31, -17.88]` lat, `[-41.88, -39.66]` lon).
   - If a support unit has null GPS coordinates, the controller must fall back to the host municipality's centroid coordinates with origin metadata (`origem_coordenada: "municipality_centroid_fallback"`).

4. **Management KPIs & Analytics**:
   - `KpiDashboardController` aggregates data across `Prontuario`, `ProntuarioTimeline`, `Egresso`, `VagaEmprego`, `CursoCapacitacao`, `MunicipioEs`, and `VideoAttendee`.
   - The endpoints compute executive KPIs (`total_atendimentos`, `taxa_remoto_pct`, `taxa_empregabilidade_pct`, `taxa_sucesso_nao_reincidencia_pct`, `qualidade_media_video_mos`) and regional distributions (Metropolitana vs Interior, 4 Macrorregiões, 78 Municipalities).

---

## 3. Caveats

1. **Authentication & Token Middleware Scope**: Auth login routes (`/login`, Gov.br/Acesso Cidadão simulation) and JWT generation are investigated and implemented in parallel by peer explorers (`explorer_m3_1` and `explorer_m3_3`). Controllers should utilize standard `Auth::id()` / `Auth::user()` and role helper methods (`isGestor()`, `isTecnico()`, `isEgresso()`).
2. **Database Engine Differences**: In local testing, SQLite memory database is used via `phpunit.xml`. SQLite supports JSON casting and standard Eloquent operations. PostgreSQL 16 is used in Docker Compose with PostGIS and immutable rules (`RULE DO INSTEAD NOTHING`). All query logic must remain compatible with both engines.
3. **No Caveats on Schema**: Database models, migrations, and seeders are 100% complete and fully verified from M2.

---

## 4. Conclusion

The architecture, contract specifications, and validation rules for Focus Areas 1-4 are completely defined and ready for implementation by Workers.

### Implementation Checklist for Worker:
1. **Controllers to Create in `app/Http/Controllers/`**:
   - `ProntuarioController.php` (CRUD + LGPD audit log on all reads/writes + pagination clamp 1..100).
   - `ProntuarioTimelineController.php` (Timeline index + `store` / `storeEvolucao` with 64KB boundary, 422 empty note, XSS escaping, author binding, and RBAC 403 on egresso).
   - `VagaEmpregoController.php` (Jobs CRUD, filters for 78 municipalities & affirmative action, `candidatar` action creating timeline event).
   - `CursoCapacitacaoController.php` (Courses CRUD, filters, `inscrever` action creating timeline event).
   - `CandidaturaController.php` (Listing and registration of job/course applications).
   - `TerritorioController.php` (78 ES municipalities, IBGE validation, accent-insensitivity, regional summary).
   - `RedeApoioController.php` (CRAS, CREAS, SINE, CAPS listing with centroid coordinate fallback).
   - `KpiDashboardController.php` (Dashboard summary metrics, regional breakdown, timeline trends, WebRTC telemetry MOS stats).
2. **Routes in `routes/api.php`**:
   - Register all RESTful resources and custom POST actions (`/timeline`, `/evolucao`, `/candidatar`, `/inscrever`, `/kpis/dashboard`, `/kpis/regional`, etc.).
3. **Automated Feature Tests in `tests/Feature/`**:
   - `tests/Feature/ProntuarioApiTest.php`
   - `tests/Feature/VagasCursosApiTest.php`
   - `tests/Feature/TerritorioRedeApoioApiTest.php`
   - `tests/Feature/KpiAnalyticsApiTest.php`

---

## 5. Verification Method

### 5.1 Test Commands
Execute the automated test suite locally to verify all API endpoints and boundary cases:
```bash
# Run Laravel Feature & Unit test suites
php artisan test

# Run specific API feature tests
php artisan test --filter=ProntuarioApiTest
php artisan test --filter=VagasCursosApiTest
php artisan test --filter=TerritorioRedeApoioApiTest
php artisan test --filter=KpiAnalyticsApiTest

# Run E2E Test Suite (Tiers 1 to 4)
python tests_e2e/test_runner.py
```

### 5.2 Verification Checklist
- [ ] `GET /api/prontuarios` returns HTTP 200 with paginated records and generates an audit log.
- [ ] `POST /api/prontuarios` creates prontuário with format `PRT-2026-XXXXXX` and creates audit log.
- [ ] `POST /api/prontuarios/{id}/timeline` with empty text returns HTTP 422.
- [ ] `POST /api/prontuarios/{id}/timeline` with payload > 64KB returns HTTP 413.
- [ ] `POST /api/prontuarios/{id}/timeline` by Egresso role returns HTTP 403.
- [ ] `GET /api/vagas?municipio=Linhares&afirmativa_egresso=1` filters correctly.
- [ ] `POST /api/vagas/{id}/candidatar` records `encaminhamento_vaga` on the egresso's timeline.
- [ ] `GET /api/territorios` returns all 78 municipalities in Espírito Santo.
- [ ] `GET /api/rede-apoio` resolves centroid fallback when facility GPS is null.
- [ ] `GET /api/kpis/dashboard` returns `meta_populacional_egressos_es: 108000` and calculated rates.
- [ ] `GET /api/kpis/regional` returns breakdown across 4 Macrorregiões (Metropolitana, Norte, Sul, Central).
