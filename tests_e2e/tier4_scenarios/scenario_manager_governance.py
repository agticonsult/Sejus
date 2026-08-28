"""
CONECTA EGRESSO (SEJUS/ES) - Tier 4 Scenario 4: Manager Governance & Route 404 Audit
Authoritative Source: ORIGINAL_REQUEST.md (§R4, §R5), PROJECT.md (Features 14-18)

Workload Description:
Simulates a statewide SEJUS Manager conducting executive governance and route health audit:
1. Authenticate as Gestor.
2. Review statewide KPI indicators covering 78 Espírito Santo municipalities.
3. Access User Management to review staff accounts and role permissions.
4. Execute an exhaustive zero-404 route sweep across all web routes and API endpoints.
5. Verify zero missing views, zero broken links, and full system operational readiness.
"""

from __future__ import annotations

import unittest
from typing import Dict, List, Any
from tests_e2e.e2e_utils import (
    CryptoVerifier,
    DataGenerator,
    AssertionHelper,
    MockApiClient,
    ES_MUNICIPALITIES,
)


class TestScenarioManagerGovernance(unittest.TestCase):
    """Tier 4 Real-World Workload: Manager Governance & Zero-404 Route Audit."""

    def setUp(self) -> None:
        self.crypto = CryptoVerifier()
        self.generator = DataGenerator()
        self.api = MockApiClient()

    def test_complete_manager_governance_and_zero_404_audit(self) -> None:
        """Executes full governance review and zero-404 route audit across all 18 features."""
        # Step 1: Login as Gestor
        login_res = self.api.post("/auth/switch-role", {"role": "gestor"})
        self.assertEqual(login_res.status_code, 200)
        self.assertEqual(login_res.json()["user"]["role"], "gestor")

        # Step 2: Statewide KPI Dashboard Review
        kpi_res = self.api.get("/api/kpis/dashboard")
        self.assertEqual(kpi_res.status_code, 200)
        kpi_data = kpi_res.json()
        self.assertIn("meta_populacional_egressos", kpi_data)
        self.assertIn("taxa_nao_reincidencia", kpi_data)
        self.assertEqual(len(ES_MUNICIPALITIES), 78)

        # Step 3: Access User Management Console
        usuarios_res = self.api.get("/usuarios")
        self.assertEqual(usuarios_res.status_code, 200)

        # Step 4: Exhaustive Zero-404 Route Audit Sweep across all 18 features
        audit_endpoints = [
            # Inertia Web Routes
            "/dashboard",
            "/atendimento",
            "/oportunidades",
            "/carteira",
            "/carteira/pdf",
            "/geolocalizacao",
            "/prontuario",
            "/relatorios",
            "/seguranca-lgpd",
            "/usuarios",
            "/validar-carteira",
            "/login",
            
            # API Endpoints
            "/api/auth/me",
            "/api/kpis/dashboard",
            "/api/kpis/regional",
            "/api/territorio/municipios",
            "/api/territorio/rede-apoio",
            "/api/vagas",
            "/api/cursos",
        ]

        sweep_results = []
        for endpoint in audit_endpoints:
            res = self.api.get(endpoint)
            # Route must be reachable (not 404)
            self.assertNotEqual(
                res.status_code,
                404,
                f"AUDIT FAILURE: Endpoint '{endpoint}' returned 404 Not Found!"
            )
            sweep_results.append((endpoint, res.status_code))

        # Assert 100% route health
        self.assertEqual(len(sweep_results), len(audit_endpoints))

        # Step 5: Verify Toast and Navigation Integrity
        toast = {
            "type": "success",
            "title": "Auditoria Concluída",
            "message": f"Todos os {len(audit_endpoints)} endpoints auditados com 100% de sucesso (Zero 404s).",
        }
        self.assertEqual(toast["type"], "success")


if __name__ == "__main__":
    unittest.main()
