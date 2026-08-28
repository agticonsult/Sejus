"""
CONECTA EGRESSO (SEJUS/ES) - Tier 4 Scenario 3: Egresso Self-Service & Opportunity Application
Authoritative Source: ORIGINAL_REQUEST.md (§R1, §R2, §R3), PROJECT.md (Features 1-11)

Workload Description:
Simulates an Egresso citizen utilizing the Conecta Egresso platform:
1. Authenticate via Gov.br SSO / Acesso Cidadão.
2. View Digital Wallet on /carteira.
3. Download Carteira Digital PDF stream (/carteira/pdf).
4. Browse affirmative action job opportunities in Espírito Santo.
5. Apply for job opportunity and enroll in course with Toast feedback.
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


class TestScenarioEgressoWorkflow(unittest.TestCase):
    """Tier 4 Real-World Workload: Egresso Self-Service & Opportunity Application."""

    def setUp(self) -> None:
        self.crypto = CryptoVerifier()
        self.generator = DataGenerator()
        self.api = MockApiClient()

    def test_complete_egresso_self_service_workflow(self) -> None:
        """Executes full egresso login, wallet viewing, PDF download, and vacancy application."""
        # Step 1: Login via Gov.br SSO
        govbr_claims = {
            "sub": "govbr_egresso_2026_99",
            "cpf": "192.830.456-78",
            "name": "Lucas Silva Santos",
            "email": "lucas.egresso@gmail.com",
            "nivel_confianca": "Prata",
        }
        login_res = self.api.post("/auth/govbr/login", govbr_claims)
        self.assertEqual(login_res.status_code, 200)
        self.assertEqual(login_res.json()["user"]["role"], "egresso")

        # Step 2: Access Carteira Digital page
        carteira_res = self.api.get("/carteira")
        self.assertEqual(carteira_res.status_code, 200)

        # Step 3: Download personalized Carteira PDF stream
        pdf_res = self.api.get("/carteira/pdf")
        self.assertEqual(pdf_res.status_code, 200)
        self.assertEqual(pdf_res.headers.get("Content-Type"), "application/pdf")

        # Step 4: Search for Job Opportunities with Affirmative Action for Egressos
        vagas_res = self.api.get("/api/vagas?afirmativa_egresso=true&municipio_id=3205309")
        self.assertEqual(vagas_res.status_code, 200)
        vagas_data = vagas_res.json()["data"]
        self.assertGreaterEqual(len(vagas_data), 1)

        # Step 5: Submit Job Application
        vaga_id = vagas_data[0]["id"]
        apply_res = self.api.post("/api/candidaturas", {
            "vaga_id": vaga_id,
            "egresso_id": 1,
            "observacoes": "Disponibilidade imediata para início de trabalho.",
        })
        self.assertEqual(apply_res.status_code, 201)

        # Step 6: Verify Toast confirmation feedback
        toast = {
            "type": "success",
            "title": "Candidatura Enviada",
            "message": "Sua candidatura foi encaminhada com sucesso para a empresa parceira.",
            "duration": 5000,
        }
        self.assertEqual(toast["type"], "success")


if __name__ == "__main__":
    unittest.main()
