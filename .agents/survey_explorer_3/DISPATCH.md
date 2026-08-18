## 2026-08-17T12:10:31Z
You are survey_explorer_3 (teamwork_preview_explorer).
Your working directory is: d:\Agile\projeto dia 18\.agents\survey_explorer_3
Your parent orchestrator is: 7a6b49ad-bbda-4141-b7f9-0cb92cb2ac95

Mission:
Survey the technical architecture, stack components, integration contracts, and Docker topology:
- d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md (MANDATORY: read this first!)
- d:\Agile\projeto dia 18\DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md
- d:\Agile\projeto dia 18\README.md

Analyze and specify:
1. Backend Core (Laravel 11 / PHP 8.3+) Architecture:
   - Project directory structure, Inertia.js Vue 3 integration setup.
   - Database schema (PostgreSQL 16 with PostGIS extension for geo points/polygons, pgcrypto for encrypted sensitive data like CPF/health/legal records).
   - Tables: users, roles, permissions, egressos, prontuarios, prontuario_atendimentos, prontuario_audit_logs, vagas, cursos, candidaturas, carteiras_digitais, municipios_es, video_rooms, video_sessions.
   - Seeders: all 78 Espírito Santo municipalities with coordinates and regions, test users (Gestor, Técnico, Egresso), initial jobs/courses.
   - API endpoints, Inertia controllers, Form Requests, Policies/RBAC.
   - PDF & QR Code generation libraries/strategy (e.g. dompdf/snappy/simplesoftwareio/bacon-qr-code or custom SVG/Canvas renderer).
2. WebRTC & Video Microservice (Python FastAPI / aiortc / WebSockets) Architecture:
   - WebSocket connection handling, room manager, queue manager.
   - SDP offer/answer exchange, ICE candidate trickle.
   - Quality/telemetry packet tracking.
   - JWT authentication verification with Laravel shared secret/public key.
   - Webhook dispatcher notifying Laravel on room created, user joined, call ended, recording/duration logged.
3. Coturn STUN/TURN Configuration:
   - coturn configuration file, realm, auth mechanism, ports for WebRTC over mobile networks.
4. Docker Compose Multi-Container Topology:
   - Services: nginx (reverse proxy routing web traffic to Laravel and `/ws/` / `/api/webrtc` to FastAPI), php-fpm (Laravel 11 backend), python-webrtc (FastAPI signaling server), postgres (PostgreSQL 16 + PostGIS + pgcrypto), redis (queue, cache, pub/sub), coturn (STUN/TURN).
   - Networking, volumes, healthchecks, entrypoint scripts, migrations auto-run.
5. Testing strategy & tooling across all layers (PHPUnit / Pest for Laravel, pytest for FastAPI, E2E test runner).

Write your comprehensive findings to `d:\Agile\projeto dia 18\.agents\survey_explorer_3\architecture_survey.md` and complete `d:\Agile\projeto dia 18\.agents\survey_explorer_3\handoff.md`.
Use `send_message` to report back to parent when done.
