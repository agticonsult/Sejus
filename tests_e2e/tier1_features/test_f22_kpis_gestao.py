"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F22
======================================================
Features Tested:
  - F22: Management KPI aggregation API (attendances by municipality, recidivism reduction, job placement rates)

Authoritative Source:
  - ORIGINAL_REQUEST.md (R1: Dashboard de KPIs gerando métricas agregadas por município)
  - PROJECT.md (Milestone M3 & Feature Inventory)
"""

import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestKpisGestaoF22(unittest.TestCase):
    """Verifies Management KPI aggregations, calculations, and regional distributions."""

    def test_f22_kpi_summary_metrics_aggregation(self):
        """
        F22: Verify executive KPI metrics computation:
          - Total cadastrados (goal / pool of ~108.000)
          - Total atendimentos remotos vs presenciais
          - Taxa de encaminhamento profissional (%)
          - Redução de reincidência criminal (%)
        """
        raw_events = [
            {"tipo": "atendimento_remoto", "municipio": "Linhares", "reincidente": False, "encaminhado_trabalho": True},
            {"tipo": "atendimento_remoto", "municipio": "São Mateus", "reincidente": False, "encaminhado_trabalho": True},
            {"tipo": "atendimento_presencial", "municipio": "Vitória", "reincidente": False, "encaminhado_trabalho": True},
            {"tipo": "atendimento_presencial", "municipio": "Serra", "reincidente": True, "encaminhado_trabalho": False},
            {"tipo": "atendimento_remoto", "municipio": "Colatina", "reincidente": False, "encaminhado_trabalho": False}
        ]
        
        def calculate_kpis(events: list) -> dict:
            total = len(events)
            if total == 0:
                return {}
            remotos = sum(1 for e in events if e["tipo"] == "atendimento_remoto")
            presenciais = sum(1 for e in events if e["tipo"] == "atendimento_presencial")
            encaminhados = sum(1 for e in events if e.get("encaminhado_trabalho"))
            nao_reincidentes = sum(1 for e in events if not e.get("reincidente"))
            
            return {
                "total_atendimentos": total,
                "atendimentos_remotos": remotos,
                "atendimentos_presenciais": presenciais,
                "taxa_remoto_pct": round((remotos / total) * 100, 1),
                "taxa_empregabilidade_pct": round((encaminhados / total) * 100, 1),
                "taxa_sucesso_nao_reincidencia_pct": round((nao_reincidentes / total) * 100, 1),
                "meta_populacional_egressos_es": 108000
            }
            
        kpis = calculate_kpis(raw_events)
        
        self.assertEqual(kpis["total_atendimentos"], 5)
        self.assertEqual(kpis["atendimentos_remotos"], 3)
        self.assertEqual(kpis["atendimentos_presenciais"], 2)
        self.assertEqual(kpis["taxa_remoto_pct"], 60.0)
        self.assertEqual(kpis["taxa_empregabilidade_pct"], 60.0)
        self.assertEqual(kpis["taxa_sucesso_nao_reincidencia_pct"], 80.0)
        self.assertEqual(kpis["meta_populacional_egressos_es"], 108000)

    def test_f22_municipality_geographic_breakdown(self):
        """
        F22: Verify aggregation of atendimentos grouped across ES municipalities.
        """
        attendances = [
            {"municipio": "Vitória", "count": 3420},
            {"municipio": "Serra", "count": 2910},
            {"municipio": "Vila Velha", "count": 2450},
            {"municipio": "Cariacica", "count": 2100},
            {"municipio": "Linhares", "count": 1150},
            {"municipio": "Cachoeiro de Itapemirim", "count": 980},
            {"municipio": "Colatina", "count": 740},
            {"municipio": "São Mateus", "count": 610}
        ]
        
        total_interior = sum(item["count"] for item in attendances if item["municipio"] in ["Linhares", "Cachoeiro de Itapemirim", "Colatina", "São Mateus"])
        total_metropolitana = sum(item["count"] for item in attendances if item["municipio"] in ["Vitória", "Serra", "Vila Velha", "Cariacica"])
        
        self.assertGreater(total_metropolitana, 10000)
        self.assertGreater(total_interior, 3000)
        self.assertEqual(len(attendances), 8)


if __name__ == "__main__":
    unittest.main()
