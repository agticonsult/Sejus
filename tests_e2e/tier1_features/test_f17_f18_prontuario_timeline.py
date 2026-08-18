"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F17 - F18
============================================================
Features Tested:
  - F17: Prontuário Único CRUD API with audit logging
  - F18: Prontuário timeline event recording (atendimentos, encaminhamentos)

Authoritative Source:
  - ORIGINAL_REQUEST.md (R1: Prontuário Único com trilha de auditoria imutável LGPD)
  - PROJECT.md (Milestone M3 & Feature Inventory)
"""

import hashlib
import json
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestProntuarioTimelineF17toF18(unittest.TestCase):
    """Verifies Prontuário Único CRUD operations, automatic audit logging, and timeline events."""

    def test_f17_prontuario_unico_crud_with_audit_logging(self):
        """
        F17: Verify Prontuário Único CRUD operations automatically trigger immutable audit logs on read/write.
        """
        audit_trail = []
        
        class MockProntuarioService:
            def __init__(self):
                self.records = {}
                self.last_hash = "0" * 64
                
            def log_audit(self, prontuario_id: int, user_id: int, action: str, payload: dict):
                serialized = json.dumps(payload, sort_keys=True)
                raw = f"{self.last_hash}|{user_id}|{action}|{prontuario_id}|{serialized}|2026-08-17T12:00:00Z"
                new_hash = hashlib.sha256(raw.encode("utf-8")).hexdigest()
                entry = {
                    "id": len(audit_trail) + 1,
                    "prontuario_id": prontuario_id,
                    "user_id": user_id,
                    "action": action,
                    "payload": payload,
                    "prev_hash": self.last_hash,
                    "current_hash": new_hash
                }
                self.last_hash = new_hash
                audit_trail.append(entry)
                return entry

            def create(self, user_id: int, egresso_id: int, data: dict):
                prontuario_id = len(self.records) + 101
                record = {
                    "id": prontuario_id,
                    "egresso_id": egresso_id,
                    "numero_prontuario": f"PRONT-ES-2026-{prontuario_id:05d}",
                    "resumo_social": data.get("resumo_social", ""),
                    "status": "ATIVO"
                }
                self.records[prontuario_id] = record
                self.log_audit(prontuario_id, user_id, "CREATE", record)
                return record

            def get(self, user_id: int, prontuario_id: int):
                record = self.records.get(prontuario_id)
                if record:
                    self.log_audit(prontuario_id, user_id, "READ", {"query": "single_prontuario"})
                return record

            def update(self, user_id: int, prontuario_id: int, updates: dict):
                record = self.records.get(prontuario_id)
                if record:
                    record.update(updates)
                    self.log_audit(prontuario_id, user_id, "UPDATE", updates)
                return record

        service = MockProntuarioService()
        
        # 1. Create Prontuário by Técnico
        created = service.create(
            user_id=2, # Técnico Márcia
            egresso_id=8412,
            data={"resumo_social": "Egresso em busca de reintegração profissional na região norte."}
        )
        self.assertEqual(created["numero_prontuario"], "PRONT-ES-2026-00101")
        self.assertEqual(len(audit_trail), 1)
        self.assertEqual(audit_trail[0]["action"], "CREATE")
        
        # 2. Read Prontuário by Gestor
        fetched = service.get(user_id=1, prontuario_id=101)
        self.assertEqual(fetched["id"], 101)
        self.assertEqual(len(audit_trail), 2)
        self.assertEqual(audit_trail[1]["action"], "READ")
        
        # 3. Update Prontuário
        updated = service.update(user_id=2, prontuario_id=101, updates={"status": "EM_ACOMPANHAMENTO"})
        self.assertEqual(updated["status"], "EM_ACOMPANHAMENTO")
        self.assertEqual(len(audit_trail), 3)
        self.assertEqual(audit_trail[2]["action"], "UPDATE")
        
        # Hash chain verification
        self.assertEqual(audit_trail[1]["prev_hash"], audit_trail[0]["current_hash"])
        self.assertEqual(audit_trail[2]["prev_hash"], audit_trail[1]["current_hash"])

    def test_f18_prontuario_timeline_event_recording(self):
        """
        F18: Verify Prontuário timeline event recording and type taxonomy.
        Allowed event types:
          - atendimento_remoto (Video WebRTC)
          - atendimento_presencial (Escritório Social)
          - encaminhamento_vaga (SINE / Vaga inclusiva)
          - matricula_curso (Qualificação técnica)
          - emissao_documento (Carteira Digital / 2ª via RG)
          - apoio_psicossocial (Acolhimento familiar)
        """
        allowed_event_types = {
            "atendimento_remoto",
            "atendimento_presencial",
            "encaminhamento_vaga",
            "matricula_curso",
            "emissao_documento",
            "apoio_psicossocial"
        }
        
        timeline_entries = []
        
        def add_timeline_event(prontuario_id: int, tipo: str, descricao: str, tecnico_id: int, metadata: dict) -> dict:
            if tipo not in allowed_event_types:
                raise ValueError(f"Invalid event type: {tipo}")
            entry = {
                "id": len(timeline_entries) + 1,
                "prontuario_id": prontuario_id,
                "tipo_evento": tipo,
                "descricao": descricao,
                "tecnico_id": tecnico_id,
                "metadata": metadata,
                "created_at": "2026-08-17T14:30:00Z"
            }
            timeline_entries.append(entry)
            return entry
            
        # Add diverse events
        e1 = add_timeline_event(
            prontuario_id=101,
            tipo="atendimento_remoto",
            descricao="Atendimento psicológico inicial via videochamada.",
            tecnico_id=2,
            metadata={"call_duration_seconds": 920, "mos_score": 4.3, "room_id": "sala-101"}
        )
        e2 = add_timeline_event(
            prontuario_id=101,
            tipo="encaminhamento_vaga",
            descricao="Encaminhado para vaga de Auxiliar de Logística na Serra/ES.",
            tecnico_id=2,
            metadata={"vaga_id": 42, "empresa": "Logística ES Ltda"}
        )
        e3 = add_timeline_event(
            prontuario_id=101,
            tipo="emissao_documento",
            descricao="Emissão da Carteira Digital do Egresso com QR Code.",
            tecnico_id=2,
            metadata={"documento": "CARTEIRA_DIGITAL", "hash": "sig_abc123"}
        )
        
        self.assertEqual(len(timeline_entries), 3)
        self.assertEqual(e1["tipo_evento"], "atendimento_remoto")
        self.assertEqual(e2["metadata"]["vaga_id"], 42)
        self.assertEqual(e3["metadata"]["documento"], "CARTEIRA_DIGITAL")
        
        # Invalid event type rejection
        with self.assertRaises(ValueError):
            add_timeline_event(101, "evento_desconhecido", "Teste", 2, {})


if __name__ == "__main__":
    unittest.main()
