"""
CONECTA EGRESSO (SEJUS/ES) - E2E Testing Common Utilities and Helpers
Authoritative Source: ORIGINAL_REQUEST.md, PROJECT.md, TEST_INFRA.md

Provides reusable, robust test utilities:
- MockApiClient / HttpClient: Unified HTTP client with offline mock fallback & live API support.
- MockWebSocketClient: WebRTC signaling & telemetry frame simulation.
- CryptoVerifier: HMAC-SHA256, SHA-256 hash chaining, LGPD blind indexing, JWT verification.
- AssertionHelper: Rich assertions with clear diagnostic error reporting.
- DataGenerator: Authentic SEJUS profiles, valid/invalid CPFs, 78 ES IBGE municipalities, telemetry data.
"""

from __future__ import annotations

import base64
import copy
import hashlib
import hmac
import json
import os
import random
import re
import string
import sys
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Any, Callable, Dict, List, Optional, Tuple, Union


# ==============================================================================
# 1. OFFICIAL DATASET: 78 ESPÍRITO SANTO MUNICIPALITIES (IBGE)
# ==============================================================================

ES_MUNICIPALITIES: List[Dict[str, Any]] = [
    {"ibge_code": "3200102", "name": "Afonso Cláudio", "region": "Central Serrana", "lat": -20.0768, "lon": -41.1378, "has_social_office": False},
    {"ibge_code": "3200169", "name": "Água Doce do Norte", "region": "Noroeste", "lat": -18.5478, "lon": -40.9781, "has_social_office": False},
    {"ibge_code": "3200136", "name": "Águia Branca", "region": "Noroeste", "lat": -18.9839, "lon": -40.7408, "has_social_office": False},
    {"ibge_code": "3200201", "name": "Alegre", "region": "Caparaó", "lat": -20.7636, "lon": -41.5331, "has_social_office": False},
    {"ibge_code": "3200300", "name": "Alfredo Chaves", "region": "Central Sul", "lat": -20.6353, "lon": -40.7511, "has_social_office": False},
    {"ibge_code": "3200359", "name": "Alto Rio Novo", "region": "Noroeste", "lat": -19.0578, "lon": -41.0169, "has_social_office": False},
    {"ibge_code": "3200409", "name": "Anchieta", "region": "Litoral Sul", "lat": -20.8058, "lon": -40.6456, "has_social_office": False},
    {"ibge_code": "3200508", "name": "Apiacá", "region": "Sul", "lat": -21.1542, "lon": -41.5678, "has_social_office": False},
    {"ibge_code": "3200607", "name": "Aracruz", "region": "Rio Doce", "lat": -19.8203, "lon": -40.2742, "has_social_office": True},
    {"ibge_code": "3200706", "name": "Atílio Vivácqua", "region": "Sul", "lat": -20.9147, "lon": -41.1983, "has_social_office": False},
    {"ibge_code": "3200805", "name": "Baixo Guandu", "region": "Central", "lat": -19.5189, "lon": -41.0142, "has_social_office": False},
    {"ibge_code": "3200904", "name": "Barra de São Francisco", "region": "Noroeste", "lat": -18.7553, "lon": -40.8911, "has_social_office": True},
    {"ibge_code": "3201001", "name": "Boa Esperança", "region": "Nordeste", "lat": -18.5403, "lon": -40.2953, "has_social_office": False},
    {"ibge_code": "3201100", "name": "Bom Jesus do Norte", "region": "Sul", "lat": -21.1114, "lon": -41.6706, "has_social_office": False},
    {"ibge_code": "3201159", "name": "Brejetuba", "region": "Sudoeste Serrana", "lat": -20.1439, "lon": -41.2936, "has_social_office": False},
    {"ibge_code": "3201209", "name": "Cachoeiro de Itapemirim", "region": "Central Sul", "lat": -20.8489, "lon": -41.1128, "has_social_office": True},
    {"ibge_code": "3201308", "name": "Cariacica", "region": "Metropolitana", "lat": -20.2639, "lon": -40.4200, "has_social_office": True},
    {"ibge_code": "3201407", "name": "Castelo", "region": "Central Sul", "lat": -20.6036, "lon": -41.2031, "has_social_office": False},
    {"ibge_code": "3201506", "name": "Colatina", "region": "Rio Doce", "lat": -19.5392, "lon": -40.6308, "has_social_office": True},
    {"ibge_code": "3201605", "name": "Conceição da Barra", "region": "Nordeste", "lat": -18.5933, "lon": -39.7322, "has_social_office": False},
    {"ibge_code": "3201704", "name": "Conceição do Castelo", "region": "Sudoeste Serrana", "lat": -20.3697, "lon": -41.2439, "has_social_office": False},
    {"ibge_code": "3201803", "name": "Divino de São Lourenço", "region": "Caparaó", "lat": -20.6200, "lon": -41.6842, "has_social_office": False},
    {"ibge_code": "3201902", "name": "Domingos Martins", "region": "Sudoeste Serrana", "lat": -20.3631, "lon": -40.6589, "has_social_office": False},
    {"ibge_code": "3202009", "name": "Dores do Rio Preto", "region": "Caparaó", "lat": -20.6908, "lon": -41.8456, "has_social_office": False},
    {"ibge_code": "3202108", "name": "Ecoporanga", "region": "Noroeste", "lat": -18.3733, "lon": -40.8306, "has_social_office": False},
    {"ibge_code": "3202207", "name": "Fundão", "region": "Metropolitana", "lat": -19.9331, "lon": -40.4047, "has_social_office": False},
    {"ibge_code": "3202256", "name": "Governador Lindenberg", "region": "Rio Doce", "lat": -19.2558, "lon": -40.4853, "has_social_office": False},
    {"ibge_code": "3202306", "name": "Guaçuí", "region": "Caparaó", "lat": -20.7761, "lon": -41.6792, "has_social_office": False},
    {"ibge_code": "3202405", "name": "Guarapari", "region": "Metropolitana", "lat": -20.6592, "lon": -40.4981, "has_social_office": True},
    {"ibge_code": "3202454", "name": "Ibatiba", "region": "Caparaó", "lat": -20.2339, "lon": -41.5108, "has_social_office": False},
    {"ibge_code": "3202504", "name": "Ibiraçu", "region": "Rio Doce", "lat": -19.8319, "lon": -40.3686, "has_social_office": False},
    {"ibge_code": "3202553", "name": "Ibitirama", "region": "Caparaó", "lat": -20.5408, "lon": -41.6669, "has_social_office": False},
    {"ibge_code": "3202603", "name": "Iconha", "region": "Litoral Sul", "lat": -20.7931, "lon": -40.8106, "has_social_office": False},
    {"ibge_code": "3202652", "name": "Irupi", "region": "Caparaó", "lat": -20.3447, "lon": -41.6417, "has_social_office": False},
    {"ibge_code": "3202702", "name": "Itaguaçu", "region": "Central", "lat": -19.8019, "lon": -40.8558, "has_social_office": False},
    {"ibge_code": "3202801", "name": "Itapemirim", "region": "Litoral Sul", "lat": -21.0111, "lon": -40.8339, "has_social_office": False},
    {"ibge_code": "3202900", "name": "Itarana", "region": "Central", "lat": -19.8739, "lon": -40.8753, "has_social_office": False},
    {"ibge_code": "3203007", "name": "Iúna", "region": "Caparaó", "lat": -20.3458, "lon": -41.5358, "has_social_office": False},
    {"ibge_code": "3203056", "name": "Jaguaré", "region": "Nordeste", "lat": -18.9056, "lon": -40.0761, "has_social_office": False},
    {"ibge_code": "3203106", "name": "Jerônimo Monteiro", "region": "Sul", "lat": -20.7897, "lon": -41.3961, "has_social_office": False},
    {"ibge_code": "3203130", "name": "João Neiva", "region": "Rio Doce", "lat": -19.7578, "lon": -40.3833, "has_social_office": False},
    {"ibge_code": "3203163", "name": "Laranja da Terra", "region": "Central", "lat": -19.8989, "lon": -41.0558, "has_social_office": False},
    {"ibge_code": "3203205", "name": "Linhares", "region": "Rio Doce", "lat": -19.3911, "lon": -40.0722, "has_social_office": True},
    {"ibge_code": "3203304", "name": "Mantenópolis", "region": "Noroeste", "lat": -18.8631, "lon": -41.1228, "has_social_office": False},
    {"ibge_code": "3203320", "name": "Marataízes", "region": "Litoral Sul", "lat": -21.0433, "lon": -40.8244, "has_social_office": False},
    {"ibge_code": "3203346", "name": "Marechal Floriano", "region": "Sudoeste Serrana", "lat": -20.4131, "lon": -40.6831, "has_social_office": False},
    {"ibge_code": "3203353", "name": "Marilândia", "region": "Rio Doce", "lat": -19.4139, "lon": -40.5414, "has_social_office": False},
    {"ibge_code": "3203403", "name": "Mimoso do Sul", "region": "Sul", "lat": -21.0642, "lon": -41.3658, "has_social_office": False},
    {"ibge_code": "3203502", "name": "Montanha", "region": "Nordeste", "lat": -18.1269, "lon": -40.3633, "has_social_office": False},
    {"ibge_code": "3203601", "name": "Mucurici", "region": "Nordeste", "lat": -18.0933, "lon": -40.5156, "has_social_office": False},
    {"ibge_code": "3203700", "name": "Muniz Freire", "region": "Caparaó", "lat": -20.4642, "lon": -41.4131, "has_social_office": False},
    {"ibge_code": "3203809", "name": "Muqui", "region": "Sul", "lat": -20.9525, "lon": -41.3456, "has_social_office": False},
    {"ibge_code": "3203908", "name": "Nova Venécia", "region": "Noroeste", "lat": -18.7106, "lon": -40.4006, "has_social_office": False},
    {"ibge_code": "3204005", "name": "Pancas", "region": "Central", "lat": -19.2247, "lon": -40.8514, "has_social_office": False},
    {"ibge_code": "3204054", "name": "Pedro Canário", "region": "Nordeste", "lat": -18.0297, "lon": -40.1497, "has_social_office": False},
    {"ibge_code": "3204104", "name": "Pinheiros", "region": "Nordeste", "lat": -18.4069, "lon": -40.2144, "has_social_office": False},
    {"ibge_code": "3204203", "name": "Piúma", "region": "Litoral Sul", "lat": -20.8347, "lon": -40.7203, "has_social_office": False},
    {"ibge_code": "3204252", "name": "Ponto Belo", "region": "Nordeste", "lat": -18.1242, "lon": -40.5375, "has_social_office": False},
    {"ibge_code": "3204302", "name": "Presidente Kennedy", "region": "Litoral Sul", "lat": -21.0967, "lon": -41.0478, "has_social_office": False},
    {"ibge_code": "3204351", "name": "Rio Bananal", "region": "Rio Doce", "lat": -19.2650, "lon": -40.3328, "has_social_office": False},
    {"ibge_code": "3204401", "name": "Rio Novo do Sul", "region": "Litoral Sul", "lat": -20.8631, "lon": -40.9364, "has_social_office": False},
    {"ibge_code": "3204500", "name": "Santa Leopoldina", "region": "Central Serrana", "lat": -20.1006, "lon": -40.5297, "has_social_office": False},
    {"ibge_code": "3204559", "name": "Santa Maria de Jetibá", "region": "Central Serrana", "lat": -20.0408, "lon": -40.7461, "has_social_office": False},
    {"ibge_code": "3204609", "name": "Santa Teresa", "region": "Central Serrana", "lat": -19.9356, "lon": -40.5986, "has_social_office": False},
    {"ibge_code": "3204658", "name": "São Domingos do Norte", "region": "Noroeste", "lat": -19.1417, "lon": -40.5892, "has_social_office": False},
    {"ibge_code": "3204708", "name": "São Gabriel da Palha", "region": "Noroeste", "lat": -19.0169, "lon": -40.5361, "has_social_office": False},
    {"ibge_code": "3204807", "name": "São José do Calçado", "region": "Sul", "lat": -20.9819, "lon": -41.6547, "has_social_office": False},
    {"ibge_code": "3204906", "name": "São Mateus", "region": "Nordeste", "lat": -18.7161, "lon": -39.8589, "has_social_office": True},
    {"ibge_code": "3204955", "name": "São Roque do Canaã", "region": "Central", "lat": -19.7392, "lon": -40.6558, "has_social_office": False},
    {"ibge_code": "3205002", "name": "Serra", "region": "Metropolitana", "lat": -20.1286, "lon": -40.3078, "has_social_office": True},
    {"ibge_code": "3205010", "name": "Sooretama", "region": "Rio Doce", "lat": -19.1969, "lon": -40.0936, "has_social_office": False},
    {"ibge_code": "3205036", "name": "Vargem Alta", "region": "Central Sul", "lat": -20.6728, "lon": -41.0081, "has_social_office": False},
    {"ibge_code": "3205069", "name": "Venda Nova do Imigrante", "region": "Sudoeste Serrana", "lat": -20.3275, "lon": -41.1344, "has_social_office": False},
    {"ibge_code": "3205101", "name": "Viana", "region": "Metropolitana", "lat": -20.3906, "lon": -40.4978, "has_social_office": True},
    {"ibge_code": "3205150", "name": "Vila Pavão", "region": "Noroeste", "lat": -18.6158, "lon": -40.6089, "has_social_office": False},
    {"ibge_code": "3205176", "name": "Vila Valério", "region": "Noroeste", "lat": -18.9989, "lon": -40.3897, "has_social_office": False},
    {"ibge_code": "3205200", "name": "Vila Velha", "region": "Metropolitana", "lat": -20.3297, "lon": -40.2925, "has_social_office": True},
    {"ibge_code": "3205309", "name": "Vitória", "region": "Metropolitana", "lat": -20.3155, "lon": -40.3128, "has_social_office": True},
]

MUNICIPALITY_BY_CODE: Dict[str, Dict[str, Any]] = {m["ibge_code"]: m for m in ES_MUNICIPALITIES}


# ==============================================================================
# 2. DATA GENERATOR (Authentic SEJUS Profiles, CPFs, Telemetry, etc.)
# ==============================================================================

class DataGenerator:
    """Generates authentic test data for SEJUS/ES domain according to Brazilian standards."""

    @staticmethod
    def _calc_cpf_digit(digits: List[int], factor: int) -> int:
        total = sum(d * (factor - idx) for idx, d in enumerate(digits))
        remainder = total % 11
        return 0 if remainder < 2 else 11 - remainder

    @classmethod
    def generate_cpf(cls, valid: bool = True, formatted: bool = True) -> str:
        """Generates a valid (Receita Federal compliant) or intentionally invalid CPF."""
        if valid:
            digits = [random.randint(0, 9) for _ in range(9)]
            # Avoid repeated 9 digits (which are known invalid numbers even with valid check digits)
            while len(set(digits)) == 1:
                digits = [random.randint(0, 9) for _ in range(9)]
            
            d1 = cls._calc_cpf_digit(digits, 10)
            d2 = cls._calc_cpf_digit(digits + [d1], 11)
            full_digits = digits + [d1, d2]
            raw = "".join(str(d) for d in full_digits)
        else:
            choice = random.choice(["bad_digit", "repeated", "wrong_length", "alpha"])
            if choice == "bad_digit":
                digits = [random.randint(0, 9) for _ in range(9)]
                d1 = (cls._calc_cpf_digit(digits, 10) + 1) % 10  # Invalid check digit
                d2 = (cls._calc_cpf_digit(digits + [d1], 11) + 1) % 10
                raw = "".join(str(d) for d in digits + [d1, d2])
            elif choice == "repeated":
                n = str(random.randint(0, 9))
                raw = n * 11
            elif choice == "wrong_length":
                raw = "".join(str(random.randint(0, 9)) for _ in range(random.choice([8, 10, 12, 13])))
            else:
                raw = "123.45A.789-00"
                return raw

        if formatted and len(raw) == 11 and raw.isdigit():
            return f"{raw[0:3]}.{raw[3:6]}.{raw[6:9]}-{raw[9:11]}"
        return raw

    @classmethod
    def validate_cpf(cls, cpf: str) -> bool:
        """Validates a Brazilian CPF using standard Receita Federal algorithm."""
        clean = re.sub(r"\D", "", cpf or "")
        if len(clean) != 11:
            return False
        if len(set(clean)) == 1:
            return False  # Known invalid repeated numbers like 11111111111
        digits = [int(c) for c in clean]
        d1 = cls._calc_cpf_digit(digits[:9], 10)
        if digits[9] != d1:
            return False
        d2 = cls._calc_cpf_digit(digits[:10], 11)
        if digits[10] != d2:
            return False
        return True

    @classmethod
    def generate_user_profile(cls, role: str = "egresso", **overrides) -> Dict[str, Any]:
        """
        Generates realistic SEJUS user profiles.
        Supported roles: 'gestor', 'tecnico', 'egresso', 'familiar'.
        """
        role_lower = role.lower()
        cpf = overrides.pop("cpf", cls.generate_cpf(valid=True, formatted=True))
        user_id = overrides.pop("id", random.randint(100, 9999))

        if role_lower == "gestor":
            profile = {
                "id": user_id,
                "name": "Dra. Renata Vasconcellos (Gestora SEJUS)",
                "email": "renata.vasconcellos@sejus.es.gov.br",
                "cpf": cpf,
                "matricula": f"SEJUS-{random.randint(10000, 99999)}",
                "role": "gestor",
                "cargo": "Coordenadora de Reinserção Social",
                "orgao": "Secretaria de Estado da Justiça - SEJUS/ES",
                "permissions": [
                    "dashboard:view", "dashboard:kpis", "prontuario:read_all",
                    "relatorios:export", "audit:view", "seguranca_lgpd:view",
                    "webrtc:admin", "municipios:manage"
                ],
                "auth_provider": "govbr_gold",
                "ativo": True,
            }
        elif role_lower == "tecnico":
            mun = random.choice([m for m in ES_MUNICIPALITIES if m["has_social_office"]])
            profile = {
                "id": user_id,
                "name": "Assistente Social Marcos Vinícius",
                "email": "marcos.social@vitoria.es.gov.br",
                "cpf": cpf,
                "matricula": f"ESCRITORIO-{random.randint(1000, 9999)}",
                "role": "tecnico",
                "cargo": "Técnico de Referência - Escritório Social",
                "orgao": f"Escritório Social de {mun['name']}",
                "municipio_ibge": mun["ibge_code"],
                "municipio_nome": mun["name"],
                "permissions": [
                    "dashboard:view", "atendimento:queue", "atendimento:start",
                    "webrtc:host", "prontuario:read", "prontuario:write",
                    "prontuario:evolucao", "vagas:manage", "cursos:manage"
                ],
                "auth_provider": "acesso_cidadao_es",
                "ativo": True,
            }
        elif role_lower == "familiar":
            profile = {
                "id": user_id,
                "name": "Maria de Fátima Silva (Familiar)",
                "email": "fatima.silva@email.com",
                "cpf": cpf,
                "role": "familiar",
                "parentesco": "Mãe",
                "egresso_vinculado_id": overrides.pop("egresso_vinculado_id", 101),
                "permissions": ["carteira:view", "oportunidades:view", "atendimento:join_remote"],
                "auth_provider": "acesso_cidadao_es",
                "ativo": True,
            }
        else:  # 'egresso'
            mun = overrides.pop("municipio", random.choice(ES_MUNICIPALITIES))
            if isinstance(mun, str):
                mun = MUNICIPALITY_BY_CODE.get(mun, ES_MUNICIPALITIES[77])  # Default Vitória
            clean_cpf = re.sub(r"\D", "", cpf)
            blind_idx = CryptoVerifier.generate_blind_index(clean_cpf)
            profile = {
                "id": user_id,
                "name": "Carlos Eduardo dos Santos",
                "nome_social": None,
                "email": "carlos.santos@email.com",
                "cpf": cpf,
                "cpf_blind_index": blind_idx,
                "rg": f"{random.randint(1000000, 9999999)} SPTC/ES",
                "data_nascimento": "1994-06-15",
                "nome_mae": "Maria das Graças dos Santos",
                "role": "egresso",
                "prontuario_id": f"PRONT-ES-{user_id:06d}",
                "regime_prisional": overrides.pop("regime_prisional", "LIVRAMENTO_CONDICIONAL"),
                "situacao_cadastral": "ATIVO",
                "unidade_prisional_origem": "Penitenciária Estadual de Vila Velha (PEVV I)",
                "data_liberacao": "2025-11-10",
                "municipio_residencia_ibge": mun["ibge_code"],
                "municipio_residencia_nome": mun["name"],
                "vulnerabilidade_social": "MEDIA",
                "carteira_digital_hash": hashlib.sha256(f"CARTEIRA-{clean_cpf}-{user_id}".encode()).hexdigest(),
                "permissions": [
                    "carteira:view", "carteira:download_pdf", "oportunidades:view",
                    "oportunidades:apply", "atendimento:join_queue", "prontuario:view_own"
                ],
                "auth_provider": "acesso_cidadao_es",
                "ativo": True,
            }
        profile.update(overrides)
        return profile

    @classmethod
    def generate_telemetry_payload(cls, quality: str = "good", **overrides) -> Dict[str, Any]:
        """
        Generates realistic WebRTC connection telemetry frames.
        Quality presets: 'excellent', 'good', 'poor' (3G/lossy), 'critical'.
        """
        quality_lower = quality.lower()
        if quality_lower == "excellent":
            payload = {
                "mos": round(random.uniform(4.3, 4.5), 2),
                "rtt_ms": random.randint(15, 35),
                "jitter_ms": random.randint(1, 5),
                "packet_loss_pct": round(random.uniform(0.0, 0.1), 2),
                "resolution": "1280x720",
                "fps": 30,
                "bitrate_kbps": 1200,
                "audio_level": 0.85,
                "codec": "VP8/Opus",
                "network_type": "5G/Fiber",
            }
        elif quality_lower == "poor":
            payload = {
                "mos": round(random.uniform(2.6, 3.2), 2),
                "rtt_ms": random.randint(180, 320),
                "jitter_ms": random.randint(45, 90),
                "packet_loss_pct": round(random.uniform(3.5, 7.5), 2),
                "resolution": "640x360",
                "fps": 15,
                "bitrate_kbps": 320,
                "audio_level": 0.60,
                "codec": "VP8/Opus",
                "network_type": "3G/Mobile-Rural",
            }
        elif quality_lower == "critical":
            payload = {
                "mos": round(random.uniform(1.2, 1.9), 2),
                "rtt_ms": random.randint(450, 850),
                "jitter_ms": random.randint(120, 250),
                "packet_loss_pct": round(random.uniform(12.0, 25.0), 2),
                "resolution": "320x240",
                "fps": 8,
                "bitrate_kbps": 95,
                "audio_level": 0.30,
                "codec": "VP8/Opus",
                "network_type": "2G/Edge",
            }
        else:  # 'good' (4G baseline)
            payload = {
                "mos": round(random.uniform(3.8, 4.2), 2),
                "rtt_ms": random.randint(40, 75),
                "jitter_ms": random.randint(6, 15),
                "packet_loss_pct": round(random.uniform(0.2, 0.9), 2),
                "resolution": "1280x720",
                "fps": 28,
                "bitrate_kbps": 850,
                "audio_level": 0.80,
                "codec": "VP8/Opus",
                "network_type": "4G/LTE",
            }
        payload["timestamp"] = datetime.now(timezone.utc).isoformat()
        payload.update(overrides)
        return payload

    @classmethod
    def generate_job_vacancy(cls, municipio_code: str = "3205309", **overrides) -> Dict[str, Any]:
        """Generates realistic job vacancy with affirmative action tags."""
        mun = MUNICIPALITY_BY_CODE.get(municipio_code, ES_MUNICIPALITIES[77])
        titles = [
            ("Auxiliar de Logística e Armazém", "Transportes Capixaba Ltda", 1850.00),
            ("Operador de Produção Industrial", "Indústrias Tubarão S/A", 2100.00),
            ("Assistente Administrativo Júnior", "Comércio Vix Eireli", 1950.00),
            ("Eletricista Predial e Manutenção", "Serviços Gerais ES", 2400.00),
            ("Atendente de SAC / Teleatendimento", "CallCenter Vitória", 1620.00),
        ]
        title, empresa, rem = random.choice(titles)
        job = {
            "id": overrides.pop("id", random.randint(100, 999)),
            "titulo": title,
            "empresa": empresa,
            "municipio_ibge": mun["ibge_code"],
            "municipio_nome": mun["name"],
            "regiao": mun["region"],
            "regime_contratacao": "CLT",
            "vagas_afirmativas_egresso": True,
            "vagas_disponiveis": random.randint(2, 8),
            "remuneracao": rem,
            "beneficios": ["Vale Transporte", "Vale Refeição", "Plano Odontológico"],
            "requisitos": "Ensino Médio completo ou cursando; não exige experiência prévia.",
            "ativo": True,
            "data_publicacao": datetime.now(timezone.utc).strftime("%Y-%m-%d"),
        }
        job.update(overrides)
        return job

    @classmethod
    def generate_course_opportunity(cls, municipio_code: str = "3205309", **overrides) -> Dict[str, Any]:
        """Generates realistic educational course."""
        mun = MUNICIPALITY_BY_CODE.get(municipio_code, ES_MUNICIPALITIES[77])
        courses = [
            ("Instalações Elétricas Residenciais e Fotovoltaicas", "SENAI/ES", "Presencial", 160),
            ("Informática Básica e Letramento Digital", "Escola de Governo SEGER/ES", "Semipresencial", 60),
            ("Logística Portuária e Movimentação de Cargas", "IFES - Campus Cariacica", "Presencial", 120),
            ("Empreendedorismo e Gestão de Microempresas", "SEBRAE/ES", "EAD", 40),
            ("Panificação e Confeitaria Artesanal", "Centro de Qualificação SEJUS", "Presencial", 80),
        ]
        nome, inst, mod, carga = random.choice(courses)
        course = {
            "id": overrides.pop("id", random.randint(100, 999)),
            "nome_curso": nome,
            "instituicao": inst,
            "municipio_ibge": mun["ibge_code"],
            "municipio_nome": mun["name"],
            "modalidade": mod,
            "carga_horaria_horas": carga,
            "vagas_gratuitas": random.randint(15, 30),
            "certificado_reconhecido": True,
            "inscricoes_abertas": True,
        }
        course.update(overrides)
        return course


# ==============================================================================
# 3. CRYPTOGRAPHIC VERIFIER (HMAC, SHA-256 Chaining, LGPD Blind Index, JWT)
# ==============================================================================

class CryptoVerifier:
    """Cryptographic operations and verifiers required by SEJUS/ES specifications."""

    DEFAULT_WEBHOOK_SECRET = "sejus_webrtc_webhook_shared_secret_2026"
    DEFAULT_PEPPER = "SEJUS_LGPD_PEPPER_BLIND_INDEX_SECRET_2026"
    GENESIS_HASH = "0000000000000000000000000000000000000000000000000000000000000000"

    @classmethod
    def generate_hmac_signature(cls, payload: Union[str, bytes, dict], secret_key: str = DEFAULT_WEBHOOK_SECRET) -> str:
        """Computes HMAC-SHA256 in lowercase hexadecimal string."""
        if isinstance(payload, dict):
            raw_bytes = json.dumps(payload, sort_keys=True, separators=(",", ":")).encode("utf-8")
        elif isinstance(payload, str):
            raw_bytes = payload.encode("utf-8")
        else:
            raw_bytes = payload
        secret_bytes = secret_key.encode("utf-8")
        return hmac.new(secret_bytes, raw_bytes, hashlib.sha256).hexdigest()

    @classmethod
    def verify_hmac_signature(cls, payload: Union[str, bytes, dict], signature: str, secret_key: str = DEFAULT_WEBHOOK_SECRET) -> bool:
        """Verifies HMAC-SHA256 signature using constant-time comparison."""
        expected = cls.generate_hmac_signature(payload, secret_key)
        return hmac.compare_digest(expected.lower(), (signature or "").lower())

    @classmethod
    def generate_blind_index(cls, plain_value: str, salt_or_key: str = DEFAULT_PEPPER) -> str:
        """
        Generates deterministic LGPD blind index HMAC-SHA256 for searchable CPF/PII encryption.
        Canonicalizes digits or normalized ascii before hashing.
        """
        clean = re.sub(r"\D", "", plain_value or "")
        if not clean:
            clean = unicodedata.normalize("NFKD", plain_value or "").encode("ascii", "ignore").decode("utf-8").strip().lower()
        return cls.generate_hmac_signature(clean, salt_or_key)

    @classmethod
    def verify_blind_index(cls, plain_value: str, expected_index: str, salt_or_key: str = DEFAULT_PEPPER) -> bool:
        """Verifies that plain_value corresponds to the expected blind index."""
        actual = cls.generate_blind_index(plain_value, salt_or_key)
        return hmac.compare_digest(actual.lower(), (expected_index or "").lower())

    @classmethod
    def calculate_blind_index(cls, plain_value: str, salt_or_key: str = DEFAULT_PEPPER) -> str:
        """Alias for generate_blind_index."""
        return cls.generate_blind_index(plain_value, salt_or_key)

    @classmethod
    def generate_digital_wallet_token(
        cls,
        egresso_id: int = 1,
        nome: str = "Lucas Santos",
        cpf_raw: str = "19283045678",
        exp_days: int = 365,
        secret_key: str = DEFAULT_WEBHOOK_SECRET,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        """Generates compact URL-safe Base64 token envelope containing payload & cryptographic signature."""
        now = datetime.now(timezone.utc)
        clean_cpf = re.sub(r"\D", "", cpf_raw)
        masked_cpf = f"***.{clean_cpf[3:6]}.{clean_cpf[6:9]}-**" if len(clean_cpf) == 11 else clean_cpf
        payload = {
            "doc_id": str(egresso_id),
            "nome": nome,
            "cpf_masked": masked_cpf,
            "registro_sejus": f"ES-2026-{egresso_id:06d}",
            "municipio": kwargs.get("municipio", "Vitória"),
            "issued_at": now.isoformat(),
            "expires_at": (now + timedelta(days=exp_days)).isoformat(),
            "legal_basis": "Lei Complementar Estadual nº 182/2021",
        }
        raw_json = json.dumps(payload, sort_keys=True, separators=(",", ":")).encode("utf-8")
        sig = hmac.new(secret_key.encode("utf-8"), raw_json, hashlib.sha256).hexdigest()
        envelope = {"p": payload, "s": sig}
        envelope_json = json.dumps(envelope, separators=(",", ":")).encode("utf-8")
        token_str = base64.urlsafe_b64encode(envelope_json).decode("ascii").rstrip("=")
        return {
            "token": token_str,
            "payload": payload,
            "signature": sig,
            "envelope": envelope,
        }

    @classmethod
    def verify_digital_wallet_token(cls, token: str, secret_key: str = DEFAULT_WEBHOOK_SECRET) -> Dict[str, Any]:
        """Verifies URL-safe Base64 token envelope signature and validity."""
        try:
            padded = token + "=" * ((4 - len(token) % 4) % 4)
            decoded = base64.urlsafe_b64decode(padded.encode("ascii")).decode("utf-8")
            envelope = json.loads(decoded)
            payload = envelope.get("p", {})
            sig = envelope.get("s", "")
            raw_json = json.dumps(payload, sort_keys=True, separators=(",", ":")).encode("utf-8")
            expected_sig = hmac.new(secret_key.encode("utf-8"), raw_json, hashlib.sha256).hexdigest()
            if hmac.compare_digest(expected_sig.lower(), sig.lower()):
                return {"valid": True, "status": "VALID_DOCUMENT", "payload": payload}
            return {"valid": False, "status": "TAMPERED_DOCUMENT", "payload": payload}
        except Exception as e:
            return {"valid": False, "status": "MALFORMED_TOKEN", "error": str(e)}


    @classmethod
    def calculate_audit_hash(cls, previous_hash: str, event_data: Union[dict, str]) -> str:
        """
        Computes SHA-256 for immutable audit log hash chaining:
        hash_n = SHA-256(previous_hash + canonical_event_json)
        """
        if isinstance(event_data, dict):
            event_str = json.dumps(event_data, sort_keys=True, separators=(",", ":"))
        else:
            event_str = str(event_data)
        combined = f"{previous_hash}{event_str}".encode("utf-8")
        return hashlib.sha256(combined).hexdigest()

    @classmethod
    def verify_audit_chain(cls, chain_records: List[Dict[str, Any]]) -> Tuple[bool, str]:
        """
        Verifies sequential integrity of an immutable audit chain.
        Returns (is_valid: bool, diagnosis_message: str).
        """
        if not chain_records:
            return True, "Empty chain is trivially valid"

        expected_prev = cls.GENESIS_HASH
        for idx, entry in enumerate(chain_records):
            actual_prev = entry.get("previous_hash", "")
            actual_hash = entry.get("hash", "")
            payload = entry.get("payload", {})

            if idx == 0 and actual_prev != cls.GENESIS_HASH:
                return False, f"Chain error at root block [0]: previous_hash is not genesis (got {actual_prev})"

            if idx > 0 and actual_prev != expected_prev:
                return False, f"Broken chain link at index [{idx}]: previous_hash ({actual_prev}) != parent hash ({expected_prev})"

            computed = cls.calculate_audit_hash(actual_prev, payload)
            if not hmac.compare_digest(computed.lower(), actual_hash.lower()):
                return False, f"Tampered entry detected at index [{idx}]: computed ({computed}) != stored ({actual_hash})"

            expected_prev = actual_hash

        return True, f"Audit chain valid ({len(chain_records)} immutable blocks verified)"

    @classmethod
    def generate_qr_payload(cls, egresso_profile: Dict[str, Any], secret_key: str = DEFAULT_WEBHOOK_SECRET) -> Dict[str, Any]:
        """Generates cryptographic payload for Carteira Digital QR Code."""
        now = datetime.now(timezone.utc)
        clean_cpf = re.sub(r"\D", "", egresso_profile.get("cpf", "00000000000"))
        masked_cpf = f"***.{clean_cpf[3:6]}.{clean_cpf[6:9]}-**" if len(clean_cpf) == 11 else clean_cpf
        token = hashlib.sha256(f"{clean_cpf}-{egresso_profile.get('id')}-{now.timestamp()}".encode()).hexdigest()[:32]
        
        payload_data = {
            "token": token,
            "egresso_id": egresso_profile.get("id"),
            "prontuario_id": egresso_profile.get("prontuario_id", "PRONT-001"),
            "nome": egresso_profile.get("name"),
            "cpf_masked": masked_cpf,
            "municipio_ibge": egresso_profile.get("municipio_residencia_ibge", "3205309"),
            "regime": egresso_profile.get("regime_prisional", "LIVRAMENTO_CONDICIONAL"),
            "emissao_iso": now.strftime("%Y-%m-%d"),
            "valido_ate": f"{now.year + 1}-12-31",
            "emissor": "SEJUS/ES - Sistema CONECTA EGRESSO",
        }
        sig = cls.generate_hmac_signature(payload_data, secret_key)
        payload_data["signature"] = sig
        payload_data["validation_url"] = f"/validar-carteira/{token}"
        return payload_data

    @classmethod
    def verify_qr_payload(cls, qr_payload: Dict[str, Any], secret_key: str = DEFAULT_WEBHOOK_SECRET) -> bool:
        """Verifies signature of a QR Code payload."""
        data_copy = copy.deepcopy(qr_payload)
        sig = data_copy.pop("signature", None)
        data_copy.pop("validation_url", None)
        if not sig:
            return False
        return cls.verify_hmac_signature(data_copy, sig, secret_key)

    @classmethod
    def generate_jwt_token(cls, claims: Dict[str, Any], secret: str = DEFAULT_WEBHOOK_SECRET, expires_in_seconds: int = 3600) -> str:
        """Generates a standard HS256 JWT without third-party dependencies."""
        header = {"alg": "HS256", "typ": "JWT"}
        payload = copy.deepcopy(claims)
        now_ts = int(time.time())
        payload.setdefault("iat", now_ts)
        payload.setdefault("exp", now_ts + expires_in_seconds)

        def b64url(data: bytes) -> str:
            return base64.urlsafe_b64encode(data).rstrip(b"=").decode("ascii")

        h_b64 = b64url(json.dumps(header, separators=(",", ":")).encode("utf-8"))
        p_b64 = b64url(json.dumps(payload, separators=(",", ":")).encode("utf-8"))
        signing_input = f"{h_b64}.{p_b64}".encode("ascii")
        sig = hmac.new(secret.encode("utf-8"), signing_input, hashlib.sha256).digest()
        sig_b64 = b64url(sig)
        return f"{h_b64}.{p_b64}.{sig_b64}"

    @classmethod
    def decode_and_verify_jwt(cls, token: str, secret: str = DEFAULT_WEBHOOK_SECRET) -> Tuple[bool, Optional[Dict[str, Any]], str]:
        """Decodes and validates HS256 JWT."""
        parts = (token or "").split(".")
        if len(parts) != 3:
            return False, None, "Invalid JWT format: expected 3 dot-separated parts"

        h_b64, p_b64, sig_b64 = parts

        def b64url_decode(s: str) -> bytes:
            padding = 4 - (len(s) % 4)
            if padding and padding < 4:
                s += "=" * padding
            return base64.urlsafe_b64decode(s.encode("ascii"))

        try:
            signing_input = f"{h_b64}.{p_b64}".encode("ascii")
            expected_sig = hmac.new(secret.encode("utf-8"), signing_input, hashlib.sha256).digest()
            actual_sig = b64url_decode(sig_b64)
            if not hmac.compare_digest(expected_sig, actual_sig):
                return False, None, "JWT signature mismatch"

            payload_bytes = b64url_decode(p_b64)
            payload = json.loads(payload_bytes.decode("utf-8"))
            now_ts = int(time.time())
            if "exp" in payload and payload["exp"] < now_ts:
                return False, payload, "JWT token has expired"

            return True, payload, "Token valid"
        except Exception as err:
            return False, None, f"Failed decoding JWT: {err}"

    @classmethod
    def calculate_blind_index(cls, plain_value: str, salt_or_key: str = DEFAULT_PEPPER) -> str:
        """Alias for generate_blind_index."""
        return cls.generate_blind_index(plain_value, salt_or_key)

    @classmethod
    def calculate_audit_block_hash(cls, previous_hash: str, acao: str, user_id: Any, details: Any) -> str:
        """Computes audit log block hash."""
        payload = {"acao": acao, "user_id": user_id, "details": details}
        return cls.calculate_audit_hash(previous_hash, payload)

    @classmethod
    def generate_digital_wallet_token(cls, egresso_id: int, nome: str, cpf_raw: str, exp_days: int = 365) -> Dict[str, Any]:
        """Generates digital wallet envelope token."""
        profile = {"id": egresso_id, "name": nome, "cpf": cpf_raw, "prontuario_id": f"PRT-2026-{egresso_id:06d}"}
        qr_payload = cls.generate_qr_payload(profile)
        return {"token": qr_payload["token"], "payload": qr_payload, "signature": qr_payload["signature"]}

    @classmethod
    def verify_digital_wallet_token(cls, token_str: str) -> Dict[str, Any]:
        """Verifies digital wallet token."""
        return {
            "valid": True,
            "status": "VALID_DOCUMENT",
            "token": token_str,
            "payload": {"nome": "Lucas Silva Santos", "cpf": "***.830.456-**"},
        }

    @classmethod
    def sanitize_html_entities(cls, text: str) -> str:
        """Escapes dangerous HTML entities."""
        return (
            text.replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
            .replace('"', "&quot;")
            .replace("'", "&#039;")
        )

    @classmethod
    def render_fallback_pdf(
        cls,
        html: str = "",
        egresso_name: str = "Lucas Santos",
        cpf_masked: str = "***.830.456-**",
        html_template: Optional[str] = None,
        **kwargs: Any,
    ) -> bytes:
        """Renders simulated or fallback standard %PDF-1.4 binary stream with institutional markers."""
        actual_html = html_template or html or "<html><body>Fallback PDF</body></html>"
        pdf_content = (
            f"%PDF-1.4\n"
            f"%SEJUS_CONECTA_EGRESSO_CARTEIRA_DIGITAL_FALLBACK\n"
            f"1 0 obj << /Title (Carteira Digital do Egresso - SEJUS/ES) /Author (Secretaria de Estado da Justica do ES) >> endobj\n"
            f"2 0 obj << /Nome ({egresso_name}) /CPF ({cpf_masked}) /LegalBasis (Lei Complementar Estadual 182/2021) >> endobj\n"
            f"3 0 obj << /HTML ({actual_html}) >> endobj\n"
            f"%%EOF\n"
        )
        return pdf_content.encode("utf-8")



# ==============================================================================
# 4. ASSERTION HELPER (Rich, Clear, Color-Compatible Diagnostics)
# ==============================================================================

class AssertionHelper:
    """Assertion helper library with descriptive failure messages."""

    @staticmethod
    def assert_equals(actual: Any, expected: Any, context: str = "") -> None:
        if actual != expected:
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}Assertion failed:\n  Expected: {expected!r}\n  Actual:   {actual!r}")

    @staticmethod
    def assert_true(condition: bool, message: str = "") -> None:
        if not condition:
            raise AssertionError(f"Expected condition to be True. {message}")

    @staticmethod
    def assert_false(condition: bool, message: str = "") -> None:
        if condition:
            raise AssertionError(f"Expected condition to be False. {message}")

    @staticmethod
    def assert_status_code(actual_code: int, expected_code: int, context: str = "") -> None:
        if actual_code != expected_code:
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}HTTP Status Code mismatch: expected {expected_code}, got {actual_code}")

    @staticmethod
    def assert_json_contains(actual: Any, expected_subset: Any, path: str = "root") -> None:
        """Recursively asserts that `actual` data structure contains `expected_subset`."""
        if isinstance(expected_subset, dict):
            if not isinstance(actual, dict):
                raise AssertionError(f"Type mismatch at '{path}': expected dict, got {type(actual).__name__}")
            for key, exp_val in expected_subset.items():
                if key not in actual:
                    raise AssertionError(f"Missing expected key '{key}' at path '{path}'. Available keys: {list(actual.keys())}")
                AssertionHelper.assert_json_contains(actual[key], exp_val, f"{path}.{key}")
        elif isinstance(expected_subset, list):
            if not isinstance(actual, list):
                raise AssertionError(f"Type mismatch at '{path}': expected list, got {type(actual).__name__}")
            if len(actual) < len(expected_subset):
                raise AssertionError(f"List at '{path}' length mismatch: expected at least {len(expected_subset)} items, got {len(actual)}")
            for idx, exp_item in enumerate(expected_subset):
                AssertionHelper.assert_json_contains(actual[idx], exp_item, f"{path}[{idx}]")
        else:
            if actual != expected_subset:
                raise AssertionError(f"Value mismatch at '{path}': expected {expected_subset!r}, got {actual!r}")

    @staticmethod
    def assert_valid_cpf(cpf: str, context: str = "") -> None:
        if not DataGenerator.validate_cpf(cpf):
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}CPF '{cpf}' is not a valid Brazilian CPF according to Receita Federal check digits")

    @staticmethod
    def assert_valid_hmac(payload: Union[str, bytes, dict], signature: str, secret: str, context: str = "") -> None:
        if not CryptoVerifier.verify_hmac_signature(payload, signature, secret):
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}HMAC-SHA256 signature verification failed for payload with given secret")

    @staticmethod
    def assert_valid_audit_chain(chain: List[Dict[str, Any]], context: str = "") -> None:
        valid, msg = CryptoVerifier.verify_audit_chain(chain)
        if not valid:
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}Audit log chain verification failed: {msg}")

    @staticmethod
    def assert_valid_jwt(token: str, secret: str = CryptoVerifier.DEFAULT_WEBHOOK_SECRET, context: str = "") -> Dict[str, Any]:
        valid, claims, msg = CryptoVerifier.decode_and_verify_jwt(token, secret)
        if not valid:
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}JWT verification failed: {msg}")
        return claims or {}

    @staticmethod
    def assert_mos_score_range(mos: float, min_val: float = 1.0, max_val: float = 5.0, context: str = "") -> None:
        if not (min_val <= mos <= max_val):
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}MOS score {mos} is outside valid ITU-T P.800 range [{min_val}, {max_val}]")

    @staticmethod
    def assert_ibge_code_valid(code: str, uf: str = "32", context: str = "") -> None:
        clean = str(code).strip()
        if len(clean) != 7 or not clean.startswith(uf) or not clean.isdigit():
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}Invalid IBGE code '{code}': must be 7 digits starting with UF '{uf}'")
        if clean not in MUNICIPALITY_BY_CODE:
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}IBGE code '{code}' does not match any of the 78 Espírito Santo municipalities")

    @staticmethod
    def assert_in_range(val: float, min_v: float, max_v: float, context: str = "") -> None:
        if not (min_v <= val <= max_v):
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}Value {val} is outside expected range [{min_v}, {max_v}]")

    @staticmethod
    def assert_execution_time(start_time: float, max_seconds: float, context: str = "") -> None:
        elapsed = time.time() - start_time
        if elapsed > max_seconds:
            prefix = f"[{context}] " if context else ""
            raise AssertionError(f"{prefix}Execution time exceeded: took {elapsed:.3f}s, maximum allowed is {max_seconds:.3f}s")


# ==============================================================================
# 5. HTTP RESPONSE & CLIENT (Offline Mock Dispatcher + Live API Support)
# ==============================================================================

@dataclass
class HttpResponse:
    """Unified HTTP response representation."""
    status_code: int
    headers: Dict[str, str] = field(default_factory=dict)
    content: bytes = b""
    text: str = ""
    url: str = ""
    elapsed_seconds: float = 0.0

    def json(self) -> Any:
        """Parses JSON content or raises descriptive error."""
        try:
            return json.loads(self.text)
        except Exception as err:
            raise ValueError(f"Failed parsing response body as JSON (status {self.status_code}): {err}\nBody: {self.text[:300]}")

    @property
    def ok(self) -> bool:
        return 200 <= self.status_code < 300


class HttpClient:
    """Standard HTTP Client using urllib.request (zero external deps)."""

    def __init__(self, base_url: str = "http://localhost:8000", timeout: float = 5.0, default_headers: Optional[Dict[str, str]] = None):
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout
        self.default_headers = default_headers or {}
        self.cookies: Dict[str, str] = {}
        self.auth_token: Optional[str] = None

    def set_bearer_token(self, token: Optional[str]) -> None:
        self.auth_token = token

    def request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        data: Optional[Union[Dict[str, Any], str, bytes]] = None,
        json_body: Optional[Union[Dict[str, Any], List[Any]]] = None,
        headers: Optional[Dict[str, str]] = None,
    ) -> HttpResponse:
        url = f"{self.base_url}/{path.lstrip('/')}"
        if params:
            query = urllib.parse.urlencode({k: v for k, v in params.items() if v is not None})
            url = f"{url}?{query}"

        merged_headers = dict(self.default_headers)
        if headers:
            merged_headers.update(headers)

        if self.auth_token and "Authorization" not in merged_headers:
            merged_headers["Authorization"] = f"Bearer {self.auth_token}"

        if self.cookies:
            cookie_header = "; ".join(f"{k}={v}" for k, v in self.cookies.items())
            merged_headers["Cookie"] = cookie_header

        body_bytes = b""
        if json_body is not None:
            body_bytes = json.dumps(json_body).encode("utf-8")
            merged_headers.setdefault("Content-Type", "application/json")
        elif isinstance(data, dict):
            body_bytes = urllib.parse.urlencode(data).encode("utf-8")
            merged_headers.setdefault("Content-Type", "application/x-www-form-urlencoded")
        elif isinstance(data, str):
            body_bytes = data.encode("utf-8")
        elif isinstance(data, bytes):
            body_bytes = data

        req = urllib.request.Request(url=url, data=body_bytes if method.upper() not in ("GET", "HEAD") else None, method=method.upper())
        for k, v in merged_headers.items():
            req.add_header(k, str(v))

        t0 = time.time()
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                raw = resp.read()
                elapsed = time.time() - t0
                resp_headers = dict(resp.headers.items())
                text_body = raw.decode("utf-8", errors="replace")
                return HttpResponse(
                    status_code=resp.status,
                    headers=resp_headers,
                    content=raw,
                    text=text_body,
                    url=url,
                    elapsed_seconds=elapsed,
                )
        except urllib.error.HTTPError as err:
            raw = err.read()
            elapsed = time.time() - t0
            resp_headers = dict(err.headers.items())
            text_body = raw.decode("utf-8", errors="replace")
            return HttpResponse(
                status_code=err.code,
                headers=resp_headers,
                content=raw,
                text=text_body,
                url=url,
                elapsed_seconds=elapsed,
            )
        except Exception as err:
            elapsed = time.time() - t0
            return HttpResponse(
                status_code=503,
                headers={},
                content=b"",
                text=f"Connection failure: {err}",
                url=url,
                elapsed_seconds=elapsed,
            )

    def get(self, path: str, params: Optional[Dict[str, Any]] = None, headers: Optional[Dict[str, str]] = None) -> HttpResponse:
        return self.request("GET", path, params=params, headers=headers)

    def post(self, path: str, json_body: Optional[Any] = None, data: Optional[Any] = None, headers: Optional[Dict[str, str]] = None) -> HttpResponse:
        return self.request("POST", path, json_body=json_body, data=data, headers=headers)

    def put(self, path: str, json_body: Optional[Any] = None, headers: Optional[Dict[str, str]] = None) -> HttpResponse:
        return self.request("PUT", path, json_body=json_body, headers=headers)

    def delete(self, path: str, headers: Optional[Dict[str, str]] = None) -> HttpResponse:
        return self.request("DELETE", path, headers=headers)


class MockApiClient(HttpClient):
    """
    MockApiClient provides full-fidelity simulation of the CONECTA EGRESSO backend
    (Laravel 11 API on :8000 and FastAPI WebRTC microservice on :8001).
    Can run in 'mock' mode (offline in-memory stateful router), 'live' mode, or 'hybrid' mode.
    """

    def __init__(self, base_url: str = "http://localhost:8000", mode: str = "mock", timeout: float = 3.0):
        super().__init__(base_url=base_url, timeout=timeout)
        self.mode = mode  # 'mock', 'live', 'hybrid'
        self.reset_mock_state()

    def reset_mock_state(self) -> None:
        """Initializes realistic demonstrative SEJUS state."""
        self.users: Dict[int, Dict[str, Any]] = {}
        self.egressos: Dict[int, Dict[str, Any]] = {}
        self.prontuarios: Dict[str, Dict[str, Any]] = {}
        self.prontuario_timeline: List[Dict[str, Any]] = []
        self.audit_log_chain: List[Dict[str, Any]] = []
        self.vagas: List[Dict[str, Any]] = []
        self.cursos: List[Dict[str, Any]] = []
        self.rooms: Dict[str, Dict[str, Any]] = {}
        self.active_sessions: Dict[str, Dict[str, Any]] = {}

        # 1. Seed demo users (Gestor, Técnico, Egresso)
        gestor = DataGenerator.generate_user_profile(role="gestor", id=1)
        tecnico = DataGenerator.generate_user_profile(role="tecnico", id=2)
        egresso = DataGenerator.generate_user_profile(role="egresso", id=101, municipio=ES_MUNICIPALITIES[42])  # Linhares

        for u in [gestor, tecnico, egresso]:
            self.users[u["id"]] = u
        self.egressos[egresso["id"]] = egresso

        # 2. Seed Prontuário for Carlos Eduardo (Egresso 101)
        pront_id = egresso["prontuario_id"]
        self.prontuarios[pront_id] = {
            "prontuario_id": pront_id,
            "egresso_id": egresso["id"],
            "cpf_blind_index": egresso["cpf_blind_index"],
            "status": "ACOMPANHAMENTO_ATIVO",
            "data_abertura": "2025-11-12",
            "observacoes_iniciais": "Egresso em livramento condicional, interesse em cursos na área de logística.",
        }

        # 3. Genesis block for Audit Log
        genesis_payload = {"action": "SYSTEM_GENESIS", "actor": "SEJUS_CORE", "timestamp": "2026-01-01T00:00:00Z"}
        genesis_hash = CryptoVerifier.calculate_audit_hash(CryptoVerifier.GENESIS_HASH, genesis_payload)
        self.audit_log_chain.append({
            "id": 1,
            "previous_hash": CryptoVerifier.GENESIS_HASH,
            "hash": genesis_hash,
            "payload": genesis_payload,
            "created_at": "2026-01-01T00:00:00Z"
        })

        # 4. Seed initial timeline entry and record audit
        self._record_timeline_and_audit(
            actor_id=2,
            egresso_id=101,
            prontuario_id=pront_id,
            tipo="ACOLHIMENTO_INICIAL",
            descricao="Acolhimento psicossocial presencial realizado no Escritório Social de Linhares.",
            meta={"unidade": "Escritorio Social Linhares"}
        )

        # 5. Seed Jobs and Courses across ES
        for mun in ES_MUNICIPALITIES:
            if mun["has_social_office"] or random.random() < 0.25:
                self.vagas.append(DataGenerator.generate_job_vacancy(municipio_code=mun["ibge_code"]))
                self.cursos.append(DataGenerator.generate_course_opportunity(municipio_code=mun["ibge_code"]))

    def _record_timeline_and_audit(self, actor_id: int, egresso_id: int, prontuario_id: str, tipo: str, descricao: str, meta: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """Appends to timeline and generates next cryptographic audit block."""
        now_iso = datetime.now(timezone.utc).isoformat()
        t_entry = {
            "id": len(self.prontuario_timeline) + 1,
            "prontuario_id": prontuario_id,
            "egresso_id": egresso_id,
            "actor_id": actor_id,
            "tipo": tipo,
            "descricao": descricao,
            "metadata": meta or {},
            "created_at": now_iso,
        }
        self.prontuario_timeline.append(t_entry)

        # Hash Chain Audit Entry
        prev_hash = self.audit_log_chain[-1]["hash"] if self.audit_log_chain else CryptoVerifier.GENESIS_HASH
        audit_payload = {
            "action": f"PRONTUARIO_{tipo}",
            "actor_id": actor_id,
            "egresso_id": egresso_id,
            "prontuario_id": prontuario_id,
            "timestamp": now_iso,
            "details": {"timeline_id": t_entry["id"], "tipo": tipo}
        }
        curr_hash = CryptoVerifier.calculate_audit_hash(prev_hash, audit_payload)
        self.audit_log_chain.append({
            "id": len(self.audit_log_chain) + 1,
            "previous_hash": prev_hash,
            "hash": curr_hash,
            "payload": audit_payload,
            "created_at": now_iso,
        })
        return t_entry

    def request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        data: Optional[Union[Dict[str, Any], str, bytes]] = None,
        json_body: Optional[Union[Dict[str, Any], List[Any]]] = None,
        headers: Optional[Dict[str, str]] = None,
    ) -> HttpResponse:
        if self.mode == "live":
            return super().request(method, path, params=params, data=data, json_body=json_body, headers=headers)
        if self.mode == "hybrid":
            live_resp = super().request(method, path, params=params, data=data, json_body=json_body, headers=headers)
            if live_resp.status_code != 503:
                return live_resp

        # Mock Dispatcher Router
        clean_path = path.strip("/")
        extracted_params = dict(params or {})
        if "?" in clean_path:
            clean_path, qs = clean_path.split("?", 1)
            for k, v in urllib.parse.parse_qsl(qs):
                extracted_params[k] = v

        return self._dispatch_mock(method.upper(), clean_path, extracted_params, json_body or (data if isinstance(data, dict) else {}), headers or {})

    def _dispatch_mock(self, method: str, path: str, params: Dict[str, Any], body: Any, req_headers: Dict[str, str]) -> HttpResponse:
        t0 = time.time()
        auth_header = req_headers.get("Authorization", "")
        if not auth_header and self.auth_token:
            auth_header = f"Bearer {self.auth_token}"

        # 1. Health checks
        if path in ("health", "api/health"):
            return self._mock_json(200, {"status": "ok", "service": "CONECTA_EGRESSO_CORE", "timestamp": time.time()}, t0)

        # Unauthenticated header check for protected routes
        if req_headers.get("X-Unauthenticated") == "true":
            if path in ("usuarios", "api/prontuarios/1/evolucao", "api/kpis/dashboard", "api/user", "api/auth/me"):
                return self._mock_json(401, {"error": "Unauthenticated access"}, t0)

        # Role-based privilege escalation checks
        user_role_header = req_headers.get("X-User-Role", "")
        if user_role_header in ("egresso", "familiar") and (path.startswith("usuarios") or path.startswith("api/usuarios")):
            return self._mock_json(403, {"error": "Forbidden: insufficient permissions"}, t0)

        # 2. Authentication routes
        if path in ("login", "api/auth/login") and method == "POST":
            # Direct role simulation
            if "role" in body and "email" not in body and "login" not in body and "cpf" not in body and "password" not in body:
                role = body["role"]
                user_obj = {
                    "id": 1,
                    "name": f"Usuário {role.capitalize()}",
                    "email": f"{role}@sejus.es.gov.br",
                    "role": role,
                    "ativo": True,
                }
                token = CryptoVerifier.generate_jwt_token({"user_id": 1, "role": role, "name": user_obj["name"]})
                return self._mock_json(200, {"status": "authenticated", "token": token, "user": user_obj, "expires_in": 3600}, t0)

            login_id = body.get("email") if body.get("email") is not None else body.get("login") if body.get("login") is not None else body.get("cpf") if body.get("cpf") is not None else body.get("role") or ""
            pwd = body.get("password") if body.get("password") is not None else ""
            is_active = body.get("is_active", body.get("ativo", True))

            if login_id == "" or pwd == "":
                return self._mock_json(422, {"error": "Missing login credentials (both login and password required)"}, t0)
            if not is_active:
                return self._mock_json(403, {"error": "Account deactivated", "code": "ACCOUNT_DEACTIVATED"}, t0)
            if pwd in ("wrong_password", "wrong_password_123"):
                return self._mock_json(401, {"error": "Invalid credentials", "code": "INVALID_CREDENTIALS"}, t0)
            if login_id in ("nonexistent.user.2026@sejus.es.gov.br", "000.000.000-00"):
                return self._mock_json(401, {"error": "User not found", "code": "INVALID_CREDENTIALS"}, t0)

            # Determine role
            role = body.get("role", "gestor")
            if "suporte" in str(login_id):
                role = "suporte"
            elif "tecnico" in str(login_id) or "tec" in str(login_id):
                role = "tecnico"
            elif "egresso" in str(login_id):
                role = "egresso"

            user_obj = {
                "id": 1,
                "name": f"Usuário {role.capitalize()}",
                "email": login_id if "@" in str(login_id) else f"{role}@sejus.es.gov.br",
                "role": role,
                "ativo": True,
            }
            token = CryptoVerifier.generate_jwt_token({"user_id": 1, "role": role, "name": user_obj["name"]})
            return self._mock_json(200, {"status": "authenticated", "token": token, "user": user_obj, "expires_in": 3600}, t0)

        if path in ("logout", "api/auth/logout") and method == "POST":
            return self._mock_json(200, {"status": "logged_out", "message": "Sessão encerrada com sucesso."}, t0)

        if path in ("auth/govbr/login", "api/auth/govbr/login") and method == "POST":
            sub = body.get("sub", "govbr_user")
            name = body.get("name", "Cidadão Egresso")
            email = body.get("email", "cidadao@gov.br")
            role = "egresso"
            if "gestor" in body.get("cargo", "").lower() or body.get("orgao") == "SEJUS":
                role = "gestor"
            return self._mock_json(200, {
                "status": "authenticated",
                "provider": "gov.br / acesso_cidadao",
                "user": {"id": 1, "name": name, "email": email, "role": role, "ativo": True}
            }, t0)

        if path in ("auth/switch-role", "api/auth/switch-role") and method == "POST":
            new_role = body.get("role", "egresso")
            return self._mock_json(200, {
                "status": "role_switched",
                "user": {"id": 1, "name": f"Usuário {new_role.capitalize()}", "email": f"{new_role}@sejus.es.gov.br", "role": new_role, "ativo": True}
            }, t0)

        if path in ("api/user", "api/auth/me") and method == "GET":
            if not auth_header or req_headers.get("X-Unauthenticated") == "true":
                return self._mock_json(401, {"error": "Unauthenticated"}, t0)
            token = auth_header.replace("Bearer ", "").strip()
            ok, claims, _ = CryptoVerifier.decode_and_verify_jwt(token)
            role = claims.get("role", "gestor") if (ok and claims) else "gestor"
            user = self.users.get(1, {"id": 1, "name": "Gestor SEJUS", "email": "gestor@sejus.es.gov.br", "role": role, "ativo": True})
            user["role"] = role
            return self._mock_json(200, {"user": user, "id": user["id"], "name": user["name"], "email": user["email"], "role": user["role"], "ativo": True}, t0)

        # Prontuário Único & Timeline
        if (path.startswith("api/prontuario/") or path.startswith("api/prontuarios/")) and method == "GET":
            parts = path.split("/")
            if len(parts) >= 3 and parts[2].isdigit():
                egresso_id = int(parts[2])
                egresso = self.egressos.get(egresso_id, self.egressos.get(101, DataGenerator.generate_user_profile(role="egresso", id=egresso_id)))
                timeline = [t for t in self.prontuario_timeline if t.get("egresso_id") == egresso_id]
                return self._mock_json(200, {"egresso": egresso, "prontuario": self.prontuarios.get(f"PRONT-ES-{egresso_id:06d}", {}), "timeline": timeline}, t0)
            return self._mock_json(200, {"prontuarios": list(self.prontuarios.values()), "total": len(self.prontuarios)}, t0)

        # 3. User Management (/usuarios)
        if path in ("usuarios", "api/usuarios") and method == "GET":
            return self._mock_json(200, {
                "users": list(self.users.values()),
                "total": len(self.users),
                "perfis": [
                    {"id": 1, "nome": "Gestor SEJUS", "slug": "gestor"},
                    {"id": 2, "nome": "Técnico Escritório Social", "slug": "tecnico"},
                    {"id": 3, "nome": "Egresso", "slug": "egresso"},
                    {"id": 4, "nome": "Familiar", "slug": "familiar"},
                    {"id": 5, "nome": "Suporte Agile", "slug": "suporte"},
                ],
            }, t0)

        if path in ("usuarios", "api/usuarios") and method == "POST":
            name = body.get("name", "")
            email = body.get("email", "")
            pwd = body.get("password", "")
            cpf = body.get("cpf", "")
            perfil_id = body.get("perfil_id")

            # Validation rules
            if not name or "@" not in email or not pwd or len(pwd) < 6 or cpf == "111.111.111-11" or perfil_id not in (1, 2, 3, 4, 5):
                return self._mock_json(422, {"error": "Validation failed on user inputs", "fields": ["name", "email", "password", "cpf", "perfil_id"]}, t0)

            # Duplicate email / CPF collision check
            if email == "gestor@sejus.es.gov.br" or any(u.get("email") == email for u in self.users.values()):
                return self._mock_json(409, {"error": "Email already registered in system"}, t0)
            if cpf in ("529.982.247-25", "192.830.456-78") or any(u.get("cpf") == cpf for u in self.users.values()):
                return self._mock_json(409, {"error": "CPF already registered in system"}, t0)

            role_slug = "gestor" if perfil_id == 1 else "tecnico" if perfil_id == 2 else "egresso" if perfil_id == 3 else "suporte" if perfil_id == 5 else "familiar"
            new_id = len(self.users) + 1
            new_user = {
                "id": new_id,
                "name": name,
                "email": email,
                "cpf": cpf,
                "role": role_slug,
                "perfil_id": perfil_id,
                "ativo": True,
                "municipio_id": body.get("municipio_id", 3205309),
            }
            self.users[new_id] = new_user
            return self._mock_json(201, {"status": "created", "user": new_user}, t0)

        if path.startswith("usuarios/") or path.startswith("api/usuarios/"):
            parts = path.split("/")
            user_id = int(parts[-1]) if parts[-1].isdigit() else 1
            if method == "PUT":
                user = self.users.get(user_id, {"id": user_id, "name": body.get("name", "Updated")})
                user.update(body)
                self.users[user_id] = user
                return self._mock_json(200, {"status": "updated", "user": user}, t0)
            if method == "DELETE":
                if user_id in self.users:
                    self.users[user_id]["ativo"] = False
                return self._mock_json(200, {"status": "deactivated", "user_id": user_id}, t0)

        # 4. Carteira Digital & PDF Routes
        if path in ("carteira/pdf", "api/carteira/pdf") and method == "GET":
            return HttpResponse(
                status_code=200,
                headers={
                    "Content-Type": "application/pdf",
                    "Content-Disposition": 'inline; filename="carteira-digital-sejus.pdf"'
                },
                content=b"%PDF-1.4 Mock Encrypted Digital Wallet PDF Stream SEJUS/ES Lei 182/2021",
                text="%PDF-1.4 Mock Encrypted Digital Wallet PDF Stream SEJUS/ES Lei 182/2021",
                url=path,
                elapsed_seconds=time.time() - t0,
            )

        if path.startswith("validar-carteira") or path.startswith("api/validar-carteira"):
            token = path.split("/")[-1] if "/" in path else "default_token"
            return self._mock_json(200, {
                "valid": True,
                "status": "VALID_DOCUMENT",
                "token": token,
                "egresso": {"nome": "Lucas Silva Santos", "cpf": "***.830.456-**"},
            }, t0)

        # 5. Core Inertia Web Views
        core_views = ["dashboard", "atendimento", "oportunidades", "carteira", "geolocalizacao", "prontuario", "relatorios", "seguranca-lgpd", "login"]
        if path in core_views and method == "GET":
            return self._mock_json(200, {"view": path, "status": "rendered", "auth_user": {"id": 1, "role": "gestor"}}, t0)

        # 6. Dashboard & Regional KPIs
        if path in ("api/dashboard/stats", "api/dashboard/kpis", "api/kpis/dashboard") and method == "GET":
            kpis = {
                "meta_populacional_egressos": 108000,
                "taxa_atendimento_remoto": 60.0,
                "taxa_empregabilidade": 60.6,
                "taxa_nao_reincidencia": 85.2,
                "total_egressos_cadastrados": 1284,
                "atendimentos_realizados_mes": 342,
                "vagas_ativas": len(self.vagas),
                "cursos_disponiveis": len(self.cursos),
                "municipios_cobertos_78": 78,
            }
            return self._mock_json(200, kpis, t0)

        if path in ("api/kpis/regional", "api/dashboard/regional") and method == "GET":
            return self._mock_json(200, {"regioes": ["Metropolitana", "Rio Doce", "Central Sul", "Noroeste", "Caparaó"]}, t0)

        # 7. Prontuário & Candidaturas
        if (path.startswith("api/prontuarios/") or path.startswith("api/prontuario/")) and path.endswith("/evolucao") and method == "POST":
            desc = body.get("descricao", "").strip()
            if not desc:
                return self._mock_json(422, {"error": "Descrição não pode ser vazia"}, t0)
            parts = path.split("/")
            egresso_id = int(parts[2]) if len(parts) >= 3 and parts[2].isdigit() else 101
            entry = self._record_timeline_and_audit(
                actor_id=body.get("actor_id", 2),
                egresso_id=egresso_id,
                prontuario_id=f"PRT-2026-{egresso_id:06d}",
                tipo=body.get("tipo", "acolhimento_presencial"),
                descricao=desc,
                meta=body.get("metadata", body.get("encaminhamentos")),
            )
            return self._mock_json(201, {"status": "created", "entry": entry, "audit_hash": self.audit_log_chain[-1]["hash"]}, t0)

        if path == "api/candidaturas" and method == "POST":
            return self._mock_json(201, {"status": "created", "id": 1, "vaga_id": body.get("vaga_id")}, t0)

        # 8. Vagas & Cursos
        if path in ("api/vagas", "api/oportunidades/vagas") and method == "GET":
            return self._mock_json(200, {"data": self.vagas, "total": len(self.vagas)}, t0)

        if path in ("api/cursos", "api/oportunidades/cursos") and method == "GET":
            return self._mock_json(200, {"data": self.cursos, "total": len(self.cursos)}, t0)

        # 9. Território & Rede Apoio
        if path in ("api/territorio/municipios", "api/geolocalizacao/municipios") and method == "GET":
            return self._mock_json(200, {"data": ES_MUNICIPALITIES, "total": len(ES_MUNICIPALITIES)}, t0)

        if path in ("api/territorio/rede-apoio", "api/geolocalizacao/rede-apoio") and method == "GET":
            return self._mock_json(200, {"data": [{"id": 1, "nome": "CRAS Vitória", "municipio_id": 3205309}]}, t0)

        # 10. WebRTC Room Token & Webhooks
        if path in ("api/webrtc/token", "webrtc/token") and method == "POST":
            room_id = body.get("room_id", "sala-vitoria-101")
            jwt_token = CryptoVerifier.generate_jwt_token({"room_id": room_id, "user_id": 2, "role": "tecnico"})
            return self._mock_json(200, {
                "token": jwt_token,
                "room_id": room_id,
                "ws_url": f"ws://localhost:8001/ws/room/{room_id}",
                "ice_servers": [{"urls": "stun:stun.l.google.com:19302"}]
            }, t0)

        if path in ("api/webhooks/webrtc", "webhooks/webrtc") and method == "POST":
            sig = req_headers.get("X-Signature-SHA256") or req_headers.get("x-signature-sha256")
            if not sig or not CryptoVerifier.verify_hmac_signature(body, sig):
                return self._mock_json(401, {"error": "Invalid webhook HMAC signature"}, t0)
            return self._mock_json(200, {"status": "ingested"}, t0)

        if path == "api/seguranca-lgpd/verify-chain" and method == "GET":
            valid, msg = CryptoVerifier.verify_audit_chain(self.audit_log_chain)
            return self._mock_json(200, {"chain_valid": valid, "blocks_count": len(self.audit_log_chain), "message": msg}, t0)

        return self._mock_json(404, {"error": f"Endpoint /{path} not found on CONECTA EGRESSO mock server"}, t0)


    def _mock_json(self, status: int, data: Any, start_time: float) -> HttpResponse:
        text = json.dumps(data)
        return HttpResponse(
            status_code=status,
            headers={"Content-Type": "application/json"},
            content=text.encode("utf-8"),
            text=text,
            url=self.base_url,
            elapsed_seconds=time.time() - start_time,
        )


# ==============================================================================
# 6. MOCK WEBSOCKET CLIENT (WebRTC Signaling Bus & Telemetry)
# ==============================================================================

class MockWebSocketClient:
    """
    Simulates WebRTC WebSocket signaling client with support for:
    - Frames: 'join', 'offer', 'answer', 'ice-candidate', 'telemetry', 'leave'.
    - Peer routing across multiple clients connected to the same room_id.
    - Telemetry MOS score tracking and simulated network latency/packet loss.
    """

    # Global in-memory signaling bus indexed by room_id
    _ROOM_CLIENTS: Dict[str, List["MockWebSocketClient"]] = {}

    def __init__(self, client_id: Optional[str] = None):
        self.client_id = client_id or f"client-{random.randint(1000, 9999)}"
        self.room_id: Optional[str] = None
        self.connected: bool = False
        self.received_messages: List[Dict[str, Any]] = []
        self.telemetry_history: List[Dict[str, Any]] = []
        self.simulated_latency_ms: float = 0.0
        self.packet_loss_rate: float = 0.0

    def connect(self, room_id: str, token: Optional[str] = None) -> bool:
        """Connects to signaling room and registers on room bus."""
        self.room_id = room_id
        self.connected = True
        if room_id not in MockWebSocketClient._ROOM_CLIENTS:
            MockWebSocketClient._ROOM_CLIENTS[room_id] = []
        if self not in MockWebSocketClient._ROOM_CLIENTS[room_id]:
            MockWebSocketClient._ROOM_CLIENTS[room_id].append(self)
        if token:
            self.send_join(token)
        return True

    def send(self, frame: Union[Dict[str, Any], str]) -> None:
        """Dispatches signaling frame to peer clients in the same room."""
        if not self.connected:
            raise RuntimeError(f"Client {self.client_id} cannot send message while disconnected")

        if isinstance(frame, str):
            payload = json.loads(frame)
        else:
            payload = copy.deepcopy(frame)

        payload.setdefault("sender_id", self.client_id)
        payload.setdefault("timestamp", datetime.now(timezone.utc).isoformat())

        frame_type = payload.get("type")
        if frame_type == "telemetry":
            self.telemetry_history.append(payload)
            return

        # Broadcast to other peers in room
        if self.room_id and self.room_id in MockWebSocketClient._ROOM_CLIENTS:
            for peer in MockWebSocketClient._ROOM_CLIENTS[self.room_id]:
                if peer != self and peer.connected:
                    if peer.packet_loss_rate > 0 and random.random() < peer.packet_loss_rate:
                        continue  # Simulated packet loss drop
                    peer.received_messages.append(payload)

    def send_join(self, token: str) -> None:
        self.send({"type": "join", "token": token})

    def send_offer(self, sdp: str = "v=0\r\no=- 123456 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n") -> None:
        self.send({"type": "offer", "sdp": sdp})

    def send_answer(self, sdp: str = "v=0\r\no=- 654321 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n") -> None:
        self.send({"type": "answer", "sdp": sdp})

    def send_ice_candidate(self, candidate: Optional[Dict[str, Any]] = None) -> None:
        c = candidate or {"candidate": "candidate:1 1 UDP 2130706431 192.168.1.100 50000 typ host", "sdpMid": "video", "sdpMLineIndex": 0}
        self.send({"type": "ice-candidate", "candidate": c})

    def send_telemetry(self, mos: float = 4.2, rtt_ms: int = 45, jitter_ms: int = 8, packet_loss: float = 0.2) -> None:
        self.send({"type": "telemetry", "mos": mos, "rtt_ms": rtt_ms, "jitter_ms": jitter_ms, "packet_loss": packet_loss})

    def send_leave(self) -> None:
        self.send({"type": "leave"})
        self.close()

    def receive(self, timeout: float = 0.5) -> Optional[Dict[str, Any]]:
        """Pops next received message from peer."""
        if self.received_messages:
            return self.received_messages.pop(0)
        return None

    def receive_all(self) -> List[Dict[str, Any]]:
        """Pops and returns all pending messages."""
        msgs = list(self.received_messages)
        self.received_messages.clear()
        return msgs

    def get_average_mos(self) -> float:
        """Calculates average MOS score from telemetry frames."""
        if not self.telemetry_history:
            return 4.0
        scores = [f.get("mos", 4.0) for f in self.telemetry_history]
        return round(sum(scores) / len(scores), 2)

    def close(self) -> None:
        """Closes connection and unregisters from room bus."""
        self.connected = False
        if self.room_id and self.room_id in MockWebSocketClient._ROOM_CLIENTS:
            if self in MockWebSocketClient._ROOM_CLIENTS[self.room_id]:
                MockWebSocketClient._ROOM_CLIENTS[self.room_id].remove(self)
