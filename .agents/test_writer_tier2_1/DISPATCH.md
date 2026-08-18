## 2026-08-17T12:19:06Z

You are the Tier 2 Boundary & Negative Test Writer for CONECTA EGRESSO (SEJUS/ES).
Working directory for metadata: d:\Agile\projeto dia 18\.agents\test_writer_tier2_1
Parent conversation ID: 6457978f-379c-4b6f-802d-5401775f664e

Authoritative specifications to read first:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\TEST_INFRA.md`

Your Mission:
Create the complete Tier 2 Boundary, Edge-Case, and Negative Test suite in `d:\Agile\projeto dia 18\tests_e2e\tier2_boundaries\` (>= 50 test cases total).

Create the following test modules with genuine, rigorous edge-case assertions:
1. `tests_e2e/tier2_boundaries/__init__.py`
2. `tests_e2e/tier2_boundaries/test_auth_boundaries.py` (>= 10 tests):
   - Expired JWT rejection
   - Invalid token signature rejection
   - Unauthorized role elevation attempt (Egresso attempting Gestor actions)
   - Missing Authorization header
   - Malformed Gov.br / OIDC claims payload
   - Deactivated/blocked user login attempt
   - Rapid repeated authentication attempts (rate limit boundary)
   - Session token reuse after logout
   - Empty/whitespace credential payloads
   - Password boundary complexity validation
3. `tests_e2e/tier2_boundaries/test_crypto_tampering.py` (>= 10 tests):
   - Tampered QR code HMAC-SHA256 signature rejection
   - Public validation with invalid hash / altered payload
   - Attempted deletion on immutable audit log (`DELETE FROM prontuario_audit_logs`)
   - Attempted update on immutable audit log (`UPDATE prontuario_audit_logs`)
   - Broken hash chain detection in audit logs
   - Blind index search with invalid salt/HMAC key
   - AES-256 decryption failure on corrupted ciphertext
   - Plaintext PII search prevention (blind index integrity)
   - Invalid CPF checksums and extreme formats
   - Zero-length / negative ID cryptographic requests
4. `tests_e2e/tier2_boundaries/test_prontuario_boundaries.py` (>= 8 tests):
   - Empty evolution text rejection
   - Payload size limits (> 64KB note)
   - Non-existent egresso ID handling
   - Malformed timestamp in timeline event
   - XSS script injection in evolution notes (sanitization check)
   - SQL injection attempts in prontuário search
   - Concurrent evolution race condition handling
   - Technician ID mismatch on evolution write
5. `tests_e2e/tier2_boundaries/test_webrtc_network_limits.py` (>= 8 tests):
   - MOS score calculation at 100% packet loss (MOS = 1.0 minimum)
   - MOS score calculation at 0ms latency / 0% packet loss (MOS = 4.5 maximum)
   - Extreme network jitter (> 1000ms) telemetry handling
   - Expired WebRTC room token rejection on WebSocket connect
   - Malformed SDP offer/answer string handling
   - Unauthenticated WebSocket message rejection
   - Room participant capacity overflow (> max participants)
   - Abrupt WebSocket disconnect cleanup
6. `tests_e2e/tier2_boundaries/test_territory_payload_limits.py` (>= 8 tests):
   - Invalid / non-existent IBGE municipality code
   - Out-of-bounds Espírito Santo geographic coordinates
   - Empty search filter payload handling
   - Negative salary filter in job opportunities
   - Extreme pagination offset (page 999999)
   - Special characters & unicode in municipality query
   - Non-ES postal code rejection/handling
   - Missing CRAS/SINE geocoordinates fallback
7. `tests_e2e/tier2_boundaries/test_frontend_a11y_limits.py` (>= 6 tests):
   - Rapid toggling of High Contrast mode state persistence
   - Font zoom level limits (cannot zoom beyond +50% or negative)
   - Simplified Language mode fallback when translation key is missing
   - Viewport boundary tests (320px mobile width up to 4K resolution)
   - Corrupted Inertia page state recovery
   - Missing user profile prop handling in UI navbar
