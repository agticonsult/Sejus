# Scope: Milestones M1 & M2 (CONECTA EGRESSO)

## Architecture
Foundational layer for the CONECTA EGRESSO platform:
1. Docker Multi-Service Infrastructure:
   - `docker-compose.yml` with Nginx, PHP 8.3 FPM, Python 3.12 FastAPI, PostgreSQL 16 PostGIS/pgcrypto, Redis 7, Coturn STUN/TURN.
   - `docker/nginx/nginx.conf` reverse proxy routing.
   - `docker/php/Dockerfile` and `docker/php/php.ini`.
   - `docker/python/Dockerfile`.
   - `docker/coturn/turnserver.conf`.
2. Database, Models, Migrations, Seeds & Security Services:
   - 12 PostgreSQL migrations:
     1. `create_perfis_table` (Gestor, Técnico, Egresso, Familiar)
     2. `create_users_table` (with perfil_id, Gov.br / Acesso Cidadão fields)
     3. `create_municipios_es_table` (78 ES municipalities, IBGE codes, lat/long, PostGIS coordinates)
     4. `create_egressos_table` (LGPD encrypted CPF, blind index hash_cpf, status penal, vulnerabilidade)
     5. `create_prontuarios_table` (numero_prontuario, situacao, resumo_diagnostico)
     6. `create_prontuario_timeline_table` (tipo_evento, metadata, responsavel)
     7. `create_prontuario_audit_logs_table` (immutable audit log with SHA-256 hash chaining and PostgreSQL RULE DO INSTEAD NOTHING)
     8. `create_video_rooms_table` (room_id, status, scheduled_at, ended_at)
     9. `create_video_attendees_table` (session participation, MOS score, telemetry)
     10. `create_vagas_emprego_table` (empresa, cargo, municipio_id, afirmativa_egresso, status)
     11. `create_cursos_capacitacao_table` (instituicao, titulo, municipio_id, carga_horaria, modalidade)
     12. `create_rede_apoio_table` (CRAS, CREAS, SINE, CAPS across 78 municipalities with lat/long)
   - Eloquent Models with relationships, scopes, and casts.
   - Core Security & Utility Services:
     - `LgpdSecurityService`: HMAC-SHA256 blind index calculation, AES-256 field encryption/decryption.
     - `AuditService`: Hash chaining (SHA-256) and audit record creation.
     - `CarteiraPdfService`: Dompdf generation of SEJUS digital wallet layout with photo placeholder.
     - `QrCodeSecurityService`: Cryptographic QR code generation with HMAC-SHA256 signature and verification method.
   - Database Seeders:
     - All 78 ES municipalities with official IBGE codes and geographic coordinates.
     - Realistic users (Gestor, Técnico, Egresso).
     - Job opportunities, vocational courses, and CRAS/CREAS/SINE/CAPS support network.
   - Full PHPUnit / Pest tests covering database migrations, models, encryption, blind index, audit immutability, PDF rendering, and QR signature verification.

## Feature Inventory Mapping
| # | Feature Code | Description | Milestone |
|---|--------------|-------------|-----------|
| 1 | F01 | Docker Compose orchestration (Nginx, PHP-FPM, Python FastAPI, PostgreSQL, Redis, Coturn) | M1 |
| 2 | F02 | Nginx reverse proxy configuration for Laravel & FastAPI routing | M1 |
| 3 | F03 | Coturn STUN/TURN configuration for 3G/4G/5G mobile traversal | M1 |
| 4 | F04 | PostgreSQL 16 container with PostGIS and pgcrypto extensions | M1 |
| 5 | F05 | Redis 7 configuration for cache, background jobs, and signaling Pub/Sub | M1 |
| 6 | F06 | Database schema & migrations (12 tables) | M2 |
| 7 | F07 | Seeder for all 78 ES municipalities with official IBGE codes, lat/long & PostGIS coordinates | M2 |
| 8 | F08 | LGPD blind index hashing (HMAC-SHA256) and AES-256 field encryption for CPF/PII | M2 |
| 9 | F09 | Immutable audit log trigger/rule (`RULE DO INSTEAD NOTHING`) with hash chaining (SHA-256) | M2 |
| 10 | F10 | Digital Wallet PDF generation (Dompdf) with official SEJUS layout and photo placeholder | M2 |
| 11 | F11 | Cryptographic QR Code generation with HMAC-SHA256 signature for verification | M2 |
| 12 | F12 | Public verification logic (`/validar-carteira/{hash}`) for QR Code validation | M2 |
| 13 | F13 | Seed data for realistic demonstrative profiles, jobs, courses, support network | M2 |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | M1: Docker Infrastructure | Docker Compose, Dockerfiles, Nginx config, Coturn config, Redis, PostgreSQL PostGIS | none | DONE |
| 2 | M2: Database Models, Migrations, Seeds & Core Services | 12 Migrations, Models, 78 ES Seeder, LGPD Crypto, Audit Hash Chain, Dompdf Wallet, QR Signature | M1 | DONE |
