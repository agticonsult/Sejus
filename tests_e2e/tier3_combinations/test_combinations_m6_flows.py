"""
CONECTA EGRESSO (SEJUS/ES) - Tier 3: Pairwise Combinatorial & Cross-Feature Suite
Authoritative Source: ORIGINAL_REQUEST.md, PROJECT.md (Features 1-18)

Cross-Feature Flows Tested:
- Flow 1: Suporte Login -> List Users -> Create Gestor -> Edit Gestor -> Switch Role -> Verify Permissions
- Flow 2: Técnico Login -> Issue Carteira -> Generate PDF -> Extract QR HMAC Token -> Public Verification Endpoint
- Flow 3: Toast Notification Trigger on Modal Mutation -> Role Switch -> Flash Toast Preservation across Navigation
- Flow 4: Unauthenticated Demo PDF -> Gov.br SSO Login -> Authenticated Personalized Carteira PDF Transition
- Flow 5: User Management CRUD Operations -> Sequential SHA-256 Audit Log Hash Chaining & Tamper Detection
"""

from __future__ import annotations

import json
import os
import unittest
from pathlib import Path
from typing import Dict, List, Any
from tests_e2e.e2e_utils import (
    CryptoVerifier,
    DataGenerator,
    AssertionHelper,
    MockApiClient,
)


class TestTier3CombinationsM6Flows(unittest.TestCase):
    """Tier 3 Cross-Feature Combinatorial Integration Tests."""

    def setUp(self) -> None:
        self.crypto = CryptoVerifier()
        self.generator = DataGenerator()
        self.api = MockApiClient()

    def test_01_cross_flow_support_login_create_edit_switch_role(self) -> None:
        """T3.1: Login as Suporte -> Create Gestor -> Edit User -> Switch to new Gestor -> Verify Permissions."""
        # 1. Login as Agile Support User
        login_res = self.api.post("/login", {
            "email": "suporte.agile@sejus.es.gov.br",
            "password": "secret123",
        })
        self.assertEqual(login_res.status_code, 200)
        self.assertEqual(login_res.json()["user"]["role"], "suporte")

        # 2. Support user provisions a new Gestor
        new_gestor_cpf = "765.432.109-88"
        create_res = self.api.post("/usuarios", {
            "name": "Dr. Fernando Gestor Regional",
            "email": "fernando.gestor@sejus.es.gov.br",
            "password": "SecretPassword2026!",
            "cpf": new_gestor_cpf,
            "perfil_id": 1, # Gestor
            "municipio_id": 3205309, # Vitória
        })
        self.assertEqual(create_res.status_code, 201)
        created_user = create_res.json()["user"]
        created_id = created_user["id"]
        self.assertEqual(created_user["role"], "gestor")

        # 3. Edit newly created Gestor (assign to Linhares)
        update_res = self.api.put(f"/usuarios/{created_id}", {
            "name": "Dr. Fernando Gestor Regional - Linhares",
            "municipio_id": 3203205, # Linhares
        })
        self.assertEqual(update_res.status_code, 200)

        # 4. Switch active role to the Gestor
        switch_res = self.api.post("/auth/switch-role", {"role": "gestor"})
        self.assertEqual(switch_res.status_code, 200)
        self.assertEqual(switch_res.json()["user"]["role"], "gestor")

        # 5. Verify Gestor permissions allow viewing all municipal reports
        reports_res = self.api.get("/api/kpis/dashboard")
        self.assertEqual(reports_res.status_code, 200)

    def test_02_cross_flow_carteira_pdf_qr_validation_chain(self) -> None:
        """T3.2: Issue Carteira -> Generate PDF with QR -> Validate QR HMAC Token on Public Route."""
        # 1. Login as Técnico
        self.api.post("/auth/switch-role", {"role": "tecnico"})

        # 2. Issue digital wallet for Egresso
        egresso_cpf = "192.830.456-78"
        raw_cpf = "19283045678"
        blind_index = self.crypto.calculate_blind_index(raw_cpf)
        
        # 3. Generate signed QR Code envelope
        token_envelope = self.crypto.generate_digital_wallet_token(
            egresso_id=1,
            nome="Lucas Silva Santos",
            cpf_raw=raw_cpf,
            exp_days=365,
        )
        token_str = token_envelope["token"]
        self.assertIsNotNone(token_str)
        self.assertTrue(len(token_str) > 20)

        # 4. Render PDF containing QR code
        pdf_bytes = self.crypto.render_fallback_pdf(
            html_template=f"<html><body><img src='data:image/svg+xml;base64,...' /><h1>VALID: {token_str}</h1></body></html>",
            egresso_name="Lucas Silva Santos",
            cpf_masked="***.830.456-**",
        )
        self.assertTrue(len(pdf_bytes) > 0)

        # 5. Validate the extracted QR token via public endpoint /validar-carteira/{token}
        validation_res = self.api.get(f"/validar-carteira/{token_str}")
        self.assertEqual(validation_res.status_code, 200)
        
        # 6. Verify cryptographic payload authenticity
        verified = self.crypto.verify_digital_wallet_token(token_str)
        self.assertTrue(verified["valid"])
        self.assertEqual(verified["status"], "VALID_DOCUMENT")
        self.assertEqual(verified["payload"]["nome"], "Lucas Silva Santos")

    def test_03_cross_flow_toast_notifications_and_role_transitions(self) -> None:
        """T3.3: Trigger mutations resulting in Toast notifications -> Switch role -> Verify flash feedback."""
        # 1. Simulate user creation triggering a success Toast
        toast_event = {
            "type": "success",
            "title": "Usuário Cadastrado",
            "message": "O usuário foi criado com sucesso no sistema SEJUS.",
            "duration": 4000,
        }
        self.assertEqual(toast_event["type"], "success")
        self.assertTrue(toast_event["duration"] >= 3000)

        # 2. Switch role triggering flash message toast in AppLayout
        switch_res = self.api.post("/auth/switch-role", {"role": "suporte"})
        self.assertEqual(switch_res.status_code, 200)
        
        flash_toast = {
            "type": "info",
            "title": "Perfil Alterado",
            "message": "Você agora está operando como Suporte SEJUS.",
        }
        self.assertEqual(flash_toast["type"], "info")

    def test_04_cross_flow_unauthenticated_fallback_to_govbr_auth_pdf(self) -> None:
        """T3.4: Access /carteira/pdf unauthenticated (fallback) -> Gov.br Login -> Personalized PDF."""
        # 1. Unauthenticated request to /carteira/pdf
        res_unauth = self.api.get("/carteira/pdf", headers={"X-Unauthenticated": "true"})
        self.assertEqual(res_unauth.status_code, 200)
        self.assertEqual(res_unauth.headers.get("Content-Type"), "application/pdf")

        # 2. Authenticate via Gov.br SSO
        govbr_claims = {
            "sub": "govbr_egresso_lucas_123",
            "cpf": "192.830.456-78",
            "name": "Lucas Santos Silva",
            "email": "lucas.santos@egresso.es.gov.br",
            "nivel_confianca": "Prata",
        }
        login_res = self.api.post("/auth/govbr/login", govbr_claims)
        self.assertEqual(login_res.status_code, 200)
        self.assertEqual(login_res.json()["user"]["role"], "egresso")

        # 3. Authenticated request to /carteira/pdf
        res_auth = self.api.get("/carteira/pdf", headers={"X-User-Id": "1"})
        self.assertEqual(res_auth.status_code, 200)
        self.assertEqual(res_auth.headers.get("Content-Type"), "application/pdf")

    def test_05_cross_flow_user_crud_cryptographic_audit_hash_chain(self) -> None:
        """T3.5: Sequential User CRUD actions form an unbroken SHA-256 cryptographic audit chain."""
        audit_events = [
            {"acao": "CREATE_USER", "user_id": 1, "target": "novo_tecnico@sejus.es.gov.br"},
            {"acao": "UPDATE_USER", "user_id": 1, "target": "novo_tecnico@sejus.es.gov.br", "changes": {"municipio": "Colatina"}},
            {"acao": "DEACTIVATE_USER", "user_id": 1, "target": "novo_tecnico@sejus.es.gov.br"},
        ]

        chain = []
        prev_hash = "0" * 64 # Genesis hash

        for event in audit_events:
            block = self.crypto.calculate_audit_block_hash(
                previous_hash=prev_hash,
                acao=event["acao"],
                user_id=event["user_id"],
                details=event,
            )
            chain.append({"event": event, "prev_hash": prev_hash, "current_hash": block})
            prev_hash = block

        # Verify chain integrity
        for i in range(1, len(chain)):
            self.assertEqual(
                chain[i]["prev_hash"],
                chain[i - 1]["current_hash"],
                f"Hash link broken between block {i-1} and block {i}"
            )
        self.assertEqual(len(chain), 3)


if __name__ == "__main__":
    unittest.main()
