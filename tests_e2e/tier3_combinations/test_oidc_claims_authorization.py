"""Tier 3 Combinatorial Test Suite: Gov.br / Acesso Cidadão OIDC Claims × RBAC Role & Territorial Scope Authorization.

Covers cross-feature matrix:
1. OpenID Connect (OIDC) Claims to RBAC Role & Territorial Scope Transformation:
   - Gov.br Ouro (Servidor SEJUS Estadual) -> mapped to `gestor` role with statewide scope (ESTADO_78_MUNICIPIOS)
   - Acesso Cidadão / Gov.br Prata (CRESS registered social worker) -> mapped to `tecnico` role with regional scope (e.g. Vitória, Linhares)
   - Gov.br Prata (Egresso) -> mapped to `egresso` role with self-only scope (SELF_ONLY)
2. Claim Transformation with Missing Optional Scopes & Graceful Degradation:
   - Missing optional scopes (`email`, `govbr_confianca`, `orgao`) handled gracefully
   - Defaults trust level to "Bronze" when missing, triggering step-up identity verification for sensitive Prontuário access
   - Fail-Secure principle: Unknown or ambiguous government organizational claims default strictly to lowest-privilege citizen role (`egresso`/`cidadao`)
3. Multi-Tenancy & Territorial Scope Enforcement:
   - Técnico restricted to assigned regional scope cannot perform write actions outside assigned territory without delegation
   - Gestor holds unconstrained statewide audit and KPI inspection permissions across all 78 ES municipalities
"""

from __future__ import annotations

import copy
import json
import unittest
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple

from tests_e2e.e2e_utils import (
    AssertionHelper,
    CryptoVerifier,
    DataGenerator,
    ES_MUNICIPALITIES,
    MUNICIPALITY_BY_CODE,
)


class OidcClaimTransformer:
    """
    Simulates Gov.br / Acesso Cidadão OpenID Connect Claim Transformation Engine
    for CONECTA EGRESSO (SEJUS/ES).
    """

    TRUST_LEVELS = {"Bronze": 1, "Prata": 2, "Ouro": 3}

    @classmethod
    def transform_claims(cls, id_token_claims: Dict[str, Any]) -> Dict[str, Any]:
        """
        Transforms raw OIDC ID Token claims into application RBAC user profile,
        enforcing security policies, least-privilege defaults, and territorial boundaries.
        """
        sub = id_token_claims.get("sub")
        if not sub or not str(sub).strip():
            raise ValueError("OIDC claim 'sub' is missing or empty")

        cpf = str(id_token_claims.get("cpf", "")).strip()
        clean_cpf = "".join(filter(str.isdigit, cpf))
        if len(clean_cpf) != 11:
            raise ValueError(f"OIDC claim 'cpf' must contain 11 digits (got '{cpf}')")

        name = id_token_claims.get("name", "Usuário Autenticado")
        email = id_token_claims.get("email") or f"user_{clean_cpf[:6]}@gov.br"
        confianca = id_token_claims.get("nivel_confianca", "Bronze")
        if confianca not in cls.TRUST_LEVELS:
            confianca = "Bronze"

        scopes = set(id_token_claims.get("scope", "").split())
        orgao = id_token_claims.get("orgao", "")
        cargo = id_token_claims.get("cargo", "")
        conselho = id_token_claims.get("registro_conselho")
        municipio_lotacao = id_token_claims.get("municipio_lotacao_ibge")

        # RBAC Role Mapping Logic
        # 1. Gestor SEJUS: Requires Ouro trust level + SEJUS public servant claim or scope
        if ("govbr_servidor" in scopes or orgao == "SEJUS") and "gestor" in cargo.lower() and confianca == "Ouro":
            role = "gestor"
            territorial_scope = "ESTADO_78_MUNICIPIOS"
            allowed_municipalities = [m["ibge_code"] for m in ES_MUNICIPALITIES]
            permissions = [
                "dashboard:view", "dashboard:kpis", "prontuario:read_all",
                "relatorios:export", "audit:view", "seguranca_lgpd:view", "territorio:all"
            ]

        # 2. Técnico Social: Requires professional registration (CRESS/CRP) and assigned territory
        elif conselho and ("cress" in conselho.lower() or "crp" in conselho.lower() or "social" in cargo.lower()):
            role = "tecnico"
            lotacao = municipio_lotacao if municipio_lotacao in MUNICIPALITY_BY_CODE else "3205309"  # default Vitória
            territorial_scope = f"MUNICIPAL_{lotacao}"
            allowed_municipalities = [lotacao]
            permissions = [
                "dashboard:view", "atendimento:queue", "atendimento:start",
                "prontuario:read", "prontuario:write", "prontuario:evolucao",
                "vagas:manage", "cursos:manage"
            ]

        # 3. Fail-Secure Default: Citizen / Egresso
        else:
            role = "egresso"
            residencia = id_token_claims.get("municipio_residencia_ibge", "3205309")
            territorial_scope = f"SELF_{clean_cpf}"
            allowed_municipalities = [residencia]
            permissions = [
                "carteira:view", "carteira:download_pdf", "oportunidades:view",
                "oportunidades:apply", "atendimento:join_queue", "prontuario:view_own"
            ]

        # Require step-up authentication if trust level is Bronze and user attempts technical access
        requires_step_up = (confianca == "Bronze" and role in ("gestor", "tecnico"))

        return {
            "sub": sub,
            "name": name,
            "email": email,
            "cpf": cpf,
            "cpf_clean": clean_cpf,
            "cpf_masked": f"***.{clean_cpf[3:6]}.{clean_cpf[6:9]}-**",
            "nivel_confianca": confianca,
            "role": role,
            "registro_conselho": conselho,
            "territorial_scope": territorial_scope,
            "allowed_municipalities": allowed_municipalities,
            "permissions": permissions,
            "requires_step_up": requires_step_up,
            "transformed_at": datetime.now(timezone.utc).isoformat(),
        }


class TestOidcClaimsAuthorization(unittest.TestCase):
    """Pairwise Integration Test Suite: Gov.br / Acesso Cidadão OIDC SSO Claims & Territorial Authorization."""

    def test_01_govbr_claims_mapped_to_rbac_and_territorial_scope(self):
        """
        Verify OIDC claims mapping to RBAC role and territorial scope permissions:
        1. Gestor Ouro Token from SEJUS:
           - Maps to role: 'gestor'.
           - Scope: 'ESTADO_78_MUNICIPIOS' with access to all 78 ES municipalities.
           - Has 'audit:view' and 'prontuario:read_all' permissions.
        2. Técnico Prata Token with CRESS license:
           - Maps to role: 'tecnico'.
           - Scope: Assigned municipality (e.g. Linhares 3203205).
           - Has 'prontuario:evolucao' and 'atendimento:start' permissions.
        3. Egresso Token:
           - Maps to role: 'egresso'.
           - Scope: 'SELF_<CPF>'.
           - Has 'carteira:view' and 'oportunidades:apply' permissions.
        """
        # Case 1: Gestor SEJUS (Ouro)
        gestor_claims = {
            "sub": "govbr-gestor-001",
            "cpf": "52998224725",
            "name": "Dr. Carlos Eduardo Silva",
            "email": "carlos.silva@sejus.es.gov.br",
            "nivel_confianca": "Ouro",
            "orgao": "SEJUS",
            "cargo": "Gestor Geral de Políticas Penais",
            "scope": "openid email profile govbr_servidor govbr_confianca",
        }
        profile_gestor = OidcClaimTransformer.transform_claims(gestor_claims)
        self.assertEqual(profile_gestor["role"], "gestor")
        self.assertEqual(profile_gestor["territorial_scope"], "ESTADO_78_MUNICIPIOS")
        self.assertEqual(len(profile_gestor["allowed_municipalities"]), 78)
        self.assertIn("audit:view", profile_gestor["permissions"])
        self.assertIn("prontuario:read_all", profile_gestor["permissions"])
        self.assertFalse(profile_gestor["requires_step_up"])

        # Case 2: Técnico Social (Linhares)
        tecnico_claims = {
            "sub": "acesso-cidadao-tec-002",
            "cpf": "12345678901",
            "name": "Dra. Márcia Oliveira",
            "email": "marcia.social@sejus.es.gov.br",
            "nivel_confianca": "Prata",
            "registro_conselho": "CRESS-ES-4891",
            "cargo": "Assistente Social",
            "municipio_lotacao_ibge": "3203205",  # Linhares
            "scope": "openid email profile cress_verified",
        }
        profile_tecnico = OidcClaimTransformer.transform_claims(tecnico_claims)
        self.assertEqual(profile_tecnico["role"], "tecnico")
        self.assertEqual(profile_tecnico["territorial_scope"], "MUNICIPAL_3203205")
        self.assertEqual(profile_tecnico["registro_conselho"], "CRESS-ES-4891")
        self.assertIn("prontuario:evolucao", profile_tecnico["permissions"])
        self.assertIn("atendimento:queue", profile_tecnico["permissions"])

        # Case 3: Egresso
        egresso_claims = {
            "sub": "govbr-egresso-003",
            "cpf": "98765432100",
            "name": "Lucas Santos",
            "nivel_confianca": "Prata",
            "municipio_residencia_ibge": "3203205",
            "scope": "openid profile",
        }
        profile_egresso = OidcClaimTransformer.transform_claims(egresso_claims)
        self.assertEqual(profile_egresso["role"], "egresso")
        self.assertEqual(profile_egresso["territorial_scope"], "SELF_98765432100")
        self.assertIn("carteira:view", profile_egresso["permissions"])
        self.assertIn("oportunidades:apply", profile_egresso["permissions"])
        self.assertNotIn("audit:view", profile_egresso["permissions"])

    def test_02_claim_transformation_missing_optional_scopes_and_fail_secure(self):
        """
        Verify graceful degradation and fail-secure principles:
        1. User authenticates with bare minimal claims (`sub`, `cpf`), missing email and trust level.
        2. Transformer handles missing optional scopes without crashing:
           - Sets synthetic email fallback.
           - Sets trust level to "Bronze".
        3. Unknown/ambiguous government role claims default strictly to 'egresso' (fail-secure).
        4. Malformed claims (missing sub or invalid CPF length) raise descriptive validation errors.
        """
        # 1. Bare minimal claims
        minimal_claims = {
            "sub": "govbr-minimal-123",
            "cpf": "11122233344",
        }
        profile_min = OidcClaimTransformer.transform_claims(minimal_claims)
        self.assertEqual(profile_min["role"], "egresso")
        self.assertEqual(profile_min["nivel_confianca"], "Bronze")
        self.assertEqual(profile_min["cpf_masked"], "***.222.333-**")
        self.assertIn("user_111222@gov.br", profile_min["email"])

        # 2. Ambiguous or unknown government org claim (Attacker claiming 'Secretaria da Fazenda')
        unauthorized_org_claims = {
            "sub": "govbr-unauthorized-org",
            "cpf": "44455566677",
            "name": "João Fiscal",
            "orgao": "SEFAZ",
            "cargo": "Auditor Fiscal da Receita Estadual",
            "nivel_confianca": "Ouro",
            "scope": "openid email",
        }
        profile_org = OidcClaimTransformer.transform_claims(unauthorized_org_claims)
        # Must NOT elevate to SEJUS gestor -> Defaults to citizen/egresso
        self.assertEqual(profile_org["role"], "egresso")
        self.assertNotIn("audit:view", profile_org["permissions"])

        # 3. Malformed claim validation
        with self.assertRaises(ValueError):
            OidcClaimTransformer.transform_claims({"sub": "", "cpf": "12345678901"})

        with self.assertRaises(ValueError):
            OidcClaimTransformer.transform_claims({"sub": "valid-sub", "cpf": "123"})  # invalid CPF length

    def test_03_territorial_scope_cross_region_authorization_boundary(self):
        """
        Verify multi-tenancy territorial boundaries:
        1. Técnico Social Marcia is lotada in Linhares (3203205).
        2. Verify that her allowed_municipalities contains ONLY Linhares.
        3. Attempted access to Cariacica (3201308) or Vitória (3205309) is outside her territorial scope.
        4. Gestor Carlos has unrestricted access to all 78 ES municipalities.
        """
        tecnico_claims = {
            "sub": "tec-linhares-001",
            "cpf": "12345678901",
            "registro_conselho": "CRESS-ES-4891",
            "cargo": "Assistente Social",
            "municipio_lotacao_ibge": "3203205",  # Linhares
        }
        p_tec = OidcClaimTransformer.transform_claims(tecnico_claims)

        def is_municipality_authorized(profile: Dict[str, Any], target_ibge: str) -> bool:
            if "territorio:all" in profile["permissions"]:
                return True
            return target_ibge in profile["allowed_municipalities"]

        # Linhares (authorized)
        self.assertTrue(is_municipality_authorized(p_tec, "3203205"))
        # Vitória (unauthorized for Linhares technician)
        self.assertFalse(is_municipality_authorized(p_tec, "3205309"))
        # Colatina (unauthorized)
        self.assertFalse(is_municipality_authorized(p_tec, "3201506"))

        # Gestor
        gestor_claims = {
            "sub": "gestor-estadual",
            "cpf": "52998224725",
            "nivel_confianca": "Ouro",
            "orgao": "SEJUS",
            "cargo": "Gestor Estadual",
            "scope": "govbr_servidor",
        }
        p_gestor = OidcClaimTransformer.transform_claims(gestor_claims)
        # Authorized in any ES municipality
        self.assertTrue(is_municipality_authorized(p_gestor, "3205309"))  # Vitória
        self.assertTrue(is_municipality_authorized(p_gestor, "3203205"))  # Linhares
        self.assertTrue(is_municipality_authorized(p_gestor, "3202009"))  # Dores do Rio Preto


if __name__ == "__main__":
    unittest.main()
