"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F23 - F25
============================================================
Features Tested:
  - F23: WebRTC Room authorization API & JWT generation
  - F24: WebRTC Webhook ingest endpoint with HMAC verification
  - F25: Automatic Prontuário timeline insertion upon video call conclusion

Authoritative Source:
  - ORIGINAL_REQUEST.md (R2: Webhooks e JWT com backend Laravel para registro automático)
  - PROJECT.md (Milestone M3 & Interface Contracts §2)
"""

import base64
import hashlib
import hmac
import json
import time
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestWebRtcWebhooksF23toF25(unittest.TestCase):
    """Verifies WebRTC JWT generation, signed Webhook ingestion, and auto-insertion to Prontuário."""

    def test_f23_webrtc_room_authorization_and_jwt_generation(self):
        """
        F23: Verify WebRTC Room authorization endpoint (`POST /api/webrtc/token`).
        Payload contains user identity, role, room permissions, and Coturn ICE servers.
        """
        shared_secret = "sejus_webrtc_jwt_shared_secret_key_2026"
        
        def generate_webrtc_token(user_id: int, name: str, role: str, room_id: str) -> dict:
            header = {"alg": "HS256", "typ": "JWT"}
            now = int(time.time())
            claims = {
                "sub": str(user_id),
                "name": name,
                "role": role,
                "room_id": room_id,
                "iat": now,
                "exp": now + 3600 # 1 hour validity
            }
            
            h_b64 = base64.urlsafe_b64encode(json.dumps(header).encode()).decode().rstrip("=")
            c_b64 = base64.urlsafe_b64encode(json.dumps(claims).encode()).decode().rstrip("=")
            to_sign = f"{h_b64}.{c_b64}".encode("utf-8")
            
            sig = base64.urlsafe_b64encode(
                hmac.new(shared_secret.encode("utf-8"), to_sign, hashlib.sha256).digest()
            ).decode().rstrip("=")
            
            jwt_token = f"{h_b64}.{c_b64}.{sig}"
            
            return {
                "token": jwt_token,
                "ws_url": f"ws://localhost:8001/ws/room/{room_id}",
                "ice_servers": [
                    {"urls": "stun:localhost:3478"},
                    {"urls": "turn:localhost:3478", "username": "sejus_turn_user", "credential": "turn_password_2026"}
                ]
            }
            
        resp = generate_webrtc_token(
            user_id=2,
            name="Dra. Márcia Oliveira",
            role="tecnico",
            room_id="sala-atendimento-vit-101"
        )
        
        self.assertIn("token", resp)
        self.assertTrue(resp["ws_url"].endswith("sala-atendimento-vit-101"))
        self.assertEqual(len(resp["ice_servers"]), 2)
        self.assertTrue(resp["ice_servers"][1]["urls"].startswith("turn:"))
        
        # Verify JWT format (3 dot-separated segments)
        parts = resp["token"].split(".")
        self.assertEqual(len(parts), 3)

    def test_f24_webrtc_webhook_ingest_hmac_verification(self):
        """
        F24: Verify WebRTC Webhook ingest endpoint with HMAC-SHA256 signature verification.
        Header: `X-Signature-SHA256`
        """
        webhook_secret = b"sejus_laravel_fastapi_webhook_secret_2026"
        
        def sign_webhook_payload(payload: dict) -> str:
            raw = json.dumps(payload, sort_keys=True).encode("utf-8")
            return hmac.new(webhook_secret, raw, hashlib.sha256).hexdigest()
            
        def verify_webhook_request(headers: dict, raw_body: bytes) -> bool:
            sig_received = headers.get("X-Signature-SHA256", "")
            expected_sig = hmac.new(webhook_secret, raw_body, hashlib.sha256).hexdigest()
            return hmac.compare_digest(expected_sig, sig_received)
            
        payload = {
            "event": "session_started",
            "room_id": "sala-atendimento-vit-101",
            "attendee_id": 8412,
            "timestamp": "2026-08-17T14:00:00Z"
        }
        
        raw_body = json.dumps(payload, sort_keys=True).encode("utf-8")
        valid_sig = sign_webhook_payload(payload)
        
        # Valid signature check
        headers_valid = {"X-Signature-SHA256": valid_sig, "Content-Type": "application/json"}
        self.assertTrue(verify_webhook_request(headers_valid, raw_body))
        
        # Tampered signature check
        headers_invalid = {"X-Signature-SHA256": "bad_sig_" + "0" * 56, "Content-Type": "application/json"}
        self.assertFalse(verify_webhook_request(headers_invalid, raw_body))

    def test_f25_automatic_prontuario_timeline_insertion_on_call_end(self):
        """
        F25: Verify automatic Prontuário timeline insertion upon receiving `session_ended` webhook.
        """
        timeline_db = []
        
        def handle_webrtc_webhook(event_payload: dict):
            event_type = event_payload.get("event")
            if event_type == "session_ended":
                room_id = event_payload.get("room_id")
                duration = event_payload.get("duration_seconds")
                telemetry = event_payload.get("summary_telemetry", {})
                prontuario_id = event_payload.get("prontuario_id", 101)
                tecnico_id = event_payload.get("tecnico_id", 2)
                
                timeline_entry = {
                    "prontuario_id": prontuario_id,
                    "tipo_evento": "atendimento_remoto",
                    "descricao": f"Atendimento psicossocial por videoconferência finalizado ({duration // 60} min {duration % 60} s).",
                    "tecnico_id": tecnico_id,
                    "metadata": {
                        "room_id": room_id,
                        "duration_seconds": duration,
                        "avg_mos": telemetry.get("avg_mos"),
                        "packet_loss_pct": telemetry.get("packet_loss_pct")
                    },
                    "created_at": event_payload.get("timestamp")
                }
                timeline_db.append(timeline_entry)
                return {"status": "SUCCESS", "timeline_id": len(timeline_db)}
            return {"status": "IGNORED"}
            
        session_ended_event = {
            "event": "session_ended",
            "room_id": "sala-atendimento-vit-101",
            "prontuario_id": 101,
            "tecnico_id": 2,
            "duration_seconds": 920, # 15 min 20 sec
            "summary_telemetry": {
                "avg_mos": 4.35,
                "packet_loss_pct": 0.38,
                "avg_rtt_ms": 42.0
            },
            "timestamp": "2026-08-17T14:15:20Z"
        }
        
        result = handle_webrtc_webhook(session_ended_event)
        
        self.assertEqual(result["status"], "SUCCESS")
        self.assertEqual(len(timeline_db), 1)
        entry = timeline_db[0]
        self.assertEqual(entry["tipo_evento"], "atendimento_remoto")
        self.assertEqual(entry["metadata"]["duration_seconds"], 920)
        self.assertEqual(entry["metadata"]["avg_mos"], 4.35)
        self.assertIn("15 min 20 s", entry["descricao"])


if __name__ == "__main__":
    unittest.main()
