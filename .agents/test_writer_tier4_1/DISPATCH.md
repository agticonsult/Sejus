## 2026-08-17T12:19:06Z
You are the Tier 4 Real-World Application Scenarios Test Writer for CONECTA EGRESSO (SEJUS/ES).
Working directory for metadata: d:\Agile\projeto dia 18\.agents\test_writer_tier4_1
Parent conversation ID: 6457978f-379c-4b6f-802d-5401775f664e

Authoritative specifications to read first:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\TEST_INFRA.md`

Your Mission:
Create the complete Tier 4 Real-World Application Scenarios in `d:\Agile\projeto dia 18\tests_e2e\tier4_scenarios\` (4 comprehensive E2E user journey workflows).

Implement the following 4 realistic operational scenarios:
1. `tests_e2e/tier4_scenarios/__init__.py`
2. `tests_e2e/tier4_scenarios/scenario_gestor_audit_kpis.py`:
   - **Scenario 1: Gestor SEJUS Global Audit & Analytics (F14, F15, F16, F21, F22, F45, F46)**
   - Complete workflow:
     1. Gestor authenticates via Gov.br / Acesso Cidadão simulation.
     2. Accesses Management Dashboard and verifies KPI statistics across all 78 Espírito Santo municipalities.
     3. Filters territorial analytics by micro-region (e.g. Região Metropolitana vs Norte).
     4. Accesses Security & LGPD Audit Viewer.
     5. Verifies cryptographic SHA-256 hash chaining of audit logs across recent interventions.
     6. Inspects system telemetry and exports attendance metrics report.
3. `tests_e2e/tier4_scenarios/scenario_egresso_onboarding_wallet.py`:
   - **Scenario 2: Egresso Digital Onboarding & Credential Issuance (F08, F10, F11, F12, F17, F42, F47)**
   - Complete workflow:
     1. Newly registered Egresso logs in to portal.
     2. Validates encrypted PII / blind index storage (CPF masked in UI).
     3. Consults initial Prontuário Único welcome record.
     4. Accesses Digital Wallet (*Carteira Digital do Egresso*) page.
     5. Generates and downloads official SEJUS PDF credential.
     6. Extracts embedded QR Code containing HMAC-SHA256 signature.
     7. Performs public verification request against `/validar-carteira/{hash}`.
     8. Confirms valid credential status, issue timestamp, and official SEJUS validation seal.
4. `tests_e2e/tier4_scenarios/scenario_video_attendance_prontuario.py`:
   - **Scenario 3: Remote Video Social Attendance & Prontuário Auto-Log (F17, F18, F23, F24, F25, F26, F27, F28, F30, F32, F40, F44)**
   - Complete workflow:
     1. Social Office Technician logs in and opens video attendance queue.
     2. Egresso enters waiting room from mobile client.
     3. Technician admits Egresso and requests signed WebRTC room JWT token from Laravel.
     4. Both clients establish WebSocket connection to Python FastAPI signaling server.
     5. Exchange SDP Offer/Answer and ICE candidates.
     6. Simulate real-time session with 4G mobile network telemetry (jitter, packet loss, MOS calculation).
     7. Video call concludes after scheduled duration.
     8. FastAPI dispatches HMAC-SHA256 signed webhook (`session_ended`) to Laravel backend.
     9. Laravel verifies webhook signature and automatically creates an immutable `ProntuarioTimeline` attendance record with duration, date, and MOS quality metrics.
     10. Technician opens Prontuário Único view and confirms the automated timeline entry is present.
5. `tests_e2e/tier4_scenarios/scenario_interior_job_application.py`:
   - **Scenario 4: Interior Territorial Job Application in Linhares (F07, F19, F20, F21, F41, F43)**
   - Complete workflow:
     1. Egresso resident in Linhares/ES logs in to the platform.
     2. Activates Accessibility Toolbar: Simplified Language mode (*Linguagem Fácil*) and High Contrast.
     3. Navigates to Opportunities View and filters affirmative action job vacancies for Linhares (IBGE 3203205).
     4. Consults available professional training courses in the Rio Doce region.
     5. Inspects Territorial Map for Linhares, viewing local SINE and CRAS contact details and address.
     6. Submits job application for affirmative vacancy.
     7. Verifies application confirmation and automatic event logged in Egresso's Prontuário timeline.
