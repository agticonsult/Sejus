"""
Scenario 3: Remote Video Social Attendance & Prontuário Auto-Log (F17, F18, F23-F28, F30, F32, F40, F44)
=========================================================================================================
Target Profiles:
- Social Office Technician: Dra. Márcia Oliveira (CRESS 4891/ES - Escritório Social Virtual)
- Egresso: Roberto Ferreira (Egresso em Cariacica/ES - IBGE 3201308)

Complete End-to-End Operational Workflow:
1. Social Office Technician logs in and opens video attendance queue.
2. Egresso enters waiting room from mobile client (4G Mobile connection).
3. Technician admits Egresso and requests signed WebRTC room JWT token from Laravel.
4. Both clients establish WebSocket connection to Python FastAPI signaling server.
5. Exchange SDP Offer/Answer and ICE candidates (including STUN/TURN 4G candidates).
6. Simulate real-time session with 4G mobile network telemetry (jitter, packet loss, MOS calculation).
7. Video call concludes after scheduled duration (900 seconds).
8. FastAPI dispatches HMAC-SHA256 signed webhook (`session_ended`) to Laravel backend.
9. Laravel verifies webhook signature and automatically creates an immutable `ProntuarioTimeline` record.
10. Technician opens Prontuário Único view and confirms the automated timeline entry is present.
"""

import unittest
import json
import hashlib
import hmac
import time
import math
import base64
from typing import Dict, List, Any, Optional, Tuple


def calculate_mos_g107(rtt_ms: float, jitter_ms: float, packet_loss_pct: float) -> float:
    """
    Computes VoIP/WebRTC Mean Opinion Score (MOS) based on simplified ITU-T E-Model (G.107).
    - Returns MOS on a 1.0 to 4.5+ scale.
    """
    # Baseline R0
    r0 = 93.2

    # Delay impairment Id
    effective_latency = rtt_ms + (jitter_ms * 2)
    if effective_latency < 160:
        id_factor = effective_latency * 0.024
    else:
        id_factor = 3.84 + (effective_latency - 160) * 0.12

    # Equipment impairment Ie (due to packet loss)
    # Standard codec loss curve (Opus / WebRTC audio/video)
    ie_factor = 25.0 * math.log(1.0 + 8.0 * (packet_loss_pct / 100.0))

    # Rating factor R
    r_factor = max(0.0, min(100.0, r0 - id_factor - ie_factor))

    # Convert R-factor to MOS (ITU-T G.107 standard conversion)
    if r_factor < 0:
        mos = 1.0
    elif r_factor > 100:
        mos = 4.5
    else:
        mos = 1.0 + (0.035 * r_factor) + (r_factor * (r_factor - 60.0) * (100.0 - r_factor) * 7.0e-6)

    return round(max(1.0, min(4.5, mos)), 2)


class MockFastAPISignalingServer:
    """
    Simulation of Python FastAPI WebRTC signaling server (aiortc / WebSockets / Redis PubSub).
    Handles room admission, SDP exchange, ICE trickle, telemetry aggregation, and HMAC webhook dispatch.
    """
    def __init__(self, webhook_secret: str = "SEJUS_WEBRTC_WEBHOOK_SECRET_KEY_2026"):
        self.webhook_secret = webhook_secret
        self.rooms: Dict[str, Dict[str, Any]] = {}
        self.dispatched_webhooks: List[Dict[str, Any]] = []

    def connect_peer(self, room_id: str, peer_id: str, role: str, jwt_token: str) -> Dict[str, Any]:
        """Connects a peer to a signaling room."""
        if room_id not in self.rooms:
            self.rooms[room_id] = {
                "room_id": room_id,
                "created_at": time.time(),
                "peers": {},
                "sdp_offers": [],
                "sdp_answers": [],
                "ice_candidates": [],
                "telemetry_packets": [],
                "status": "WAITING_PEERS",
            }

        room = self.rooms[room_id]
        room["peers"][peer_id] = {
            "peer_id": peer_id,
            "role": role,
            "token": jwt_token,
            "connected_at": time.time(),
        }

        if len(room["peers"]) >= 2:
            room["status"] = "CONNECTED"

        return {
            "type": "joined",
            "room_id": room_id,
            "peer_id": peer_id,
            "peers_count": len(room["peers"]),
            "room_status": room["status"],
        }

    def handle_sdp_offer(self, room_id: str, sender_id: str, sdp: str) -> Dict[str, Any]:
        """Processes SDP Offer from Technician."""
        room = self.rooms[room_id]
        offer_record = {"sender": sender_id, "sdp": sdp, "timestamp": time.time()}
        room["sdp_offers"].append(offer_record)
        return {"type": "offer", "sdp": sdp, "sender": sender_id}

    def handle_sdp_answer(self, room_id: str, sender_id: str, sdp: str) -> Dict[str, Any]:
        """Processes SDP Answer from Egresso."""
        room = self.rooms[room_id]
        answer_record = {"sender": sender_id, "sdp": sdp, "timestamp": time.time()}
        room["sdp_answers"].append(answer_record)
        return {"type": "answer", "sdp": sdp, "sender": sender_id}

    def handle_ice_candidate(self, room_id: str, sender_id: str, candidate: Dict[str, Any]) -> Dict[str, Any]:
        """Processes ICE candidate exchange."""
        room = self.rooms[room_id]
        cand_record = {"sender": sender_id, "candidate": candidate, "timestamp": time.time()}
        room["ice_candidates"].append(cand_record)
        return {"type": "ice-candidate", "candidate": candidate, "sender": sender_id}

    def record_telemetry(self, room_id: str, sender_id: str, telemetry: Dict[str, float]) -> Dict[str, Any]:
        """Records network telemetry packet and computes real-time MOS."""
        room = self.rooms[room_id]
        mos = calculate_mos_g107(
            rtt_ms=telemetry.get("rtt_ms", 50.0),
            jitter_ms=telemetry.get("jitter_ms", 10.0),
            packet_loss_pct=telemetry.get("packet_loss_pct", 0.5),
        )
        packet = {
            "sender": sender_id,
            "rtt_ms": telemetry["rtt_ms"],
            "jitter_ms": telemetry["jitter_ms"],
            "packet_loss_pct": telemetry["packet_loss_pct"],
            "mos": mos,
            "timestamp": time.time(),
        }
        room["telemetry_packets"].append(packet)
        return {"type": "telemetry_ack", "computed_mos": mos}

    def end_call_and_dispatch_webhook(self, room_id: str, duration_seconds: int,
                                      attendee_id: int, technician_id: int) -> Dict[str, Any]:
        """Concludes call, aggregates telemetry, and generates HMAC signed webhook payload for Laravel."""
        if room_id not in self.rooms:
            self.rooms[room_id] = {
                "room_id": room_id,
                "created_at": time.time(),
                "peers": {},
                "sdp_offers": [],
                "sdp_answers": [],
                "ice_candidates": [],
                "telemetry_packets": [],
                "status": "WAITING_PEERS",
            }
        room = self.rooms[room_id]
        room["status"] = "ENDED"

        # Aggregate telemetry
        packets = room["telemetry_packets"]
        if packets:
            avg_mos = round(sum(p["mos"] for p in packets) / len(packets), 2)
            avg_loss = round(sum(p["packet_loss_pct"] for p in packets) / len(packets), 2)
            avg_jitter = round(sum(p["jitter_ms"] for p in packets) / len(packets), 2)
            avg_rtt = round(sum(p["rtt_ms"] for p in packets) / len(packets), 2)
        else:
            avg_mos, avg_loss, avg_jitter, avg_rtt = 4.20, 0.4, 8.0, 45.0

        webhook_payload = {
            "event": "session_ended",
            "room_id": room_id,
            "attendee_id": attendee_id,
            "technician_id": technician_id,
            "started_at": "2026-08-17T14:00:00Z",
            "ended_at": "2026-08-17T14:15:00Z",
            "duration_seconds": duration_seconds,
            "telemetry": {
                "avg_mos": avg_mos,
                "packet_loss_pct": avg_loss,
                "jitter_ms": avg_jitter,
                "rtt_ms": avg_rtt,
                "connection_type": "4G_MOBILE_RELAY",
                "coturn_relay_used": True,
            },
            "timestamp": "2026-08-17T14:15:01Z",
        }

        # Canonical string for HMAC-SHA256 signature
        canonical_str = json.dumps(webhook_payload, sort_keys=True, separators=(',', ':'))
        signature = hmac.new(self.webhook_secret.encode(), canonical_str.encode(), hashlib.sha256).hexdigest()

        dispatched_event = {
            "url": "http://localhost:8000/api/webhooks/webrtc",
            "header_signature": signature,
            "payload": webhook_payload,
        }
        self.dispatched_webhooks.append(dispatched_event)

        return dispatched_event


class MockLaravelBackend:
    """
    Simulation of Laravel 11 Backend handling RBAC queue, WebRTC JWT issuance,
    Webhook signature verification, and automatic ProntuarioTimeline persistence.
    """
    def __init__(self, webhook_secret: str = "SEJUS_WEBRTC_WEBHOOK_SECRET_KEY_2026",
                 jwt_secret: str = "SEJUS_JWT_SECRET_KEY_2026"):
        self.webhook_secret = webhook_secret
        self.jwt_secret = jwt_secret

        self.queue: List[Dict[str, Any]] = []
        self.prontuarios: Dict[int, Dict[str, Any]] = {
            10842: {
                "id": "PRON-2026-3201308-10842",
                "egresso_id": 10842,
                "nome": "Roberto Ferreira",
                "cpf_masked": "***.491.820-**",
                "municipio": "Cariacica",
                "timeline": [
                    {
                        "id": 1,
                        "tipo_evento": "CADASTRO_INICIAL",
                        "descricao": "Prontuário aberto no Escritório Social de Cariacica.",
                        "data": "2026-08-10 10:00:00",
                        "tecnico_id": 4891,
                        "imutavel": True,
                    }
                ]
            }
        }
        self.audit_log: List[Dict[str, Any]] = []

    def egresso_enter_queue(self, egresso_id: int, motivo: str, tipo_conexao: str) -> Dict[str, Any]:
        """Egresso requests remote video assistance."""
        ticket_id = f"TK-2026-{egresso_id}-{int(time.time())}"
        ticket = {
            "ticket_id": ticket_id,
            "egresso_id": egresso_id,
            "nome": "Roberto Ferreira",
            "municipio": "Cariacica",
            "motivo": motivo,
            "tipo_conexao": tipo_conexao,
            "entrou_em": time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()),
            "status": "AGUARDANDO_TECNICO",
        }
        self.queue.append(ticket)
        return {"status": "SUCCESS", "ticket": ticket}

    def tecnico_admit_and_create_room(self, ticket_id: str, tecnico_id: int) -> Dict[str, Any]:
        """Technician admits Egresso from queue and generates signed WebRTC JWT tokens."""
        ticket = next((t for t in self.queue if t["ticket_id"] == ticket_id), None)
        if not ticket:
            return {"status": "TICKET_NOT_FOUND"}

        ticket["status"] = "EM_ATENDIMENTO"
        ticket["tecnico_id"] = tecnico_id
        room_id = f"sala-es-cariacica-{ticket['egresso_id']}"

        # Generate JWT tokens for both parties
        token_tecnico = self._generate_webrtc_jwt(user_id=tecnico_id, role="tecnico", room_id=room_id)
        token_egresso = self._generate_webrtc_jwt(user_id=ticket["egresso_id"], role="egresso", room_id=room_id)

        return {
            "status": "ROOM_CREATED",
            "room_id": room_id,
            "token_tecnico": token_tecnico,
            "token_egresso": token_egresso,
            "ws_url": f"ws://localhost:8001/ws/room/{room_id}",
            "ice_servers": [
                {"urls": "stun:stun.l.google.com:19302"},
                {"urls": "turn:coturn.sejus.es.gov.br:3478", "username": "sejus_user", "credential": "turn_password_2026"},
            ]
        }

    def _generate_webrtc_jwt(self, user_id: int, role: str, room_id: str) -> str:
        """Signs JWT room authorization token."""
        header = base64.urlsafe_b64encode(b'{"alg":"HS256","typ":"JWT"}').decode().rstrip("=")
        payload_data = {
            "uid": user_id,
            "role": role,
            "room": room_id,
            "exp": int(time.time()) + 7200,
        }
        payload = base64.urlsafe_b64encode(json.dumps(payload_data).encode()).decode().rstrip("=")
        sig = hmac.new(self.jwt_secret.encode(), f"{header}.{payload}".encode(), hashlib.sha256).hexdigest()
        return f"{header}.{payload}.{sig}"

    def handle_webrtc_webhook(self, signature_header: str, raw_payload: Dict[str, Any]) -> Dict[str, Any]:
        """
        Receives webhook from FastAPI, verifies HMAC signature, and auto-records ProntuarioTimeline event.
        """
        # Canonical string for verification
        canonical_str = json.dumps(raw_payload, sort_keys=True, separators=(',', ':'))
        computed_sig = hmac.new(self.webhook_secret.encode(), canonical_str.encode(), hashlib.sha256).hexdigest()

        if not hmac.compare_digest(computed_sig, signature_header):
            return {"status": "UNAUTHORIZED", "error": "INVALID_HMAC_SIGNATURE"}

        event_type = raw_payload.get("event")
        if event_type == "session_ended":
            attendee_id = raw_payload["attendee_id"]
            prontuario = self.prontuarios.get(attendee_id)
            if not prontuario:
                return {"status": "ERROR", "error": "PRONTUARIO_NOT_FOUND"}

            duration = raw_payload["duration_seconds"]
            telemetry = raw_payload.get("telemetry", {})
            mos = telemetry.get("avg_mos", 4.0)

            timeline_event = {
                "id": len(prontuario["timeline"]) + 1,
                "tipo_evento": "ATENDIMENTO_REMOTO_VIDEO",
                "titulo": "Atendimento Psicossocial por Videoconferência",
                "descricao": f"Atendimento remoto concluído com sucesso. Duração: {duration // 60} min {duration % 60}s. Qualidade MOS: {mos}.",
                "duracao_segundos": duration,
                "data": raw_payload["ended_at"],
                "tecnico_id": raw_payload["technician_id"],
                "telemetria": telemetry,
                "imutavel": True,
            }
            prontuario["timeline"].append(timeline_event)

            # Audit record
            self.audit_log.append({
                "action": "AUTOMATED_PRONTUARIO_TIMELINE_INSERTION",
                "user_id": f"SYSTEM_WEBHOOK_WEBRTC",
                "prontuario_id": prontuario["id"],
                "event_type": "ATENDIMENTO_REMOTO_VIDEO",
                "duration": duration,
                "timestamp": raw_payload["timestamp"],
            })

            return {
                "status": "PROCESSED",
                "message": "Atendimento registrado no Prontuário com sucesso.",
                "timeline_event_id": timeline_event["id"],
            }

        return {"status": "IGNORED", "message": "Event not handled"}


def run_scenario_video_attendance_prontuario() -> Dict[str, Any]:
    """
    Executes complete Scenario 3 end-to-end user journey workflow.
    """
    laravel = MockLaravelBackend()
    fastapi = MockFastAPISignalingServer()
    results = {}

    # Step 1: Technician opens queue
    # Step 2: Egresso enters waiting room
    queue_res = laravel.egresso_enter_queue(
        egresso_id=10842,
        motivo="Atendimento Psicossocial e Orientação de Vagas",
        tipo_conexao="4G_MOBILE",
    )
    results["step2_queue"] = queue_res
    assert queue_res["status"] == "SUCCESS"
    ticket_id = queue_res["ticket"]["ticket_id"]

    # Step 3: Technician admits Egresso and requests signed WebRTC Room JWT
    admit_res = laravel.tecnico_admit_and_create_room(ticket_id=ticket_id, tecnico_id=4891)
    results["step3_room"] = admit_res
    assert admit_res["status"] == "ROOM_CREATED"
    room_id = admit_res["room_id"]
    token_tecnico = admit_res["token_tecnico"]
    token_egresso = admit_res["token_egresso"]

    # Step 4: Both connect to FastAPI WebSocket signaling server
    join_tec = fastapi.connect_peer(room_id, "peer_tecnico_4891", "tecnico", token_tecnico)
    join_egr = fastapi.connect_peer(room_id, "peer_egresso_10842", "egresso", token_egresso)
    results["step4_signaling_join"] = {"tecnico": join_tec, "egresso": join_egr}
    assert join_egr["room_status"] == "CONNECTED"

    # Step 5: Exchange SDP Offer/Answer and ICE candidates
    sdp_offer_text = "v=0\r\no=- 123456 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n"
    sdp_ans_text = "v=0\r\no=- 654321 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n"
    ice_candidate_relay = {
        "candidate": "candidate:1 1 UDP 1694498815 177.136.20.10 40000 typ relay raddr 10.0.0.2 rport 50000",
        "sdpMid": "video",
        "sdpMLineIndex": 0,
    }

    offer_res = fastapi.handle_sdp_offer(room_id, "peer_tecnico_4891", sdp_offer_text)
    answer_res = fastapi.handle_sdp_answer(room_id, "peer_egresso_10842", sdp_ans_text)
    ice_res = fastapi.handle_ice_candidate(room_id, "peer_egresso_10842", ice_candidate_relay)
    results["step5_webrtc_handshake"] = {"offer": offer_res, "answer": answer_res, "ice": ice_res}
    assert offer_res["type"] == "offer"
    assert answer_res["type"] == "answer"
    assert "relay" in ice_res["candidate"]["candidate"]

    # Step 6: Simulate real-time 4G network telemetry & calculate MOS
    telemetry_samples = [
        {"rtt_ms": 42.0, "jitter_ms": 6.5, "packet_loss_pct": 0.2},
        {"rtt_ms": 115.0, "jitter_ms": 28.0, "packet_loss_pct": 2.5},
        {"rtt_ms": 50.0, "jitter_ms": 8.0, "packet_loss_pct": 0.4},
    ]
    computed_mos_scores = []
    for s in telemetry_samples:
        ack = fastapi.record_telemetry(room_id, "peer_egresso_10842", s)
        computed_mos_scores.append(ack["computed_mos"])
    results["step6_telemetry_mos"] = computed_mos_scores
    assert all(3.0 <= m <= 4.5 for m in computed_mos_scores)

    # Step 7 & 8: Video call concludes (900 seconds) -> FastAPI dispatches HMAC signed webhook to Laravel
    duration = 900
    webhook_dispatched = fastapi.end_call_and_dispatch_webhook(
        room_id=room_id,
        duration_seconds=duration,
        attendee_id=10842,
        technician_id=4891,
    )
    results["step8_webhook"] = webhook_dispatched

    # Step 9: Laravel receives and verifies webhook signature, then auto-inserts Prontuario timeline event
    laravel_webhook_res = laravel.handle_webrtc_webhook(
        signature_header=webhook_dispatched["header_signature"],
        raw_payload=webhook_dispatched["payload"],
    )
    results["step9_laravel_ingest"] = laravel_webhook_res
    assert laravel_webhook_res["status"] == "PROCESSED"

    # Step 10: Technician opens Prontuário Único and verifies automated entry
    prontuario = laravel.prontuarios[10842]
    newest_event = prontuario["timeline"][-1]
    results["step10_prontuario_entry"] = newest_event
    assert newest_event["tipo_evento"] == "ATENDIMENTO_REMOTO_VIDEO"
    assert newest_event["duracao_segundos"] == 900
    assert newest_event["imutavel"] is True
    assert newest_event["tecnico_id"] == 4891
    assert "telemetria" in newest_event

    return {"status": "SUCCESS", "scenario": "Remote Video Social Attendance & Prontuário Auto-Log", "details": results}


class TestScenarioVideoAttendanceProntuario(unittest.TestCase):
    """
    TestCase class for Scenario 3.
    """
    def setUp(self):
        self.laravel = MockLaravelBackend()
        self.fastapi = MockFastAPISignalingServer()

    def test_complete_video_attendance_workflow(self):
        """Executes full Scenario 3 user journey."""
        res = run_scenario_video_attendance_prontuario()
        self.assertEqual(res["status"], "SUCCESS")

    def test_mos_calculation_algorithm(self):
        """Validates ITU-T G.107 MOS score calculator under diverse network profiles."""
        # Ideal fiber connection
        mos_ideal = calculate_mos_g107(rtt_ms=20, jitter_ms=2, packet_loss_pct=0.0)
        self.assertGreaterEqual(mos_ideal, 4.3)

        # Standard 4G Mobile connection
        mos_4g = calculate_mos_g107(rtt_ms=50, jitter_ms=8, packet_loss_pct=0.5)
        self.assertGreaterEqual(mos_4g, 4.0)

        # Degraded 3G/Fading connection
        mos_degraded = calculate_mos_g107(rtt_ms=280, jitter_ms=45, packet_loss_pct=8.0)
        self.assertLess(mos_degraded, 3.5)
        self.assertGreaterEqual(mos_degraded, 1.0)

    def test_jwt_room_token_generation_and_validation(self):
        """Verifies JWT room authorization tokens for Technician and Egresso."""
        ticket = self.laravel.egresso_enter_queue(10842, "Atendimento", "4G_MOBILE")["ticket"]
        room_data = self.laravel.tecnico_admit_and_create_room(ticket["ticket_id"], 4891)

        self.assertEqual(room_data["status"], "ROOM_CREATED")
        self.assertIn("token_tecnico", room_data)
        self.assertIn("token_egresso", room_data)
        self.assertIn("turn:coturn.sejus.es.gov.br:3478", str(room_data["ice_servers"]))

    def test_webhook_hmac_verification_and_tamper_rejection(self):
        """Verifies Laravel webhook ingest rejects forged signatures."""
        webhook_event = self.fastapi.end_call_and_dispatch_webhook("sala-test-1", 600, 10842, 4891)

        # Valid signature succeeds
        valid_res = self.laravel.handle_webrtc_webhook(
            signature_header=webhook_event["header_signature"],
            raw_payload=webhook_event["payload"],
        )
        self.assertEqual(valid_res["status"], "PROCESSED")

        # Corrupted signature fails
        bad_res = self.laravel.handle_webrtc_webhook(
            signature_header="deadbeefcafebabe" * 4,
            raw_payload=webhook_event["payload"],
        )
        self.assertEqual(bad_res["status"], "UNAUTHORIZED")


if __name__ == "__main__":
    unittest.main()
