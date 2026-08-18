## 2026-08-17T17:19:40Z
<USER_REQUEST>
You are the Sub-orchestrator for Milestone M3: Backend Business APIs, RBAC & Webhooks.

Your working directory is: d:\Agile\projeto dia 18\.agents\sub_orch_m3_backend
Project root: d:\Agile\projeto dia 18
Your Parent Conversation ID: 9285f12b-64c2-4188-ba61-bc8ba009b89b

Mandatory Reading:
- d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md
- d:\Agile\projeto dia 18\PROJECT.md
- d:\Agile\projeto dia 18\TEST_INFRA.md

Scope of Milestone M3:
1. Authentication & RBAC: Simulated OIDC / Gov.br / Acesso Cidadão login provider with claim mapping, session management, roles (Gestor SEJUS, Técnico Escritório Social, Egresso/Familiar).
2. Role-based Middleware & Policies: CheckRole, AuditAccessLog middleware ensuring LGPD compliance on all reads/writes.
3. Prontuário Único CRUD API: Full CRUD for Prontuário with automatic audit logging on every read/write.
4. Prontuário Timeline & Evoluções: Timeline event recording (atendimentos, encaminhamentos, cursos, vagas, video chamadas).
5. Vagas de Emprego & Cursos de Capacitação APIs: Querying, filtering by 78 ES municipalities, affirmative action flags, application registration.
6. Territorial Mapping & Rede de Apoio APIs: Endpoints for 78 ES municipalities data, CRAS, CREAS, SINE, CAPS info.
7. Management KPIs & Analytics APIs: Aggregate metrics for attendances, regional distribution, recidivism reduction, job placement rates.
8. WebRTC Room Token Generator (`POST /api/webrtc/token`): Validates user and room, signs JWT with shared secret for FastAPI microservice.
9. WebRTC Webhook Ingest (`POST /api/webhooks/webrtc`): Verifies HMAC-SHA256 signature (`X-Signature` header), records session details, and automatically inserts an immutable `ProntuarioTimeline` event for the atendido.
10. Comprehensive automated test suite in `tests/` verifying all controllers, middleware, policies, APIs, and webhooks.

Execute the full Sub-orchestrator workflow:
- Decompose scope or run iteration loop (Explorer -> Worker -> Reviewer -> Challenger -> Forensic Auditor -> Gate).
- When Worker is dispatched, include the mandatory integrity warning.
- Ensure all tests pass.
- Write handoff report and notify parent via send_message when gate passes.
</USER_REQUEST>
