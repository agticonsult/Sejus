"""
Scenario 1: Gestor SEJUS Global Audit & Analytics (F14, F15, F16, F21, F22, F45, F46)
=======================================================================================
Target Profile: Gestor SEJUS (Carlos Eduardo Silva - Subsecretaria de Reintegração Social)

Complete End-to-End Operational Workflow:
1. Gestor authenticates via Gov.br / Acesso Cidadão OIDC simulation.
2. Accesses Management Dashboard and verifies KPI statistics across all 78 Espírito Santo municipalities.
3. Filters territorial analytics by micro-region (e.g. Região Metropolitana vs Rio Doce / Norte).
4. Accesses Security & LGPD Audit Viewer.
5. Verifies cryptographic SHA-256 hash chaining of audit logs across recent interventions.
6. Inspects system telemetry and exports attendance metrics report.
"""

import unittest
import json
import hashlib
import hmac
import time
import base64
from typing import Dict, List, Any, Optional

# Official Full List of all 78 Espírito Santo Municipalities with IBGE Codes, Microregions, Coordinates and Baseline Demand
ES_78_MUNICIPALITIES: List[Dict[str, Any]] = [
    {"ibge": "3200102", "nome": "Afonso Cláudio", "microregiao": "Central", "lat": -20.0764, "lng": -41.1367, "demand": 190},
    {"ibge": "3200136", "nome": "Água Doce do Norte", "microregiao": "Noroeste", "lat": -18.5469, "lng": -40.9856, "demand": 90},
    {"ibge": "3200169", "nome": "Águia Branca", "microregiao": "Rio Doce", "lat": -18.9839, "lng": -40.7403, "demand": 85},
    {"ibge": "3200201", "nome": "Alegre", "microregiao": "Caparaó", "lat": -20.7639, "lng": -41.5331, "demand": 220},
    {"ibge": "3200300", "nome": "Alfredo Chaves", "microregiao": "Litoral Sul", "lat": -20.6358, "lng": -40.7511, "demand": 110},
    {"ibge": "3200359", "nome": "Alto Rio Novo", "microregiao": "Noroeste", "lat": -19.0569, "lng": -41.0169, "demand": 80},
    {"ibge": "3200409", "nome": "Anchieta", "microregiao": "Litoral Sul", "lat": -20.8058, "lng": -40.6456, "demand": 210},
    {"ibge": "3200508", "nome": "Apiacá", "microregiao": "Caparaó", "lat": -21.1542, "lng": -41.5681, "demand": 60},
    {"ibge": "3200607", "nome": "Aracruz", "microregiao": "Rio Doce", "lat": -19.8203, "lng": -40.2733, "demand": 450},
    {"ibge": "3200706", "nome": "Atílio Vivácqua", "microregiao": "Caparaó", "lat": -20.9147, "lng": -41.1983, "demand": 80},
    {"ibge": "3200805", "nome": "Baixo Guandu", "microregiao": "Central", "lat": -19.5189, "lng": -41.0147, "demand": 210},
    {"ibge": "3200904", "nome": "Barra de São Francisco", "microregiao": "Rio Doce", "lat": -18.7553, "lng": -40.8908, "demand": 280},
    {"ibge": "3201001", "nome": "Boa Esperança", "microregiao": "Noroeste", "lat": -18.5400, "lng": -40.2958, "demand": 115},
    {"ibge": "3201100", "nome": "Bom Jesus do Norte", "microregiao": "Caparaó", "lat": -21.1906, "lng": -41.6706, "demand": 75},
    {"ibge": "3201159", "nome": "Brejetuba", "microregiao": "Serrana", "lat": -20.1444, "lng": -41.2917, "demand": 85},
    {"ibge": "3201209", "nome": "Cachoeiro de Itapemirim", "microregiao": "Sul Central", "lat": -20.8489, "lng": -41.1128, "demand": 980},
    {"ibge": "3201308", "nome": "Cariacica", "microregiao": "Metropolitana", "lat": -20.2643, "lng": -40.4206, "demand": 2100},
    {"ibge": "3201407", "nome": "Castelo", "microregiao": "Serrana", "lat": -20.6036, "lng": -41.2036, "demand": 240},
    {"ibge": "3201506", "nome": "Colatina", "microregiao": "Rio Doce", "lat": -19.5392, "lng": -40.6306, "demand": 740},
    {"ibge": "3201605", "nome": "Conceição da Barra", "microregiao": "Rio Doce", "lat": -18.5933, "lng": -39.7322, "demand": 210},
    {"ibge": "3201704", "nome": "Conceição do Castelo", "microregiao": "Serrana", "lat": -20.3686, "lng": -41.2439, "demand": 95},
    {"ibge": "3201753", "nome": "Divino de São Lourenço", "microregiao": "Caparaó", "lat": -20.6203, "lng": -41.6853, "demand": 45},
    {"ibge": "3201803", "nome": "Domingos Martins", "microregiao": "Serrana", "lat": -20.3633, "lng": -40.6592, "demand": 160},
    {"ibge": "3201852", "nome": "Dores do Rio Preto", "microregiao": "Caparaó", "lat": -20.6894, "lng": -41.8456, "demand": 55},
    {"ibge": "3201902", "nome": "Ecoporanga", "microregiao": "Noroeste", "lat": -18.3733, "lng": -40.8306, "demand": 160},
    {"ibge": "3202207", "nome": "Fundão", "microregiao": "Metropolitana", "lat": -19.9333, "lng": -40.4056, "demand": 190},
    {"ibge": "3202256", "nome": "Governador Lindenberg", "microregiao": "Central", "lat": -19.2778, "lng": -40.6000, "demand": 75},
    {"ibge": "3202306", "nome": "Guaçuí", "microregiao": "Caparaó", "lat": -20.7761, "lng": -41.6792, "demand": 210},
    {"ibge": "3202405", "nome": "Guarapari", "microregiao": "Metropolitana", "lat": -20.6714, "lng": -40.4975, "demand": 680},
    {"ibge": "3202454", "nome": "Ibatiba", "microregiao": "Caparaó", "lat": -20.2339, "lng": -41.5111, "demand": 185},
    {"ibge": "3202504", "nome": "Ibiraçu", "microregiao": "Rio Doce", "lat": -19.8319, "lng": -40.3606, "demand": 120},
    {"ibge": "3202553", "nome": "Ibitirama", "microregiao": "Caparaó", "lat": -20.5408, "lng": -41.6669, "demand": 70},
    {"ibge": "3202603", "nome": "Iconha", "microregiao": "Litoral Sul", "lat": -20.7931, "lng": -40.8106, "demand": 95},
    {"ibge": "3202652", "nome": "Irupi", "microregiao": "Caparaó", "lat": -20.3456, "lng": -41.6406, "demand": 95},
    {"ibge": "3202702", "nome": "Itaguaçu", "microregiao": "Central", "lat": -19.8022, "lng": -40.8561, "demand": 110},
    {"ibge": "3202801", "nome": "Itapemirim", "microregiao": "Litoral Sul", "lat": -21.0111, "lng": -40.8339, "demand": 230},
    {"ibge": "3202900", "nome": "Itarana", "microregiao": "Central", "lat": -19.8739, "lng": -40.8753, "demand": 85},
    {"ibge": "3203007", "nome": "Iúna", "microregiao": "Caparaó", "lat": -20.3458, "lng": -41.5358, "demand": 190},
    {"ibge": "3203106", "nome": "Jerônimo Monteiro", "microregiao": "Caparaó", "lat": -20.7897, "lng": -41.3958, "demand": 85},
    {"ibge": "3203130", "nome": "Jaguaré", "microregiao": "Rio Doce", "lat": -18.9061, "lng": -40.0761, "demand": 195},
    {"ibge": "3203155", "nome": "João Neiva", "microregiao": "Rio Doce", "lat": -19.7569, "lng": -40.3847, "demand": 130},
    {"ibge": "3203163", "nome": "Laranja da Terra", "microregiao": "Central", "lat": -19.8986, "lng": -41.0558, "demand": 75},
    {"ibge": "3203205", "nome": "Linhares", "microregiao": "Rio Doce", "lat": -19.3911, "lng": -40.0722, "demand": 1150},
    {"ibge": "3203304", "nome": "Mantenópolis", "microregiao": "Noroeste", "lat": -18.8628, "lng": -41.1228, "demand": 105},
    {"ibge": "3203320", "nome": "Marataízes", "microregiao": "Litoral Sul", "lat": -21.0433, "lng": -40.8244, "demand": 260},
    {"ibge": "3203353", "nome": "Marechal Floriano", "microregiao": "Serrana", "lat": -20.4131, "lng": -40.6831, "demand": 120},
    {"ibge": "3203403", "nome": "Marilândia", "microregiao": "Central", "lat": -19.4136, "lng": -40.5414, "demand": 90},
    {"ibge": "3203502", "nome": "Mimoso do Sul", "microregiao": "Caparaó", "lat": -21.0642, "lng": -41.3664, "demand": 170},
    {"ibge": "3203601", "nome": "Montanha", "microregiao": "Noroeste", "lat": -18.1269, "lng": -40.3633, "demand": 125},
    {"ibge": "3203700", "nome": "Mucurici", "microregiao": "Noroeste", "lat": -18.0933, "lng": -40.5161, "demand": 70},
    {"ibge": "3203759", "nome": "Muniz Freire", "microregiao": "Caparaó", "lat": -20.4642, "lng": -41.4131, "demand": 130},
    {"ibge": "3203809", "nome": "Muqui", "microregiao": "Caparaó", "lat": -20.9525, "lng": -41.3458, "demand": 105},
    {"ibge": "3203908", "nome": "Nova Venécia", "microregiao": "Rio Doce", "lat": -18.7106, "lng": -40.4006, "demand": 310},
    {"ibge": "3204005", "nome": "Pancas", "microregiao": "Noroeste", "lat": -19.2250, "lng": -40.8514, "demand": 140},
    {"ibge": "3204054", "nome": "Pedro Canário", "microregiao": "Rio Doce", "lat": -18.0297, "lng": -40.1497, "demand": 140},
    {"ibge": "3204104", "nome": "Pinheiros", "microregiao": "Rio Doce", "lat": -18.4239, "lng": -40.2144, "demand": 160},
    {"ibge": "3204203", "nome": "Piúma", "microregiao": "Litoral Sul", "lat": -20.8336, "lng": -40.7297, "demand": 140},
    {"ibge": "3204252", "nome": "Ponto Belo", "microregiao": "Noroeste", "lat": -18.1242, "lng": -40.5372, "demand": 65},
    {"ibge": "3204302", "nome": "Presidente Kennedy", "microregiao": "Litoral Sul", "lat": -21.0967, "lng": -41.0483, "demand": 120},
    {"ibge": "3204351", "nome": "Rio Bananal", "microregiao": "Rio Doce", "lat": -19.2647, "lng": -40.3325, "demand": 135},
    {"ibge": "3204401", "nome": "Rio Novo do Sul", "microregiao": "Litoral Sul", "lat": -20.8631, "lng": -40.9367, "demand": 85},
    {"ibge": "3204500", "nome": "Santa Leopoldina", "microregiao": "Central", "lat": -20.1006, "lng": -40.5297, "demand": 95},
    {"ibge": "3204559", "nome": "Santa Maria de Jetibá", "microregiao": "Central", "lat": -20.0400, "lng": -40.7461, "demand": 180},
    {"ibge": "3204609", "nome": "Santa Teresa", "microregiao": "Central", "lat": -19.9364, "lng": -40.6006, "demand": 140},
    {"ibge": "3204658", "nome": "São Domingos do Norte", "microregiao": "Central", "lat": -19.1417, "lng": -40.6125, "demand": 85},
    {"ibge": "3204708", "nome": "São Gabriel da Palha", "microregiao": "Rio Doce", "lat": -19.0169, "lng": -40.5361, "demand": 230},
    {"ibge": "3204807", "nome": "São Roque do Canaã", "microregiao": "Central", "lat": -19.7389, "lng": -40.6558, "demand": 80},
    {"ibge": "3204856", "nome": "São José do Calçado", "microregiao": "Caparaó", "lat": -21.0253, "lng": -41.6547, "demand": 80},
    {"ibge": "3204906", "nome": "São Mateus", "microregiao": "Rio Doce", "lat": -18.7161, "lng": -39.8589, "demand": 610},
    {"ibge": "3204955", "nome": "Sooretama", "microregiao": "Rio Doce", "lat": -19.1969, "lng": -40.0911, "demand": 180},
    {"ibge": "3205002", "nome": "Serra", "microregiao": "Metropolitana", "lat": -20.1288, "lng": -40.3078, "demand": 2910},
    {"ibge": "3205069", "nome": "Vargem Alta", "microregiao": "Serrana", "lat": -20.6711, "lng": -41.0069, "demand": 130},
    {"ibge": "3205036", "nome": "Venda Nova do Imigrante", "microregiao": "Serrana", "lat": -20.3297, "lng": -41.1347, "demand": 175},
    {"ibge": "3205101", "nome": "Viana", "microregiao": "Metropolitana", "lat": -20.3906, "lng": -40.4958, "demand": 820},
    {"ibge": "3205150", "nome": "Vila Pavão", "microregiao": "Rio Doce", "lat": -18.6144, "lng": -40.6083, "demand": 95},
    {"ibge": "3205176", "nome": "Vila Valério", "microregiao": "Rio Doce", "lat": -18.9983, "lng": -40.3897, "demand": 110},
    {"ibge": "3205200", "nome": "Vila Velha", "microregiao": "Metropolitana", "lat": -20.3297, "lng": -40.2925, "demand": 2450},
    {"ibge": "3205309", "nome": "Vitória", "microregiao": "Metropolitana", "lat": -20.3155, "lng": -40.3128, "demand": 3420},
]

assert len(ES_78_MUNICIPALITIES) == 78, f"Expected exactly 78 municipalities in ES, found {len(ES_78_MUNICIPALITIES)}"


class GestorAuditSimulationService:
    """
    Simulation backend engine for Gestor SEJUS verification.
    Provides mathematically accurate KPI aggregation, OIDC claim authentication,
    cryptographic SHA-256 hash chaining, and telemetry extraction.
    """
    def __init__(self, secret_key: str = "SEJUS_SECRET_2026_CONECTA_EGRESSO"):
        self.secret_key = secret_key
        self.audit_chain: List[Dict[str, Any]] = []
        self._init_audit_chain()

    def authenticate_oidc(self, cpf: str, role_requested: str = "gestor") -> Dict[str, Any]:
        """Simulates Gov.br / Acesso Cidadão OIDC claims issuance."""
        timestamp = int(time.time())
        claims = {
            "iss": "https://acessocidadao.es.gov.br",
            "sub": "govbr-gestor-77894",
            "cpf": cpf,
            "cpf_masked": f"***.{cpf[4:7]}.{cpf[8:11]}-**" if len(cpf) >= 11 else "***.***.***-**",
            "name": "Carlos Eduardo Silva",
            "email": "carlos.silva@sejus.es.gov.br",
            "perfil": "gestor",
            "orgao": "SEJUS/ES - Subsecretaria de Reintegração Social",
            "escopo_territorial": "ESTADUAL_78_MUNICIPIOS",
            "roles": ["GESTOR_SEJUS", "AUDITOR_LGPD", "ESTATISTICA_ESTADUAL"],
            "iat": timestamp,
            "exp": timestamp + 28800,
        }
        # Sign mock JWT
        header_b64 = base64.urlsafe_b64encode(json.dumps({"alg": "HS256", "typ": "JWT"}).encode()).decode().rstrip("=")
        payload_b64 = base64.urlsafe_b64encode(json.dumps(claims).encode()).decode().rstrip("=")
        token_data = f"{header_b64}.{payload_b64}"
        signature = hmac.new(self.secret_key.encode(), token_data.encode(), hashlib.sha256).hexdigest()
        token = f"{token_data}.{signature}"

        self.append_audit_log(
            user_id="gestor_77894",
            actor_cpf_hash=hashlib.sha256(cpf.encode()).hexdigest(),
            actor_role="GESTOR_SEJUS",
            action="AUTH_LOGIN_OIDC",
            resource_type="AUTH_SESSION",
            resource_id="SESSION_GESTOR_ES",
            payload={"auth_provider": "Acesso Cidadão", "role": "gestor", "scope": "78_MUNICIPIOS"},
        )

        return {"token": token, "claims": claims, "status": "AUTHENTICATED"}

    def get_management_kpis(self) -> Dict[str, Any]:
        """Aggregates state-level KPIs across all 78 ES municipalities."""
        total_demand = sum(m["demand"] for m in ES_78_MUNICIPALITIES)
        metropolitan_demand = sum(m["demand"] for m in ES_78_MUNICIPALITIES if m["microregiao"] == "Metropolitana")
        interior_demand = total_demand - metropolitan_demand

        return {
            "total_egressos_cadastrados": 108420,
            "total_demand_mapped": total_demand,
            "metropolitan_demand": metropolitan_demand,
            "interior_demand": interior_demand,
            "municipios_cobertos": 78,
            "total_atendimentos_remotos": 18450,
            "taxa_reintegracao_trabalho_pct": 42.0,
            "taxa_qualificacao_cursos_pct": 28.0,
            "taxa_apoio_psicossocial_pct": 18.0,
            "taxa_documentacao_emitida_pct": 12.0,
            "taxa_reincidencia_reducao_pct": 34.5,
            "municipios_lista": ES_78_MUNICIPALITIES,
        }

    def filter_analytics_by_region(self, microregion: str) -> Dict[str, Any]:
        """Filters municipal analytics by specified micro-region."""
        filtered = [m for m in ES_78_MUNICIPALITIES if m["microregiao"].lower() == microregion.lower()]
        subtotal_demand = sum(m["demand"] for m in filtered)
        return {
            "microregiao": microregion,
            "municipios_count": len(filtered),
            "subtotal_demand": subtotal_demand,
            "municipios": filtered,
            "distribuicao_setorial": {
                "trabalho_renda": round(subtotal_demand * 0.42),
                "cursos_capacitacao": round(subtotal_demand * 0.28),
                "apoio_psicossocial": round(subtotal_demand * 0.18),
                "documentacao": round(subtotal_demand * 0.12),
            }
        }

    def _init_audit_chain(self):
        """Initializes blockchain-like hash-chained audit log for SEJUS LGPD compliance."""
        genesis_prev = "0" * 64
        genesis_timestamp = "2026-08-17T08:00:00Z"
        genesis_payload = {"event": "GENESIS_SEJUS_AUDIT_LOG_INITIALIZED", "system": "CONECTA_EGRESSO"}
        digest = hashlib.sha256(json.dumps(genesis_payload, sort_keys=True).encode()).hexdigest()
        raw_string = f"{genesis_prev}|{genesis_timestamp}|SYSTEM|INITIALIZE|CHAIN_ROOT|{digest}"
        genesis_hash = hashlib.sha256(raw_string.encode()).hexdigest()

        self.audit_chain.append({
            "index": 0,
            "prev_hash": genesis_prev,
            "timestamp": genesis_timestamp,
            "user_id": "SYSTEM",
            "actor_cpf_hash": "SYSTEM",
            "actor_role": "SYSTEM_BOOT",
            "action": "INITIALIZE",
            "resource_type": "SYSTEM_CORE",
            "resource_id": "CHAIN_ROOT",
            "payload": genesis_payload,
            "payload_digest": digest,
            "current_hash": genesis_hash,
        })

    def append_audit_log(self, user_id: str, actor_cpf_hash: str, actor_role: str,
                         action: str, resource_type: str, resource_id: str,
                         payload: Dict[str, Any]) -> Dict[str, Any]:
        """Appends a new cryptographically chained audit record."""
        prev_entry = self.audit_chain[-1]
        prev_hash = prev_entry["current_hash"]
        timestamp = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
        digest = hashlib.sha256(json.dumps(payload, sort_keys=True).encode()).hexdigest()

        raw_string = f"{prev_hash}|{timestamp}|{user_id}|{action}|{resource_id}|{digest}"
        current_hash = hashlib.sha256(raw_string.encode()).hexdigest()

        entry = {
            "index": len(self.audit_chain),
            "prev_hash": prev_hash,
            "timestamp": timestamp,
            "user_id": user_id,
            "actor_cpf_hash": actor_cpf_hash,
            "actor_role": actor_role,
            "action": action,
            "resource_type": resource_type,
            "resource_id": resource_id,
            "payload": payload,
            "payload_digest": digest,
            "current_hash": current_hash,
        }
        self.audit_chain.append(entry)
        return entry

    def verify_audit_chain_integrity(self, chain: Optional[List[Dict[str, Any]]] = None) -> Dict[str, Any]:
        """
        Validates SHA-256 hash chaining continuity and payload immutability across all blocks.
        """
        target_chain = chain if chain is not None else self.audit_chain
        if not target_chain:
            return {"valid": False, "error": "EMPTY_CHAIN", "broken_index": None}

        # Check genesis
        if target_chain[0]["prev_hash"] != "0" * 64:
            return {"valid": False, "error": "INVALID_GENESIS_PREV_HASH", "broken_index": 0}

        for i in range(len(target_chain)):
            block = target_chain[i]
            # Verify payload digest
            recomputed_digest = hashlib.sha256(json.dumps(block["payload"], sort_keys=True).encode()).hexdigest()
            if recomputed_digest != block["payload_digest"]:
                return {
                    "valid": False,
                    "error": f"PAYLOAD_DIGEST_MISMATCH at index {i}",
                    "broken_index": i,
                }

            # Verify current hash calculation
            raw_string = f"{block['prev_hash']}|{block['timestamp']}|{block['user_id']}|{block['action']}|{block['resource_id']}|{block['payload_digest']}"
            recomputed_hash = hashlib.sha256(raw_string.encode()).hexdigest()
            if recomputed_hash != block["current_hash"]:
                return {
                    "valid": False,
                    "error": f"HASH_TAMPERED at index {i}",
                    "broken_index": i,
                }

            # Verify link to previous block
            if i > 0:
                prev_block = target_chain[i - 1]
                if block["prev_hash"] != prev_block["current_hash"]:
                    return {
                        "valid": False,
                        "error": f"CHAIN_BROKEN_LINK between {i-1} and {i}",
                        "broken_index": i,
                    }

        return {"valid": True, "total_blocks": len(target_chain), "latest_hash": target_chain[-1]["current_hash"]}

    def get_system_telemetry(self) -> Dict[str, Any]:
        """Returns WebRTC signaling and network telemetry across the state."""
        return {
            "active_rooms": 14,
            "total_calls_today": 348,
            "total_call_minutes_today": 5220,
            "avg_call_duration_minutes": 15.0,
            "avg_mos_score": 4.28,
            "avg_rtt_ms": 46.2,
            "avg_jitter_ms": 7.8,
            "avg_packet_loss_pct": 0.38,
            "network_distribution": {
                "4g_mobile_nat": 68.4,
                "wifi_broadband": 26.2,
                "3g_legacy_turn": 5.4,
            },
            "coturn_server_status": "HEALTHY",
            "fastapi_instances_active": 2,
            "redis_pubsub_channel": "webrtc_rooms_state",
        }

    def export_attendance_report(self, export_format: str = "json") -> Any:
        """Exports full attendance metrics dataset in JSON or CSV format."""
        kpis = self.get_management_kpis()
        telemetry = self.get_system_telemetry()
        data = {
            "orgao": "SEJUS/ES - Subsecretaria de Reintegração Social e Cidadania",
            "programa": "CONECTA EGRESSO - Edital CPSI Nº 010/2026",
            "data_emissao": time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()),
            "kpis_gerais": {
                "populacao_alvo": kpis["total_egressos_cadastrados"],
                "total_demand_mapped": kpis["total_demand_mapped"],
                "atendimentos_remotos": kpis["total_atendimentos_remotos"],
                "taxa_reincidencia_reducao": f"{kpis['taxa_reincidencia_reducao_pct']}%",
                "taxa_reintegracao_trabalho": f"{kpis['taxa_reintegracao_trabalho_pct']}%",
            },
            "telemetria_atendimento": telemetry,
            "cobertura_78_municipios": kpis["municipios_lista"],
        }
        if export_format == "csv":
            lines = ["IBGE,Município,Microrregião,Demanda_Mapeada,Latitude,Longitude"]
            for m in kpis["municipios_lista"]:
                lines.append(f"{m['ibge']},{m['nome']},{m['microregiao']},{m['demand']},{m['lat']},{m['lng']}")
            return "\n".join(lines)
        return data


def run_scenario_gestor_audit_kpis() -> Dict[str, Any]:
    """
    Executes the full Scenario 1 workflow programmatically and returns execution summary.
    """
    service = GestorAuditSimulationService()
    results = {}

    # Step 1: OIDC Authentication
    auth = service.authenticate_oidc("10020030040", "gestor")
    results["step1_auth"] = auth
    assert auth["status"] == "AUTHENTICATED"
    assert auth["claims"]["perfil"] == "gestor"
    assert "GESTOR_SEJUS" in auth["claims"]["roles"]
    assert auth["claims"]["escopo_territorial"] == "ESTADUAL_78_MUNICIPIOS"

    # Step 2: Management Dashboard KPIs across all 78 ES municipalities
    kpis = service.get_management_kpis()
    results["step2_kpis"] = {
        "total_egressos": kpis["total_egressos_cadastrados"],
        "municipios_cobertos": kpis["municipios_cobertos"],
        "taxa_reincidencia_reducao": kpis["taxa_reincidencia_reducao_pct"],
    }
    assert kpis["municipios_cobertos"] == 78
    assert len(kpis["municipios_lista"]) == 78
    assert kpis["taxa_reintegracao_trabalho_pct"] == 42.0

    # Step 3: Territorial Filter by Micro-Region
    metro = service.filter_analytics_by_region("Metropolitana")
    norte = service.filter_analytics_by_region("Rio Doce")
    results["step3_regions"] = {
        "metropolitana_municipios": metro["municipios_count"],
        "metropolitana_demand": metro["subtotal_demand"],
        "rio_doce_municipios": norte["municipios_count"],
        "rio_doce_demand": norte["subtotal_demand"],
    }
    assert metro["municipios_count"] == 7
    assert norte["municipios_count"] == 18

    # Step 4 & 5: Audit Log Hash Chain Verification
    # Add simulated actions
    service.append_audit_log(
        user_id="gestor_77894",
        actor_cpf_hash=hashlib.sha256("10020030040".encode()).hexdigest(),
        actor_role="GESTOR_SEJUS",
        action="EXPORT_MANAGEMENT_REPORT",
        resource_type="KPI_DASHBOARD",
        resource_id="RELATORIO_ESTADUAL_78",
        payload={"periodo": "2026-08", "escopo": "TOTAL_78_MUNICIPIOS"},
    )
    chain_check = service.verify_audit_chain_integrity()
    results["step5_chain_integrity"] = chain_check
    assert chain_check["valid"] is True
    assert chain_check["total_blocks"] >= 3

    # Step 5b: Tamper detection verification
    tampered_chain = [dict(b) for b in service.audit_chain]
    tampered_chain[1]["payload"] = {"tampered": True}  # alter payload
    tamper_check = service.verify_audit_chain_integrity(tampered_chain)
    results["step5_tamper_detection"] = tamper_check
    assert tamper_check["valid"] is False, "Tampering must be detected!"

    # Step 6: Inspect Telemetry & Export Attendance Report
    telemetry = service.get_system_telemetry()
    results["step6_telemetry"] = telemetry
    assert telemetry["avg_mos_score"] >= 4.0
    assert telemetry["coturn_server_status"] == "HEALTHY"

    report_csv = service.export_attendance_report(export_format="csv")
    results["step6_export_csv_lines"] = len(report_csv.split("\n"))
    assert len(report_csv.split("\n")) == 79  # Header + 78 municipalities

    return {"status": "SUCCESS", "scenario": "Gestor SEJUS Global Audit & Analytics", "details": results}


class TestScenarioGestorAuditKPIs(unittest.TestCase):
    """
    TestCase class for pytest / unittest test runners.
    """

    def setUp(self):
        self.service = GestorAuditSimulationService()

    def test_complete_gestor_audit_kpis_workflow(self):
        """Exercises the entire Scenario 1 end-to-end journey."""
        result = run_scenario_gestor_audit_kpis()
        self.assertEqual(result["status"], "SUCCESS")

    def test_step1_gestor_oidc_authentication_and_claims(self):
        """Verifies Gov.br / Acesso Cidadão OIDC token generation and claim mapping."""
        auth = self.service.authenticate_oidc("10020030040", "gestor")
        self.assertEqual(auth["status"], "AUTHENTICATED")
        claims = auth["claims"]
        self.assertEqual(claims["perfil"], "gestor")
        self.assertEqual(claims["orgao"], "SEJUS/ES - Subsecretaria de Reintegração Social")
        self.assertEqual(claims["escopo_territorial"], "ESTADUAL_78_MUNICIPIOS")
        self.assertIn("GESTOR_SEJUS", claims["roles"])
        self.assertIn("AUDITOR_LGPD", claims["roles"])

    def test_step2_dashboard_kpis_covers_all_78_municipalities(self):
        """Verifies state-level KPI aggregation encompasses all 78 ES municipalities with valid IBGE codes."""
        kpis = self.service.get_management_kpis()
        self.assertEqual(kpis["municipios_cobertos"], 78)
        self.assertEqual(len(kpis["municipios_lista"]), 78)
        self.assertGreaterEqual(kpis["total_egressos_cadastrados"], 100000)

        # Check all IBGE codes start with 32 (Espírito Santo)
        for m in kpis["municipios_lista"]:
            self.assertTrue(m["ibge"].startswith("32"), f"Invalid ES IBGE code: {m['ibge']}")
            self.assertGreater(m["demand"], 0)
            self.assertIn("microregiao", m)

    def test_step3_microregion_territorial_filtering(self):
        """Verifies filtering by microregions (Metropolitana vs Rio Doce vs Serrana vs Caparaó)."""
        metro = self.service.filter_analytics_by_region("Metropolitana")
        self.assertEqual(metro["municipios_count"], 7)
        self.assertEqual(metro["subtotal_demand"], 12570)
        self.assertEqual(metro["distribuicao_setorial"]["trabalho_renda"], round(12570 * 0.42))

        rio_doce = self.service.filter_analytics_by_region("Rio Doce")
        self.assertEqual(rio_doce["municipios_count"], 18)
        self.assertGreater(rio_doce["subtotal_demand"], 0)

    def test_step4_and_step5_cryptographic_audit_hash_chain(self):
        """Verifies SHA-256 hash chaining and tamper detection in LGPD audit logs."""
        # Append 5 diverse audit entries
        for i in range(5):
            self.service.append_audit_log(
                user_id=f"gestor_{i}",
                actor_cpf_hash=hashlib.sha256(f"cpf_{i}".encode()).hexdigest(),
                actor_role="GESTOR_SEJUS",
                action=f"AUDIT_VIEW_ACTION_{i}",
                resource_type="PRONTUARIO",
                resource_id=f"PRON_REC_{100+i}",
                payload={"action_detail": f"Consultou prontuário {100+i}", "iter": i},
            )

        # Check pristine chain
        verify_res = self.service.verify_audit_chain_integrity()
        self.assertTrue(verify_res["valid"])
        self.assertEqual(verify_res["total_blocks"], 6)  # 1 genesis + 5 entries

        # Check tampering detection: alter action in block 3
        tampered_chain = [dict(b) for b in self.service.audit_chain]
        tampered_chain[3]["action"] = "UNAUTHORIZED_TAMPER_ACTION"
        tamper_res = self.service.verify_audit_chain_integrity(tampered_chain)
        self.assertFalse(tamper_res["valid"])
        self.assertIn("HASH_TAMPERED", tamper_res["error"])

    def test_step6_telemetry_and_export_generation(self):
        """Verifies system telemetry extraction and attendance report CSV formatting."""
        telemetry = self.service.get_system_telemetry()
        self.assertGreaterEqual(telemetry["avg_mos_score"], 4.0)
        self.assertIn("4g_mobile_nat", telemetry["network_distribution"])
        self.assertEqual(telemetry["coturn_server_status"], "HEALTHY")

        csv_output = self.service.export_attendance_report(export_format="csv")
        lines = csv_output.strip().split("\n")
        self.assertEqual(len(lines), 79)
        self.assertIn("Linhares", csv_output)
        self.assertIn("Vitória", csv_output)
        self.assertIn("São Mateus", csv_output)


if __name__ == "__main__":
    unittest.main()
