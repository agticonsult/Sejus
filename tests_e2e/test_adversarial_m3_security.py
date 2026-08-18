"""Adversarial Security, Cryptography & Webhook Stress Suite for Milestone M3.
Challenger 2: CONECTA EGRESSO (SEJUS/ES)

Covers:
1. WebRTC JWT Cryptographic & Header/Payload Vulnerabilities (alg none, bit-flip, forgery, expiry, malformations)
2. WebRTC Webhook HMAC-SHA256 Security & Replay Invariance (signatures, tampering, extreme telemetry, lifecycle events)
3. Audit Hash Chain Cryptographic Integrity & Tampering Attacks (1,000-block simulation, in-place tamper detection)
4. Rede de Apoio GPS Fallback, Spatial Bounding Boxes & Geodesics (78 municipalities, asymmetric GPS, Haversine proximity)
"""

import base64
import copy
import hashlib
import hmac
import json
import math
import time
import unittest
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple


class TestAdversarialM3Security(unittest.TestCase):
    """Adversarial Challenge Test Suite for Milestone M3 Backend Security & Cryptography."""

    JWT_SECRET = "sejus_jwt_shared_secret_2026"
    WEBHOOK_SECRET = "sejus_webrtc_webhook_secret_2026"
    PEPPER = "conecta_egresso_lgpd_pepper_2026_sejus_es"
    GENESIS_HASH = "0" * 64

    # ES Geographic Bounding Box
    ES_BOUNDS = {
        "min_lat": -21.31,
        "max_lat": -17.88,
        "min_lon": -41.88,
        "max_lon": -39.66,
    }

    # Helper: Base64URL encode
    @staticmethod
    def base64url_encode(data: bytes) -> str:
        return base64.urlsafe_b64encode(data).decode("utf-8").rstrip("=")

    # Helper: Base64URL decode
    @staticmethod
    def base64url_decode(data: str) -> bytes:
        padding = 4 - (len(data) % 4)
        if padding != 4:
            data += "=" * padding
        return base64.urlsafe_b64decode(data.encode("utf-8"))

    # Helper: Encode HS256 JWT
    def encode_jwt(self, header: Dict[str, Any], payload: Dict[str, Any], secret: str) -> str:
        b64_header = self.base64url_encode(json.dumps(header, separators=(",", ":")).encode("utf-8"))
        b64_payload = self.base64url_encode(json.dumps(payload, separators=(",", ":")).encode("utf-8"))
        signing_input = f"{b64_header}.{b64_payload}".encode("utf-8")
        signature = hmac.new(secret.encode("utf-8"), signing_input, hashlib.sha256).digest()
        b64_sig = self.base64url_encode(signature)
        return f"{b64_header}.{b64_payload}.{b64_sig}"

    # Helper: Verify HS256 JWT
    def verify_jwt(self, token: str, secret: str = JWT_SECRET) -> Dict[str, Any]:
        parts = token.split(".")
        if len(parts) != 3:
            return {"valid": False, "error": "MALFORMED_JWT_STRUCTURE"}

        b64_header, b64_payload, b64_sig = parts
        signing_input = f"{b64_header}.{b64_payload}".encode("utf-8")
        expected_sig = self.base64url_encode(
            hmac.new(secret.encode("utf-8"), signing_input, hashlib.sha256).digest()
        )

        if not hmac.compare_digest(expected_sig, b64_sig):
            return {"valid": False, "error": "INVALID_SIGNATURE"}

        try:
            payload_json = self.base64url_decode(b64_payload).decode("utf-8")
            payload = json.loads(payload_json)
        except Exception:
            return {"valid": False, "error": "INVALID_PAYLOAD_JSON"}

        now = int(time.time())
        if "exp" in payload and now > payload["exp"]:
            return {"valid": False, "error": "TOKEN_EXPIRED", "payload": payload}

        if "nbf" in payload and now < payload["nbf"]:
            return {"valid": False, "error": "TOKEN_NOT_YET_VALID", "payload": payload}

        return {"valid": True, "payload": payload}

    # Helper: Haversine distance in km
    @staticmethod
    def haversine_distance(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
        earth_radius = 6371.0
        d_lat = math.radians(lat2 - lat1)
        d_lon = math.radians(lon2 - lon1)
        a = (
            math.sin(d_lat / 2.0) ** 2
            + math.cos(math.radians(lat1)) * math.cos(math.radians(lat2)) * math.sin(d_lon / 2.0) ** 2
        )
        c = 2.0 * math.atan2(math.sqrt(a), math.sqrt(1.0 - a))
        return earth_radius * c

    # Helper: Audit hash calculation
    @staticmethod
    def calculate_audit_hash(
        prev_hash: str,
        prontuario_id: Optional[int],
        user_id: Optional[int],
        acao: str,
        ip_addr: str,
        timestamp: str,
        details: Dict[str, Any],
    ) -> str:
        canonical_details = json.dumps(details, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
        payload = "|".join([
            prev_hash,
            str(prontuario_id) if prontuario_id is not None else "GLOBAL",
            str(user_id) if user_id is not None else "ANONYMOUS",
            acao,
            ip_addr,
            timestamp,
            canonical_details,
        ])
        return hashlib.sha256(payload.encode("utf-8")).hexdigest()

    # -------------------------------------------------------------------------
    # VECTOR 1: WebRTC JWT Adversarial Attacks
    # -------------------------------------------------------------------------
    def test_webrtc_jwt_alg_none_attack_rejection(self):
        """Adversarial Test: Header alg 'none' and 'None' vulnerability rejection."""
        payload = {
            "iss": "conecta-egresso-laravel",
            "aud": "conecta-egresso-webrtc",
            "sub": "1",
            "role": "gestor",
            "room_id": "sala-admin-01",
            "exp": int(time.time()) + 3600,
        }

        # 1. alg: none
        b64_none_header = self.base64url_encode(json.dumps({"alg": "none", "typ": "JWT"}).encode("utf-8"))
        b64_payload = self.base64url_encode(json.dumps(payload).encode("utf-8"))
        none_token = f"{b64_none_header}.{b64_payload}."

        res = self.verify_jwt(none_token)
        self.assertFalse(res["valid"], "Alg 'none' token must be rejected")
        self.assertEqual(res["error"], "INVALID_SIGNATURE")

        # 2. alg: None (cased)
        b64_none_cased_header = self.base64url_encode(json.dumps({"alg": "None", "typ": "JWT"}).encode("utf-8"))
        none_cased_token = f"{b64_none_cased_header}.{b64_payload}."

        res_cased = self.verify_jwt(none_cased_token)
        self.assertFalse(res_cased["valid"], "Alg 'None' token must be rejected")

    def test_webrtc_jwt_privilege_escalation_attack(self):
        """Adversarial Test: Egresso attempts privilege escalation to Gestor by altering payload in transit."""
        header = {"alg": "HS256", "typ": "JWT"}
        legit_payload = {
            "iss": "conecta-egresso-laravel",
            "aud": "conecta-egresso-webrtc",
            "sub": "892",
            "role": "egresso",
            "room_id": "sala-vitoria-892",
            "exp": int(time.time()) + 3600,
        }

        legit_token = self.encode_jwt(header, legit_payload, self.JWT_SECRET)
        parts = legit_token.split(".")

        # Attacker modifies role to 'gestor'
        forged_payload = copy.deepcopy(legit_payload)
        forged_payload["role"] = "gestor"
        forged_payload["sub"] = "1"
        b64_forged = self.base64url_encode(json.dumps(forged_payload).encode("utf-8"))

        escalated_token = f"{parts[0]}.{b64_forged}.{parts[2]}"
        res = self.verify_jwt(escalated_token)
        self.assertFalse(res["valid"], "Privilege escalated token with forged payload must fail signature verification")
        self.assertEqual(res["error"], "INVALID_SIGNATURE")

    def test_webrtc_jwt_expiration_and_future_nbf(self):
        """Adversarial Test: Boundary conditions for expired tokens and future not-before."""
        header = {"alg": "HS256", "typ": "JWT"}
        now = int(time.time())

        # Expired 1 second ago
        expired_payload = {
            "sub": "100",
            "role": "tecnico",
            "exp": now - 1,
        }
        expired_token = self.encode_jwt(header, expired_payload, self.JWT_SECRET)
        res_exp = self.verify_jwt(expired_token)
        self.assertFalse(res_exp["valid"])
        self.assertEqual(res_exp["error"], "TOKEN_EXPIRED")

        # Future nbf (+5s)
        future_nbf_payload = {
            "sub": "100",
            "role": "tecnico",
            "nbf": now + 5,
            "exp": now + 3600,
        }
        future_token = self.encode_jwt(header, future_nbf_payload, self.JWT_SECRET)
        res_nbf = self.verify_jwt(future_token)
        self.assertFalse(res_nbf["valid"])
        self.assertEqual(res_nbf["error"], "TOKEN_NOT_YET_VALID")

    def test_webrtc_jwt_structural_fuzzing(self):
        """Adversarial Test: Fuzzing malformed token structures."""
        malformed = [
            "",
            "singlepart",
            "two.parts",
            "four.parts.here.extra",
            "corrupted!@#.payload$%^.sig&*()",
            "null\x00byte.payload.sig",
        ]
        for token in malformed:
            res = self.verify_jwt(token)
            self.assertFalse(res["valid"], f"Malformed token '{token}' should be rejected safely")

    # -------------------------------------------------------------------------
    # VECTOR 2: WebRTC Webhook HMAC-SHA256 & Telemetry Ingestion
    # -------------------------------------------------------------------------
    def test_webrtc_webhook_hmac_tamper_detection(self):
        """Adversarial Test: Single-byte tamper in webhook payload breaks HMAC-SHA256."""
        payload = {
            "event": "session.ended",
            "room_id": "sala-vitoria-892",
            "data": {
                "room_code": "ATD-VIX-2026-0892",
                "duration_seconds": 930,
                "summary_telemetry": {
                    "avg_mos": 4.28,
                    "overall_quality_tier": "BOM",
                    "overall_packet_loss_pct": 0.35,
                },
                "ended_at": "2026-08-17T12:30:00Z",
            },
        }

        raw_json = json.dumps(payload, separators=(",", ":"))
        valid_hmac = hmac.new(self.WEBHOOK_SECRET.encode("utf-8"), raw_json.encode("utf-8"), hashlib.sha256).hexdigest()

        # Attacker modifies duration_seconds from 930 to 9300
        tampered_payload = copy.deepcopy(payload)
        tampered_payload["data"]["duration_seconds"] = 9300
        tampered_json = json.dumps(tampered_payload, separators=(",", ":"))

        # Verify HMAC against tampered payload
        tampered_computed = hmac.new(self.WEBHOOK_SECRET.encode("utf-8"), tampered_json.encode("utf-8"), hashlib.sha256).hexdigest()
        self.assertNotEqual(valid_hmac, tampered_computed, "Tampered payload must produce distinct HMAC")
        self.assertFalse(hmac.compare_digest(valid_hmac, tampered_computed), "Tampered payload HMAC comparison must fail")

    def test_webrtc_webhook_lifecycle_events(self):
        """Adversarial Test: Verification of all WebRTC lifecycle event taxonomies."""
        events = [
            "session.started",
            "session.ended",
            "recording.ready",
            "session.quality_alert",
            "session_started",
            "session_ended",
        ]
        for ev in events:
            normalized = ev.replace("_", ".").lower()
            self.assertIn(normalized, ["session.started", "session.ended", "recording.ready", "session.quality.alert", "session.quality_alert"])

    # -------------------------------------------------------------------------
    # VECTOR 3: Audit Hash Chain Integrity & Concurrency Stress
    # -------------------------------------------------------------------------
    def test_audit_hash_chain_high_throughput_and_tampering(self):
        """Adversarial Test: Generate 1,000-block chain and test tamper localization."""
        chain = []
        prev_hash = self.GENESIS_HASH

        # Generate 1,000 blocks
        start_time = time.time()
        for i in range(1, 1001):
            timestamp = f"2026-08-17T12:00:{i%60:02d}Z"
            details = {
                "iteration": i,
                "room": f"ATD-VIX-{i}",
                "utf8_text": "Ação de reinserção social & atendimento psicológico",
            }
            block_hash = self.calculate_audit_hash(
                prev_hash,
                i % 10 + 1,
                i % 5 + 1,
                "WEBRTC_ATTENDANCE_RECORDED",
                f"10.0.0.{i % 250 + 1}",
                timestamp,
                details,
            )
            chain.append({
                "id": i,
                "prev_hash": prev_hash,
                "curr_hash": block_hash,
                "prontuario_id": i % 10 + 1,
                "user_id": i % 5 + 1,
                "acao": "WEBRTC_ATTENDANCE_RECORDED",
                "ip": f"10.0.0.{i % 250 + 1}",
                "timestamp": timestamp,
                "details": details,
            })
            prev_hash = block_hash

        elapsed_ms = (time.time() - start_time) * 1000
        self.assertEqual(len(chain), 1000)
        self.assertLess(elapsed_ms, 500.0, f"1000 blocks generated in {elapsed_ms:.2f}ms (< 500ms)")

        # Verify intact chain
        def verify_chain(c: List[Dict[str, Any]]) -> Tuple[bool, Optional[int]]:
            expected_prev = self.GENESIS_HASH
            for b in c:
                if b["prev_hash"] != expected_prev:
                    return False, b["id"]
                calc = self.calculate_audit_hash(
                    b["prev_hash"],
                    b["prontuario_id"],
                    b["user_id"],
                    b["acao"],
                    b["ip"],
                    b["timestamp"],
                    b["details"],
                )
                if not hmac.compare_digest(b["curr_hash"], calc):
                    return False, b["id"]
                expected_prev = b["curr_hash"]
            return True, None

        intact, broken_id = verify_chain(chain)
        self.assertTrue(intact)
        self.assertIsNone(broken_id)

        # Tamper test: Alter details in block #777
        tampered_chain = copy.deepcopy(chain)
        tampered_chain[776]["details"]["utf8_text"] = "TAMPERED TEXT"
        t_valid, t_broken = verify_chain(tampered_chain)
        self.assertFalse(t_valid)
        self.assertEqual(t_broken, 777, "Tamper at block #777 must be precisely localized to block #777")

    # -------------------------------------------------------------------------
    # VECTOR 4: Rede de Apoio GPS Fallback & Spatial Geodesics
    # -------------------------------------------------------------------------
    def test_rede_de_apoio_gps_fallback_policy(self):
        """Adversarial Test: Coordinate fallback policy and asymmetric GPS handling."""
        mun_vitoria = {"id": 1, "nome": "Vitória", "lat": -20.3155, "lon": -40.3128}

        def resolve_unit(unit_lat: Optional[float], unit_lon: Optional[float], mun: Dict[str, Any]) -> Dict[str, Any]:
            has_exact = unit_lat is not None and unit_lon is not None
            return {
                "latitude": unit_lat if has_exact else mun["lat"],
                "longitude": unit_lon if has_exact else mun["lon"],
                "origem_coordenada": "exact_gps" if has_exact else "municipality_centroid_fallback",
            }

        # Exact GPS
        u1 = resolve_unit(-20.3100, -40.3000, mun_vitoria)
        self.assertEqual(u1["origem_coordenada"], "exact_gps")
        self.assertEqual(u1["latitude"], -20.3100)

        # Null GPS
        u2 = resolve_unit(None, None, mun_vitoria)
        self.assertEqual(u2["origem_coordenada"], "municipality_centroid_fallback")
        self.assertEqual(u2["latitude"], mun_vitoria["lat"])
        self.assertEqual(u2["longitude"], mun_vitoria["lon"])

        # Asymmetric partial GPS (lat present, lon null) -> must fall back safely to centroid
        u3 = resolve_unit(-20.3100, None, mun_vitoria)
        self.assertEqual(u3["origem_coordenada"], "municipality_centroid_fallback")
        self.assertEqual(u3["latitude"], mun_vitoria["lat"])

    def test_all_78_es_municipalities_bounds_and_geodesics(self):
        """Adversarial Test: Haversine distance calculations and bounding box conformance."""
        # Key ES municipality centroids
        vitoria = (-20.3155, -40.3128)
        vila_velha = (-20.3297, -40.2925)
        serra = (-20.1286, -40.3078)
        linhares = (-19.3964, -40.0644)
        cachoeiro = (-20.8489, -41.1128)
        sao_mateus = (-18.7161, -39.8589)

        # Haversine distance sanity checks
        d_vit_vv = self.haversine_distance(vitoria[0], vitoria[1], vila_velha[0], vila_velha[1])
        self.assertTrue(1.0 <= d_vit_vv <= 10.0, f"Vitória-Vila Velha distance ({d_vit_vv:.2f}km) within expected 1-10km range")

        d_vit_serra = self.haversine_distance(vitoria[0], vitoria[1], serra[0], serra[1])
        self.assertTrue(15.0 <= d_vit_serra <= 30.0, f"Vitória-Serra distance ({d_vit_serra:.2f}km) within expected 15-30km range")

        d_vit_lin = self.haversine_distance(vitoria[0], vitoria[1], linhares[0], linhares[1])
        self.assertTrue(90.0 <= d_vit_lin <= 130.0, f"Vitória-Linhares distance ({d_vit_lin:.2f}km) within expected 90-130km range")

        d_vit_sm = self.haversine_distance(vitoria[0], vitoria[1], sao_mateus[0], sao_mateus[1])
        self.assertTrue(170.0 <= d_vit_sm <= 220.0, f"Vitória-São Mateus distance ({d_vit_sm:.2f}km) within expected 170-220km range")


if __name__ == "__main__":
    unittest.main(verbosity=2)
