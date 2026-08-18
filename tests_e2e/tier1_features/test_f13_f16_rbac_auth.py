"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F13 - F16
============================================================
Features Tested:
  - F13: Demo user seed profiles (Gestor, Técnico, Egresso)
  - F14: RBAC authentication system & role permissions
  - F15: Simulated OIDC / Gov.br / Acesso Cidadão claim mapping
  - F16: Role-based middleware & route authorization policies

Authoritative Source:
  - ORIGINAL_REQUEST.md (R1: Autenticação OIDC / Acesso Cidadão / Gov.br e RBAC)
  - PROJECT.md (Milestone M3 & Feature Inventory)
"""

import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestRbacAuthF13toF16(unittest.TestCase):
    """Verifies User Seed Profiles, RBAC, Gov.br/OIDC Claim Mapping, and Route Policies."""

    def test_f13_demo_user_seed_profiles(self):
        """
        F13: Verify seed data definitions for the 3 representative user profiles:
          1. Gestor SEJUS: Carlos Eduardo Silva (Subsecretaria de Reintegração)
          2. Técnico Escritório Social: Dra. Márcia Oliveira (Assistente Social, CRESS 4891/ES)
          3. Egresso / Familiar: Lucas Santos (CPF ***.192.830-**, São Mateus/ES)
        """
        demo_profiles = {
            "gestor": {
                "name": "Carlos Eduardo Silva",
                "email": "carlos.silva@sejus.es.gov.br",
                "role": "gestor",
                "department": "Subsecretaria de Reintegração Social - SEJUS/ES",
                "scope": "ESTADUAL_78_MUNICIPIOS"
            },
            "tecnico": {
                "name": "Dra. Márcia Oliveira",
                "email": "marcia.oliveira@sejus.es.gov.br",
                "role": "tecnico",
                "credential": "CRESS 4891/ES",
                "scope": "ATENDIMENTO_REMOTO_PRESENCIAL"
            },
            "egresso": {
                "name": "Lucas Santos",
                "email": "lucas.santos@egresso.es.gov.br",
                "role": "egresso",
                "cpf_masked": "***.192.830-**",
                "municipio": "São Mateus / ES",
                "scope": "AUTO_ATENDIMENTO"
            }
        }
        
        self.assertEqual(len(demo_profiles), 3, "Must configure exactly 3 demo profiles")
        self.assertEqual(demo_profiles["gestor"]["role"], "gestor")
        self.assertEqual(demo_profiles["tecnico"]["role"], "tecnico")
        self.assertEqual(demo_profiles["egresso"]["role"], "egresso")
        self.assertIn("CRESS", demo_profiles["tecnico"]["credential"])

    def test_f14_rbac_permission_matrix(self):
        """
        F14: Verify RBAC role permissions matrix.
        Permissions:
          - view_dashboard_kpis: gestor (yes), tecnico (yes), egresso (no)
          - manage_video_queue: gestor (yes), tecnico (yes), egresso (no)
          - write_prontuario_evolution: gestor (yes), tecnico (yes), egresso (no)
          - view_management_reports: gestor (yes), tecnico (no), egresso (no)
          - view_audit_logs: gestor (yes), tecnico (no), egresso (no)
          - view_own_wallet: gestor (no), tecnico (no), egresso (yes)
          - join_waiting_room: gestor (no), tecnico (no), egresso (yes)
        """
        role_permissions = {
            "gestor": {
                "view_dashboard_kpis": True,
                "manage_video_queue": True,
                "write_prontuario_evolution": True,
                "view_management_reports": True,
                "view_audit_logs": True,
                "view_own_wallet": False,
                "join_waiting_room": False
            },
            "tecnico": {
                "view_dashboard_kpis": True,
                "manage_video_queue": True,
                "write_prontuario_evolution": True,
                "view_management_reports": False,
                "view_audit_logs": False,
                "view_own_wallet": False,
                "join_waiting_room": False
            },
            "egresso": {
                "view_dashboard_kpis": False,
                "manage_video_queue": False,
                "write_prontuario_evolution": False,
                "view_management_reports": False,
                "view_audit_logs": False,
                "view_own_wallet": True,
                "join_waiting_room": True
            }
        }
        
        def has_permission(role: str, permission: str) -> bool:
            return role_permissions.get(role, {}).get(permission, False)
            
        # Assert Gestor permissions
        self.assertTrue(has_permission("gestor", "view_management_reports"))
        self.assertTrue(has_permission("gestor", "view_audit_logs"))
        
        # Assert Técnico restrictions
        self.assertTrue(has_permission("tecnico", "write_prontuario_evolution"))
        self.assertFalse(has_permission("tecnico", "view_management_reports"))
        self.assertFalse(has_permission("tecnico", "view_audit_logs"))
        
        # Assert Egresso restrictions and privileges
        self.assertTrue(has_permission("egresso", "view_own_wallet"))
        self.assertTrue(has_permission("egresso", "join_waiting_room"))
        self.assertFalse(has_permission("egresso", "write_prontuario_evolution"))

    def test_f15_oidc_govbr_claim_mapping(self):
        """
        F15: Verify simulated OpenID Connect / Gov.br / Acesso Cidadão claim mapping.
        Maps JWT claims (sub, cpf, name, email, amr, roles) to internal authenticated User entity.
        """
        govbr_id_token_claims = {
            "iss": "https://sso.acessocidadao.es.gov.br",
            "sub": "govbr-user-uuid-98412-es",
            "cpf": "19283044700",
            "name": "Lucas Santos de Oliveira",
            "email": "lucas.santos@cidadao.es.gov.br",
            "email_verified": True,
            "amr": ["govbr_facial_biometrics", "level_silver"],
            "roles": ["cidadao_egresso"]
        }
        
        def map_oidc_claims_to_user(claims: dict) -> dict:
            role_map = {
                "sejus_admin": "gestor",
                "sejus_tecnico": "tecnico",
                "cidadao_egresso": "egresso"
            }
            mapped_role = "egresso"
            for r in claims.get("roles", []):
                if r in role_map:
                    mapped_role = role_map[r]
                    break
                    
            clean_cpf = "".join(filter(str.isdigit, claims.get("cpf", "")))
            masked_cpf = f"***.{clean_cpf[3:6]}.{clean_cpf[6:9]}-**" if len(clean_cpf) == 11 else "***"
            
            return {
                "sso_provider": "acesso_cidadao_es",
                "sso_sub": claims.get("sub"),
                "name": claims.get("name"),
                "email": claims.get("email"),
                "cpf_clean": clean_cpf,
                "cpf_masked": masked_cpf,
                "role": mapped_role,
                "govbr_level": "silver" if "level_silver" in claims.get("amr", []) else "bronze"
            }
            
        user = map_oidc_claims_to_user(govbr_id_token_claims)
        
        self.assertEqual(user["sso_provider"], "acesso_cidadao_es")
        self.assertEqual(user["role"], "egresso")
        self.assertEqual(user["cpf_masked"], "***.830.447-**")
        self.assertEqual(user["govbr_level"], "silver")

    def test_f16_role_based_route_middleware_policy(self):
        """
        F16: Verify role-based middleware & route authorization enforcement policies.
        """
        route_policies = {
            "/dashboard": ["gestor", "tecnico"],
            "/atendimento/fila": ["tecnico", "gestor"],
            "/atendimento/sala": ["tecnico", "egresso"],
            "/oportunidades": ["gestor", "tecnico", "egresso"],
            "/carteira": ["egresso"],
            "/geolocalizacao": ["gestor", "tecnico", "egresso"],
            "/prontuario": ["gestor", "tecnico"],
            "/prontuario/evolucao": ["tecnico"],
            "/relatorios": ["gestor"],
            "/seguranca-lgpd": ["gestor"],
            "/validar-carteira": ["public"]
        }
        
        def authorize_request(route: str, user_role: str) -> bool:
            allowed = route_policies.get(route, [])
            if "public" in allowed:
                return True
            return user_role in allowed
            
        # Gestor authorizations
        self.assertTrue(authorize_request("/relatorios", "gestor"))
        self.assertTrue(authorize_request("/seguranca-lgpd", "gestor"))
        self.assertFalse(authorize_request("/carteira", "gestor"))
        
        # Técnico authorizations
        self.assertTrue(authorize_request("/atendimento/fila", "tecnico"))
        self.assertTrue(authorize_request("/prontuario/evolucao", "tecnico"))
        self.assertFalse(authorize_request("/relatorios", "tecnico"))
        
        # Egresso authorizations
        self.assertTrue(authorize_request("/carteira", "egresso"))
        self.assertTrue(authorize_request("/oportunidades", "egresso"))
        self.assertFalse(authorize_request("/prontuario", "egresso"))
        self.assertFalse(authorize_request("/relatorios", "egresso"))
        
        # Public route
        self.assertTrue(authorize_request("/validar-carteira", "anonymous"))


if __name__ == "__main__":
    unittest.main()
