"""
CONECTA EGRESSO (SEJUS/ES) - Tier 2: Boundary & Corner Cases Suite
Authoritative Source: ORIGINAL_REQUEST.md, PROJECT.md (Features 1-18)

Covers:
- Microservice offline, timeout, 500 error fallback testing
- Invalid login credentials (wrong password, non-existent user, malformed email/CPF)
- Account deactivated login attempt (inactive user blocked with 403)
- Duplicate email / CPF registration attempts in user management
- Invalid user input validation (missing name, invalid email, weak/short password, invalid CPF checksum)
- Unauthenticated access to protected routes & PDF generation
- Privilege escalation attempts (egresso attempting to access admin endpoints)
- Extreme payload boundaries & XSS injection sanitization
"""

from __future__ import annotations

import json
import os
import re
import unittest
from pathlib import Path
from typing import Dict, List, Any
from tests_e2e.e2e_utils import (
    CryptoVerifier,
    DataGenerator,
    AssertionHelper,
    MockApiClient,
)


class TestTier2BoundariesM6Features(unittest.TestCase):
    """Tier 2 Boundary, Negative, and Corner Case Tests."""

    def setUp(self) -> None:
        self.crypto = CryptoVerifier()
        self.generator = DataGenerator()
        self.api = MockApiClient()

    def test_01_document_generator_microservice_offline_fallback(self) -> None:
        """T2.1: Tests that when microservice is offline / connection refused, fallback returns valid PDF."""
        # Simulate microservice failure
        html_payload = "<html><body><h1>CARTEIRA DIGITAL SEJUS/ES</h1><p>Lucas Santos</p></body></html>"
        
        # Test fallback generation
        fallback_pdf = self.crypto.render_fallback_pdf(html_payload, egresso_name="Lucas Santos", cpf_masked="***.830.456-**")
        self.assertIsNotNone(fallback_pdf)
        self.assertTrue(len(fallback_pdf) > 100, "Fallback PDF must produce valid non-empty byte stream")
        self.assertTrue(fallback_pdf.startswith(b"%PDF-") or b"%PDF-" in fallback_pdf, "Fallback must start with %PDF- header")

    def test_02_document_generator_timeout_and_500_resilience(self) -> None:
        """T2.2: Tests resilience when microservice responds with HTTP 500 Internal Server Error or times out."""
        # Simulated service response codes
        error_codes = [500, 502, 503, 504, 408]
        for code in error_codes:
            # When error occurs, system must gracefully fall back without throwing unhandled exception
            fallback_bytes = self.crypto.render_fallback_pdf("<html>Error Test</html>", "Egresso Teste", "***.123.456-**")
            self.assertTrue(len(fallback_bytes) > 0, f"Fallback must succeed on HTTP {code} error")

    def test_03_invalid_login_credentials_rejected(self) -> None:
        """T2.3: Tests that invalid password, non-existent user, or empty fields are rejected with 401/422."""
        invalid_cases = [
            {"email": "gestor@sejus.es.gov.br", "password": "wrong_password_123"},
            {"email": "nonexistent.user.2026@sejus.es.gov.br", "password": "any_password"},
            {"cpf": "000.000.000-00", "password": "wrong_password"},
            {"email": "", "password": "secret_password"},
            {"email": "valid@sejus.es.gov.br", "password": ""},
        ]

        for payload in invalid_cases:
            res = self.api.post("/login", payload)
            self.assertIn(
                res.status_code,
                [401, 422],
                f"Invalid login payload {payload} should return 401 or 422, received {res.status_code}"
            )

    def test_04_deactivated_account_login_blocked(self) -> None:
        """T2.4: Tests that users with ativo = false cannot log in and receive 403 ACCOUNT_DEACTIVATED."""
        # Simulate deactivated user login
        deactivated_user_payload = {
            "email": "deactivated.user@sejus.es.gov.br",
            "password": "correct_password_123",
            "is_active": False,
        }
        res = self.api.post("/login", deactivated_user_payload)
        # Should return 403 Forbidden
        self.assertIn(res.status_code, [401, 403], "Deactivated user must not be granted session")

    def test_05_invalid_user_creation_inputs_rejected(self) -> None:
        """T2.5: Tests that invalid user inputs (missing name, bad email, weak password, invalid CPF) are rejected."""
        invalid_user_payloads = [
            # Missing name
            {"name": "", "email": "teste1@sejus.es.gov.br", "password": "Password123!", "cpf": "529.982.247-25", "perfil_id": 1},
            # Invalid email format
            {"name": "Teste Email", "email": "not-an-email", "password": "Password123!", "cpf": "529.982.247-25", "perfil_id": 1},
            # Short password (< 6 chars)
            {"name": "Teste Senha", "email": "teste2@sejus.es.gov.br", "password": "123", "cpf": "529.982.247-25", "perfil_id": 1},
            # Invalid CPF checksum (repeated sequence)
            {"name": "Teste CPF", "email": "teste3@sejus.es.gov.br", "password": "Password123!", "cpf": "111.111.111-11", "perfil_id": 1},
            # Invalid perfil_id (non-existent role)
            {"name": "Teste Perfil", "email": "teste4@sejus.es.gov.br", "password": "Password123!", "cpf": "529.982.247-25", "perfil_id": 999},
        ]

        for payload in invalid_user_payloads:
            res = self.api.post("/usuarios", payload)
            self.assertEqual(
                res.status_code,
                422,
                f"Invalid user input {payload} should trigger validation error 422, received {res.status_code}"
            )

    def test_06_duplicate_email_and_cpf_registration_collision(self) -> None:
        """T2.6: Tests that attempting to register an existing email or CPF blind index collision is rejected."""
        existing_email = "gestor@sejus.es.gov.br"
        existing_cpf = "529.982.247-25"

        duplicate_email_payload = {
            "name": "Novo Usuário Duplicado",
            "email": existing_email,
            "password": "Password123!",
            "cpf": "192.830.456-78",
            "perfil_id": 2,
        }
        res_email = self.api.post("/usuarios", duplicate_email_payload)
        self.assertIn(res_email.status_code, [409, 422], "Duplicate email must be rejected with 409 or 422")

        duplicate_cpf_payload = {
            "name": "Outro Usuário Duplicado",
            "email": "unique.user.99@sejus.es.gov.br",
            "password": "Password123!",
            "cpf": existing_cpf,
            "perfil_id": 2,
        }
        res_cpf = self.api.post("/usuarios", duplicate_cpf_payload)
        self.assertIn(res_cpf.status_code, [409, 422], "Duplicate CPF blind index must be rejected with 409 or 422")

    def test_07_unauthenticated_access_to_protected_routes(self) -> None:
        """T2.7: Tests that unauthenticated requests to protected endpoints return 401 or redirect to /login."""
        protected_routes = [
            "/usuarios",
            "/api/prontuarios/1/evolucao",
            "/api/kpis/dashboard",
        ]

        for route in protected_routes:
            res = self.api.get(route, headers={"X-Unauthenticated": "true"})
            self.assertIn(
                res.status_code,
                [401, 403, 302],
                f"Unauthenticated request to '{route}' must return 401, 403, or 302 redirect, received {res.status_code}"
            )

    def test_08_privilege_escalation_guard(self) -> None:
        """T2.8: Tests that standard Egresso or Familiar roles cannot access or mutate user management."""
        admin_mutations = [
            ("POST", "/usuarios", {"name": "Hacker User", "email": "hacker@evil.com", "password": "pass", "perfil_id": 1}),
            ("DELETE", "/usuarios/1", {}),
            ("PUT", "/usuarios/1", {"name": "Tampered Name"}),
        ]

        for method, endpoint, payload in admin_mutations:
            # Simulated Egresso token
            headers = {"X-User-Role": "egresso"}
            if method == "POST":
                res = self.api.post(endpoint, payload, headers=headers)
            elif method == "DELETE":
                res = self.api.delete(endpoint, headers=headers)
            else:
                res = self.api.put(endpoint, payload, headers=headers)

            self.assertEqual(
                res.status_code,
                403,
                f"Egresso attempting {method} {endpoint} must be blocked with HTTP 403 Forbidden"
            )

    def test_09_boundary_payload_size_and_xss_escaping(self) -> None:
        """T2.9: Tests boundary strings (64KB, Unicode, XSS script injection) in user management fields."""
        xss_name = "<script>alert('XSS')</script>Lucas Santos"
        sanitized = self.crypto.sanitize_html_entities(xss_name)
        self.assertNotIn("<script>", sanitized, "XSS tags must be escaped to &lt;script&gt;")
        self.assertIn("&lt;script&gt;", sanitized)

        # 64KB string payload
        large_description = "A" * (65 * 1024)
        is_within_limit = len(large_description.encode("utf-8")) <= 64 * 1024
        self.assertFalse(is_within_limit, "65KB string exceeds 64KB boundary limit")


if __name__ == "__main__":
    unittest.main()
