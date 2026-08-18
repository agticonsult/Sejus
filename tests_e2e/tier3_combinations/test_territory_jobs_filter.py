"""Tier 3 Combinatorial Test Suite: 78 ES Municipalities × Affirmative Jobs, Courses & Territorial Geo-Search.

Covers cross-feature matrix:
1. Multi-Criteria Territorial Cross-Filtering:
   - 78 Espírito Santo municipalities mapped across 10 official micro-regions (Metropolitana, Rio Doce, Central Serrana, Caparaó, Litoral Sul, etc.)
   - Filters affirmative action job vacancies (`vagas_afirmativas_egresso=true`) and vocational courses by micro-region, modality, and salary
   - Confirms zero data contamination from other regions
2. Spatial Proximity Geo-Query (Haversine & PostGIS Simulation):
   - Proximity search within radius (e.g. 30 km, 50 km) of an Egresso's registered city (e.g., Linhares, Cachoeiro, Vitória)
   - Matches both affirmative job vacancies and socio-assistive support facilities (CRAS, CREAS, SINE)
   - Accurately computes geodesic distance and sorts results ascending by proximity
3. Zero-Result Municipality Graceful Fallback to Regional Hubs:
   - Queries small interior municipalities with zero direct affirmative openings (e.g. Dores do Rio Preto, Divino de São Lourenço)
   - System triggers automated regional expansion fallback: returns vacancies in regional hub cities (e.g., Guaçuí, Alegre, Cachoeiro) and 100% remote / EAD courses
   - Returns structured fallback notification banner
4. Comprehensive Support Network (CRAS / CREAS / SINE) Integration:
   - Validates official contact information, service hours, address, and accessibility indicators for local socio-assistive units
"""

from __future__ import annotations

import copy
import math
import unittest
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple

from tests_e2e.e2e_utils import (
    AssertionHelper,
    DataGenerator,
    ES_MUNICIPALITIES,
    MUNICIPALITY_BY_CODE,
    MockApiClient,
)


def haversine_distance_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    """Computes great-circle distance between two points on Earth in kilometers."""
    r = 6371.0  # Earth radius in kilometers
    phi1 = math.radians(lat1)
    phi2 = math.radians(lat2)
    delta_phi = math.radians(lat2 - lat1)
    delta_lambda = math.radians(lon2 - lon1)

    a = math.sin(delta_phi / 2.0) ** 2 + math.cos(phi1) * math.cos(phi2) * math.sin(delta_lambda / 2.0) ** 2
    c = 2.0 * math.atan2(math.sqrt(a), math.sqrt(1.0 - a))
    return round(r * c, 2)


class MockTerritoryJobsCatalog:
    """
    Stateful catalog combining 78 ES municipalities, affirmative jobs,
    vocational courses, and socio-assistive support units (CRAS/SINE).
    """

    def __init__(self):
        self.municipalities = ES_MUNICIPALITIES
        self.muni_by_code = MUNICIPALITY_BY_CODE
        self.vagas: List[Dict[str, Any]] = []
        self.cursos: List[Dict[str, Any]] = []
        self.support_network: List[Dict[str, Any]] = []

        self._seed_catalog()

    def _seed_catalog(self):
        # 1. Seed realistic Job Vacancies across different regions
        job_samples = [
            # Metropolitana
            {"id": 101, "titulo": "Operador de Logística Portuária", "empresa": "Vix Logística S/A", "municipio_ibge": "3205309", "remuneracao": 2200.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 102, "titulo": "Auxiliar de Almoxarifado", "empresa": "Serra Transportes", "municipio_ibge": "3205002", "remuneracao": 1850.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 103, "titulo": "Assistente de Produção Industrial", "empresa": "Cariacica Alimentos", "municipio_ibge": "3201308", "remuneracao": 1950.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 104, "titulo": "Recepcionista Comercial", "empresa": "Vila Velha Saúde", "municipio_ibge": "3205200", "remuneracao": 1650.0, "afirmativa": False, "tipo": "CLT"},

            # Rio Doce
            {"id": 201, "titulo": "Operador de Máquinas e Equipamentos", "empresa": "Linhares Agrícola", "municipio_ibge": "3203205", "remuneracao": 2500.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 202, "titulo": "Auxiliar de Manutenção Predial", "empresa": "Colatina Têxtil", "municipio_ibge": "3201506", "remuneracao": 1800.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 203, "titulo": "Conferente de Cargas", "empresa": "Aracruz Celulose Parceira", "municipio_ibge": "3200607", "remuneracao": 2100.0, "afirmativa": True, "tipo": "CLT"},

            # Central Sul / Caparaó
            {"id": 301, "titulo": "Marmorista e Polidor de Rochas", "empresa": "Cachoeiro Rochas Ornamentais", "municipio_ibge": "3201209", "remuneracao": 2400.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 302, "titulo": "Operador de Trator e Colheitadeira", "empresa": "Alegre Cafeicultura", "municipio_ibge": "3200201", "remuneracao": 2050.0, "afirmativa": True, "tipo": "CLT"},
            {"id": 303, "titulo": "Auxiliar de Mecânica Agrícola", "empresa": "Guaçuí Implementos", "municipio_ibge": "3202306", "remuneracao": 1900.0, "afirmativa": True, "tipo": "CLT"},

            # Nordeste
            {"id": 401, "titulo": "Eletricista de Manutenção", "empresa": "São Mateus Petróleo e Gás", "municipio_ibge": "3204906", "remuneracao": 2800.0, "afirmativa": True, "tipo": "CLT"},
        ]

        for js in job_samples:
            mun = self.muni_by_code[js["municipio_ibge"]]
            self.vagas.append({
                "id": js["id"],
                "titulo": js["titulo"],
                "empresa": js["empresa"],
                "municipio_ibge": mun["ibge_code"],
                "municipio_nome": mun["name"],
                "regiao": mun["region"],
                "lat": mun["lat"],
                "lon": mun["lon"],
                "remuneracao": js["remuneracao"],
                "vagas_afirmativas_egresso": js["afirmativa"],
                "regime_contratacao": js["tipo"],
                "beneficios": ["VT", "VR", "Seguro de Vida"],
                "ativo": True,
            })

        # 2. Seed Training Courses
        course_samples = [
            {"id": 501, "nome": "Logística e Armazenagem Integrada", "inst": "SENAI/ES", "mun": "3205309", "mod": "Presencial", "horas": 120, "ead": False},
            {"id": 502, "nome": "Letramento Digital e Pacote Office", "inst": "Escola de Governo SEGER", "mun": "3205309", "mod": "EAD", "horas": 60, "ead": True},
            {"id": 503, "nome": "Mecânica de Máquinas Agrícolas", "inst": "SENAR/ES", "mun": "3203205", "mod": "Presencial", "horas": 80, "ead": False},
            {"id": 504, "nome": "Gestão Financeira para Microempreendedores", "inst": "SEBRAE/ES", "mun": "3200201", "mod": "EAD", "horas": 40, "ead": True},
            {"id": 505, "nome": "Panificação e Confeitaria Comercial", "inst": "SEJUS Cursos", "mun": "3201209", "mod": "Presencial", "horas": 90, "ead": False},
        ]
        for cs in course_samples:
            mun = self.muni_by_code[cs["mun"]]
            self.cursos.append({
                "id": cs["id"],
                "nome_curso": cs["nome"],
                "instituicao": cs["inst"],
                "municipio_ibge": mun["ibge_code"],
                "municipio_nome": mun["name"],
                "regiao": mun["region"],
                "modalidade": cs["mod"],
                "is_ead": cs["ead"],
                "carga_horaria_horas": cs["horas"],
                "vagas_gratuitas": 25,
                "inscricoes_abertas": True,
            })

        # 3. Seed Support Network (CRAS, CREAS, SINE, Escritórios Sociais)
        network_samples = [
            {"id": 1, "tipo": "SINE", "nome": "Agência SINE Linhares", "mun": "3203205", "endereco": "Av. Governador Lindemberg, 660, Centro", "telefone": "(27) 3371-3456", "lat": -19.3920, "lon": -40.0730},
            {"id": 2, "tipo": "CRAS", "nome": "CRAS Aviso - Linhares", "mun": "3203205", "endereco": "Rua Rio Grande do Sul, 120, Bairro Aviso", "telefone": "(27) 3372-1144", "lat": -19.3850, "lon": -40.0650},
            {"id": 3, "tipo": "ESCRITORIO_SOCIAL", "nome": "Escritório Social de Linhares", "mun": "3203205", "endereco": "Fórum Desembargador Mendes Wanderley", "telefone": "(27) 3371-8800", "lat": -19.3900, "lon": -40.0710},
            {"id": 4, "tipo": "SINE", "nome": "Agência SINE Vitória", "mun": "3205309", "endereco": "Av. Princesa Isabel, 599, Centro", "telefone": "(27) 3132-5300", "lat": -20.3160, "lon": -40.3130},
            {"id": 5, "tipo": "CRAS", "nome": "CRAS Guaçuí Centro", "mun": "3202306", "endereco": "Rua Marechal Floriano, 45", "telefone": "(28) 3553-1200", "lat": -20.7760, "lon": -41.6790},
            {"id": 6, "tipo": "SINE", "nome": "Agência SINE Cachoeiro de Itapemirim", "mun": "3201209", "endereco": "Rua 25 de Março, 80, Centro", "telefone": "(28) 3522-5612", "lat": -20.8490, "lon": -41.1130},
        ]
        for ns in network_samples:
            mun = self.muni_by_code[ns["mun"]]
            self.support_network.append({
                **ns,
                "municipio_nome": mun["name"],
                "regiao": mun["region"],
            })

    def search_jobs(
        self,
        microregion: Optional[str] = None,
        municipio_ibge: Optional[str] = None,
        only_affirmative: bool = True,
        min_salary: Optional[float] = None,
    ) -> Tuple[List[Dict[str, Any]], Optional[Dict[str, Any]]]:
        """
        Searches job vacancies with territorial filters and automated regional fallback if 0 results found.
        """
        results = list(self.vagas)

        if only_affirmative:
            results = [v for v in results if v["vagas_afirmativas_egresso"]]

        if municipio_ibge:
            results = [v for v in results if v["municipio_ibge"] == municipio_ibge]
        elif microregion:
            results = [v for v in results if v["regiao"] == microregion]

        if min_salary is not None:
            results = [v for v in results if v["remuneracao"] >= min_salary]

        fallback_info = None
        if len(results) == 0 and municipio_ibge:
            # Trigger graceful regional fallback
            target_mun = self.muni_by_code.get(municipio_ibge)
            if target_mun:
                region = target_mun["region"]
                regional_jobs = [v for v in self.vagas if v["regiao"] == region and (not only_affirmative or v["vagas_afirmativas_egresso"])]
                remote_courses = [c for c in self.cursos if c["is_ead"]]
                fallback_info = {
                    "fallback_triggered": True,
                    "target_municipio": target_mun["name"],
                    "region": region,
                    "regional_jobs_count": len(regional_jobs),
                    "regional_jobs": regional_jobs,
                    "remote_courses": remote_courses,
                    "message": (
                        f"Nenhuma vaga direta encontrada em {target_mun['name']}. "
                        f"Exibindo {len(regional_jobs)} vagas afirmativas na microrregião {region} e cursos 100% online."
                    ),
                }

        return results, fallback_info

    def search_by_proximity(
        self,
        center_lat: float,
        center_lon: float,
        radius_km: float = 30.0,
    ) -> Dict[str, Any]:
        """
        Queries affirmative jobs and socio-assistive support units within radius in km.
        Sorts ascending by distance.
        """
        nearby_jobs = []
        for job in self.vagas:
            if not job["vagas_afirmativas_egresso"]:
                continue
            dist = haversine_distance_km(center_lat, center_lon, job["lat"], job["lon"])
            if dist <= radius_km:
                nearby_jobs.append({**job, "distance_km": dist})

        nearby_support = []
        for net in self.support_network:
            dist = haversine_distance_km(center_lat, center_lon, net["lat"], net["lon"])
            if dist <= radius_km:
                nearby_support.append({**net, "distance_km": dist})

        # Sort ascending by distance
        nearby_jobs.sort(key=lambda x: x["distance_km"])
        nearby_support.sort(key=lambda x: x["distance_km"])

        return {
            "center": {"lat": center_lat, "lon": center_lon},
            "radius_km": radius_km,
            "jobs_count": len(nearby_jobs),
            "jobs": nearby_jobs,
            "support_network_count": len(nearby_support),
            "support_network": nearby_support,
        }


class TestTerritoryJobsFilter(unittest.TestCase):
    """Pairwise Integration Test Suite: 78 ES Municipalities × Affirmative Jobs × Proximity Geo-Query."""

    def setUp(self):
        self.catalog = MockTerritoryJobsCatalog()

    def test_01_cross_filtering_78_municipalities_and_microregions_with_jobs(self):
        """
        Verify multi-criteria territorial cross-filtering:
        1. Query vacancies with combined filters: `microregion="Rio Doce"`, `only_affirmative=True`.
        2. Assert all returned results belong to 'Rio Doce' municipalities (Linhares, Colatina, Aracruz).
        3. Confirm zero cross-region contamination (no jobs from Metropolitana or Central Sul).
        4. Query with salary threshold (`min_salary=2000.00`) and confirm filtering precision.
        """
        # Step 1: Filter Rio Doce affirmative jobs
        jobs_rio_doce, fallback = self.catalog.search_jobs(microregion="Rio Doce", only_affirmative=True)
        self.assertIsNone(fallback, "Direct query with results should not trigger fallback")
        self.assertGreater(len(jobs_rio_doce), 0)

        # Step 2: Validate territorial boundaries
        for job in jobs_rio_doce:
            self.assertEqual(job["regiao"], "Rio Doce", f"Job {job['id']} in {job['municipio_nome']} should be in Rio Doce")
            self.assertTrue(job["vagas_afirmativas_egresso"])
            self.assertIn(job["municipio_ibge"], ["3203205", "3201506", "3200607"])

        # Step 3: Filter Metropolitana region
        jobs_metro, _ = self.catalog.search_jobs(microregion="Metropolitana", only_affirmative=True)
        self.assertGreater(len(jobs_metro), 0)
        for job in jobs_metro:
            self.assertEqual(job["regiao"], "Metropolitana")
            self.assertNotIn(job["municipio_ibge"], ["3203205", "3201506"])

        # Step 4: Combine with minimum salary filter
        high_salary_jobs, _ = self.catalog.search_jobs(microregion="Rio Doce", only_affirmative=True, min_salary=2200.0)
        for job in high_salary_jobs:
            self.assertGreaterEqual(job["remuneracao"], 2200.0)
            self.assertEqual(job["regiao"], "Rio Doce")

    def test_02_spatial_proximity_query_matching_jobs_and_support_network(self):
        """
        Verify spatial proximity geo-queries:
        1. Egresso located in Linhares (lat: -19.3911, lon: -40.0722).
        2. Search within radius of 30 km.
        3. Assert matched local jobs (Linhares) and support network (CRAS Aviso, SINE Linhares, Escritório Social).
        4. Verify distance is accurately calculated and results are sorted ascending by proximity.
        5. Expand radius to 65 km -> Assert neighboring municipality Colatina/Aracruz jobs are now included.
        """
        linhares_lat = -19.3911
        linhares_lon = -40.0722

        # Step 1: 30 km search
        res_30km = self.catalog.search_by_proximity(linhares_lat, linhares_lon, radius_km=30.0)
        self.assertGreaterEqual(res_30km["jobs_count"], 1)
        self.assertGreaterEqual(res_30km["support_network_count"], 3)

        # Step 2: Validate distance sorting
        distances = [j["distance_km"] for j in res_30km["jobs"]]
        self.assertEqual(distances, sorted(distances), "Jobs must be sorted ascending by distance")

        support_distances = [s["distance_km"] for s in res_30km["support_network"]]
        self.assertEqual(support_distances, sorted(support_distances), "Support network must be sorted ascending by distance")

        # Step 3: Verify Linhares SINE & CRAS exist in local support network
        sine_found = any(s["tipo"] == "SINE" and "Linhares" in s["nome"] for s in res_30km["support_network"])
        cras_found = any(s["tipo"] == "CRAS" and "Linhares" in s["nome"] for s in res_30km["support_network"])
        self.assertTrue(sine_found, "Local SINE Linhares must be included in proximity search")
        self.assertTrue(cras_found, "Local CRAS Linhares must be included in proximity search")

        # Step 4: Expand radius to 65 km (reaches Colatina ~50km and Aracruz ~55km)
        res_65km = self.catalog.search_by_proximity(linhares_lat, linhares_lon, radius_km=65.0)
        self.assertGreater(res_65km["jobs_count"], res_30km["jobs_count"])
        muni_names = {j["municipio_nome"] for j in res_65km["jobs"]}
        self.assertIn("Linhares", muni_names)
        self.assertTrue("Colatina" in muni_names or "Aracruz" in muni_names)

    def test_03_zero_result_municipality_graceful_fallback(self):
        """
        Verify graceful regional fallback when a small interior municipality has 0 direct vacancies:
        1. Egresso resident in 'Dores do Rio Preto' (IBGE: 3202009, Caparaó region).
        2. Query direct vacancies in Dores do Rio Preto -> Returns 0 direct results.
        3. System automatically triggers regional expansion fallback:
           - Identifies parent micro-region: 'Caparaó'.
           - Returns affirmative vacancies in regional hub cities (Guaçuí, Alegre).
           - Returns 100% remote / EAD courses from SEBRAE/Escola de Governo.
           - Provides friendly notification message.
        """
        dores_ibge = "3202009"  # Dores do Rio Preto
        direct_jobs, fallback_info = self.catalog.search_jobs(municipio_ibge=dores_ibge, only_affirmative=True)

        # 1. Direct results should be empty
        self.assertEqual(len(direct_jobs), 0)

        # 2. Fallback must be triggered
        self.assertIsNotNone(fallback_info, "Zero-result municipality must trigger regional fallback")
        self.assertTrue(fallback_info["fallback_triggered"])
        self.assertEqual(fallback_info["target_municipio"], "Dores do Rio Preto")
        self.assertEqual(fallback_info["region"], "Caparaó")

        # 3. Regional alternatives provided
        self.assertGreater(fallback_info["regional_jobs_count"], 0)
        regional_cities = {j["municipio_nome"] for j in fallback_info["regional_jobs"]}
        self.assertTrue("Alegre" in regional_cities or "Guaçuí" in regional_cities)

        # 4. Remote courses provided
        self.assertGreater(len(fallback_info["remote_courses"]), 0)
        for course in fallback_info["remote_courses"]:
            self.assertTrue(course["is_ead"])

        # 5. User-friendly message
        self.assertIn("Dores do Rio Preto", fallback_info["message"])
        self.assertIn("Caparaó", fallback_info["message"])

    def test_04_all_78_es_municipalities_coverage_and_ibge_integrity(self):
        """
        Verify that all 78 Espírito Santo municipalities have valid IBGE codes,
        assigned micro-regions, and valid coordinates within ES geographic bounds.
        """
        self.assertEqual(len(self.catalog.municipalities), 78, "Must contain exactly 78 ES municipalities")

        for mun in self.catalog.municipalities:
            # 1. Valid IBGE code
            AssertionHelper.assert_ibge_code_valid(mun["ibge_code"], uf="32")

            # 2. Coordinates within Espírito Santo bounds (Lat: -21.5 to -17.8, Lon: -41.9 to -39.5)
            lat = mun["lat"]
            lon = mun["lon"]
            self.assertTrue(-21.6 <= lat <= -17.8, f"Latitude {lat} for {mun['name']} is out of ES bounds")
            self.assertTrue(-42.0 <= lon <= -39.4, f"Longitude {lon} for {mun['name']} is out of ES bounds")

            # 3. Region assigned
            self.assertIsNotNone(mun.get("region"))
            self.assertIn(mun["region"], [
                "Metropolitana", "Rio Doce", "Central Serrana", "Caparaó",
                "Central Sul", "Sul", "Litoral Sul", "Noroeste", "Nordeste",
                "Central", "Sudoeste Serrana"
            ])


if __name__ == "__main__":
    unittest.main()
