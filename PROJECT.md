# Project: CONECTA EGRESSO (SEJUS/ES)

## Architecture
A unified, multi-service public policy platform designed for the State Secretariat of Justice of Espírito Santo (SEJUS/ES) to provide remote social assistance, electronic medical/social records (*Prontuário Único*), digital credentials (*Carteira Digital do Egresso*), labor integration, and territorial coverage across all 78 municipalities in Espírito Santo.

```
                         ┌─────────────────────────────────────────┐
                         │       Nginx Reverse Proxy (:80/:443)    │
                         └───────┬─────────────────────────┬───────┘
                                 │                         │
                                 ▼                         ▼
             ┌───────────────────────────────┐ ┌───────────────────────────────┐
             │ Laravel 11 + Inertia.js Vue 3 │ │  Python FastAPI WebRTC Micro  │
             │     PHP 8.3 FPM (:8000)       │ │     FastAPI/aiortc (:8001)    │
             └───────────────┬───────────────┘ └───────────────┬───────────────┘
                             │                                 │
                             ▼                                 ▼
             ┌───────────────────────────────┐ ┌───────────────────────────────┐
             │ PostgreSQL 16 + PostGIS       │ │ Redis 7.2 (Pub/Sub, Queue)    │
             │ + pgcrypto (:5432)            │ │ (:6379)                       │
             └───────────────────────────────┘ └───────────────────────────────┘
                                                               │
                                                               ▼
                                               ┌───────────────────────────────┐
                                               │ Coturn STUN/TURN (:3478)      │
                                               │ (3G/4G/5G mobile NAT traversal│
                                               └───────────────────────────────┘
```

---

## Feature Inventory

Every feature discovered during the survey is mapped to a specific milestone.

| # | Feature Code | Description | Milestone | Source |
|---|--------------|-------------|-----------|--------|
| 1 | F01 | Docker Compose orchestration (Nginx, PHP-FPM, Python FastAPI, PostgreSQL, Redis, Coturn) | M1 | ORIGINAL_REQUEST R4 |
| 2 | F02 | Nginx reverse proxy configuration for Laravel & FastAPI routing | M1 | Survey / Architecture |
| 3 | F03 | Coturn STUN/TURN configuration for 3G/4G/5G mobile traversal | M1 | ORIGINAL_REQUEST R4 |
| 4 | F04 | PostgreSQL 16 container with PostGIS and pgcrypto extensions | M1 | ORIGINAL_REQUEST R4 |
| 5 | F05 | Redis 7 configuration for cache, background jobs, and signaling Pub/Sub | M1 | ORIGINAL_REQUEST R4 |
| 6 | F06 | Database schema & migrations (12 tables: users, perfis, egressos, prontuarios, prontuario_timeline, prontuario_audit_logs, video_rooms, video_attendees, vagas_emprego, cursos_capacitacao, municipios_es, rede_apoio) | M2 | Survey / Spec Miner |
| 7 | F07 | Seeder for all 78 ES municipalities with official IBGE codes, lat/long & PostGIS coordinates | M2 | ORIGINAL_REQUEST R1 |
| 8 | F08 | LGPD blind index hashing (HMAC-SHA256) and AES-256 field encryption for CPF/PII | M2 | Survey / Spec Miner |
| 9 | F09 | Immutable audit log trigger/rule (`RULE DO INSTEAD NOTHING`) with hash chaining (SHA-256) | M2 | ORIGINAL_REQUEST R1 |
| 10 | F10 | Digital Wallet PDF generation (Dompdf) with official SEJUS layout and photo placeholder | M2 | ORIGINAL_REQUEST R1 |
| 11 | F11 | Cryptographic QR Code generation with HMAC-SHA256 signature for verification | M2 | ORIGINAL_REQUEST R1 |
| 12 | F12 | Public verification route (`/validar-carteira/{hash}`) for QR Code validation | M2 | ORIGINAL_REQUEST R1 |
| 13 | F13 | Seed data for realistic demonstrative profiles (Gestor, Técnico, Egresso), jobs, courses, support network | M2 | Survey / Codebase |
| 14 | F14 | Authentication system with RBAC (Gestor SEJUS, Técnico Escritório Social, Egresso/Familiar) | M3 | ORIGINAL_REQUEST R1 |
| 15 | F15 | Simulated OpenID Connect / Gov.br / Acesso Cidadão login provider with claim mapping | M3 | ORIGINAL_REQUEST R1 |
| 16 | F16 | Role-based middleware & route authorization policies | M3 | ORIGINAL_REQUEST R1 |
| 17 | F17 | Prontuário Único CRUD API with automatic audit logging on every read/write | M3 | ORIGINAL_REQUEST R1 |
| 18 | F18 | Prontuário timeline event recording (atendimentos, encaminhamentos, cursos, vagas) | M3 | ORIGINAL_REQUEST R1 |
| 19 | F19 | Opportunities & Job vacancies API with municipality filters and affirmative action tags | M3 | ORIGINAL_REQUEST R1 |
| 20 | F20 | Training courses & educational opportunities API | M3 | ORIGINAL_REQUEST R1 |
| 21 | F21 | Territorial mapping API for 78 municipalities with socio-assistive network (CRAS, CREAS, SINE, CAPS) | M3 | ORIGINAL_REQUEST R1 |
| 22 | F22 | Management KPI aggregation API (attendances by municipality, recidivism reduction, job placement rates) | M3 | ORIGINAL_REQUEST R1 |
| 23 | F23 | WebRTC Room authorization API (generates signed JWT for room entry) | M3 | ORIGINAL_REQUEST R2 |
| 24 | F24 | WebRTC Webhook ingest endpoint with HMAC-SHA256 signature verification | M3 | ORIGINAL_REQUEST R2 |
| 25 | F25 | Automatic Prontuário timeline insertion upon video call conclusion webhook | M3 | ORIGINAL_REQUEST R2 |
| 26 | F26 | FastAPI asynchronous WebRTC signaling server with WebSocket endpoints | M4 | ORIGINAL_REQUEST R2 |
| 27 | F27 | SDP Offer/Answer exchange protocol | M4 | ORIGINAL_REQUEST R2 |
| 28 | F28 | ICE Candidate trickle & routing | M4 | ORIGINAL_REQUEST R2 |
| 29 | F29 | Real-time queue management (waiting room, technician notification, patient admission) | M4 | ORIGINAL_REQUEST R2 |
| 30 | F30 | WebRTC connection telemetry & quality monitoring (MOS score calculation, packet loss, latency) | M4 | ORIGINAL_REQUEST R2 |
| 31 | F31 | Redis Pub/Sub multi-instance room state synchronization | M4 | ORIGINAL_REQUEST R2 |
| 32 | F32 | Signed webhook dispatcher (session_started, session_ended, telemetry_reported) to Laravel | M4 | ORIGINAL_REQUEST R2 |
| 33 | F33 | Video call room auto-expiration and cleanup daemon | M4 | ORIGINAL_REQUEST R2 |
| 34 | F34 | Inertia.js + Vue 3 application scaffolding with TailwindCSS styling | M5 | ORIGINAL_REQUEST R3 |
| 35 | F35 | Global Layout with SEJUS/ES header, sidebar navigation, user profile info, and role switcher | M5 | ORIGINAL_REQUEST R3 |
| 36 | F36 | Accessibility Toolbar: High Contrast mode (`.high-contrast`) | M5 | ORIGINAL_REQUEST R3 |
| 37 | F37 | Accessibility Toolbar: Font size scaling (+18% zoom) | M5 | ORIGINAL_REQUEST R3 |
| 38 | F38 | Accessibility Toolbar: Simplified Language mode (*Linguagem Fácil*) for low digital literacy | M5 | ORIGINAL_REQUEST R3 |
| 39 | F39 | Dashboard View: KPI summary cards, attendance chart, regional distribution, and activity feed | M5 | ORIGINAL_REQUEST R3 |
| 40 | F40 | Video Attendance View: Queue list, call initiation, WebRTC video/audio grid, chat, call controls, and signal meter | M5 | ORIGINAL_REQUEST R3 |
| 41 | F41 | Opportunities View: Job and course list, filters by 78 municipalities, modality, and application modal | M5 | ORIGINAL_REQUEST R3 |
| 42 | F42 | Digital Wallet View: Visual credential card, QR Code display, and PDF download button | M5 | ORIGINAL_REQUEST R3 |
| 43 | F43 | Territorial Map View: Interactive map / grid of 78 municipalities, search, statistics, and local CRAS/SINE details | M5 | ORIGINAL_REQUEST R3 |
| 44 | F44 | Prontuário Único View: Egresso profile, timeline of past interventions, notes editor, and new entry modal | M5 | ORIGINAL_REQUEST R3 |
| 45 | F45 | Management Reports View: Detailed analytics, filters by date/region, export tools, and audit log viewer | M5 | ORIGINAL_REQUEST R3 |
| 46 | F46 | Security & LGPD View: Privacy policy, consent records, encryption status, and tamper-proof log inspection | M5 | ORIGINAL_REQUEST R3 |
| 47 | F47 | Public Document Validation Page (`/validar-carteira/{hash}`) | M5 | ORIGINAL_REQUEST R1 |
| 48 | F48 | Full E2E Integration between Vue frontend, Laravel backend, and Python WebRTC signaling | M6 | ORIGINAL_REQUEST Criteria |
| 49 | F49 | E2E Test Suite Execution (Tiers 1-4: Feature, Boundary, Combinatorial, Real-World) passing 100% | M6 | Acceptance Criteria |
| 50 | F50 | Tier 5 Adversarial Coverage Hardening and Forensic Audit clean verdict | M6 | Acceptance Criteria |

---

## Milestones

| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M1: Docker Infrastructure & Multi-Service Environment | Docker Compose, Dockerfiles, Nginx config, PostgreSQL PostGIS/pgcrypto, Redis, Coturn config | none | DONE |
| 2 | M2: Database Models, Migrations, Seeds & Core Services | 12 PostgreSQL migrations, Eloquent models, 78 ES municipalities seeder, LGPD blind index & audit trigger, Dompdf Digital Wallet & QR Code generator | M1 | DONE |
| 3 | M3: Backend Business APIs, RBAC & Webhooks | Laravel 11 Auth OIDC/RBAC, Prontuário CRUD, Vagas/Cursos, Territorial KPIs, WebRTC JWT token generation & Webhook receiver | M2 | DONE |
| 4 | M4: Python FastAPI WebRTC Signaling & Telemetry | FastAPI app, WebSocket signaling, Redis Pub/Sub, Queue management, MOS telemetry, HMAC webhook dispatcher | M1, M3 | DONE |
| 5 | M5: Reactive & Accessible Frontend (Inertia + Vue 3) | Inertia.js + Vue 3 app, TailwindCSS, 8 functional views, High Contrast, Font Scaling, Simplified Language, WebRTC client | M3, M4 | DONE |
| 6 | M6: E2E Full Integration, Verification & Coverage Hardening | Pass 100% E2E test suite (Tiers 1-4), Tier 5 Adversarial Coverage Hardening, Forensic Audit clean verdict | M1, M2, M3, M4, M5 | DONE |

---

## Interface Contracts

### 1. Backend Laravel ↔ Frontend Vue (Inertia.js)
- **Props Protocol**: Inertia page components receive authenticated user info (`auth.user`, `auth.role`), flash messages, and view-specific datasets.
- **Route Namespace**:
  - `GET /` -> Redirect to login or dashboard based on session.
  - `GET /dashboard` -> `Dashboard` page with KPI stats.
  - `GET /atendimento` -> `Atendimento` page with queue and room credentials.
  - `GET /oportunidades` -> `Oportunidades` page with jobs and courses.
  - `GET /carteira` -> `Carteira` page with digital wallet data and PDF download URL.
  - `GET /carteira/pdf` -> Binary PDF stream (`application/pdf`).
  - `GET /geolocalizacao` -> `Geolocalizacao` page with 78 municipalities data.
  - `GET /prontuario/{id?}` -> `Prontuario` page with timeline and history.
  - `POST /prontuario/{id}/evolucao` -> Adds new timeline entry and writes immutable audit log.
  - `GET /relatorios` -> `Relatorios` page with exportable reports.
  - `GET /seguranca-lgpd` -> `SegurancaLgpd` page with audit logs.
  - `GET /validar-carteira/{token}` -> Public validation page.

### 2. Backend Laravel ↔ Python FastAPI WebRTC Microservice
- **Room Token Generation**:
  - Laravel endpoint: `POST /api/webrtc/token`
  - Payload: `{ "user_id": 1, "name": "João Silva", "role": "tecnico", "room_id": "sala-vitoria-101" }`
  - Response: `{ "token": "<JWT_SIGNED_WITH_SHARED_SECRET>", "ws_url": "ws://localhost:8001/ws/room/sala-vitoria-101", "ice_servers": [...] }`
- **Webhook Ingest**:
  - FastAPI dispatches to Laravel: `POST /api/webhooks/webrtc`
  - Headers: `X-Signature-SHA256: <HMAC_SHA256_HEX>`, `Content-Type: application/json`
  - Payload Events:
    - `session_started`: `{ "event": "session_started", "room_id": "...", "attendee_id": "...", "timestamp": "2026-08-17T12:00:00Z" }`
    - `session_ended`: `{ "event": "session_ended", "room_id": "...", "duration_seconds": 920, "summary_telemetry": { "avg_mos": 4.3, "packet_loss_pct": 0.4 }, "timestamp": "2026-08-17T12:15:20Z" }`
  - Behavior on Laravel: Ingests event, verifies HMAC signature, records video session record, and automatically creates an immutable `ProntuarioTimeline` event for the atendido.

### 3. Python WebRTC ↔ Browser Clients (WebSocket Protocol)
- **Signaling Messages**:
  - `{"type": "join", "token": "<JWT>"}`
  - `{"type": "offer", "sdp": "..."}`
  - `{"type": "answer", "sdp": "..."}`
  - `{"type": "ice-candidate", "candidate": {...}}`
  - `{"type": "telemetry", "mos": 4.2, "rtt_ms": 45, "jitter_ms": 8, "packet_loss": 0.2}`
  - `{"type": "leave"}`

---

## Code Layout

```
d:\Agile\projeto dia 18\
├── .agents\                                # Agent metadata, plans, progress, handoffs
├── docker\                                 # Docker configuration files
│   ├── nginx\                              # Nginx reverse proxy conf
│   ├── php\                                # PHP 8.3 FPM Dockerfile and php.ini
│   ├── python\                             # Python 3.12 FastAPI Dockerfile
│   └── coturn\                             # Turnserver configuration
├── docker-compose.yml                      # Unified multi-container orchestration
├── app\                                    # Laravel application core
│   ├── Http\Controllers\                  # Controllers (Dashboard, Prontuario, Carteira, Webhook, etc.)
│   ├── Http\Middleware\                   # RBAC & LGPD audit middleware
│   ├── Models\                             # Eloquent models (Egresso, Prontuario, AuditLog, etc.)
│   └── Services\                           # Business services (CarteiraPdfService, AuditService, WebhookService)
├── database\
│   ├── migrations\                         # 12 PostgreSQL migrations
│   └── seeders\                            # Database seeders (78 municipalities, demo users, jobs)
├── routes\
│   ├── web.php                             # Inertia routes
│   └── api.php                             # REST & Webhook routes
├── resources\
│   ├── js\                                 # Inertia.js + Vue 3 Frontend
│   │   ├── Pages\                          # 8 Core Views (Dashboard, Atendimento, Oportunidades, etc.)
│   │   ├── Components\                     # UI & Accessibility components (Toolbar, Navbar, Modal, etc.)
│   │   └── app.js                          # Inertia bootstrap
│   └── css\
│       └── app.css                         # TailwindCSS + High Contrast / Accessibility styles
├── webrtc_service\                         # Python FastAPI Microservice
│   ├── app\
│   │   ├── main.py                         # FastAPI bootstrap & CORS
│   │   ├── signaling.py                    # WebSocket signaling router
│   │   ├── telemetry.py                    # MOS & network quality calculator
│   │   ├── webhooks.py                     # HMAC signed webhook client
│   │   └── config.py                       # Settings & Coturn credentials
│   ├── requirements.txt                    # Python dependencies
│   └── tests\                              # Pytest test suite
├── tests\                                  # Laravel Pest/PHPUnit tests
│   ├── Feature\                            # Feature tests for RBAC, Prontuario, Carteira, Webhooks
│   └── Unit\                               # Unit tests for crypto, blind index, PDF generation
├── tests_e2e\                              # E2E Test Suite (Tiers 1-5)
│   ├── test_runner.py                      # Multi-tier E2E Test Runner
│   ├── tier1_features\                     # Tier 1 Feature coverage tests (70 tests)
│   ├── tier2_boundaries\                   # Tier 2 Boundary & Corner case tests (61 tests)
│   ├── tier3_combinations\                 # Tier 3 Cross-feature pairwise tests (23 tests)
│   ├── tier4_scenarios\                    # Tier 4 Real-world application scenarios (21 tests)
│   └── tier5_adversarial\                  # Tier 5 Adversarial hardening tests (34 tests)
├── PROJECT.md                              # Global project architecture & milestones
├── TEST_INFRA.md                           # E2E Testing architecture & methodology
└── TEST_READY.md                           # E2E test suite publish signal (209 tests)
```
