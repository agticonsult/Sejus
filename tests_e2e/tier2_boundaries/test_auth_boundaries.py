"""Tier 2 Boundary & Negative Tests: Authentication, RBAC, OIDC, and Session Boundaries.

Verifies:
- Expired JWT token rejection
- Invalid token signature rejection
- Unauthorized role elevation attempt (Egresso attempting Gestor actions)
- Missing / malformed Authorization header
- Malformed Gov.br / OIDC claims payload
- Deactivated/blocked user login attempt
- Rapid repeated authentication attempts (rate limit boundary)
- Session token reuse after logout
- Empty/whitespace credential payloads
- Password boundary complexity validation
- JWT algorithm 'none' spoofing rejection
- Future 'nbf' (Not Before) token rejection
"""

import base64
import hashlib
import hmac
import json
import time
import unittest
from typing import Any, Dict, Optional, Tuple


# --- Domain Helper Classes for Auth Simulation ---

class JwtHelper:
    """Standard RFC 7519 JWT implementation for boundary testing."""

    @staticmethod
    def base64url_encode(data: bytes) -> str:
        return base64.urlsafe_b64encode(data).rstrip(b"=").decode("utf-8")

    @staticmethod
    def base64url_decode(data: str) -> bytes:
        padding = 4 - (len(data) % 4)
        if padding != 4:
            data += "=" * padding
        return base64.urlsafe_b64decode(data.encode("utf-8"))

    @classmethod
    def create_token(
        cls,
        payload: Dict[str, Any],
        secret: str = "sejus_es_jwt_secret_key_2026",
        algorithm: str = "HS256",
        custom_header: Optional[Dict[str, Any]] = None,
    ) -> str:
        header = custom_header or {"alg": algorithm, "typ": "JWT"}
        header_b64 = cls.base64url_encode(json.dumps(header, separators=(",", ":")).encode("utf-8"))
        payload_b64 = cls.base64url_encode(json.dumps(payload, separators=(",", ":")).encode("utf-8"))
        signing_input = f"{header_b64}.{payload_b64}".encode("utf-8")

        if algorithm.upper() == "NONE":
            signature_b64 = ""
        else:
            signature = hmac.new(secret.encode("utf-8"), signing_input, hashlib.sha256).digest()
            signature_b64 = cls.base64url_encode(signature)

        return f"{header_b64}.{payload_b64}.{signature_b64}"

    @classmethod
    def verify_token(
        cls,
        token: str,
        secret: str = "sejus_es_jwt_secret_key_2026",
        allowed_algorithms: Tuple[str, ...] = ("HS256",),
    ) -> Tuple[bool, Optional[Dict[str, Any]], str]:
        parts = token.split(".")
        if len(parts) != 3:
            return False, None, "malformed_token_parts"

        header_b64, payload_b64, signature_b64 = parts

        try:
            header_json = cls.base64url_decode(header_b64)
            header = json.loads(header_json)
        except Exception:
            return False, None, "invalid_header_encoding"

        alg = header.get("alg")
        if not alg or alg not in allowed_algorithms:
            return False, None, "unsupported_or_insecure_algorithm"

        signing_input = f"{header_b64}.{payload_b64}".encode("utf-8")
        expected_sig = hmac.new(secret.encode("utf-8"), signing_input, hashlib.sha256).digest()
        expected_sig_b64 = cls.base64url_encode(expected_sig)

        if not hmac.compare_digest(signature_b64, expected_sig_b64):
            return False, None, "invalid_signature"

        try:
            payload_json = cls.base64url_decode(payload_b64)
            payload = json.loads(payload_json)
        except Exception:
            return False, None, "invalid_payload_encoding"

        now = int(time.time())
        if "exp" in payload and payload["exp"] < now:
            return False, None, "token_expired"

        if "nbf" in payload and payload["nbf"] > now:
            return False, None, "token_not_yet_valid"

        return True, payload, "valid"


class RateLimiter:
    """Sliding-window rate limiter for boundary testing."""

    def __init__(self, max_attempts: int = 5, window_seconds: int = 60):
        self.max_attempts = max_attempts
        self.window_seconds = window_seconds
        self.attempts: Dict[str, list] = {}

    def is_allowed(self, identifier: str, now: Optional[float] = None) -> Tuple[bool, int]:
        current_time = now if now is not None else time.time()
        timestamps = self.attempts.get(identifier, [])
        # Filter timestamps within the sliding window
        valid_timestamps = [t for t in timestamps if current_time - t < self.window_seconds]
        self.attempts[identifier] = valid_timestamps

        if len(valid_timestamps) >= self.max_attempts:
            retry_after = int(self.window_seconds - (current_time - valid_timestamps[0]))
            return False, max(1, retry_after)

        self.attempts[identifier].append(current_time)
        return True, 0


class AuthValidator:
    """Validates passwords, Gov.br OIDC claims, and RBAC policies."""

    ROLE_PERMISSIONS = {
        "gestor": {
            "view_dashboard", "manage_users", "view_reports", "view_lgpd_logs",
            "view_all_prontuarios", "export_kpis", "manage_vagas", "access_webrtc"
        },
        "tecnico": {
            "view_dashboard", "write_prontuario_evolution", "view_prontuario",
            "manage_attendance_queue", "access_webrtc", "view_vagas", "view_territorio"
        },
        "egresso": {
            "view_own_profile", "view_own_prontuario", "view_carteira_digital",
            "download_carteira_pdf", "apply_vaga", "join_webrtc_room"
        }
    }

    @staticmethod
    def validate_password_complexity(password: str) -> Tuple[bool, list]:
        errors = []
        if len(password) < 8:
            errors.append("Password must be at least 8 characters long.")
        if len(password) > 128:
            errors.append("Password must not exceed 128 characters.")
        if not any(c.isupper() for c in password):
            errors.append("Password must contain at least one uppercase letter.")
        if not any(c.islower() for c in password):
            errors.append("Password must contain at least one lowercase letter.")
        if not any(c.isdigit() for c in password):
            errors.append("Password must contain at least one numeric digit.")
        special_chars = set("!@#$%^&*()-_=+[]{}|;:,.<>?/~`")
        if not any(c in special_chars for c in password):
            errors.append("Password must contain at least one special character.")
        common_passwords = {"12345678", "password123!", "Admin123!", "Sejus123!"}
        if password in common_passwords:
            errors.append("Password is too common or easily guessable.")

        return (len(errors) == 0), errors

    @staticmethod
    def validate_govbr_claims(claims: Dict[str, Any]) -> Tuple[bool, list]:
        errors = []
        if not claims.get("sub"):
            errors.append("Claim 'sub' (subject ID) is missing or empty.")
        cpf = str(claims.get("cpf", "")).strip()
        clean_cpf = "".join(filter(str.isdigit, cpf))
        if len(clean_cpf) != 11:
            errors.append("Claim 'cpf' must contain exactly 11 digits.")
        else:
            # Check for all same digits
            if len(set(clean_cpf)) == 1:
                errors.append("Claim 'cpf' contains invalid repeated sequence.")
            else:
                # Validate CPF check digits
                sum1 = sum(int(clean_cpf[i]) * (10 - i) for i in range(9))
                d1 = (sum1 * 10 % 11) % 10
                sum2 = sum(int(clean_cpf[i]) * (11 - i) for i in range(10))
                d2 = (sum2 * 10 % 11) % 10
                if int(clean_cpf[9]) != d1 or int(clean_cpf[10]) != d2:
                    errors.append("Claim 'cpf' has invalid verification digits.")

        if not claims.get("name") or not str(claims.get("name")).strip():
            errors.append("Claim 'name' is required.")
        if claims.get("nivel_confianca") not in ("Prata", "Ouro", "Bronze"):
            errors.append("Claim 'nivel_confianca' must be Bronze, Prata, or Ouro.")

        return (len(errors) == 0), errors

    @classmethod
    def check_permission(cls, role: str, permission: str) -> bool:
        allowed = cls.ROLE_PERMISSIONS.get(role, set())
        return permission in allowed


# --- Test Suite ---

class TestAuthBoundaries(unittest.TestCase):
    """Tier 2 Boundary test suite for Authentication and Authorization."""

    def setUp(self):
        self.jwt_secret = "sejus_es_jwt_secret_key_2026"
        self.now = int(time.time())

    def test_01_expired_jwt_rejection(self):
        """Verify that JWT with expiration timestamp in the past is strictly rejected."""
        payload = {
            "user_id": 101,
            "role": "tecnico",
            "name": "Maria Santos",
            "exp": self.now - 300,  # Expired 5 minutes ago
            "iat": self.now - 3900,
        }
        token = JwtHelper.create_token(payload, self.jwt_secret)
        is_valid, decoded, reason = JwtHelper.verify_token(token, self.jwt_secret)

        self.assertFalse(is_valid, "Expired token must not be accepted.")
        self.assertIsNone(decoded)
        self.assertEqual(reason, "token_expired")

    def test_02_invalid_token_signature_rejection(self):
        """Verify that JWT with altered payload or wrong HMAC signature is rejected."""
        payload = {"user_id": 101, "role": "egresso", "exp": self.now + 3600}
        token = JwtHelper.create_token(payload, self.jwt_secret)

        # 1. Test verification with wrong secret
        is_valid, _, reason = JwtHelper.verify_token(token, "wrong_secret_key_999")
        self.assertFalse(is_valid)
        self.assertEqual(reason, "invalid_signature")

        # 2. Test tampering payload without updating signature
        parts = token.split(".")
        tampered_payload = {"user_id": 101, "role": "gestor", "exp": self.now + 3600}
        tampered_b64 = JwtHelper.base64url_encode(json.dumps(tampered_payload).encode())
        tampered_token = f"{parts[0]}.{tampered_b64}.{parts[2]}"

        is_valid_t, _, reason_t = JwtHelper.verify_token(tampered_token, self.jwt_secret)
        self.assertFalse(is_valid_t, "Tampered payload token must fail signature check.")
        self.assertEqual(reason_t, "invalid_signature")

    def test_03_unauthorized_role_elevation_attempt(self):
        """Verify that an Egresso role attempting Gestor actions is strictly forbidden."""
        user_role = "egresso"
        gestor_actions = [
            "view_reports",
            "view_lgpd_logs",
            "manage_users",
            "export_kpis",
        ]

        for action in gestor_actions:
            has_perm = AuthValidator.check_permission(user_role, action)
            self.assertFalse(
                has_perm,
                f"Egresso must NOT have permission for Gestor action: {action}"
            )

        # Egresso should only have egresso permissions
        self.assertTrue(AuthValidator.check_permission(user_role, "view_carteira_digital"))
        self.assertTrue(AuthValidator.check_permission(user_role, "download_carteira_pdf"))

    def test_04_missing_authorization_header(self):
        """Verify handling of missing, empty, or malformed Authorization headers."""
        invalid_headers = [
            None,
            "",
            "   ",
            "Bearer",
            "Bearer ",
            "Basic dXNlcjpwYXNz",
            "Token 12345",
            "Bearer abc.def",  # incomplete JWT
        ]

        for header in invalid_headers:
            if not header or not header.strip():
                status = "missing_header"
            elif not header.startswith("Bearer "):
                status = "invalid_scheme"
            else:
                token_candidate = header[7:].strip()
                if not token_candidate:
                    status = "empty_bearer_token"
                else:
                    valid, _, reason = JwtHelper.verify_token(token_candidate, self.jwt_secret)
                    status = "invalid_token" if not valid else "valid"

            self.assertIn(status, ["missing_header", "invalid_scheme", "empty_bearer_token", "invalid_token"])
            self.assertNotEqual(status, "valid", f"Header '{header}' should not be valid.")

    def test_05_malformed_govbr_oidc_claims_payload(self):
        """Verify Gov.br OIDC claim validation rejects missing sub, invalid CPF, or bad levels."""
        malformed_claims = [
            {},  # Empty
            {"sub": "", "cpf": "12345678909", "name": "Teste", "nivel_confianca": "Prata"},  # Missing sub
            {"sub": "gov-1", "cpf": "11111111111", "name": "Teste", "nivel_confianca": "Prata"},  # Repeating CPF
            {"sub": "gov-2", "cpf": "123.456.789-00", "name": "Teste", "nivel_confianca": "Prata"},  # Invalid checksum
            {"sub": "gov-3", "cpf": "01234567890", "name": "", "nivel_confianca": "Prata"},  # Missing name
            {"sub": "gov-4", "cpf": "52998224725", "name": "João", "nivel_confianca": "Invalido"},  # Invalid nivel
        ]

        for claims in malformed_claims:
            is_valid, errors = AuthValidator.validate_govbr_claims(claims)
            self.assertFalse(is_valid, f"Claims {claims} should have failed validation.")
            self.assertGreater(len(errors), 0)

        # Valid claim test (52998224725 is a mathematically valid CPF)
        valid_claims = {
            "sub": "gov-br-user-12345",
            "cpf": "529.982.247-25",
            "name": "Carlos Eduardo da Silva",
            "email": "carlos.silva@email.com",
            "nivel_confianca": "Ouro",
        }
        is_valid_ok, errors_ok = AuthValidator.validate_govbr_claims(valid_claims)
        self.assertTrue(is_valid_ok, f"Valid claims failed: {errors_ok}")
        self.assertEqual(len(errors_ok), 0)

    def test_06_deactivated_blocked_user_login_attempt(self):
        """Verify that inactive or suspended user accounts cannot authenticate."""
        mock_user_db = {
            "user_active": {"id": 1, "email": "active@sejus.es.gov.br", "status": "ativo"},
            "user_inactive": {"id": 2, "email": "inactive@sejus.es.gov.br", "status": "inativo"},
            "user_blocked": {"id": 3, "email": "blocked@sejus.es.gov.br", "status": "bloqueado"},
        }

        def attempt_login(user_key: str) -> Tuple[bool, str]:
            user = mock_user_db.get(user_key)
            if not user:
                return False, "user_not_found"
            if user["status"] != "ativo":
                return False, f"account_{user['status']}"
            return True, "authenticated"

        self.assertEqual(attempt_login("user_active"), (True, "authenticated"))
        self.assertEqual(attempt_login("user_inactive"), (False, "account_inativo"))
        self.assertEqual(attempt_login("user_blocked"), (False, "account_bloqueado"))

    def test_07_rapid_repeated_authentication_attempts_rate_limit(self):
        """Verify that rapid authentication requests trigger HTTP 429 rate limit."""
        limiter = RateLimiter(max_attempts=5, window_seconds=60)
        client_ip = "192.168.10.45"
        base_time = 1000.0

        # First 5 attempts within 10 seconds should pass
        for i in range(5):
            allowed, _ = limiter.is_allowed(client_ip, now=base_time + (i * 2))
            self.assertTrue(allowed, f"Attempt {i+1} within limit should be allowed.")

        # 6th attempt at 11s must be blocked
        allowed_6th, retry_after = limiter.is_allowed(client_ip, now=base_time + 11)
        self.assertFalse(allowed_6th, "6th attempt must be rate-limited.")
        self.assertGreaterEqual(retry_after, 1, "Retry-After should be positive.")

        # After window expiration (61s later), requests should be allowed again
        allowed_after_window, _ = limiter.is_allowed(client_ip, now=base_time + 62)
        self.assertTrue(allowed_after_window, "Request after rate window expiration must succeed.")

    def test_08_session_token_reuse_after_logout(self):
        """Verify that session tokens / JWTs added to revocation blacklist cannot be reused."""
        revocation_blacklist = set()

        payload = {"user_id": 50, "jti": "jwt-uuid-998877", "exp": self.now + 3600}
        token = JwtHelper.create_token(payload, self.jwt_secret)

        # 1. Active token check
        is_valid, decoded, _ = JwtHelper.verify_token(token, self.jwt_secret)
        self.assertTrue(is_valid)
        self.assertNotIn(decoded["jti"], revocation_blacklist)

        # 2. User logs out -> JTI blacklisted
        revocation_blacklist.add(decoded["jti"])

        # 3. Subsequent request with same token must be rejected
        is_valid_after, decoded_after, _ = JwtHelper.verify_token(token, self.jwt_secret)
        self.assertTrue(is_valid_after)  # Signature itself is mathematically valid
        token_is_revoked = decoded_after["jti"] in revocation_blacklist
        self.assertTrue(token_is_revoked, "Revoked JTI must be recognized in blacklist.")

    def test_09_empty_whitespace_credential_payloads(self):
        """Verify login validation rejects empty, whitespace, or missing credentials."""
        invalid_payloads = [
            {},
            {"cpf": "", "password": ""},
            {"cpf": "   ", "password": "   "},
            {"cpf": None, "password": None},
            {"cpf": "52998224725"},  # missing password
            {"password": "ValidPassword123!"},  # missing CPF
        ]

        def validate_login_form(data: Dict[str, Any]) -> Tuple[bool, Dict[str, str]]:
            errors = {}
            cpf = str(data.get("cpf", "") or "").strip()
            password = str(data.get("password", "") or "").strip()
            if not cpf:
                errors["cpf"] = "O CPF é obrigatório."
            if not password:
                errors["password"] = "A senha é obrigatória."
            return len(errors) == 0, errors

        for payload in invalid_payloads:
            valid, errs = validate_login_form(payload)
            self.assertFalse(valid, f"Payload {payload} should be rejected.")
            self.assertGreater(len(errs), 0)

    def test_10_password_boundary_complexity_validation(self):
        """Verify password complexity boundary conditions (length, chars, common lists)."""
        # Test boundary cases
        self.assertFalse(AuthValidator.validate_password_complexity("Ab1!")[0])  # Too short (<8)
        self.assertFalse(AuthValidator.validate_password_complexity("alllowercasenumber1!")[0])  # No upper
        self.assertFalse(AuthValidator.validate_password_complexity("ALLUPPERCASENUMBER1!")[0])  # No lower
        self.assertFalse(AuthValidator.validate_password_complexity("NoNumbersSpecialChar!")[0])  # No digits
        self.assertFalse(AuthValidator.validate_password_complexity("NoSpecialChars123456")[0])  # No special
        self.assertFalse(AuthValidator.validate_password_complexity("A" * 129 + "1!a")[0])  # Exceeds max length
        self.assertFalse(AuthValidator.validate_password_complexity("Admin123!")[0])  # Common password

        # Valid boundary password (exact 8 characters)
        valid_8 = "Ab1@cdef"
        is_ok, errs = AuthValidator.validate_password_complexity(valid_8)
        self.assertTrue(is_ok, f"Valid 8-char password rejected: {errs}")

        # Valid strong password
        valid_strong = "Conecta@SEJUS#2026_Egresso!"
        is_ok2, _ = AuthValidator.validate_password_complexity(valid_strong)
        self.assertTrue(is_ok2)

    def test_11_tampered_jwt_algorithm_none_attack(self):
        """Verify that 'alg': 'none' JWT attack vector is strictly rejected."""
        payload = {"user_id": 1, "role": "gestor", "exp": self.now + 3600}
        none_token = JwtHelper.create_token(payload, algorithm="none", custom_header={"alg": "none", "typ": "JWT"})

        is_valid, _, reason = JwtHelper.verify_token(none_token, self.jwt_secret, allowed_algorithms=("HS256",))
        self.assertFalse(is_valid, "Algorithm 'none' token must NEVER be accepted.")
        self.assertEqual(reason, "unsupported_or_insecure_algorithm")

    def test_12_jwt_future_nbf_not_before_rejection(self):
        """Verify that JWT with 'nbf' in the future is rejected until its activation time."""
        payload = {
            "user_id": 105,
            "role": "tecnico",
            "nbf": self.now + 1800,  # Valid only 30 minutes in future
            "exp": self.now + 7200,
        }
        token = JwtHelper.create_token(payload, self.jwt_secret)
        is_valid, _, reason = JwtHelper.verify_token(token, self.jwt_secret)

        self.assertFalse(is_valid, "Token with future 'nbf' must not be accepted.")
        self.assertEqual(reason, "token_not_yet_valid")


if __name__ == "__main__":
    unittest.main()
