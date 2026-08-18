## 2026-08-17T12:19:06Z

You are the Test Harness Engineer for the E2E Testing Track of CONECTA EGRESSO (SEJUS/ES).
Working directory for metadata: d:\Agile\projeto dia 18\.agents\test_writer_harness_1
Parent conversation ID: 6457978f-379c-4b6f-802d-5401775f664e

Authoritative specifications to read first:
- `d:\Agile\projeto dia 18\ORIGINAL_REQUEST.md`
- `d:\Agile\projeto dia 18\PROJECT.md`
- `d:\Agile\projeto dia 18\TEST_INFRA.md`

Your Mission:
Create the complete E2E testing framework harness and common utilities:
1. `d:\Agile\projeto dia 18\tests_e2e\__init__.py`
2. `d:\Agile\projeto dia 18\tests_e2e\test_runner.py`:
   - Unified Python CLI test runner supporting arguments: `--tier 1`, `--tier 2`, `--tier 3`, `--tier 4`, `--all`, `--verbose`, `--json`, `--filter <pattern>`.
   - Automatic discovery and execution of tests in `tier1_features`, `tier2_boundaries`, `tier3_combinations`, `tier4_scenarios`.
   - Clear, colorized terminal reporting (ANSI colors for PASS, FAIL, SKIP, duration, error tracebacks).
   - Exit code handling: exit 0 when all tests pass, exit 1 if any test fails.
   - Capability to execute standalone test functions or classes using a lightweight test discovery engine or unittest/pytest compatibility.
3. `d:\Agile\projeto dia 18\tests_e2e\e2e_utils.py`:
   - Common test helper classes and utilities:
     - `MockApiClient` / `HttpClient`: sends HTTP requests with mock fallback or live connection to Laravel API (:8000) and FastAPI (:8001).
     - `MockWebSocketClient`: handles WebSocket signaling frames (`join`, `offer`, `answer`, `ice-candidate`, `telemetry`, `leave`).
     - `CryptoVerifier`: helper methods for HMAC-SHA256 signature verification (QR code, WebRTC webhooks), SHA-256 hash chaining, and LGPD blind indexing.
     - `AssertionHelper`: rich assertions with clear failure messages.
     - `DataGenerator`: generators for valid/invalid CPFs, SEJUS user profiles, IBGE municipality codes (78 ES cities), WebRTC telemetry payloads.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. An auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Verification:
- Run `python tests_e2e/test_runner.py --help` to verify the runner executes without syntax errors.
- Write `handoff.md` and send_message with your completion summary.
