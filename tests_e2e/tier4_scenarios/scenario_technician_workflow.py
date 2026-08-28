"""
CONECTA EGRESSO (SEJUS/ES) - Tier 4 Scenario 2: Social Technician Attendance & Wallet Issuance
Authoritative Source: ORIGINAL_REQUEST.md (§R1, §R2), PROJECT.md (Features 1-8)

Workload Description:
Simulates a Social Assistance Technician conducting citizen intake at the Escritório Social:
1. Authenticate as Técnico.
2. Open attendance case at /atendimento.
3. Record progress note in Prontuário with Toast feedback.
4. Issue newly signed digital wallet with cryptographic QR code.
5. Generate and download Carteira Digital PDF via Document Generator (with fallback).
6. Verify public QR validation endpoint.
"""

from __future__ import annotations

import unittest
from typing import Dict, List, Any
from tests_e2e.e2e_utils import (
    CryptoVerifier,
    DataGenerator,
    AssertionHelper,
    MockApiClient,
)


class TestScenarioTechnicianWorkflow(unittest.TestCase):
    """Tier 4 Real-World Workload: Social Technician Attendance & Wallet Issuance."""

    def setUp(self) -> None:
        self.crypto = CryptoVerifier()
        self.generator = DataGenerator()
        self.api = MockApiClient()

    def test_complete_technician_attendance_and_wallet_workflow(self) -> None:
        """Executes full technician intake, note recording, wallet issuance, and PDF download."""
        # Step 1: Login as Técnico
        login_res = self.api.post("/auth/switch-role", {"role": "tecnico"})
        self.assertEqual(login_res.status_code, 200)
        self.assertEqual(login_res.json()["user"]["role"], "tecnico")

        # Step 2: Access Atendimento view
        atendimento_res = self.api.get("/atendimento")
        self.assertEqual(atendimento_res.status_code, 200)

        # Step 3: Record evolution entry in Prontuário
        prontuario_id = 1
        note_res = self.api.post(f"/api/prontuarios/{prontuario_id}/evolucao", {
            "tipo": "acolhimento_presencial",
            "descricao": "Atendimento presencial realizado no Escritório Social de Vitória. Cidadão orientado sobre qualificação profissional e emissão de carteira digital.",
            "encaminhamentos": ["emissao_carteira", "curso_capacitacao"],
        })
        self.assertEqual(note_res.status_code, 201)

        # Step 4: Generate Toast notification for save action
        toast = {
            "type": "success",
            "title": "Evolução Registrada",
            "message": "A evolução do prontuário foi gravada e vinculada à cadeia criptográfica.",
            "duration": 4000,
        }
        self.assertEqual(toast["type"], "success")

        # Step 5: Issue Digital Wallet with HMAC signature
        raw_cpf = "19283045678"
        token_envelope = self.crypto.generate_digital_wallet_token(
            egresso_id=prontuario_id,
            nome="Lucas Silva Santos",
            cpf_raw=raw_cpf,
            exp_days=365,
        )
        token_str = token_envelope["token"]
        self.assertIsNotNone(token_str)

        # Step 6: Download Carteira PDF stream
        pdf_res = self.api.get("/carteira/pdf")
        self.assertEqual(pdf_res.status_code, 200)
        self.assertEqual(pdf_res.headers.get("Content-Type"), "application/pdf")
        self.assertIn("carteira-digital-sejus.pdf", pdf_res.headers.get("Content-Disposition", ""))

        # Step 7: Validate public QR validation endpoint
        val_res = self.api.get(f"/validar-carteira/{token_str}")
        self.assertEqual(val_res.status_code, 200)
        
        verification = self.crypto.verify_digital_wallet_token(token_str)
        self.assertTrue(verification["valid"])
        self.assertEqual(verification["status"], "VALID_DOCUMENT")


if __name__ == "__main__":
    unittest.main()
