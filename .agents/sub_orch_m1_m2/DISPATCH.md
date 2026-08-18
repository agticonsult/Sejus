## 2026-08-17T12:18:12Z

You are the Sub-orchestrator for Milestones M1 & M2 of the CONECTA EGRESSO (SEJUS/ES) platform.
Your working directory for metadata is: d:\Agile\projeto dia 18\.agents\sub_orch_m1_m2
Project root: d:\Agile\projeto dia 18
Parent conversation ID: 29c133b3-c8cb-485f-8777-6d6d91b3abc4

Authoritative specifications to read:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`

Your Mission:
1. Initialize your BRIEFING.md, plan.md, and progress.md in `d:\Agile\projeto dia 18\.agents\sub_orch_m1_m2`.
2. Execute Milestones M1 and M2 via the Explorer -> Worker -> Reviewer -> Challenger -> Auditor iteration loop:
   - **M1 (Docker Multi-Service Environment)**:
     - `docker-compose.yml` (Nginx, PHP 8.3 FPM, Python FastAPI WebRTC, PostgreSQL 16 PostGIS/pgcrypto, Redis 7, Coturn STUN/TURN).
     - `docker/nginx/nginx.conf` (Reverse proxy routing Laravel & WebRTC).
     - `docker/php/Dockerfile` & `php.ini`.
     - `docker/python/Dockerfile`.
     - `docker/coturn/turnserver.conf`.
   - **M2 (Database Models, Migrations, Seeds & Core Services)**:
     - 12 PostgreSQL migrations covering all entities (users, perfis, egressos, prontuarios, prontuario_timeline, prontuario_audit_logs, video_rooms, video_attendees, vagas_emprego, cursos_capacitacao, municipios_es, rede_apoio).
     - Seeder with all 78 ES municipalities, official IBGE codes, lat/long & PostGIS coordinates.
     - LGPD blind index hashing (HMAC-SHA256) and AES-256 field encryption for CPF/PII.
     - Immutable audit log rule/trigger (`RULE DO INSTEAD NOTHING`) with hash chaining (SHA-256).
     - Dompdf Digital Wallet service with SEJUS credential layout and photo placeholder.
     - Cryptographic QR Code generator (HMAC-SHA256 signature) and verification logic.
     - Realistic seed data for Gestor, Técnico, Egresso, jobs, courses, and support network (CRAS, CREAS, SINE, CAPS).
     - Full PHPUnit/Pest unit & feature tests for DB, crypto, audit log, PDF, and QR verification.
3. Validate everything with Reviewers, Challengers, and Forensic Auditor.
4. When M1 and M2 are fully verified and pass the gate, write your handoff and send a completion message to the parent orchestrator.
