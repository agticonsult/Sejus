## 2026-08-17T12:19:06Z

You are the Tier 1 Feature Test Writer for CONECTA EGRESSO (SEJUS/ES).
Working directory for metadata: d:\Agile\projeto dia 18\.agents\test_writer_tier1_1
Parent conversation ID: 6457978f-379c-4b6f-802d-5401775f664e

Authoritative specifications to read first:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\TEST_INFRA.md`

Your Mission:
Create the complete Tier 1 Feature Test suite in `d:\Agile\projeto dia 18\tests_e2e\tier1_features\` covering all 50 features from PROJECT.md in isolation (>= 50 test cases total).

Create the following test modules with genuine, verifiable test assertions:
1. `tests_e2e/tier1_features/__init__.py`
2. `tests_e2e/tier1_features/test_f01_f05_docker_infra.py`: (Features F01-F05)
   - Test F01: Docker Compose multi-service topology configuration
   - Test F02: Nginx reverse proxy routing rules (/ -> Laravel, /ws and /api/webrtc -> FastAPI)
   - Test F03: Coturn STUN/TURN credentials & mobile NAT traversal config
   - Test F04: PostgreSQL 16 PostGIS and pgcrypto extensions
   - Test F05: Redis 7.2 configuration for pub/sub & queues
3. `tests_e2e/tier1_features/test_f06_f09_db_lgpd.py`: (Features F06-F09)
   - Test F06: 12 database tables schema definition & foreign keys
   - Test F07: 78 ES municipalities seeder with IBGE codes and coordinates
   - Test F08: LGPD blind index hashing (HMAC-SHA256) and AES-256 CPF field encryption
   - Test F09: Immutable audit log trigger/rule & SHA-256 hash chaining
4. `tests_e2e/tier1_features/test_f10_f12_carteira_qr.py`: (Features F10-F12)
   - Test F10: Digital Wallet PDF layout and fields (SEJUS template)
   - Test F11: Cryptographic QR code generation with HMAC-SHA256 signature
   - Test F12: Public verification route `/validar-carteira/{hash}` resolution
5. `tests_e2e/tier1_features/test_f13_f16_rbac_auth.py`: (Features F13-F16)
   - Test F13: Demo user seed profiles (Gestor, Técnico, Egresso)
   - Test F14: RBAC authentication system & role permissions
   - Test F15: Simulated OIDC / Gov.br / Acesso Cidadão claim mapping
   - Test F16: Role-based middleware & route authorization policies
6. `tests_e2e/tier1_features/test_f17_f18_prontuario_timeline.py`: (Features F17-F18)
   - Test F17: Prontuário Único CRUD API with audit logging
   - Test F18: Prontuário timeline event recording (atendimentos, encaminhamentos)
7. `tests_e2e/tier1_features/test_f19_f21_vagas_territorio.py`: (Features F19-F21)
   - Test F19: Job opportunities API with affirmative action tags & municipality filter
   - Test F20: Training courses API
   - Test F21: Territorial mapping API for 78 municipalities (CRAS, CREAS, SINE)
8. `tests_e2e/tier1_features/test_f22_kpis_gestao.py`: (Feature F22)
   - Test F22: Management KPI aggregation API (attendances by municipality, recidivism reduction)
9. `tests_e2e/tier1_features/test_f23_f25_webrtc_webhooks.py`: (Features F23-F25)
   - Test F23: WebRTC Room authorization API & JWT generation
   - Test F24: WebRTC Webhook ingest endpoint with HMAC verification
   - Test F25: Automatic Prontuário timeline insertion upon video call conclusion
10. `tests_e2e/tier1_features/test_f26_f33_python_webrtc.py`: (Features F26-F33)
    - Test F26: FastAPI WebSocket signaling server endpoint
    - Test F27: SDP Offer/Answer exchange protocol
    - Test F28: ICE Candidate trickle & routing
    - Test F29: Real-time queue management (waiting room)
    - Test F30: WebRTC connection telemetry & MOS calculation
    - Test F31: Redis Pub/Sub multi-instance room state sync
    - Test F32: Signed webhook dispatcher to Laravel
    - Test F33: Video room auto-expiration & cleanup
11. `tests_e2e/tier1_features/test_f34_f47_frontend_views.py`: (Features F34-F47)
    - Tests for Vue 3 scaffolding, Global layout, High Contrast, Font size zoom, Simplified Language mode, Dashboard, Video Attendance, Opportunities, Digital Wallet, Territorial Map, Prontuário Único, Management Reports, Security & LGPD, Public Validation page.
12. `tests_e2e/tier1_features/test_f48_f50_e2e_meta.py`: (Features F48-F50)
    - Tests for full multi-service integration, test suite execution criteria, and coverage hardening.
