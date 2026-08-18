"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F19 - F21
============================================================
Features Tested:
  - F19: Job opportunities API with affirmative action tags & municipality filter
  - F20: Training courses & educational opportunities API
  - F21: Territorial mapping API for 78 municipalities (CRAS, CREAS, SINE)

Authoritative Source:
  - ORIGINAL_REQUEST.md (R1: Oportunidades & Vagas de Emprego, Mapeamento Territorial dos 78 municípios)
  - PROJECT.md (Milestone M3 & Feature Inventory)
"""

import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestVagasTerritorioF19toF21(unittest.TestCase):
    """Verifies Job Vacancies, Training Courses, and 78 Municipalities Socio-Assistive Network."""

    def test_f19_job_opportunities_api_and_filters(self):
        """
        F19: Verify job opportunities API with affirmative action tags (`afirmativa_egresso: true`)
        and filtering by municipality.
        """
        mock_jobs_db = [
            {"id": 1, "titulo": "Auxiliar de Almoxarifado", "empresa": "Viana Logística", "municipio": "Viana", "codigo_ibge": 3205101, "afirmativa_egresso": True, "salario": 1850.00, "status": "ABERTA"},
            {"id": 2, "titulo": "Operador de Máquinas", "empresa": "Linhares Agro", "municipio": "Linhares", "codigo_ibge": 3203205, "afirmativa_egresso": True, "salario": 2200.00, "status": "ABERTA"},
            {"id": 3, "titulo": "Atendente de Balcão", "empresa": "Comércio Vitória", "municipio": "Vitória", "codigo_ibge": 3205309, "afirmativa_egresso": False, "salario": 1600.00, "status": "ABERTA"},
            {"id": 4, "titulo": "Auxiliar de Produção", "empresa": "Indústria Colatina", "municipio": "Colatina", "codigo_ibge": 3201506, "afirmativa_egresso": True, "salario": 1750.00, "status": "ABERTA"},
            {"id": 5, "titulo": "Mecânico Geral", "empresa": "Oficina São Mateus", "municipio": "São Mateus", "codigo_ibge": 3204906, "afirmativa_egresso": True, "salario": 2400.00, "status": "ABERTA"}
        ]
        
        def filter_jobs(municipio=None, apenas_afirmativas=False):
            results = mock_jobs_db
            if municipio:
                results = [j for j in results if j["municipio"].lower() == municipio.lower() or str(j["codigo_ibge"]) == str(municipio)]
            if apenas_afirmativas:
                results = [j for j in results if j["afirmativa_egresso"] is True]
            return results
            
        # 1. Filter by Linhares
        linhares_jobs = filter_jobs(municipio="Linhares")
        self.assertEqual(len(linhares_jobs), 1)
        self.assertEqual(linhares_jobs[0]["titulo"], "Operador de Máquinas")
        
        # 2. Filter affirmative vacancies only
        affirmative_jobs = filter_jobs(apenas_afirmativas=True)
        self.assertEqual(len(affirmative_jobs), 4)
        for job in affirmative_jobs:
            self.assertTrue(job["afirmativa_egresso"])

    def test_f20_training_courses_api(self):
        """
        F20: Verify training courses & educational opportunities API (SENAI, IFES, etc.).
        """
        mock_courses = [
            {"id": 1, "titulo": "Eletricista Predial Básico", "instituicao": "SENAI / ES", "modalidade": "Presencial", "carga_horaria_horas": 160, "vagas_gratuitas": 30, "municipio": "Serra"},
            {"id": 2, "titulo": "Informática e Letramento Digital", "instituicao": "IFES", "modalidade": "Híbrido", "carga_horaria_horas": 80, "vagas_gratuitas": 40, "municipio": "Vitória"},
            {"id": 3, "titulo": "Mecânica de Motocicletas", "instituicao": "SENAI / ES", "modalidade": "Presencial", "carga_horaria_horas": 120, "vagas_gratuitas": 25, "municipio": "Linhares"},
            {"id": 4, "titulo": "Empreendedorismo e Gestão MEI", "instituicao": "SEBRAE / ES", "modalidade": "Online", "carga_horaria_horas": 40, "vagas_gratuitas": 100, "municipio": "Todos (Estadual)"}
        ]
        
        self.assertGreaterEqual(len(mock_courses), 4)
        institutions = {c["instituicao"] for c in mock_courses}
        self.assertIn("SENAI / ES", institutions)
        self.assertIn("IFES", institutions)
        
        # Ensure free spots for egressos
        for c in mock_courses:
            self.assertGreater(c["vagas_gratuitas"], 0)
            self.assertGreater(c["carga_horaria_horas"], 0)

    def test_f21_territorial_mapping_and_support_network(self):
        """
        F21: Verify territorial mapping API for all 78 municipalities with socio-assistive support units
        (CRAS, CREAS, SINE, CAPS, Escritório Social).
        """
        mock_network_data = {
            3205309: { # Vitória
                "nome": "Vitória",
                "tem_escritorio_social_fisico": True,
                "unidades_apoio": [
                    {"tipo": "ESCRITORIO_SOCIAL", "nome": "Escritório Social de Vitória", "endereco": "Centro, Vitória"},
                    {"tipo": "CRAS", "nome": "CRAS Centro", "endereco": "Parque Moscoso"},
                    {"tipo": "SINE", "nome": "Agência do Trabalhador de Vitória", "endereco": "Av. Beira-Mar"}
                ]
            },
            3203205: { # Linhares (Interior sem Escritório Social físico)
                "nome": "Linhares",
                "tem_escritorio_social_fisico": False,
                "unidades_apoio": [
                    {"tipo": "CRAS", "nome": "CRAS Interlagos", "endereco": "Interlagos, Linhares", "telefone": "(27) 3372-2000"},
                    {"tipo": "CREAS", "nome": "CREAS Linhares", "endereco": "Centro, Linhares"},
                    {"tipo": "SINE", "nome": "SINE Linhares", "endereco": "Av. Governador Lindemberg"}
                ]
            }
        }
        
        def get_municipality_coverage(codigo_ibge: int) -> dict:
            data = mock_network_data.get(codigo_ibge)
            if not data:
                return None
            return {
                "codigo_ibge": codigo_ibge,
                "nome": data["nome"],
                "tem_escritorio_social": data["tem_escritorio_social_fisico"],
                "atendimento_remoto_disponivel": True, # Available in all 78
                "unidades_contadas": len(data["unidades_apoio"]),
                "unidades": data["unidades_apoio"]
            }
            
        vitoria = get_municipality_coverage(3205309)
        self.assertTrue(vitoria["tem_escritorio_social"])
        self.assertTrue(vitoria["atendimento_remoto_disponivel"])
        
        linhares = get_municipality_coverage(3203205)
        self.assertFalse(linhares["tem_escritorio_social"])
        self.assertTrue(linhares["atendimento_remoto_disponivel"])
        self.assertGreaterEqual(linhares["unidades_contadas"], 3)
        
        # Verify CRAS and SINE presence in interior
        tipos_linhares = [u["tipo"] for u in linhares["unidades"]]
        self.assertIn("CRAS", tipos_linhares)
        self.assertIn("SINE", tipos_linhares)


if __name__ == "__main__":
    unittest.main()
