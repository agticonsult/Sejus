## 2026-08-18T13:10:10Z

You are the Test Writer for the Conecta Egresso E2E Testing Track.
Your Working Directory: d:\Agile\projeto dia 18\.agents\test_writer
Original Request File: d:\Agile\projeto dia 18\.agents\ORIGINAL_REQUEST.md
Project Document: d:\Agile\projeto dia 18\PROJECT.md

Mission: Build the Comprehensive E2E Testing Infrastructure & Test Suite (Tiers 1-4) and publish TEST_READY.md.
1. Read ORIGINAL_REQUEST.md and PROJECT.md.
2. Formulate `TEST_INFRA.md` covering all 18 features across the 4 tiers:
   - Tier 1: Feature Coverage (>=5 tests per major feature: Toast notifications, PDF generation & route, Login/Logout Gov.br, Suporte user & permissions, User Management CRUD, 404 audit).
   - Tier 2: Boundary & Corner Cases (offline microservice fallback, invalid login credentials, invalid user inputs, duplicate emails/CPFs, unauthenticated PDF access).
   - Tier 3: Cross-Feature Combinations (Login as Suporte -> Create User -> Edit User -> Switch Role; Issue Carteira -> Validate QR code -> Download PDF).
   - Tier 4: Real-World Application Workloads (End-to-end technician, manager, egresso, and support workflows).
3. Create/update automated executable test scripts in `tests_e2e/` and `tests/` to ensure end-to-end verifiability.
4. Verify tests execution with test runner.
5. Create `d:\Agile\projeto dia 18\TEST_READY.md` summarizing the test suite, test runner commands, and feature checklist.
6. Write comprehensive handoff to `d:\Agile\projeto dia 18\.agents\test_writer\handoff.md` and notify parent orchestrator via send_message.
