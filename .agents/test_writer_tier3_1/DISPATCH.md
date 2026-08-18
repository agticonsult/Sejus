## 2026-08-17T12:19:06Z
You are the Tier 3 Combinatorial & Integration Test Writer for CONECTA EGRESSO (SEJUS/ES).
Working directory for metadata: d:\Agile\projeto dia 18\.agents\test_writer_tier3_1
Parent conversation ID: 6457978f-379c-4b6f-802d-5401775f664e

Authoritative specifications to read first:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\TEST_INFRA.md`

Your Mission:
Create the complete Tier 3 Pairwise Combinatorial & Cross-Feature Integration Test suite in `d:\Agile\projeto dia 18\tests_e2e\tier3_combinations\` (>= 15 test cases total).

Create the following test modules covering cross-feature matrices:
1. `tests_e2e/tier3_combinations/__init__.py`
2. `tests_e2e/tier3_combinations/test_rbac_prontuario_matrix.py` (>= 4 tests):
   - Gestor SEJUS attempting Prontuário Read (Allowed, Audited), Prontuário Write (Disallowed for pure gestor role without technical license)
   - Técnico Social attempting Prontuário Read (Allowed, Audited), Prontuário Evolution Add (Allowed, Audited)
   - Egresso attempting own Prontuário Read (Allowed restricted view, Audited), other Egresso's Prontuário Read (Forbidden 403)
   - Anonymous visitor attempting Prontuário Read (Unauthorized 401)
3. `tests_e2e/tier3_combinations/test_webrtc_webhook_timeline.py` (>= 3 tests):
   - Full lifecycle: Técnico generates WebRTC JWT -> FastAPI WebSocket session active -> Call concludes -> FastAPI sends HMAC signed webhook -> Laravel verifies HMAC -> Automatic ProntuarioTimeline event created with MOS score & duration metadata
   - Webhook replay protection & timestamp freshness validation
   - Webhook failure retry and fallback logging
4. `tests_e2e/tier3_combinations/test_pdf_qr_validation_chain.py` (>= 3 tests):
   - Issue Digital Wallet for Egresso -> Generate PDF with HMAC-SHA256 QR code -> Parse QR code payload -> Validate against public route `/validar-carteira/{hash}` -> Assert document validity and matching profile
   - Validation with modified timestamp or altered Egresso ID fails verification
   - Wallet status revocation reflection on public validation endpoint
5. `tests_e2e/tier3_combinations/test_territory_jobs_filter.py` (>= 3 tests):
   - Cross-filtering: 78 ES municipalities territorial data combined with affirmative action jobs and training courses filtered by micro-region (Metropolitana, Central, Rio Doce, etc.)
   - Spatial proximity query: Matching jobs and support network (CRAS/SINE) within radius of an Egresso's registered municipality
   - Zero-result municipality graceful fallback with regional alternatives
6. `tests_e2e/tier3_combinations/test_a11y_multimode_states.py` (>= 3 tests):
   - Simultaneous combination: High Contrast Mode (`.high-contrast`) + Font Zoom (+18%) + Simplified Language Mode (*Linguagem Fácil*) active across all views
   - Session persistence of accessibility preferences across navigation
   - Accessibility attributes (ARIA, role, alt, tabindex) preserved across dynamic view updates
7. `tests_e2e/tier3_combinations/test_oidc_claims_authorization.py` (>= 2 tests):
   - Gov.br / Acesso Cidadão SSO claims mapped to RBAC role and territorial scope permissions
   - Claim transformation with missing optional scopes

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. An auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Verification:
- Ensure all test modules import cleanly and run under `python tests_e2e/test_runner.py --tier 3`.
- Write `handoff.md` and send_message with your completion summary.
