# Handoff Report: WebRTC Signaling & Realtime Architecture (Milestone M4)

**Agent:** Explorer 2 (Signaling & Realtime Architect)  
**Recipient:** Orchestrator / Sub-Orchestrator M4  
**Date:** 2026-08-17  
**Artifact Generated:** `d:\Agile\projeto dia 18\.agents\explorer_m4_2\analysis.md`

---

## 1. Observation

1. **User Request & Edital Scope (`ORIGINAL_REQUEST.md:18-20`):**
   > "Microsserviço assíncrono em Python (FastAPI / WebSockets / aiortc) para controle de salas de videochamada seguras, sinalização SDP/ICE, fila de espera em tempo real e monitoramento de telemetria/qualidade da conexão."
2. **Project Feature Inventory (`PROJECT.md:63-70`):**
   - Feature F26: FastAPI asynchronous WebRTC signaling server with WebSocket endpoints.
   - Feature F27: SDP Offer/Answer exchange protocol.
   - Feature F28: ICE Candidate trickle & routing.
   - Feature F29: Real-time queue management (waiting room, technician notification, patient admission).
   - Feature F31: Redis Pub/Sub multi-instance room state synchronization.
   - Feature F33: Video call room auto-expiration and cleanup daemon.
3. **Prototype Interface Analysis (`DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md:28-32` & `index.html:400-500`):**
   - Waiting room displays citizens categorized by municipality and priority tags (`Acolhimento Inicial • Psicossocial`, `Orientação Documental`, `Encaminhamento p/ Vaga`).
   - Video room provides 1-on-1 and multi-party layouts with signal indicators (`4G Estável`, `Wi-Fi`, `Sinal Fraco`), media toggles (mic, camera, screenshare), and call termination triggers.
4. **Sub-Orchestrator M4 Scope (`.agents/sub_orch_m4_webrtc/SCOPE.md:37-50`):**
   - Defined endpoint `/ws/signaling/{room_id}?token=...` and `/ws/queue/{unit_id}?user_id=...`.
   - Message categories specified: `join`, `joined`, `peer_joined`, `peer_left`, `offer`, `answer`, `ice_candidate`, `queue_status`, `call_attendee`, `admit_to_room`.

---

## 2. Logic Chain

1. **Async Concurrency & Non-Overlapping Socket Writes (Derived from Observation 2 & 4):**
   - In Starlette / FastAPI async WebSockets, parallel calls to `websocket.send_text()` or `send_json()` concurrently raise `RuntimeError: cannot call send() while send() is already active`.
   - *Design Choice:* Implement a dedicated `ClientConnection` class encapsulating an `asyncio.Lock` per socket (`send_lock`), ensuring all outbound messages (SDP, ICE, Heartbeat, Telemetry) are serialized safely without frame collisions.
2. **Deterministic WebRTC Perfect Negotiation (Derived from Observation 1, 3 & 4):**
   - Simultaneous SDP offer generation by both the technician and attendee results in SDP glare.
   - *Design Choice:* Implement W3C Perfect Negotiation rules where the citizen/egresso is designated as the `polite` peer (rolls back local offer on collision) and the technician/host is the `impolite` peer.
3. **Multi-Instance Scale with Zero Loopback (Derived from Observation 2):**
   - Multiple FastAPI worker pods require Pub/Sub synchronization via Redis. If Worker A publishes a signaling message to channel `room:{room_id}:events`, Worker A's own subscriber must not echo the message back to the originating socket.
   - *Design Choice:* Package all messages in a `RedisEnvelope` carrying `origin_worker_id`. Local subscribers filter out local origin sockets to prevent infinite echo loops while broadcasting to other pods seamlessly.
4. **Queue Prioritization & Race-Free Technician Claiming (Derived from Observation 2 & 3):**
   - High demand in rural municipalities without physical Escritórios Sociais requires fair FIFO queueing with priority support for urgent and legally preferred citizens (Lei 10.048/2000).
   - Multiple technicians operating simultaneously in the same queue could attempt to admit the same waiting citizen at the same exact millisecond.
   - *Design Choice:* Use a Redis Sorted Set (`ZSET`) where $\text{Score} = \text{Timestamp} - \text{PriorityBonus}$, paired with an atomic Lua script (`queue_claim_ticket.lua`) executing `ZREM` and `HSET status CLAIMED` atomically.
5. **Mobile Network Tolerance for 78 Municipalities (Derived from Observation 1 & 3):**
   - 3G/4G/5G mobile data drops in rural areas frequently cause transient TCP disconnects.
   - *Design Choice:* Implement a 30-second disconnect grace period and an ICE Restart protocol (`{"type": "offer", "ice_restart": true}`) so that temporary cell tower handoffs do not prematurely abort active social assistance consultations.

---

## 3. Caveats

1. **Coturn STUN/TURN Credentials:** Coturn must be accessible via port 3478 (and TURN UDP relay ports 49152–65535) in the Docker network. The signaling server dynamically passes Coturn STUN/TURN configurations to clients in the `joined` message payload.
2. **Audio/Video Media Relay vs Signaling:** The microservice acts strictly as a high-performance signaling and room management coordinator; media packets flow directly peer-to-peer (or via TURN relay), avoiding CPU bottlenecks on the Python service.
3. **Audio Recording / Archiving:** If compliance requires server-side recording in future phases, a Selective Forwarding Unit (SFU) or `aiortc` headless media sink will need to be attached as an observer peer in the room.

---

## 4. Conclusion

The asynchronous WebSocket signaling and Redis Pub/Sub architecture has been fully specified in `analysis.md`. The design guarantees:
- **Zero Race Conditions:** Per-socket async locks and per-room membership locks eliminate Starlette write race conditions.
- **Multi-Node High Availability:** Redis Pub/Sub channels (`room:{room_id}:events`, `queue:{unit_id}:events`) with envelope-based loopback suppression support horizontal scaling across N worker pods.
- **Atomic Queue Management:** Redis ZSET and atomic Lua claiming prevent double-admission across multi-technician desks.
- **Mobile Resilience:** Heartbeat reapers, 30s reconnection grace windows, and ICE restart support provide robust connectivity for citizens across all 78 ES municipalities.

---

## 5. Verification Method

To verify the architecture independently during implementation (Milestone M4.2):
1. **Inspect Architecture Spec:**
   - Open and review `d:\Agile\projeto dia 18\.agents\explorer_m4_2\analysis.md`.
2. **Execute Pytest WebSocket & Redis Test Fixtures (upon M4.2 delivery):**
   ```powershell
   cd "d:\Agile\projeto dia 18\webrtc_service"
   pytest tests/test_signaling.py tests/test_queue.py tests/test_connection_manager.py -v
   ```
3. **Validation Criteria:**
   - 100% pass on SDP Offer/Answer exchange and ICE candidate relay tests.
   - Zero `RuntimeError` concurrency exceptions under 100 simultaneous simulated client connections.
   - Atomic Lua claiming returns `SUCCESS` for only 1 technician and `TICKET_ALREADY_CLAIMED` for all subsequent concurrent claims.
