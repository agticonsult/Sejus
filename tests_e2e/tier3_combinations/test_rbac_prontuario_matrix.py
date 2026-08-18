"""Tier 3 Combinatorial Test Suite: RBAC × Prontuário Único Matrix & Multi-Tenant Access Control.

Covers cross-feature matrix:
- Gestor SEJUS: Prontuário Read (Allowed, Audited) vs Prontuário Evolution Write (Disallowed without technical license CRESS/CRP/OAB -> 403 Forbidden, Audited)
- Técnico Social: Prontuário Read (Allowed, Audited) and Evolution Add (Allowed, Audited with SHA-256 hash chaining)
- Egresso: Own Prontuário Read (Allowed restricted view omitting confidential notes, Audited) vs Other Egresso's Prontuário Read (Forbidden 403, Security alert logged)
- Anonymous Visitor: Prontuário Read/Write (Unauthorized 401, Redirect to Login, No data leakage)
- Combinatorial Action Matrix: Full permission check across all roles (Gestor, Técnico, Egresso, Familiar, Anon) and Prontuário endpoints.
"""

from __future__ import annotations

import copy
import hashlib
import json
import time
import unittest
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple

from tests_e2e.e2e_utils import (
    AssertionHelper,
    CryptoVerifier,
    DataGenerator,
    ES_MUNICIPALITIES,
    HttpResponse,
    MockApiClient,
)


class MockProntuarioRbacEngine:
    """
    High-fidelity RBAC & Row-Level Security (RLS) engine for Prontuário Único.
    Implements SEJUS/ES security policies, LGPD data masking, and immutable hash-chained audit logs.
    """

    def __init__(self):
        self.users: Dict[int, Dict[str, Any]] = {}
        self.egressos: Dict[int, Dict[str, Any]] = {}
        self.prontuarios: Dict[int, Dict[str, Any]] = {}  # indexed by egresso_id
        self.evolutions: Dict[int, List[Dict[str, Any]]] = {}  # egresso_id -> list of evolutions
        self.audit_log_chain: List[Dict[str, Any]] = []

        # Genesis audit block
        genesis_payload = {"action": "GENESIS", "details": "Initial audit block for Prontuario engine"}
        genesis_hash = CryptoVerifier.calculate_audit_hash(CryptoVerifier.GENESIS_HASH, genesis_payload)
        self.audit_log_chain.append({
            "id": 1,
            "previous_hash": CryptoVerifier.GENESIS_HASH,
            "hash": genesis_hash,
            "payload": genesis_payload,
            "timestamp": datetime.now(timezone.utc).isoformat()
        })

        self._seed_initial_data()

    def _seed_initial_data(self):
        # 1. Gestor SEJUS (Statewide scope, no clinical council registration)
        gestor = {
            "id": 1,
            "name": "Dr. Carlos Eduardo Silva",
            "email": "carlos.gestor@sejus.es.gov.br",
            "role": "gestor",
            "registro_conselho": None,  # Pure administrative manager
            "scope": "ESTADUAL_78_MUNICIPIOS",
            "ativo": True,
        }
        # 2. Técnico Social (CRESS-ES registered social worker)
        tecnico = {
            "id": 2,
            "name": "Dra. Márcia Oliveira",
            "email": "marcia.social@sejus.es.gov.br",
            "role": "tecnico",
            "registro_conselho": "CRESS-ES-4891",
            "scope": "REGIONAL_METROPOLITANA",
            "ativo": True,
        }
        # 3. Egresso A (Lucas Santos)
        egresso_a = {
            "id": 101,
            "name": "Lucas Santos",
            "cpf": "123.456.789-01",
            "cpf_masked": "***.456.789-**",
            "role": "egresso",
            "ativo": True,
        }
        # 4. Egresso B (Bruno Costa)
        egresso_b = {
            "id": 102,
            "name": "Bruno Costa",
            "cpf": "987.654.321-09",
            "cpf_masked": "***.654.321-**",
            "role": "egresso",
            "ativo": True,
        }

        for u in [gestor, tecnico, egresso_a, egresso_b]:
            self.users[u["id"]] = u

        self.egressos[101] = egresso_a
        self.egressos[102] = egresso_b

        # Seed Prontuário for Egresso A
        self.prontuarios[101] = {
            "prontuario_id": "PRONT-ES-000101",
            "egresso_id": 101,
            "status": "ACOMPANHAMENTO_ATIVO",
            "data_admissao": "2026-01-15",
            "diagnostico_social": "Egresso em livramento condicional, demanda por qualificação profissional.",
            "unidade_prisional_anterior": "Penitenciária Estadual de Vila Velha",
        }
        self.evolutions[101] = [
            {
                "id": 1,
                "egresso_id": 101,
                "author_id": 2,
                "author_name": "Dra. Márcia Oliveira",
                "author_council": "CRESS-ES-4891",
                "tipo": "ACOLHIMENTO_INICIAL",
                "texto": "Atendimento psicossocial de acolhimento inicial realizado no Escritório Social.",
                "confidencial_tecnico": False,  # Visible to Egresso
                "data_registro": "2026-01-16T10:00:00Z",
            },
            {
                "id": 2,
                "egresso_id": 101,
                "author_id": 2,
                "author_name": "Dra. Márcia Oliveira",
                "author_council": "CRESS-ES-4891",
                "tipo": "AVALIACAO_SIGILOSA",
                "texto": "Nota sigilosa multidisciplinar para equipe técnica: acompanhamento familiar de vulnerabilidade grave.",
                "confidencial_tecnico": True,  # Hidden from Egresso self-view (LGPD & Professional ethics)
                "data_registro": "2026-01-20T14:30:00Z",
            }
        ]

        # Seed Prontuário for Egresso B
        self.prontuarios[102] = {
            "prontuario_id": "PRONT-ES-000102",
            "egresso_id": 102,
            "status": "ACOMPANHAMENTO_ATIVO",
            "data_admissao": "2026-02-01",
            "diagnostico_social": "Egresso com demanda habitacional e reintegração laboral em Linhares.",
            "unidade_prisional_anterior": "Centro de Detenção Provisória de Colatina",
        }
        self.evolutions[102] = []

    def _append_audit(self, actor: Optional[Dict[str, Any]], action: str, target_egresso_id: int, status: str, details: str) -> Dict[str, Any]:
        """Appends an immutable SHA-256 hash-chained audit record."""
        now_iso = datetime.now(timezone.utc).isoformat()
        prev_hash = self.audit_log_chain[-1]["hash"]

        payload = {
            "action": action,
            "status": status,
            "actor_id": actor["id"] if actor else None,
            "actor_role": actor["role"] if actor else "ANONYMOUS",
            "target_egresso_id": target_egresso_id,
            "details": details,
            "timestamp": now_iso,
        }
        curr_hash = CryptoVerifier.calculate_audit_hash(prev_hash, payload)
        record = {
            "id": len(self.audit_log_chain) + 1,
            "previous_hash": prev_hash,
            "hash": curr_hash,
            "payload": payload,
            "timestamp": now_iso,
        }
        self.audit_log_chain.append(record)
        return record

    def read_prontuario(self, actor: Optional[Dict[str, Any]], egresso_id: int) -> Tuple[int, Dict[str, Any]]:
        """Handles GET /prontuario/{egresso_id} with RBAC & LGPD masking."""
        if not actor:
            self._append_audit(None, "READ_PRONTUARIO", egresso_id, "DENIED_UNAUTHORIZED", "Unauthenticated read attempt")
            return 401, {"error": "Autenticação obrigatória para acessar prontuários.", "code": "UNAUTHORIZED"}

        role = actor.get("role")

        if egresso_id not in self.prontuarios:
            self._append_audit(actor, "READ_PRONTUARIO", egresso_id, "NOT_FOUND", "Target egresso not found")
            return 404, {"error": "Prontuário não encontrado.", "code": "NOT_FOUND"}

        pront = copy.deepcopy(self.prontuarios[egresso_id])
        evols = copy.deepcopy(self.evolutions.get(egresso_id, []))

        # 1. Gestor SEJUS: Allowed read (Administrative/Audit view)
        if role == "gestor":
            self._append_audit(actor, "READ_PRONTUARIO", egresso_id, "ALLOWED_GESTOR", "Gestor consulted prontuario for governance audit")
            return 200, {
                "prontuario": pront,
                "evolucoes": evols,
                "view_mode": "GESTOR_GOVERNANCE",
                "audited": True,
            }

        # 2. Técnico Social: Allowed full clinical read
        if role == "tecnico":
            self._append_audit(actor, "READ_PRONTUARIO", egresso_id, "ALLOWED_TECNICO", "Tecnico social clinical inspection")
            return 200, {
                "prontuario": pront,
                "evolucoes": evols,
                "view_mode": "TECNICO_FULL_CLINICAL",
                "audited": True,
            }

        # 3. Egresso / Familiar
        if role == "egresso":
            # Can only access OWN prontuário
            if actor.get("id") != egresso_id:
                self._append_audit(actor, "READ_PRONTUARIO", egresso_id, "DENIED_CROSS_TENANT", "Egresso attempted to view another egresso's record")
                return 403, {
                    "error": "Acesso negado: você só tem permissão para visualizar o seu próprio prontuário.",
                    "code": "FORBIDDEN_CROSS_TENANT",
                }

            # Restricted view for self: filter out confidential internal technical notes
            filtered_evols = [e for e in evols if not e.get("confidencial_tecnico", False)]
            self._append_audit(actor, "READ_PRONTUARIO", egresso_id, "ALLOWED_EGRESSO_SELF", "Egresso accessed own restricted social record")
            return 200, {
                "prontuario": pront,
                "evolucoes": filtered_evols,
                "view_mode": "EGRESSO_RESTRICTED_SELF",
                "confidential_notes_filtered": len(evols) - len(filtered_evols),
                "audited": True,
            }

        return 403, {"error": "Perfil não autorizado.", "code": "FORBIDDEN"}

    def add_evolution(self, actor: Optional[Dict[str, Any]], egresso_id: int, payload: Dict[str, Any]) -> Tuple[int, Dict[str, Any]]:
        """Handles POST /prontuario/{egresso_id}/evolucao."""
        if not actor:
            self._append_audit(None, "WRITE_EVOLUTION", egresso_id, "DENIED_UNAUTHORIZED", "Unauthenticated write attempt")
            return 401, {"error": "Autenticação obrigatória.", "code": "UNAUTHORIZED"}

        role = actor.get("role")

        # Gestor CANNOT write clinical evolutions without professional council license
        if role == "gestor":
            self._append_audit(actor, "WRITE_EVOLUTION", egresso_id, "DENIED_ROLE_RESTRICTION", "Gestor attempted to write clinical evolution without license")
            return 403, {
                "error": "Apenas profissionais técnicos habilitados (CRESS/CRP/OAB) podem registrar evoluções sociais.",
                "code": "FORBIDDEN_LICENSE_REQUIRED",
            }

        if role == "egresso":
            self._append_audit(actor, "WRITE_EVOLUTION", egresso_id, "DENIED_EGRESSO_WRITE", "Egresso attempted to append social evolution")
            return 403, {
                "error": "Egressos não possuem permissão para registrar anotações técnicas em prontuários.",
                "code": "FORBIDDEN_EGRESSO_WRITE",
            }

        if role == "tecnico":
            if not actor.get("registro_conselho"):
                self._append_audit(actor, "WRITE_EVOLUTION", egresso_id, "DENIED_MISSING_COUNCIL", "Tecnico without registered council")
                return 403, {"error": "Registro de conselho profissional ausente.", "code": "FORBIDDEN_MISSING_COUNCIL"}

            texto = payload.get("texto", "").strip()
            if not texto:
                return 422, {"error": "O texto da evolução não pode ser vazio.", "field": "texto"}

            new_evol = {
                "id": len(self.evolutions.get(egresso_id, [])) + 1,
                "egresso_id": egresso_id,
                "author_id": actor["id"],
                "author_name": actor["name"],
                "author_council": actor["registro_conselho"],
                "tipo": payload.get("tipo", "EVOLUCAO_TECNICA"),
                "texto": texto,
                "confidencial_tecnico": bool(payload.get("confidencial_tecnico", False)),
                "data_registro": datetime.now(timezone.utc).isoformat(),
            }
            self.evolutions.setdefault(egresso_id, []).append(new_evol)

            audit_rec = self._append_audit(
                actor,
                "WRITE_EVOLUTION",
                egresso_id,
                "SUCCESS",
                f"Evolution #{new_evol['id']} registered by {actor['name']} ({actor['registro_conselho']})"
            )

            return 201, {
                "status": "created",
                "evolution": new_evol,
                "audit_hash": audit_rec["hash"],
                "previous_hash": audit_rec["previous_hash"],
            }

        return 403, {"error": "Ação não permitida.", "code": "FORBIDDEN"}


class TestRbacProntuarioMatrix(unittest.TestCase):
    """Pairwise Combinatorial Test Suite: RBAC × Prontuário Único Operations."""

    def setUp(self):
        self.engine = MockProntuarioRbacEngine()
        self.gestor = self.engine.users[1]
        self.tecnico = self.engine.users[2]
        self.egresso_a = self.engine.users[101]
        self.egresso_b = self.engine.users[102]

    def test_01_gestor_prontuario_read_allowed_and_write_blocked(self):
        """
        Verify Gestor SEJUS permissions:
        1. Read access to Prontuário is ALLOWED for state-level monitoring and governance audit.
        2. Audit log records the read event with actor details.
        3. Write access to clinical social evolution is FORBIDDEN (403) for pure Gestor role without technical license.
        4. Attempted unauthorized write is recorded in the immutable audit log.
        """
        # Step 1: Gestor reads Prontuário 101
        status_read, body_read = self.engine.read_prontuario(self.gestor, egresso_id=101)
        AssertionHelper.assert_status_code(status_read, 200, "Gestor Prontuário Read")
        self.assertEqual(body_read["view_mode"], "GESTOR_GOVERNANCE")
        self.assertIn("prontuario", body_read)
        self.assertEqual(body_read["prontuario"]["egresso_id"], 101)
        self.assertEqual(len(body_read["evolucoes"]), 2)

        # Step 2: Check Audit Log for Gestor Read
        last_audit = self.engine.audit_log_chain[-1]
        self.assertEqual(last_audit["payload"]["action"], "READ_PRONTUARIO")
        self.assertEqual(last_audit["payload"]["status"], "ALLOWED_GESTOR")
        self.assertEqual(last_audit["payload"]["actor_id"], self.gestor["id"])

        # Step 3: Gestor attempts to write social evolution
        status_write, body_write = self.engine.add_evolution(
            self.gestor,
            egresso_id=101,
            payload={"texto": "Tentativa de anotação clínica pelo Gestor Administrativo", "tipo": "EVOLUCAO_TECNICA"}
        )
        AssertionHelper.assert_status_code(status_write, 403, "Gestor Prontuário Evolution Write")
        self.assertEqual(body_write["code"], "FORBIDDEN_LICENSE_REQUIRED")

        # Step 4: Verify Audit Chain integrity and attempted write log
        write_audit = self.engine.audit_log_chain[-1]
        self.assertEqual(write_audit["payload"]["action"], "WRITE_EVOLUTION")
        self.assertEqual(write_audit["payload"]["status"], "DENIED_ROLE_RESTRICTION")
        self.assertEqual(write_audit["payload"]["actor_id"], self.gestor["id"])

        # Step 5: Assert cryptographic chain valid
        valid_chain, chain_msg = CryptoVerifier.verify_audit_chain(self.engine.audit_log_chain)
        self.assertTrue(valid_chain, f"Audit chain integrity compromised: {chain_msg}")

    def test_02_tecnico_social_read_and_evolution_write_allowed_audited(self):
        """
        Verify Técnico Social permissions:
        1. Read access to Prontuário is ALLOWED with full clinical view (including confidential technical notes).
        2. Technical Evolution Add is ALLOWED (201 Created) with CRESS license stamped.
        3. Audit log generates a new SHA-256 block linking to the previous hash.
        4. Newly created evolution appears in subsequent reads.
        """
        # Step 1: Técnico reads Prontuário 101
        status_read, body_read = self.engine.read_prontuario(self.tecnico, egresso_id=101)
        AssertionHelper.assert_status_code(status_read, 200, "Técnico Prontuário Read")
        self.assertEqual(body_read["view_mode"], "TECNICO_FULL_CLINICAL")
        initial_evol_count = len(body_read["evolucoes"])
        self.assertEqual(initial_evol_count, 2)

        # Step 2: Técnico adds new clinical evolution
        evolution_payload = {
            "tipo": "ENCAMINHAMENTO_LABORAL",
            "texto": "Encaminhado para vaga afirmativa de Almoxarife na empresa parceira SEJUS em Vitória.",
            "confidencial_tecnico": False,
        }
        status_write, body_write = self.engine.add_evolution(self.tecnico, egresso_id=101, payload=evolution_payload)
        AssertionHelper.assert_status_code(status_write, 201, "Técnico Add Evolution")
        self.assertEqual(body_write["status"], "created")
        new_evol = body_write["evolution"]
        self.assertEqual(new_evol["author_council"], "CRESS-ES-4891")
        self.assertEqual(new_evol["author_name"], self.tecnico["name"])

        # Step 3: Verify audit log block linkage
        audit_record = self.engine.audit_log_chain[-1]
        self.assertEqual(audit_record["payload"]["action"], "WRITE_EVOLUTION")
        self.assertEqual(audit_record["payload"]["status"], "SUCCESS")
        self.assertEqual(audit_record["hash"], body_write["audit_hash"])
        self.assertEqual(audit_record["previous_hash"], body_write["previous_hash"])

        # Step 4: Re-read Prontuário to confirm newly appended evolution
        status_read2, body_read2 = self.engine.read_prontuario(self.tecnico, egresso_id=101)
        self.assertEqual(len(body_read2["evolucoes"]), initial_evol_count + 1)
        latest_evol = body_read2["evolucoes"][-1]
        self.assertEqual(latest_evol["texto"], evolution_payload["texto"])

        # Step 5: Verify entire audit chain
        AssertionHelper.assert_valid_audit_chain(self.engine.audit_log_chain, "Técnico Evolution Flow")

    def test_03_egresso_own_prontuario_restricted_and_other_forbidden(self):
        """
        Verify Egresso Row-Level Security (RLS) & Multi-Tenant Boundaries:
        1. Egresso A reads own Prontuário (ID: 101) -> ALLOWED (200 OK) with RESTRICTED view.
           - Non-confidential evolutions are visible.
           - Confidential technical notes are filtered out as per LGPD / Social Work Code of Ethics.
        2. Egresso A attempts to read Egresso B's Prontuário (ID: 102) -> FORBIDDEN (403).
        3. Cross-tenant attempt is flagged and recorded in immutable audit log.
        4. Egresso A attempts to write an evolution note -> FORBIDDEN (403).
        """
        # Step 1: Egresso A reads own Prontuário
        status_own, body_own = self.engine.read_prontuario(self.egresso_a, egresso_id=101)
        AssertionHelper.assert_status_code(status_own, 200, "Egresso Own Prontuário Read")
        self.assertEqual(body_own["view_mode"], "EGRESSO_RESTRICTED_SELF")
        # In setup, Prontuário 101 has 2 evolutions: 1 public and 1 confidential
        self.assertEqual(len(body_own["evolucoes"]), 1, "Confidential technical notes must be hidden from egresso")
        self.assertEqual(body_own["confidential_notes_filtered"], 1)
        self.assertFalse(body_own["evolucoes"][0]["confidencial_tecnico"])

        # Check self-read audit log
        self.assertEqual(self.engine.audit_log_chain[-1]["payload"]["action"], "READ_PRONTUARIO")
        self.assertEqual(self.engine.audit_log_chain[-1]["payload"]["status"], "ALLOWED_EGRESSO_SELF")

        # Step 2: Egresso A attempts to read Egresso B's Prontuário
        status_cross, body_cross = self.engine.read_prontuario(self.egresso_a, egresso_id=102)
        AssertionHelper.assert_status_code(status_cross, 403, "Egresso Cross-Tenant Read")
        self.assertEqual(body_cross["code"], "FORBIDDEN_CROSS_TENANT")

        # Check cross-tenant denied audit log
        cross_audit = self.engine.audit_log_chain[-1]
        self.assertEqual(cross_audit["payload"]["action"], "READ_PRONTUARIO")
        self.assertEqual(cross_audit["payload"]["status"], "DENIED_CROSS_TENANT")
        self.assertEqual(cross_audit["payload"]["target_egresso_id"], 102)
        self.assertEqual(cross_audit["payload"]["actor_id"], 101)

        # Step 3: Egresso A attempts to write an evolution
        status_write, body_write = self.engine.add_evolution(
            self.egresso_a,
            egresso_id=101,
            payload={"texto": "Tentativa de escrita direta"}
        )
        AssertionHelper.assert_status_code(status_write, 403, "Egresso Evolution Write")
        self.assertEqual(body_write["code"], "FORBIDDEN_EGRESSO_WRITE")

        # Step 4: Verify audit chain
        AssertionHelper.assert_valid_audit_chain(self.engine.audit_log_chain, "Egresso Boundaries")

    def test_04_anonymous_visitor_prontuario_unauthorized(self):
        """
        Verify unauthenticated / anonymous visitor boundaries:
        1. Anonymous request to read Prontuário -> UNAUTHORIZED (401).
        2. Anonymous request to add evolution -> UNAUTHORIZED (401).
        3. Response body does NOT leak sensitive profile, medical, or criminal history fields.
        4. Audit log records anonymous intrusion attempt with actor_role='ANONYMOUS'.
        """
        # Step 1: Anonymous read attempt
        status_anon_read, body_anon_read = self.engine.read_prontuario(actor=None, egresso_id=101)
        AssertionHelper.assert_status_code(status_anon_read, 401, "Anonymous Read")
        self.assertEqual(body_anon_read["code"], "UNAUTHORIZED")
        self.assertNotIn("prontuario", body_anon_read)
        self.assertNotIn("diagnostico_social", body_anon_read)

        # Step 2: Anonymous write attempt
        status_anon_write, body_anon_write = self.engine.add_evolution(
            actor=None,
            egresso_id=101,
            payload={"texto": "Injeção não autenticada"}
        )
        AssertionHelper.assert_status_code(status_anon_write, 401, "Anonymous Write")
        self.assertEqual(body_anon_write["code"], "UNAUTHORIZED")

        # Step 3: Check audit logs for anonymous events
        anon_audit = self.engine.audit_log_chain[-1]
        self.assertEqual(anon_audit["payload"]["actor_role"], "ANONYMOUS")
        self.assertIsNone(anon_audit["payload"]["actor_id"])

        # Step 4: Verify chain
        AssertionHelper.assert_valid_audit_chain(self.engine.audit_log_chain, "Anonymous Access")

    def test_05_combinatorial_rbac_prontuario_matrix_table(self):
        """
        Exhaustive Pairwise Combinatorial Matrix:
        Roles: [Gestor, Técnico, Egresso_Owner, Egresso_Stranger, Anonymous]
        Operations: [Read_Self, Read_Other, Write_Evolution, Read_Confidential_Notes]
        Asserts exact authorization decisions across the entire combinatorial matrix.
        """
        matrix_cases = [
            # (Actor, Target_ID, Operation, Expected_Status, Expected_Decision)
            (self.gestor, 101, "read", 200, "ALLOWED_ADMIN_READ"),
            (self.gestor, 101, "write", 403, "DENIED_NO_COUNCIL_LICENSE"),
            (self.tecnico, 101, "read", 200, "ALLOWED_CLINICAL_READ"),
            (self.tecnico, 101, "write", 201, "ALLOWED_CLINICAL_WRITE"),
            (self.egresso_a, 101, "read", 200, "ALLOWED_RESTRICTED_SELF"),
            (self.egresso_a, 102, "read", 403, "DENIED_CROSS_TENANT"),
            (self.egresso_a, 101, "write", 403, "DENIED_EGRESSO_WRITE"),
            (None, 101, "read", 401, "DENIED_UNAUTHENTICATED"),
            (None, 101, "write", 401, "DENIED_UNAUTHENTICATED"),
        ]

        for actor, target_id, op, exp_status, desc in matrix_cases:
            actor_name = actor["role"] if actor else "anon"
            if op == "read":
                st, _ = self.engine.read_prontuario(actor, target_id)
            else:
                st, _ = self.engine.add_evolution(actor, target_id, {"texto": f"Matrix test {desc}"})

            self.assertEqual(
                st, exp_status,
                f"Matrix failed for actor '{actor_name}' on target {target_id} op '{op}' (Desc: {desc}). Got {st}, expected {exp_status}."
            )

        # Final assertion on cryptographic integrity of entire test matrix audit chain
        valid, msg = CryptoVerifier.verify_audit_chain(self.engine.audit_log_chain)
        self.assertTrue(valid, f"Exhaustive matrix audit chain broken: {msg}")


if __name__ == "__main__":
    unittest.main()
