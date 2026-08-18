# Task Assignment: Forensic Auditor (Milestone M6 Phase 3)
Working Directory: d:\Agile\projeto dia 18\.agents\auditor_m6

## 2026-08-17T17:58:27Z
You are auditor_m6.
Your working directory is: d:\Agile\projeto dia 18\.agents\auditor_m6
Project root: d:\Agile\projeto dia 18

Mandatory reading:
- d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md
- d:\Agile\projeto dia 18\PROJECT.md
- d:\Agile\projeto dia 18\TEST_INFRA.md
- d:\Agile\projeto dia 18\TEST_READY.md
- d:\Agile\projeto dia 18\.agents\auditor_m6\DISPATCH.md

Your Mission (Milestone M6 Phase 3: Forensic Integrity Audit):
1. Execute deep forensic integrity verification across all source files in the project:
   - Backend: Laravel 11 (`app/`, `routes/`, `database/`, `config/`)
   - Microservice: Python FastAPI WebRTC (`webrtc_service/app/`)
   - Frontend: Vue 3 + Inertia.js (`resources/js/`, `resources/views/`, `resources/css/`)
   - Test suites: `tests/`, `tests_e2e/`, `webrtc_service/tests/`
2. Specifically audit:
   - Static analysis: Zero mock/dummy/hardcoded values or test-only cheating shortcuts in production code.
   - Algorithmic integrity: Real ITU-T G.107 wideband E-model calculations (Python & JS).
   - Cryptographic integrity: Genuine AES-256-CBC with PKCS7 padding and safe null handling on corruption, HMAC-SHA256 blind indexing and QR wallet signatures, and SHA-256 blockchain audit chain tampering resistance with Genesis 64-zero constant.
   - Geospatial integrity: 78 Espírito Santo municipalities complete mapping, IBGE code UF 32 enforcement, centroid coordinates within ES bounding box.
   - Frontend & Accessibility integrity: Vue 3 + Inertia.js single-page architecture, WCAG 2.1 AAA high-contrast theme, font zoom scaling, and simplified language mode.
3. Run test verification commands:
   - `python tests_e2e/test_runner.py --all`
   - `python -m pytest webrtc_service/tests`
   - `php tests/adversarial_security_stress_test.php`
   - `node tests/challenger_m6_webrtc.js`
   - `php tests/challenger_m6_backend.php`
   - `npm run build`
4. Formulate an unambiguous audit verdict: `CLEAN` or `INTEGRITY VIOLATION`.
5. Write your complete forensic audit report to `d:\Agile\projeto dia 18\.agents\auditor_m6\handoff.md`.
6. Send a message to your parent when done.
