"""Tier 2 Boundary & Negative Tests: WebRTC Signaling, Telemetry, and Network Limits.

Verifies:
- MOS score calculation at 100% packet loss (MOS = 1.0 minimum floor)
- MOS score calculation at 0ms latency / 0% packet loss (MOS = 4.5 maximum ceiling)
- Extreme network jitter (> 1000ms) and latency telemetry handling
- Expired WebRTC room token rejection on WebSocket connect
- Malformed SDP offer/answer string handling
- Unauthenticated WebSocket message rejection
- Room participant capacity overflow (> max participants)
- Abrupt WebSocket disconnect cleanup
- Negative telemetry metrics clamping
- Malformed ICE candidate handling
"""

import base64
import hashlib
import hmac
import json
import math
import time
import unittest
from typing import Any, Dict, List, Optional, Set, Tuple


# --- WebRTC Telemetry & Signaling Simulators ---

class WebRTCTelemetryEngine:
    """ITU-T G.107 E-model approximation for WebRTC audio/video MOS calculation."""

    @staticmethod
    def calculate_mos(rtt_ms: float, jitter_ms: float, packet_loss_ratio: float) -> float:
        """Calculates Mean Opinion Score (MOS) in range [1.0, 4.5].

        - rtt_ms: Round Trip Time in milliseconds (e.g. 0 to 5000)
        - jitter_ms: Network jitter in milliseconds (e.g. 0 to 2000)
        - packet_loss_ratio: Packet loss fraction between 0.0 (0%) and 1.0 (100%)
        """
        # Clamp inputs to physically valid non-negative numbers
        rtt = max(0.0, float(rtt_ms))
        jitter = max(0.0, float(jitter_ms))
        loss = max(0.0, min(1.0, float(packet_loss_ratio)))

        # Effective one-way latency including jitter buffer delay
        one_way_delay = (rtt / 2.0) + (jitter * 2.0)

        # Baseline R-factor for WebRTC Opus codec (scaled to 100 max)
        r_0 = 100.0

        # Delay impairment Id
        if one_way_delay <= 177.3:
            id_delay = 0.024 * one_way_delay
        else:
            id_delay = 0.024 * one_way_delay + 0.11 * (one_way_delay - 177.3)

        # Equipment impairment Ie due to packet loss
        # At 100% packet loss (1.0), impairment completely degrades R
        ie_loss = 100.0 * (loss / (loss + 0.05)) if loss > 0 else 0.0

        r_factor = r_0 - id_delay - ie_loss

        # Convert R-factor to MOS [1.0, 4.5]
        if r_factor <= 0:
            mos = 1.0
        elif r_factor >= 100:
            mos = 4.5
        else:
            mos = 1.0 + (0.035 * r_factor) + (r_factor * (r_factor - 60.0) * (100.0 - r_factor) * 7.0e-6)

        # Strict clamping
        return max(1.0, min(4.5, round(mos, 2)))


class MockSignalingServer:
    """Simulates FastAPI WebSocket WebRTC signaling server boundaries."""

    def __init__(self, jwt_secret: str = "webrtc_shared_secret_2026"):
        self.jwt_secret = jwt_secret
        self.rooms: Dict[str, Dict[str, Any]] = {}
        self.authenticated_clients: Dict[str, Dict[str, Any]] = {}
        self.webhooks_dispatched: List[Dict[str, Any]] = []

    def verify_room_token(self, token: str) -> Tuple[bool, Optional[Dict[str, Any]], str]:
        parts = token.split(".")
        if len(parts) != 3:
            return False, None, "malformed_jwt_parts"

        header_b64, payload_b64, sig_b64 = parts
        try:
            # Verify signature
            signing_input = f"{header_b64}.{payload_b64}".encode("utf-8")
            expected_sig = hmac.new(self.jwt_secret.encode(), signing_input, hashlib.sha256).digest()
            expected_b64 = base64.urlsafe_b64encode(expected_sig).rstrip(b"=").decode()

            if not hmac.compare_digest(sig_b64, expected_b64):
                return False, None, "invalid_signature"

            # Parse payload
            padding = 4 - (len(payload_b64) % 4)
            if padding != 4:
                payload_b64 += "=" * padding
            payload = json.loads(base64.urlsafe_b64decode(payload_b64.encode()))

            now = int(time.time())
            if payload.get("exp", 0) < now:
                return False, None, "token_expired"

            return True, payload, "valid"
        except Exception:
            return False, None, "token_decode_error"

    def handle_client_join(
        self,
        client_id: str,
        room_id: str,
        token: str,
        max_capacity: int = 2,
    ) -> Tuple[int, Dict[str, Any]]:
        # 1. Verify token
        is_valid, payload, error_code = self.verify_room_token(token)
        if not is_valid:
            return 4401, {"type": "error", "code": error_code}

        # 2. Check room authorization
        if payload.get("room_id") != room_id:
            return 4403, {"type": "error", "code": "room_id_mismatch"}

        # 3. Create or get room
        if room_id not in self.rooms:
            self.rooms[room_id] = {
                "id": room_id,
                "participants": [],
                "max_capacity": max_capacity,
                "created_at": time.time(),
            }

        room = self.rooms[room_id]

        # 4. Check capacity limit
        if len(room["participants"]) >= room["max_capacity"]:
            return 4409, {"type": "error", "code": "room_full", "current_count": len(room["participants"])}

        # 5. Register participant
        participant = {
            "client_id": client_id,
            "user_id": payload.get("user_id"),
            "role": payload.get("role"),
            "name": payload.get("name"),
            "joined_at": time.time(),
        }
        room["participants"].append(participant)
        self.authenticated_clients[client_id] = {"room_id": room_id, "user": participant}

        return 200, {
            "type": "joined",
            "room_id": room_id,
            "participant_count": len(room["participants"]),
            "peers": [p["client_id"] for p in room["participants"] if p["client_id"] != client_id],
        }

    def handle_signaling_message(self, client_id: str, message: Dict[str, Any]) -> Tuple[int, Dict[str, Any]]:
        # Check authentication
        if client_id not in self.authenticated_clients:
            return 4401, {"type": "error", "code": "unauthenticated_session"}

        msg_type = message.get("type")

        if msg_type in ("offer", "answer"):
            sdp = message.get("sdp")
            is_valid_sdp, sdp_err = self.validate_sdp(sdp)
            if not is_valid_sdp:
                return 4422, {"type": "error", "code": "invalid_sdp", "reason": sdp_err}
            return 200, {"type": msg_type, "sdp": sdp, "from": client_id}

        elif msg_type == "ice-candidate":
            candidate = message.get("candidate")
            is_valid_ice, ice_err = self.validate_ice_candidate(candidate)
            if not is_valid_ice:
                return 4422, {"type": "error", "code": "invalid_ice_candidate", "reason": ice_err}
            return 200, {"type": "ice-candidate", "candidate": candidate, "from": client_id}

        elif msg_type == "telemetry":
            rtt = message.get("rtt_ms", 0)
            jitter = message.get("jitter_ms", 0)
            loss = message.get("packet_loss", 0.0)
            mos = WebRTCTelemetryEngine.calculate_mos(rtt, jitter, loss)
            return 200, {"type": "telemetry_ack", "computed_mos": mos}

        return 4400, {"type": "error", "code": "unknown_message_type"}

    def handle_disconnect(self, client_id: str) -> Optional[Dict[str, Any]]:
        client_info = self.authenticated_clients.pop(client_id, None)
        if not client_info:
            return None

        room_id = client_info["room_id"]
        room = self.rooms.get(room_id)
        if room:
            room["participants"] = [p for p in room["participants"] if p["client_id"] != client_id]
            # Dispatched webhook event to Laravel
            event = {
                "event": "peer_disconnected",
                "room_id": room_id,
                "client_id": client_id,
                "remaining_participants": len(room["participants"]),
                "timestamp": time.time(),
            }
            self.webhooks_dispatched.append(event)
            return event
        return None

    @staticmethod
    def validate_sdp(sdp: Any) -> Tuple[bool, str]:
        if not sdp or not isinstance(sdp, str):
            return False, "sdp_must_be_non_empty_string"
        if len(sdp) > 256 * 1024:  # 256KB max
            return False, "sdp_size_exceeded"
        lines = sdp.splitlines()
        # Must start with v=0 according to RFC 4566
        if not lines or not lines[0].strip().startswith("v=0"):
            return False, "sdp_missing_version_header_v0"
        # Must have media line m=
        if not any(line.strip().startswith("m=") for line in lines):
            return False, "sdp_missing_media_description"
        return True, "valid"

    @staticmethod
    def validate_ice_candidate(candidate: Any) -> Tuple[bool, str]:
        if not isinstance(candidate, dict):
            return False, "candidate_must_be_json_object"
        if "candidate" not in candidate or not candidate["candidate"]:
            return False, "missing_candidate_string"
        if not isinstance(candidate.get("sdpMLineIndex"), (int, type(None))):
            return False, "invalid_sdpMLineIndex"
        return True, "valid"


# --- Test Suite ---

class TestWebRTCNetworkLimits(unittest.TestCase):
    """Tier 2 Boundary test suite for WebRTC Telemetry, Signaling, and Limits."""

    def setUp(self):
        self.secret = "webrtc_shared_secret_2026"
        self.server = MockSignalingServer(jwt_secret=self.secret)
        self.now = int(time.time())

    def _create_webrtc_jwt(self, room_id: str, user_id: int, role: str, exp_delta: int = 3600) -> str:
        header = {"alg": "HS256", "typ": "JWT"}
        payload = {
            "room_id": room_id,
            "user_id": user_id,
            "role": role,
            "name": f"User {user_id}",
            "exp": self.now + exp_delta,
            "iat": self.now,
        }
        h_b64 = base64.urlsafe_b64encode(json.dumps(header).encode()).rstrip(b"=").decode()
        p_b64 = base64.urlsafe_b64encode(json.dumps(payload).encode()).rstrip(b"=").decode()
        sig = hmac.new(self.secret.encode(), f"{h_b64}.{p_b64}".encode(), hashlib.sha256).digest()
        s_b64 = base64.urlsafe_b64encode(sig).rstrip(b"=").decode()
        return f"{h_b64}.{p_b64}.{s_b64}"

    def test_01_mos_score_calculation_at_100_percent_packet_loss(self):
        """Verify MOS score calculation at total network blackout (100% loss) returns 1.0 minimum floor."""
        mos_100_loss = WebRTCTelemetryEngine.calculate_mos(rtt_ms=100, jitter_ms=20, packet_loss_ratio=1.0)
        self.assertEqual(mos_100_loss, 1.0, "100% packet loss must result in absolute minimum MOS 1.0.")

        # Even with 0ms latency, 100% packet loss must still yield 1.0
        mos_zero_lat_100_loss = WebRTCTelemetryEngine.calculate_mos(rtt_ms=0, jitter_ms=0, packet_loss_ratio=1.0)
        self.assertEqual(mos_zero_lat_100_loss, 1.0)

    def test_02_mos_score_calculation_at_zero_latency_and_zero_loss(self):
        """Verify MOS score calculation under perfect network conditions yields 4.5 maximum ceiling."""
        mos_perfect = WebRTCTelemetryEngine.calculate_mos(rtt_ms=0, jitter_ms=0, packet_loss_ratio=0.0)
        self.assertEqual(mos_perfect, 4.5, "Perfect zero-latency zero-loss connection must yield 4.5 ceiling.")

    def test_03_extreme_network_jitter_and_latency_handling(self):
        """Verify extreme network degradation (> 1500ms jitter, 3000ms latency) degrades gracefully to 1.0."""
        extreme_cases = [
            (3000.0, 1500.0, 0.20),
            (10000.0, 5000.0, 0.80),
            (500.0, 2000.0, 0.0),
        ]

        for rtt, jitter, loss in extreme_cases:
            mos = WebRTCTelemetryEngine.calculate_mos(rtt, jitter, loss)
            self.assertGreaterEqual(mos, 1.0)
            self.assertLessEqual(mos, 4.5)
            self.assertEqual(mos, 1.0, f"Extreme conditions ({rtt}ms/{jitter}ms) must bottom out at MOS 1.0.")

    def test_04_expired_webrtc_room_token_rejection(self):
        """Verify expired room token is rejected during WebSocket connection handshake."""
        expired_token = self._create_webrtc_jwt(room_id="sala-vitoria-101", user_id=1, role="tecnico", exp_delta=-60)

        status, resp = self.server.handle_client_join(
            client_id="ws-client-1",
            room_id="sala-vitoria-101",
            token=expired_token,
        )
        self.assertEqual(status, 4401)
        self.assertEqual(resp["code"], "token_expired")

    def test_05_malformed_sdp_offer_answer_string_handling(self):
        """Verify malformed SDP offer/answer strings are safely rejected with 4422 error."""
        valid_token = self._create_webrtc_jwt(room_id="sala-1", user_id=1, role="tecnico")
        self.server.handle_client_join("client-1", "sala-1", valid_token)

        malformed_sdps = [
            "",  # Empty
            None,  # Null
            "INVALID_BINARY_GARBAGE_STRING",  # Missing v=0
            "v=0\r\no=alice 123 456\r\ns=Session\r\nt=0 0\r\n",  # Missing m= line
            "A" * (300 * 1024),  # Exceeding 256KB max size
        ]

        for bad_sdp in malformed_sdps:
            status, resp = self.server.handle_signaling_message("client-1", {"type": "offer", "sdp": bad_sdp})
            self.assertEqual(status, 4422, f"Malformed SDP should return 4422, got {status}")
            self.assertEqual(resp["code"], "invalid_sdp")

        # Valid SDP should pass
        valid_sdp = "v=0\r\no=alice 2890844526 2890844526 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=audio 49170 RTP/AVP 0\r\n"
        status_ok, resp_ok = self.server.handle_signaling_message("client-1", {"type": "offer", "sdp": valid_sdp})
        self.assertEqual(status_ok, 200)
        self.assertEqual(resp_ok["type"], "offer")

    def test_06_unauthenticated_websocket_message_rejection(self):
        """Verify that sending signaling messages before completing join handshake is rejected."""
        unauthenticated_client_id = "rogue-client-99"
        status, resp = self.server.handle_signaling_message(
            unauthenticated_client_id,
            {"type": "offer", "sdp": "v=0\r\nm=video 5000 RTP/AVP 96\r\n"},
        )
        self.assertEqual(status, 4401)
        self.assertEqual(resp["code"], "unauthenticated_session")

    def test_07_room_participant_capacity_overflow(self):
        """Verify that exceeding room capacity (2 participants) rejects the 3rd attendee."""
        room_id = "sala-atendimento-201"
        tok1 = self._create_webrtc_jwt(room_id, user_id=1, role="tecnico")
        tok2 = self._create_webrtc_jwt(room_id, user_id=2, role="egresso")
        tok3 = self._create_webrtc_jwt(room_id, user_id=3, role="egresso")

        # Participant 1 (Técnico) joins
        s1, _ = self.server.handle_client_join("c-1", room_id, tok1, max_capacity=2)
        self.assertEqual(s1, 200)

        # Participant 2 (Egresso) joins
        s2, _ = self.server.handle_client_join("c-2", room_id, tok2, max_capacity=2)
        self.assertEqual(s2, 200)

        # Participant 3 attempts to join full room -> Rejected
        s3, resp3 = self.server.handle_client_join("c-3", room_id, tok3, max_capacity=2)
        self.assertEqual(s3, 4409, "3rd participant in 2-person room must be rejected with 4409.")
        self.assertEqual(resp3["code"], "room_full")
        self.assertEqual(resp3["current_count"], 2)

    def test_08_abrupt_websocket_disconnect_cleanup(self):
        """Verify that unexpected client disconnects clean up room state and dispatch webhook event."""
        room_id = "sala-cleanup-test"
        tok = self._create_webrtc_jwt(room_id, user_id=10, role="tecnico")
        self.server.handle_client_join("c-disconnect", room_id, tok)

        self.assertIn("c-disconnect", self.server.authenticated_clients)
        self.assertEqual(len(self.server.rooms[room_id]["participants"]), 1)

        # Simulate sudden disconnect
        dispatched_event = self.server.handle_disconnect("c-disconnect")
        self.assertIsNotNone(dispatched_event)
        self.assertEqual(dispatched_event["event"], "peer_disconnected")
        self.assertEqual(dispatched_event["remaining_participants"], 0)

        # Verify client removed from active state
        self.assertNotIn("c-disconnect", self.server.authenticated_clients)
        self.assertEqual(len(self.server.rooms[room_id]["participants"]), 0)
        self.assertEqual(len(self.server.webhooks_dispatched), 1)

    def test_09_negative_telemetry_metrics_clamping(self):
        """Verify negative RTT, jitter, or packet loss are sanitized/clamped to 0.0."""
        # Negative RTT (-50) and negative loss (-0.5) should clamp to 0.0 -> MOS 4.5
        mos_clamped = WebRTCTelemetryEngine.calculate_mos(rtt_ms=-50, jitter_ms=-10, packet_loss_ratio=-0.2)
        self.assertEqual(mos_clamped, 4.5)

    def test_10_malformed_ice_candidate_handling(self):
        """Verify invalid ICE candidate payload structure is rejected with 4422."""
        valid_token = self._create_webrtc_jwt("sala-ice", 1, "tecnico")
        self.server.handle_client_join("c-ice", "sala-ice", valid_token)

        invalid_ice_payloads = [
            {},
            {"candidate": ""},
            {"candidate": None},
            {"candidate": "candidate:1 1 UDP 2130706431 192.168.1.1 5000 typ host", "sdpMLineIndex": "not-an-int"},
        ]

        for bad_ice in invalid_ice_payloads:
            status, resp = self.server.handle_signaling_message("c-ice", {"type": "ice-candidate", "candidate": bad_ice})
            self.assertEqual(status, 4422)
            self.assertEqual(resp["code"], "invalid_ice_candidate")


if __name__ == "__main__":
    unittest.main()
