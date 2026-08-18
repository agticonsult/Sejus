"""
Scenario 4: Interior Territorial Job Application in Linhares (F07, F19, F20, F21, F41, F43)
===========================================================================================
Target Profile: Marcos Vinícius (Egresso Residente em Linhares/ES - IBGE 3203205)

Complete End-to-End Operational Workflow:
1. Egresso resident in Linhares/ES logs in to the platform.
2. Activates Accessibility Toolbar: Simplified Language mode (Linguagem Fácil) and High Contrast.
3. Navigates to Opportunities View and filters affirmative action job vacancies for Linhares (IBGE 3203205).
4. Consults available professional training courses in the Rio Doce region.
5. Inspects Territorial Map for Linhares, viewing local SINE and CRAS contact details and address.
6. Submits job application for affirmative vacancy.
7. Verifies application confirmation and automatic event logged in Egresso's Prontuário timeline.
"""

import unittest
import json
import hashlib
import time
import base64
from typing import Dict, List, Any, Optional


class InteriorJobApplicationEngine:
    """
    Simulation backend for Linhares / Rio Doce Territorial Job Applications,
    Accessibility Settings, Support Network (CRAS/SINE) and Prontuário Timeline mutations.
    """
    def __init__(self):
        self.users: Dict[int, Dict[str, Any]] = {
            10955: {
                "id": 10955,
                "nome": "Marcos Vinícius dos Santos",
                "cpf_masked": "***.512.940-**",
                "municipio_ibge": "3203205",
                "municipio_nome": "Linhares",
                "bairro": "Interlagos",
                "perfil": "egresso",
                "prontuario_id": "PRON-2026-3203205-10955",
                "accessibility_settings": {
                    "high_contrast": False,
                    "font_scale": 1.0,
                    "simplified_language": False,
                }
            }
        }

        # Territorial Data: Linhares and Rio Doce Network
        self.territorio_db: Dict[str, Dict[str, Any]] = {
            "3203205": {
                "ibge": "3203205",
                "nome": "Linhares",
                "microregiao": "Rio Doce",
                "latitude": -19.3911,
                "longitude": -40.0722,
                "populacao_estimada": 176688,
                "demanda_egressos_mapeada": 1150,
                "possui_escritorio_social_fisico": False,
                "atendimento_remoto_habilitado": True,
                "rede_apoio": [
                    {
                        "tipo": "SINE",
                        "nome": "Agência SINE Linhares",
                        "endereco": "Av. Governador Lindemberg, 660 - Centro, Linhares - ES, 29900-020",
                        "telefone": "(27) 3371-3456",
                        "email": "sine.linhares@setades.es.gov.br",
                        "horario": "08:00 às 17:00",
                        "convenio_sejus": True,
                    },
                    {
                        "tipo": "CRAS",
                        "nome": "CRAS Interlagos",
                        "endereco": "Rua José Cândido Durão, 450 - Interlagos, Linhares - ES",
                        "telefone": "(27) 3372-2100",
                        "servicos": ["PAIF", "Acolhimento Familiar", "Cadastro Único"],
                    },
                    {
                        "tipo": "CRAS",
                        "nome": "CRAS Bebedouro",
                        "endereco": "Av. Benevenuto Rossi, 120 - Bebedouro, Linhares - ES",
                        "telefone": "(27) 3373-1520",
                        "servicos": ["PAIF", "Acolhimento Social"],
                    },
                    {
                        "tipo": "CREAS",
                        "nome": "CREAS Regional Rio Doce - Linhares",
                        "endereco": "Rua Rui Barbosa, 310 - Centro, Linhares - ES",
                        "telefone": "(27) 3372-6800",
                        "servicos": ["Acompanhamento Especializado", "Medidas em Meio Aberto"],
                    },
                ]
            }
        }

        # Jobs / Vagas Database (Linhares / ES)
        self.vagas_db: List[Dict[str, Any]] = [
            {
                "id": 401,
                "titulo": "Auxiliar de Operações Logísticas",
                "empresa_parceira": "Logística Rio Doce S/A (Conveniada SEJUS)",
                "municipio_ibge": "3203205",
                "municipio_nome": "Linhares",
                "bairro": "Polo Industrial Canivete",
                "regiao": "Rio Doce",
                "vaga_afirmativa": True,
                "reserva_lei_estadual": "Lei Estadual nº 10.987/2019 (Cotas de Reintegração)",
                "faixa_salarial": "R$ 1.850,00 + Benefícios",
                "escolaridade": "Ensino Fundamental Completo",
                "vagas_disponiveis": 4,
                "status": "ABERTA",
            },
            {
                "id": 402,
                "titulo": "Ajudante de Produção Industrial",
                "empresa_parceira": "Moveleira Linhares Indústria Ltda",
                "municipio_ibge": "3203205",
                "municipio_nome": "Linhares",
                "bairro": "Bebedouro",
                "regiao": "Rio Doce",
                "vaga_afirmativa": True,
                "reserva_lei_estadual": "Programa Recomeço SEJUS",
                "faixa_salarial": "R$ 1.720,00 + Vale Transporte",
                "escolaridade": "Não exigida",
                "vagas_disponiveis": 2,
                "status": "ABERTA",
            },
            {
                "id": 403,
                "titulo": "Operador de Máquinas Agrícolas",
                "empresa_parceira": "Agropecuária Norte Capixaba",
                "municipio_ibge": "3204906",
                "municipio_nome": "São Mateus",
                "regiao": "Rio Doce",
                "vaga_afirmativa": True,
                "reserva_lei_estadual": "Programa Recomeço SEJUS",
                "faixa_salarial": "R$ 2.400,00",
                "escolaridade": "Fundamental Completo + CNH C",
                "vagas_disponiveis": 1,
                "status": "ABERTA",
            }
        ]

        # Courses Database
        self.cursos_db: List[Dict[str, Any]] = [
            {
                "id": 801,
                "titulo": "Operador de Empilhadeira e Armazenagem",
                "instituicao": "SENAI Linhares / Parceria SEJUS Qualifica",
                "municipio_ibge": "3203205",
                "regiao": "Rio Doce",
                "carga_horaria": 160,
                "modalidade": "Presencial Noturno",
                "bolsa_auxilio": True,
                "vagas": 25,
            },
            {
                "id": 802,
                "titulo": "Instalações Elétricas Básicas e Manutenção Predial",
                "instituicao": "IFES Campus Linhares",
                "municipio_ibge": "3203205",
                "regiao": "Rio Doce",
                "carga_horaria": 120,
                "modalidade": "Semipresencial",
                "bolsa_auxilio": True,
                "vagas": 30,
            }
        ]

        # Prontuário Timeline DB
        self.prontuario_timelines: Dict[int, List[Dict[str, Any]]] = {
            10955: [
                {
                    "id": 1,
                    "tipo_evento": "ACOLHIMENTO_TERRITORIAL",
                    "descricao": "Vinculação ao atendimento remoto territorial do polo Rio Doce / Linhares.",
                    "data": "2026-08-12 09:00:00",
                    "imutavel": True,
                }
            ]
        }

        self.candidaturas: List[Dict[str, Any]] = []

    def login_egresso(self, user_id: int) -> Dict[str, Any]:
        """Simulates login for Linhares resident Egresso."""
        user = self.users.get(user_id)
        if not user:
            return {"status": "USER_NOT_FOUND"}

        return {
            "status": "AUTHENTICATED",
            "user": {
                "id": user["id"],
                "nome": user["nome"],
                "cpf_masked": user["cpf_masked"],
                "municipio": user["municipio_nome"],
                "ibge": user["municipio_ibge"],
                "bairro": user["bairro"],
                "perfil": user["perfil"],
                "accessibility": user["accessibility_settings"],
            }
        }

    def update_accessibility_toolbar(self, user_id: int,
                                     high_contrast: bool,
                                     simplified_language: bool,
                                     font_scale: float) -> Dict[str, Any]:
        """Updates and persists accessibility settings."""
        user = self.users[user_id]
        user["accessibility_settings"] = {
            "high_contrast": high_contrast,
            "simplified_language": simplified_language,
            "font_scale": font_scale,
        }
        return {
            "status": "SUCCESS",
            "accessibility_settings": user["accessibility_settings"],
            "classes_applied": [
                *(["high-contrast"] if high_contrast else []),
                *(["simplified-lang"] if simplified_language else []),
            ],
            "font_scale_css": f"{font_scale}",
        }

    def filter_vagas(self, municipio_ibge: Optional[str] = None, afirmativa_only: bool = True) -> List[Dict[str, Any]]:
        """Filters job vacancies by IBGE municipality code and affirmative action flag."""
        results = []
        for vaga in self.vagas_db:
            if afirmativa_only and not vaga.get("vaga_afirmativa"):
                continue
            if municipio_ibge and vaga.get("municipio_ibge") != municipio_ibge:
                continue
            results.append(vaga)
        return results

    def filter_cursos(self, regiao: Optional[str] = None, municipio_ibge: Optional[str] = None) -> List[Dict[str, Any]]:
        """Filters professional training courses by region or municipality."""
        results = []
        for c in self.cursos_db:
            if regiao and c.get("regiao").lower() != regiao.lower():
                continue
            if municipio_ibge and c.get("municipio_ibge") != municipio_ibge:
                continue
            results.append(c)
        return results

    def get_territorial_map_details(self, ibge: str) -> Dict[str, Any]:
        """Retrieves municipality details including support network (SINE/CRAS/CREAS)."""
        muni = self.territorio_db.get(ibge)
        if not muni:
            return {"status": "MUNICIPALITY_NOT_FOUND"}
        return {"status": "SUCCESS", "municipio": muni}

    def submit_job_application(self, egresso_id: int, vaga_id: int, curriculo_data: Dict[str, Any]) -> Dict[str, Any]:
        """Submits job application for affirmative vacancy and auto-records Prontuário timeline event."""
        user = self.users.get(egresso_id)
        vaga = next((v for v in self.vagas_db if v["id"] == vaga_id), None)
        if not user or not vaga:
            return {"status": "ERROR", "error": "INVALID_DATA"}

        protocolo = f"CAND-2026-LIN-{user['municipio_ibge']}-{vaga_id:04d}-{int(time.time())}"
        candidatura = {
            "protocolo": protocolo,
            "egresso_id": egresso_id,
            "vaga_id": vaga_id,
            "vaga_titulo": vaga["titulo"],
            "empresa": vaga["empresa_parceira"],
            "municipio": vaga["municipio_nome"],
            "data_inscricao": time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()),
            "status": "ENCAMINHADO_EMPRESA_PARCEIRA",
            "curriculo_simplificado": curriculo_data,
        }
        self.candidaturas.append(candidatura)

        # Automatically insert timeline event into Egresso's Prontuário Único
        timeline = self.prontuario_timelines.setdefault(egresso_id, [])
        timeline_event = {
            "id": len(timeline) + 1,
            "tipo_evento": "ENCAMINHAMENTO_VAGA_EMPREGO",
            "titulo": f"Candidatura à Vaga: {vaga['titulo']}",
            "descricao": f"Egresso candidatou-se à vaga afirmativa na empresa {vaga['empresa_parceira']} em {vaga['municipio_nome']}/ES. Protocolo: {protocolo}.",
            "protocolo": protocolo,
            "data": candidatura["data_inscricao"],
            "municipio_ibge": vaga["municipio_ibge"],
            "imutavel": True,
        }
        timeline.append(timeline_event)

        return {
            "status": "SUCCESS",
            "mensagem": "Candidatura submetida com sucesso ao parceiro SEJUS.",
            "candidatura": candidatura,
            "timeline_event_id": timeline_event["id"],
        }


def run_scenario_interior_job_application() -> Dict[str, Any]:
    """
    Executes Scenario 4 complete end-to-end user journey workflow.
    """
    engine = InteriorJobApplicationEngine()
    results = {}

    # Step 1: Egresso resident in Linhares/ES logs in
    login_res = engine.login_egresso(10955)
    results["step1_login"] = login_res
    assert login_res["status"] == "AUTHENTICATED"
    assert login_res["user"]["municipio"] == "Linhares"
    assert login_res["user"]["ibge"] == "3203205"

    # Step 2: Activates Accessibility Toolbar (Simplified Language + High Contrast)
    a11y_res = engine.update_accessibility_toolbar(
        user_id=10955,
        high_contrast=True,
        simplified_language=True,
        font_scale=1.18,
    )
    results["step2_accessibility"] = a11y_res
    assert a11y_res["status"] == "SUCCESS"
    assert "high-contrast" in a11y_res["classes_applied"]
    assert "simplified-lang" in a11y_res["classes_applied"]
    assert a11y_res["accessibility_settings"]["simplified_language"] is True

    # Step 3: Filters affirmative action job vacancies in Linhares (IBGE 3203205)
    vagas_linhares = engine.filter_vagas(municipio_ibge="3203205", afirmativa_only=True)
    results["step3_vagas"] = vagas_linhares
    assert len(vagas_linhares) >= 2
    assert all(v["municipio_ibge"] == "3203205" for v in vagas_linhares)
    assert all(v["vaga_afirmativa"] is True for v in vagas_linhares)

    # Step 4: Consults available training courses in Rio Doce region
    cursos_rio_doce = engine.filter_cursos(regiao="Rio Doce", municipio_ibge="3203205")
    results["step4_cursos"] = cursos_rio_doce
    assert len(cursos_rio_doce) >= 2
    assert any("SENAI" in c["instituicao"] for c in cursos_rio_doce)
    assert any("IFES" in c["instituicao"] for c in cursos_rio_doce)

    # Step 5: Inspects Territorial Map for Linhares (CRAS / SINE support network)
    territorio_res = engine.get_territorial_map_details(ibge="3203205")
    results["step5_territorio"] = territorio_res
    assert territorio_res["status"] == "SUCCESS"
    muni = territorio_res["municipio"]
    assert muni["nome"] == "Linhares"
    assert muni["possui_escritorio_social_fisico"] is False
    assert muni["atendimento_remoto_habilitado"] is True

    rede = muni["rede_apoio"]
    sine_entry = next((item for item in rede if item["tipo"] == "SINE"), None)
    cras_entry = next((item for item in rede if item["tipo"] == "CRAS"), None)
    assert sine_entry is not None
    assert cras_entry is not None
    assert "Governador Lindemberg" in sine_entry["endereco"]

    # Step 6: Submits job application for affirmative vacancy 401
    app_res = engine.submit_job_application(
        egresso_id=10955,
        vaga_id=401,
        curriculo_data={
            "experiencia": "Auxiliar de Carga e Descarga",
            "cursos_sejus": ["Qualificação Básica para o Trabalho"],
            "disponibilidade": "Imediata",
        }
    )
    results["step6_application"] = app_res
    assert app_res["status"] == "SUCCESS"
    assert "CAND-2026-LIN-3203205-0401" in app_res["candidatura"]["protocolo"]

    # Step 7: Verifies application event auto-logged in Egresso's Prontuário timeline
    timeline = engine.prontuario_timelines[10955]
    latest_event = timeline[-1]
    results["step7_prontuario_event"] = latest_event
    assert latest_event["tipo_evento"] == "ENCAMINHAMENTO_VAGA_EMPREGO"
    assert "CAND-2026-LIN-3203205-0401" in latest_event["protocolo"]
    assert latest_event["municipio_ibge"] == "3203205"
    assert latest_event["imutavel"] is True

    return {"status": "SUCCESS", "scenario": "Interior Territorial Job Application in Linhares", "details": results}


class TestScenarioInteriorJobApplication(unittest.TestCase):
    """
    TestCase class for Scenario 4.
    """
    def setUp(self):
        self.engine = InteriorJobApplicationEngine()

    def test_complete_interior_job_application_workflow(self):
        """Executes full Scenario 4 user journey."""
        res = run_scenario_interior_job_application()
        self.assertEqual(res["status"], "SUCCESS")

    def test_accessibility_toolbar_configuration(self):
        """Verifies high contrast and simplified language activation."""
        res = self.engine.update_accessibility_toolbar(10955, True, True, 1.18)
        self.assertEqual(res["status"], "SUCCESS")
        self.assertIn("high-contrast", res["classes_applied"])
        self.assertIn("simplified-lang", res["classes_applied"])
        self.assertEqual(res["font_scale_css"], "1.18")

    def test_vagas_and_cursos_territorial_filtering(self):
        """Verifies filtering of affirmative action jobs in Linhares."""
        vagas = self.engine.filter_vagas(municipio_ibge="3203205", afirmativa_only=True)
        self.assertEqual(len(vagas), 2)
        for v in vagas:
            self.assertEqual(v["municipio_ibge"], "3203205")
            self.assertTrue(v["vaga_afirmativa"])

        cursos = self.engine.filter_cursos(regiao="Rio Doce")
        self.assertGreaterEqual(len(cursos), 2)

    def test_territorial_support_network_inspection(self):
        """Verifies Linhares support network (SINE, CRAS, CREAS)."""
        res = self.engine.get_territorial_map_details("3203205")
        self.assertEqual(res["status"], "SUCCESS")
        muni = res["municipio"]
        self.assertEqual(muni["microregiao"], "Rio Doce")
        self.assertFalse(muni["possui_escritorio_social_fisico"])
        self.assertEqual(len(muni["rede_apoio"]), 4)

    def test_application_submission_and_timeline_mutation(self):
        """Verifies application submission creates an immutable Prontuário timeline entry."""
        app = self.engine.submit_job_application(10955, 401, {"exp": "Logística"})
        self.assertEqual(app["status"], "SUCCESS")

        timeline = self.engine.prontuario_timelines[10955]
        self.assertEqual(timeline[-1]["tipo_evento"], "ENCAMINHAMENTO_VAGA_EMPREGO")
        self.assertTrue(timeline[-1]["imutavel"])


if __name__ == "__main__":
    unittest.main()
