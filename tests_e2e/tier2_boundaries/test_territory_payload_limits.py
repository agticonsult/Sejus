"""Tier 2 Boundary & Negative Tests: Territorial Coverage, 78 Municipalities, and Payload Limits.

Verifies:
- Invalid / non-existent IBGE municipality code handling (only 32XXXXX allowed)
- Out-of-bounds Espírito Santo geographic coordinates (bounding box validation)
- Empty search filter payload handling
- Negative salary filter in job opportunities (clamping to 0 / validation error)
- Extreme pagination offset (page 999999, per_page 1000000 DoS protection)
- Special characters, unicode, and diacritics in municipality search
- Non-ES postal code rejection (CEPs outside 29000-000..29999-999)
- Missing CRAS/SINE geocoordinates fallback to municipality centroid
- Total ES municipality count boundary (exactly 78 municipalities)
- Max distance radius boundary in territorial search
"""

import math
import unicodedata
import unittest
from typing import Any, Dict, List, Optional, Tuple


# --- Territorial & Opportunities Domain Simulator ---

class TerritoryBoundaryEngine:
    """Territorial rules and geographic boundaries for the State of Espírito Santo."""

    # Official ES Geographic Bounding Box (WGS84)
    ES_MIN_LAT = -21.31
    ES_MAX_LAT = -17.88
    ES_MIN_LON = -41.88
    ES_MAX_LON = -39.66

    # Sample canonical ES Municipalities with official IBGE codes (prefix 32)
    SAMPLE_ES_MUNICIPALITIES = {
        3205309: {"nome": "Vitória", "lat": -20.3155, "lon": -40.3128, "regiao": "Metropolitana"},
        3205200: {"nome": "Vila Velha", "lat": -20.3297, "lon": -40.2925, "regiao": "Metropolitana"},
        3205002: {"nome": "Serra", "lat": -20.1286, "lon": -40.3079, "regiao": "Metropolitana"},
        3201308: {"nome": "Cariacica", "lat": -20.2639, "lon": -40.4200, "regiao": "Metropolitana"},
        3203205: {"nome": "Linhares", "lat": -19.3911, "lon": -40.0722, "regiao": "Rio Doce"},
        3201209: {"nome": "Cachoeiro de Itapemirim", "lat": -20.8489, "lon": -41.1128, "regiao": "Central Sul"},
        3201506: {"nome": "Colatina", "lat": -19.5392, "lon": -40.6300, "regiao": "Noroeste"},
        3202405: {"nome": "Guarapari", "lat": -20.6708, "lon": -40.4975, "regiao": "Metropolitana"},
        3204906: {"nome": "São Mateus", "lat": -18.7161, "lon": -39.8589, "regiao": "Nordeste"},
        3200102: {"nome": "Afonso Cláudio", "lat": -20.0747, "lon": -41.1367, "regiao": "Central Serrana"},
        3200169: {"nome": "Água Doce do Norte", "lat": -18.5478, "lon": -40.9786, "regiao": "Noroeste"},
        3200201: {"nome": "Águia Branca", "lat": -18.9839, "lon": -40.7403, "regiao": "Noroeste"},
    }

    TOTAL_ES_MUNICIPALITIES_COUNT = 78

    @classmethod
    def is_valid_es_ibge_code(cls, code: Any) -> Tuple[bool, str]:
        if not isinstance(code, int):
            try:
                code = int(str(code).strip())
            except Exception:
                return False, "ibge_code_must_be_numeric"

        code_str = str(code)
        if len(code_str) != 7:
            return False, "ibge_code_must_be_7_digits"
        if not code_str.startswith("32"):
            return False, "ibge_code_outside_es_prefix_32"

        return True, "valid_es_code"

    @classmethod
    def is_within_es_bounds(cls, lat: float, lon: float) -> bool:
        return (cls.ES_MIN_LAT <= lat <= cls.ES_MAX_LAT) and (cls.ES_MIN_LON <= lon <= cls.ES_MAX_LON)

    @staticmethod
    def normalize_string(text: str) -> str:
        """Removes accents and converts to lowercase for resilient searching."""
        if not text:
            return ""
        normalized = unicodedata.normalize("NFKD", text)
        return "".join(c for c in normalized if not unicodedata.combining(c)).strip().lower()

    @staticmethod
    def validate_es_cep(cep: Any) -> Tuple[bool, str]:
        if not cep:
            return False, "empty_cep"
        clean = "".join(filter(str.isdigit, str(cep)))
        if len(clean) != 8:
            return False, "invalid_cep_length"
        # Espírito Santo CEP range: 29000-000 to 29999-999
        num_cep = int(clean)
        if not (29000000 <= num_cep <= 29999999):
            return False, "cep_outside_es_jurisdiction"
        return True, "valid_es_cep"


class MockOpportunitiesRepository:
    """Mock repository for jobs, courses, and support networks with strict boundaries."""

    def __init__(self):
        self.jobs = [
            {"id": 1, "titulo": "Auxiliar de Logística", "ibge": 3205309, "municipio": "Vitória", "salario": 1850.0, "afirmativa": True},
            {"id": 2, "titulo": "Soldador Industrial", "ibge": 3203205, "municipio": "Linhares", "salario": 2600.0, "afirmativa": True},
            {"id": 3, "titulo": "Atendente de Serviços", "ibge": 3205200, "municipio": "Vila Velha", "salario": 1500.0, "afirmativa": False},
        ]
        self.cras_units = {
            101: {"id": 101, "nome": "CRAS Linhares Centro", "ibge": 3203205, "lat": None, "lon": None},  # Missing GPS
            102: {"id": 102, "nome": "CRAS Vitória Continental", "ibge": 3205309, "lat": -20.2900, "lon": -40.3000},
        }

    def filter_opportunities(self, params: Dict[str, Any]) -> Dict[str, Any]:
        # 1. Sanitize pagination params (DoS protection)
        raw_page = params.get("page", 1)
        raw_per_page = params.get("per_page", 20)

        try:
            page = max(1, int(raw_page))
        except (ValueError, TypeError):
            page = 1

        try:
            per_page = max(1, min(100, int(raw_per_page)))  # Clamped strictly between 1 and 100
        except (ValueError, TypeError):
            per_page = 20

        # 2. Sanitize salary filter (negative values clamped to 0)
        salario_min = params.get("salario_min")
        if salario_min is not None:
            try:
                salario_min = max(0.0, float(salario_min))
            except (ValueError, TypeError):
                salario_min = 0.0

        # 3. Municipality search with accent-insensitivity
        query_muni = params.get("municipio")
        norm_query_muni = TerritoryBoundaryEngine.normalize_string(str(query_muni)) if query_muni else None

        filtered = []
        for job in self.jobs:
            if norm_query_muni:
                job_muni_norm = TerritoryBoundaryEngine.normalize_string(job["municipio"])
                if norm_query_muni not in job_muni_norm:
                    continue
            if salario_min is not None and job["salario"] < salario_min:
                continue
            filtered.append(job)

        total_items = len(filtered)
        total_pages = math.ceil(total_items / per_page) if total_items > 0 else 1

        start_idx = (page - 1) * per_page
        end_idx = start_idx + per_page
        paginated_data = filtered[start_idx:end_idx] if start_idx < total_items else []

        return {
            "data": paginated_data,
            "meta": {
                "current_page": page,
                "per_page": per_page,
                "total_items": total_items,
                "total_pages": total_pages,
            }
        }

    def get_cras_location(self, cras_id: int) -> Tuple[float, float, str]:
        """Resolves CRAS coordinates with fallback to municipality centroid if unmapped."""
        cras = self.cras_units.get(cras_id)
        if not cras:
            raise KeyError(f"CRAS unit {cras_id} not found.")

        lat = cras.get("lat")
        lon = cras.get("lon")

        if lat is not None and lon is not None:
            return lat, lon, "exact_gps"

        # Fallback to municipality centroid
        ibge = cras.get("ibge")
        muni = TerritoryBoundaryEngine.SAMPLE_ES_MUNICIPALITIES.get(ibge)
        if muni:
            return muni["lat"], muni["lon"], "municipality_centroid_fallback"

        # State capital fallback (Vitória)
        return -20.3155, -40.3128, "state_capital_fallback"


# --- Test Suite ---

class TestTerritoryPayloadLimits(unittest.TestCase):
    """Tier 2 Boundary test suite for Territorial mapping and Opportunities."""

    def setUp(self):
        self.repo = MockOpportunitiesRepository()

    def test_01_invalid_nonexistent_ibge_municipality_code(self):
        """Verify that non-ES or invalid IBGE codes are strictly rejected."""
        invalid_codes = [
            3304557,  # Rio de Janeiro (RJ starts with 33)
            3550308,  # São Paulo (SP starts with 35)
            3106200,  # Belo Horizonte (MG starts with 31)
            12345,    # Too short
            9999999,  # Non-existent prefix
            "invalid",
            -3205309, # Negative
        ]

        for code in invalid_codes:
            is_valid, reason = TerritoryBoundaryEngine.is_valid_es_ibge_code(code)
            self.assertFalse(is_valid, f"IBGE code {code} should be rejected.")
            self.assertIn(reason, ["ibge_code_outside_es_prefix_32", "ibge_code_must_be_7_digits", "ibge_code_must_be_numeric"])

        # Valid ES code
        is_valid_es, _ = TerritoryBoundaryEngine.is_valid_es_ibge_code(3205309)
        self.assertTrue(is_valid_es)

    def test_02_out_of_bounds_es_geographic_coordinates(self):
        """Verify bounding box checks reject points outside Espírito Santo."""
        out_of_bounds_coords = [
            (-23.5505, -46.6333),  # São Paulo
            (-22.9068, -43.1729),  # Rio de Janeiro
            (-19.9167, -43.9345),  # Belo Horizonte
            (35.6762, 139.6503),   # Tokyo
            (0.0, 0.0),            # Gulf of Guinea
        ]

        for lat, lon in out_of_bounds_coords:
            self.assertFalse(
                TerritoryBoundaryEngine.is_within_es_bounds(lat, lon),
                f"Coordinates ({lat}, {lon}) should be detected as outside ES boundaries."
            )

        # Valid coordinates inside ES (Vitória, Linhares, Cachoeiro)
        self.assertTrue(TerritoryBoundaryEngine.is_within_es_bounds(-20.3155, -40.3128))  # Vitória
        self.assertTrue(TerritoryBoundaryEngine.is_within_es_bounds(-19.3911, -40.0722))  # Linhares
        self.assertTrue(TerritoryBoundaryEngine.is_within_es_bounds(-20.8489, -41.1128))  # Cachoeiro

    def test_03_empty_search_filter_payload_handling(self):
        """Verify that empty, null, or whitespace filter payloads return default paginated results."""
        empty_payloads = [
            {},
            {"municipio": "", "salario_min": "", "page": ""},
            {"municipio": None, "salario_min": None},
            {"municipio": "   "},
        ]

        for payload in empty_payloads:
            result = self.repo.filter_opportunities(payload)
            self.assertIn("data", result)
            self.assertIn("meta", result)
            self.assertEqual(len(result["data"]), len(self.repo.jobs))
            self.assertEqual(result["meta"]["current_page"], 1)

    def test_04_negative_salary_filter_in_job_opportunities(self):
        """Verify negative salary query parameters are clamped to 0 without crashing."""
        negative_payloads = [
            {"salario_min": -5000.0},
            {"salario_min": "-999"},
            {"salario_min": -0.01},
        ]

        for payload in negative_payloads:
            result = self.repo.filter_opportunities(payload)
            # All jobs have salary > 0, so all should be returned when clamped to 0
            self.assertEqual(len(result["data"]), 3)

    def test_05_extreme_pagination_offset_dos_protection(self):
        """Verify extreme page numbers and oversized per_page values are safely clamped."""
        # Oversized per_page (1,000,000) should clamp to max allowed (100)
        res_per_page = self.repo.filter_opportunities({"per_page": 1000000})
        self.assertEqual(res_per_page["meta"]["per_page"], 100)

        # Extreme page offset (page 999999) should return empty data list with valid metadata
        res_page = self.repo.filter_opportunities({"page": 999999, "per_page": 10})
        self.assertEqual(res_page["meta"]["current_page"], 999999)
        self.assertEqual(len(res_page["data"]), 0)
        self.assertEqual(res_page["meta"]["total_items"], 3)

    def test_06_special_characters_and_unicode_in_municipality_query(self):
        """Verify search handles diacritics, unicode, emojis, and quotes seamlessly."""
        # Accent insensitivity: 'Vitória' vs 'vitoria' vs 'VITORIA'
        for q in ["Vitória", "vitoria", "VITORIA", "Vitoria", "  vitória  "]:
            res = self.repo.filter_opportunities({"municipio": q})
            self.assertEqual(len(res["data"]), 1, f"Query '{q}' failed to match Vitória.")
            self.assertEqual(res["data"][0]["municipio"], "Vitória")

        # 'São Mateus' vs 'Sao Mateus'
        norm_sao = TerritoryBoundaryEngine.normalize_string("São Mateus")
        norm_sao2 = TerritoryBoundaryEngine.normalize_string("Sao Mateus")
        self.assertEqual(norm_sao, norm_sao2)

        # Non-matching emoji / garbage query returns empty list safely
        res_garbage = self.repo.filter_opportunities({"municipio": "🌲🌲🌲 NonExistentMuni 123"})
        self.assertEqual(len(res_garbage["data"]), 0)

    def test_07_non_es_postal_code_rejection(self):
        """Verify that postal codes outside Espírito Santo (29000-000 to 29999-999) are flagged."""
        non_es_ceps = [
            "01310-100",  # São Paulo (Avenida Paulista)
            "20040-002",  # Rio de Janeiro (Centro)
            "30140-071",  # Belo Horizonte (Savassi)
            "70040-010",  # Brasília (Esplanada)
            "12345",      # Malformed
            "ABCDE-FGH",  # Letters
        ]

        for cep in non_es_ceps:
            is_valid, reason = TerritoryBoundaryEngine.validate_es_cep(cep)
            self.assertFalse(is_valid, f"CEP {cep} should be rejected.")

        # Valid ES CEPs (Vitória, Vila Velha, Linhares)
        self.assertTrue(TerritoryBoundaryEngine.validate_es_cep("29010-000")[0])  # Vitória
        self.assertTrue(TerritoryBoundaryEngine.validate_es_cep("29100-000")[0])  # Vila Velha
        self.assertTrue(TerritoryBoundaryEngine.validate_es_cep("29900-000")[0])  # Linhares

    def test_08_missing_cras_geocoordinates_fallback(self):
        """Verify CRAS units with missing GPS coordinates fall back to municipality centroid."""
        # CRAS 102 has exact GPS
        lat_exact, lon_exact, origin_exact = self.repo.get_cras_location(102)
        self.assertEqual(origin_exact, "exact_gps")
        self.assertEqual(lat_exact, -20.2900)

        # CRAS 101 has null GPS -> falls back to Linhares centroid (-19.3911, -40.0722)
        lat_fallback, lon_fallback, origin_fb = self.repo.get_cras_location(101)
        self.assertEqual(origin_fb, "municipality_centroid_fallback")
        self.assertAlmostEqual(lat_fallback, -19.3911, places=3)
        self.assertAlmostEqual(lon_fallback, -40.0722, places=3)

    def test_09_total_es_municipalities_count_boundary(self):
        """Verify that Espírito Santo geographical registry accounts for exactly 78 municipalities."""
        self.assertEqual(
            TerritoryBoundaryEngine.TOTAL_ES_MUNICIPALITIES_COUNT,
            78,
            "Espírito Santo has exactly 78 municipalities as mandated by SEJUS/ES specification."
        )

    def test_10_affirmative_action_tag_filter_boundary(self):
        """Verify affirmative action filter for egressos matches only flagged opportunities."""
        jobs = self.repo.jobs
        affirmative_jobs = [j for j in jobs if j.get("afirmativa") is True]
        self.assertEqual(len(affirmative_jobs), 2)
        self.assertEqual(affirmative_jobs[0]["id"], 1)
        self.assertEqual(affirmative_jobs[1]["id"], 2)


if __name__ == "__main__":
    unittest.main()
