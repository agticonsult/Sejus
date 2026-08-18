"""
Tier 1 Feature Test: E2E Test Harness & Common Utilities Verification
Authoritative Source: ORIGINAL_REQUEST.md, PROJECT.md, TEST_INFRA.md

Verifies the integrity, reliability, and correctness of:
- DataGenerator (CPFs, SEJUS profiles, IBGE 78 municipalities, Telemetry)
- CryptoVerifier (HMAC-SHA256, SHA-256 Hash Chaining, LGPD Blind Index, JWT, QR Code)
- AssertionHelper (Status codes, JSON subset, CPF, Audit chain, MOS, IBGE)
- MockApiClient / HttpClient (Authentication, RBAC, Prontuário, Opportunities, Webhooks)
- MockWebSocketClient (Signaling frames, peer room routing, MOS telemetry tracking)
"""

import copy
import hashlib
import time
import unittest
from datetime import datetime, timezone

from tests_e2e.e2e_utils import (
    ES_MUNICIPALITIES,
    MUNICIPALITY_BY_CODE,
    AssertionHelper,
    CryptoVerifier,
    DataGenerator,
    HttpClient,
    HttpResponse,
    MockApiClient,
    MockWebSocketClient,
)


class TestDataGenerator(unittest.TestCase):
    """Verifies DataGenerator accuracy for authentic SEJUS / Brazilian data."""

    def test_valid_cpf_generation_and_validation(self):
        """Generates 50 valid CPFs (formatted and raw) and validates them with Receita Federal rules."""
        for _ in range(50):
            cpf_formatted = DataGenerator.generate_cpf(valid=True, formatted=True)
            self.assertTrue(DataGenerator.validate_cpf(cpf_formatted), f"Generated formatted CPF {cpf_formatted} should be valid")
            self.assertRegex(cpf_formatted, r"^\d{3}\.\d{3}\.\d{3}-\d{2}$")

            cpf_raw = DataGenerator.generate_cpf(valid=True, formatted=False)
            self.assertTrue(DataGenerator.validate_cpf(cpf_raw), f"Generated raw CPF {cpf_raw} should be valid")
            self.assertEqual(len(cpf_raw), 11)
            self.assertTrue(cpf_raw.isdigit())

    def test_invalid_cpf_detection(self):
        """Generates invalid CPFs (corrupted check digits, repeated digits, bad length) and ensures rejection."""
        for _ in range(30):
            cpf_invalid = DataGenerator.generate_cpf(valid=False, formatted=True)
            self.assertFalse(DataGenerator.validate_cpf(cpf_invalid), f"Invalid CPF {cpf_invalid} must be rejected")

        # Explicit known invalid numbers
        self.assertFalse(DataGenerator.validate_cpf("111.111.111-11"))
        self.assertFalse(DataGenerator.validate_cpf("000.000.000-00"))
        self.assertFalse(DataGenerator.validate_cpf("123.456.789-00"))
        self.assertFalse(DataGenerator.validate_cpf(""))
        self.assertFalse(DataGenerator.validate_cpf("abc"))

    def test_sejus_user_profiles(self):
        """Validates generated profiles for Gestor SEJUS, Técnico Escritório Social, Egresso, and Familiar."""
        # 1. Gestor SEJUS
        gestor = DataGenerator.generate_user_profile(role="gestor")
        self.assertEqual(gestor["role"], "gestor")
        self.assertIn("dashboard:view", gestor["permissions"])
        self.assertIn("audit:view", gestor["permissions"])
        self.assertTrue(DataGenerator.validate_cpf(gestor["cpf"]))

        # 2. Técnico Escritório Social
        tecnico = DataGenerator.generate_user_profile(role="tecnico")
        self.assertEqual(tecnico["role"], "tecnico")
        self.assertIn("atendimento:queue", tecnico["permissions"])
        self.assertIn("prontuario:evolucao", tecnico["permissions"])
        self.assertTrue(tecnico["municipio_ibge"].startswith("32"))

        # 3. Egresso
        egresso = DataGenerator.generate_user_profile(role="egresso")
        self.assertEqual(egresso["role"], "egresso")
        self.assertIn("prontuario_id", egresso)
        self.assertIn("cpf_blind_index", egresso)
        self.assertIn("regime_prisional", egresso)

        # 4. Familiar
        familiar = DataGenerator.generate_user_profile(role="familiar")
        self.assertEqual(familiar["role"], "familiar")
        self.assertIn("egresso_vinculado_id", familiar)

    def test_ibge_78_es_municipalities(self):
        """Ensures all 78 Espírito Santo municipalities are cataloged with official 32XXXXX IBGE codes."""
        self.assertEqual(len(ES_MUNICIPALITIES), 78, "Must contain exactly 78 municipalities for Espírito Santo")
        codes_seen = set()

        for mun in ES_MUNICIPALITIES:
            code = mun["ibge_code"]
            self.assertEqual(len(code), 7)
            self.assertTrue(code.startswith("32"), f"IBGE code {code} must start with 32 (ES)")
            self.assertNotIn(code, codes_seen, f"Duplicate IBGE code: {code}")
            codes_seen.add(code)
            self.assertTrue(mun["name"])
            self.assertTrue(mun["region"])
            self.assertIsInstance(mun["lat"], float)
            self.assertIsInstance(mun["lon"], float)

        # Verify key municipalities
        self.assertIn("3205309", MUNICIPALITY_BY_CODE)  # Vitória
        self.assertEqual(MUNICIPALITY_BY_CODE["3205309"]["name"], "Vitória")
        self.assertIn("3205200", MUNICIPALITY_BY_CODE)  # Vila Velha
        self.assertIn("3205002", MUNICIPALITY_BY_CODE)  # Serra
        self.assertIn("3201308", MUNICIPALITY_BY_CODE)  # Cariacica
        self.assertIn("3203205", MUNICIPALITY_BY_CODE)  # Linhares
        self.assertIn("3201209", MUNICIPALITY_BY_CODE)  # Cachoeiro de Itapemirim
        self.assertIn("3201506", MUNICIPALITY_BY_CODE)  # Colatina

    def test_telemetry_payloads(self):
        """Verifies MOS scores and network metrics generated across quality tiers."""
        for quality, min_mos, max_mos in [
            ("excellent", 4.3, 4.5),
            ("good", 3.8, 4.2),
            ("poor", 2.6, 3.2),
            ("critical", 1.2, 1.9),
        ]:
            telem = DataGenerator.generate_telemetry_payload(quality=quality)
            self.assertGreaterEqual(telem["mos"], min_mos)
            self.assertLessEqual(telem["mos"], max_mos)
            self.assertIn("rtt_ms", telem)
            self.assertIn("jitter_ms", telem)
            self.assertIn("packet_loss_pct", telem)
            self.assertIn("codec", telem)


class TestCryptoVerifier(unittest.TestCase):
    """Verifies cryptographic operations for HMAC, SHA-256 chaining, LGPD, JWT, and QR code."""

    def test_hmac_sha256_generation_and_verification(self):
        """Tests HMAC-SHA256 signature generation and constant-time verification."""
        secret = "super_secret_sejus_key_123"
        payload = {"event": "session_ended", "room_id": "sala-01", "duration": 420}

        sig = CryptoVerifier.generate_hmac_signature(payload, secret)
        self.assertTrue(isinstance(sig, str))
        self.assertEqual(len(sig), 64)  # 256-bit hex

        # Valid verification
        self.assertTrue(CryptoVerifier.verify_hmac_signature(payload, sig, secret))

        # Tampered payload verification failure
        tampered_payload = {"event": "session_ended", "room_id": "sala-01", "duration": 999}
        self.assertFalse(CryptoVerifier.verify_hmac_signature(tampered_payload, sig, secret))

        # Wrong secret failure
        self.assertFalse(CryptoVerifier.verify_hmac_signature(payload, sig, "wrong_secret"))

    def test_lgpd_blind_index(self):
        """Tests deterministic blind indexing for searchable encrypted CPF/PII."""
        cpf_formatted = "123.456.789-01"
        cpf_clean = "12345678901"

        idx1 = CryptoVerifier.generate_blind_index(cpf_formatted)
        idx2 = CryptoVerifier.generate_blind_index(cpf_clean)
        self.assertEqual(idx1, idx2, "Blind index must be identical regardless of punctuation formatting")
        self.assertTrue(CryptoVerifier.verify_blind_index(cpf_formatted, idx1))

        # Different CPF gives distinct hash
        idx3 = CryptoVerifier.generate_blind_index("987.654.321-09")
        self.assertNotEqual(idx1, idx3)

    def test_sha256_immutable_audit_chain(self):
        """Tests sequential SHA-256 hash chaining and tamper detection."""
        # 1. Build a 5-block chain
        chain = []
        prev_hash = CryptoVerifier.GENESIS_HASH

        for i in range(1, 6):
            payload = {"action": f"ACTION_{i}", "user_id": 100 + i, "timestamp": f"2026-08-17T10:0{i}:00Z"}
            curr_hash = CryptoVerifier.calculate_audit_hash(prev_hash, payload)
            chain.append({
                "id": i,
                "previous_hash": prev_hash,
                "hash": curr_hash,
                "payload": payload,
            })
            prev_hash = curr_hash

        # 2. Verify pristine chain
        valid, msg = CryptoVerifier.verify_audit_chain(chain)
        self.assertTrue(valid, f"Pristine chain should be valid: {msg}")

        # 3. Tamper with block #3 payload
        tampered_chain = copy.deepcopy(chain)
        tampered_chain[2]["payload"]["user_id"] = 999  # Tamper
        valid_tamper, msg_tamper = CryptoVerifier.verify_audit_chain(tampered_chain)
        self.assertFalse(valid_tamper, "Tampered chain must be detected as invalid")
        self.assertIn("index [2]", msg_tamper)

    def test_jwt_token_creation_and_verification(self):
        """Tests HS256 JWT creation, claim decoding, expiration, and signature security."""
        claims = {"user_id": 42, "role": "tecnico", "room_id": "sala-vitoria-101"}
        secret = "jwt_secret_token_123"

        token = CryptoVerifier.generate_jwt_token(claims, secret, expires_in_seconds=3600)
        self.assertEqual(token.count("."), 2)

        # Valid decode
        ok, decoded, msg = CryptoVerifier.decode_and_verify_jwt(token, secret)
        self.assertTrue(ok, f"JWT verification should succeed: {msg}")
        self.assertEqual(decoded["user_id"], 42)
        self.assertEqual(decoded["role"], "tecnico")

        # Wrong secret
        ok_wrong, _, _ = CryptoVerifier.decode_and_verify_jwt(token, "wrong_secret")
        self.assertFalse(ok_wrong)

        # Expired token
        expired_token = CryptoVerifier.generate_jwt_token(claims, secret, expires_in_seconds=-10)
        ok_exp, _, msg_exp = CryptoVerifier.decode_and_verify_jwt(expired_token, secret)
        self.assertFalse(ok_exp)
        self.assertIn("expired", msg_exp.lower())

    def test_qr_payload_generation_and_verification(self):
        """Tests signed QR Code payload creation and cryptographic verification."""
        egresso = DataGenerator.generate_user_profile(role="egresso", id=501)
        qr_payload = CryptoVerifier.generate_qr_payload(egresso)

        self.assertIn("signature", qr_payload)
        self.assertIn("token", qr_payload)
        self.assertTrue(CryptoVerifier.verify_qr_payload(qr_payload))

        # Tampered payload
        tampered = copy.deepcopy(qr_payload)
        tampered["nome"] = "Nome Falso Injetado"
        self.assertFalse(CryptoVerifier.verify_qr_payload(tampered))


class TestAssertionHelper(unittest.TestCase):
    """Verifies AssertionHelper methods and rich failure reporting."""

    def test_status_code_assertions(self):
        AssertionHelper.assert_status_code(200, 200)
        with self.assertRaises(AssertionError):
            AssertionHelper.assert_status_code(404, 200, context="GET /test")

    def test_json_contains_assertions(self):
        actual = {"status": "ok", "user": {"id": 1, "name": "Renata", "role": "gestor"}, "items": [10, 20, 30]}
        expected = {"status": "ok", "user": {"role": "gestor"}, "items": [10, 20]}
        AssertionHelper.assert_json_contains(actual, expected)

        with self.assertRaises(AssertionError):
            AssertionHelper.assert_json_contains(actual, {"user": {"role": "egresso"}})

    def test_cpf_and_ibge_assertions(self):
        valid_cpf = DataGenerator.generate_cpf(valid=True)
        AssertionHelper.assert_valid_cpf(valid_cpf)

        with self.assertRaises(AssertionError):
            AssertionHelper.assert_valid_cpf("000.111.222-33")

        AssertionHelper.assert_ibge_code_valid("3205309")  # Vitória
        with self.assertRaises(AssertionError):
            AssertionHelper.assert_ibge_code_valid("3304557")  # Rio de Janeiro (UF 33)

    def test_mos_range_assertions(self):
        AssertionHelper.assert_mos_score_range(4.2)
        with self.assertRaises(AssertionError):
            AssertionHelper.assert_mos_score_range(5.5)


class TestMockApiClient(unittest.TestCase):
    """Verifies MockApiClient stateful routes and responses."""

    def setUp(self):
        self.client = MockApiClient(mode="mock")

    def test_health_check(self):
        resp = self.client.get("health")
        AssertionHelper.assert_status_code(resp.status_code, 200)
        self.assertEqual(resp.json()["status"], "ok")

    def test_authentication_and_user_info(self):
        # 1. Login as Gestor
        login_resp = self.client.post("api/auth/login", json_body={"role": "gestor"})
        AssertionHelper.assert_status_code(login_resp.status_code, 200)
        token = login_resp.json()["token"]
        self.assertTrue(token)

        # 2. Get User with Bearer token
        self.client.set_bearer_token(token)
        user_resp = self.client.get("api/user")
        AssertionHelper.assert_status_code(user_resp.status_code, 200)
        self.assertEqual(user_resp.json()["user"]["role"], "gestor")

        # 3. Unauthenticated request
        anon_client = MockApiClient(mode="mock")
        anon_resp = anon_client.get("api/user")
        AssertionHelper.assert_status_code(anon_resp.status_code, 401)

    def test_prontuario_evolution_and_audit(self):
        # 1. Fetch initial prontuario
        p_resp = self.client.get("api/prontuario/101")
        AssertionHelper.assert_status_code(p_resp.status_code, 200)
        data = p_resp.json()
        initial_timeline_count = len(data["timeline"])

        # 2. Add evolution
        evo_resp = self.client.post("api/prontuario/101/evolucao", json_body={
            "tipo": "ENCAMINHAMENTO_SINE",
            "descricao": "Encaminhado para vaga de Auxiliar de Logística no SINE de Linhares.",
            "actor_id": 2,
        })
        AssertionHelper.assert_status_code(evo_resp.status_code, 201)

        # 3. Verify timeline increased
        p_resp2 = self.client.get("api/prontuario/101")
        self.assertEqual(len(p_resp2.json()["timeline"]), initial_timeline_count + 1)

        # 4. Verify audit chain validity
        chain_resp = self.client.get("api/seguranca-lgpd/verify-chain")
        AssertionHelper.assert_status_code(chain_resp.status_code, 200)
        self.assertTrue(chain_resp.json()["chain_valid"])

    def test_webrtc_webhook_ingest_with_hmac(self):
        webhook_payload = {
            "event": "session_ended",
            "room_id": "sala-vitoria-101",
            "duration_seconds": 780,
            "egresso_id": 101,
            "tecnico_id": 2,
            "summary_telemetry": {"avg_mos": 4.3, "packet_loss_pct": 0.3},
        }
        valid_sig = CryptoVerifier.generate_hmac_signature(webhook_payload, CryptoVerifier.DEFAULT_WEBHOOK_SECRET)

        # Post with valid HMAC
        resp = self.client.post("api/webhooks/webrtc", json_body=webhook_payload, headers={"X-Signature-SHA256": valid_sig})
        AssertionHelper.assert_status_code(resp.status_code, 200)
        self.assertEqual(resp.json()["status"], "ingested")

        # Post with invalid HMAC
        resp_invalid = self.client.post("api/webhooks/webrtc", json_body=webhook_payload, headers={"X-Signature-SHA256": "fake_signature"})
        AssertionHelper.assert_status_code(resp_invalid.status_code, 401)


class TestMockWebSocketClient(unittest.TestCase):
    """Verifies WebRTC signaling frames, room routing, and telemetry tracking."""

    def test_peer_signaling_exchange(self):
        room_id = "sala-teste-peer-1"
        client_a = MockWebSocketClient("tecnico-client")
        client_b = MockWebSocketClient("egresso-client")

        client_a.connect(room_id)
        client_b.connect(room_id)

        # A sends offer -> B receives offer
        client_a.send_offer("v=0 offer-sdp-test")
        msg_b = client_b.receive()
        self.assertIsNotNone(msg_b)
        self.assertEqual(msg_b["type"], "offer")
        self.assertEqual(msg_b["sdp"], "v=0 offer-sdp-test")

        # B sends answer -> A receives answer
        client_b.send_answer("v=0 answer-sdp-test")
        msg_a = client_a.receive()
        self.assertIsNotNone(msg_a)
        self.assertEqual(msg_a["type"], "answer")

        # ICE candidate exchange
        client_a.send_ice_candidate({"candidate": "candidate:test", "sdpMid": "0"})
        ice_b = client_b.receive()
        self.assertIsNotNone(ice_b)
        self.assertEqual(ice_b["type"], "ice-candidate")

        # Telemetry tracking
        client_a.send_telemetry(mos=4.4, rtt_ms=30, jitter_ms=4, packet_loss=0.1)
        client_a.send_telemetry(mos=4.2, rtt_ms=40, jitter_ms=6, packet_loss=0.2)
        self.assertAlmostEqual(client_a.get_average_mos(), 4.3, delta=0.05)

        client_a.close()
        client_b.close()
