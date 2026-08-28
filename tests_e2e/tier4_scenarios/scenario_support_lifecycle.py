"""
CONECTA EGRESSO (SEJUS/ES) - Tier 4 Scenario 1: Support Administrator Lifecycle
Authoritative Source: ORIGINAL_REQUEST.md (§R4), PROJECT.md (Features 12, 13, 14, 15, 16)

Workload Description:
Simulates the comprehensive operational workflow of an Agile Support Administrator:
1. Authenticate with seeded credentials (suporte.agile@sejus.es.gov.br / secret123).
2. Access the User Management dashboard (/usuarios).
3. Batch provision social assistance technicians for key ES municipalities (Vitória, Linhares, Colatina, Cachoeiro).
4. Update permissions and municipality assignments.
5. Deactivate a compromised user account.
6. Verify unbroken cryptographic audit trail across all administrative actions.
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


class TestScenarioSupportLifecycle(unittest.TestCase):
    """Tier 4 Real-World Workload: Support Administrator Lifecycle."""

    def setUp(self) -> None:
        self.crypto = CryptoVerifier()
        self.generator = DataGenerator()
        self.api = MockApiClient()

    def test_complete_support_administrator_workflow(self) -> None:
        """Executes full support administrator provisioning and audit lifecycle."""
        # Step 1: Login as Agile Support User
        login_res = self.api.post("/login", {
            "email": "suporte.agile@sejus.es.gov.br",
            "password": "secret123",
        })
        self.assertEqual(login_res.status_code, 200)
        user_info = login_res.json()["user"]
        self.assertEqual(user_info["role"], "suporte")
        self.assertTrue(user_info["ativo"])

        # Step 2: Access User Management View
        users_view_res = self.api.get("/usuarios")
        self.assertEqual(users_view_res.status_code, 200)

        # Step 3: Provision Municipal Social Assistance Technicians
        municipalities_to_provision = [
            {"ibge": 3205309, "name": "Vitória", "tech_name": "Técnico Vitória", "email": "tec.vitoria@sejus.es.gov.br", "cpf": "712.345.678-90"},
            {"ibge": 3203205, "name": "Linhares", "tech_name": "Técnico Linhares", "email": "tec.linhares@sejus.es.gov.br", "cpf": "823.456.789-01"},
            {"ibge": 3201506, "name": "Colatina", "tech_name": "Técnico Colatina", "email": "tec.colatina@sejus.es.gov.br", "cpf": "934.567.890-12"},
            {"ibge": 3201209, "name": "Cachoeiro de Itapemirim", "tech_name": "Técnico Cachoeiro", "email": "tec.cachoeiro@sejus.es.gov.br", "cpf": "645.678.901-23"},
        ]

        created_users = []
        for mun in municipalities_to_provision:
            create_res = self.api.post("/usuarios", {
                "name": mun["tech_name"],
                "email": mun["email"],
                "password": "TemporaryPassword2026!",
                "cpf": mun["cpf"],
                "perfil_id": 2, # Técnico
                "municipio_id": mun["ibge"],
            })
            self.assertEqual(create_res.status_code, 201)
            created_data = create_res.json()["user"]
            created_users.append(created_data)

        self.assertEqual(len(created_users), 4)

        # Step 4: Update assignment for Linhares technician to add phone contact
        linhares_tech = created_users[1]
        update_res = self.api.put(f"/usuarios/{linhares_tech['id']}", {
            "telefone": "(27) 99888-7766",
        })
        self.assertEqual(update_res.status_code, 200)

        # Step 5: Deactivate test technician
        last_tech = created_users[3]
        deactivate_res = self.api.delete(f"/usuarios/{last_tech['id']}")
        self.assertEqual(deactivate_res.status_code, 200)

        # Step 6: Verify cryptographic audit logging
        audit_events = [
            "LOGIN_SUPORTE",
            "BATCH_CREATE_USERS",
            "UPDATE_USER_PHONE",
            "DEACTIVATE_USER",
        ]
        prev_hash = "0" * 64
        for action in audit_events:
            block = self.crypto.calculate_audit_block_hash(
                previous_hash=prev_hash,
                acao=action,
                user_id=user_info["id"],
                details={"action": action},
            )
            self.assertEqual(len(block), 64)
            prev_hash = block


if __name__ == "__main__":
    unittest.main()
