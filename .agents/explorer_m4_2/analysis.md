# CONECTA EGRESSO (SEJUS/ES) — Architecture Analysis & Technical Specification
## Milestone M4: Python FastAPI WebRTC Signaling & Realtime Architecture

**Author:** Explorer 2 (Signaling & Realtime Architect)  
**Date:** 2026-08-17  
**Scope:** `webrtc_service/` Asynchronous WebSocket Signaling, Redis Pub/Sub Synchronization, Queue / Waiting Room Manager, Network Resilience & Teardown Procedures.  
**Target Compliance:** Edital CPSI Nº 010/2026 SEJUS/ES, LGPD (Blind Index / PII protection), 78 ES Municipalities mobile 3G/4G/5G traversal.

---

## 1. Executive Summary & Architectural Overview

The WebRTC Microservice (`webrtc_service`) provides the real-time communications backbone for **CONECTA EGRESSO**, enabling secure, encrypted, low-latency audio/video consultations between SEJUS technicians (Assistentes Sociais, Psicólogos, Gestores) and citizens/egressos across all 78 municipalities in Espírito Santo.

### Architectural Blueprint
```
                              ┌───────────────────────────────────────────────┐
                              │            Nginx Ingress (:80 / :443)         │
                              └───────┬───────────────────────────────┬───────┘
                                      │ /ws/*                         │ /api/*
                                      ▼                               ▼
                 ┌────────────────────────────────────────┐       ┌───────────────────────┐
                 │  FastAPI WebRTC Microservice (:8001)   │       │  Laravel 11 Backend   │
                 │  Worker Pods (N instances)             │       │  PHP 8.3 FPM (:8000)  │
                 │                                        │       │                       │
                 │  ┌──────────────────────────────────┐  │       │  ┌─────────────────┐  │
                 │  │ ConnectionManager (Async Locks)  │  │       │  │ Webhook Ingest  │  │
                 │  └─────────────────┬────────────────┘  │       │  │ (/api/webhooks) │  │
                 │  ┌─────────────────┴────────────────┐  │       │  └─────────────────┘  │
                 │  │ SignalingEngine (SDP/ICE Relay)  │  │       │  ┌─────────────────┐  │
                 │  └─────────────────┬────────────────┘  │       │  │ JWT Signer      │  │
                 │  ┌─────────────────┴────────────────┐  │       │  │ (/api/webrtc)   │  │
                 │  │ QueueManager (ZSET / FIFO)       │  │       │  └─────────────────┘  │
                 │  └─────────────────┬────────────────┘  │       └───────────▲───────────┘
                 └────────────────────┼───────────────────┘                   │ HMAC-SHA256
                                      │                                       │ Webhooks
                                      ▼                                       │
                      ┌───────────────────────────────┐                       │
                      │   Redis 7.2 Cluster/Instance  ├───────────────────────┘
                      │   - Pub/Sub Channels          │
                      │   - State Hashes & ZSETs      │
                      │   - Distributed Locks (Redlock│
                      └───────────────┬───────────────┘
                                      │
                                      ▼
                      ┌───────────────────────────────┐
                      │    Coturn STUN/TURN (:3478)   │
                      │    (Mobile NAT Traversal)     │
                      └───────────────────────────────┘
```

---

## 2. WebSocket Connection Manager (`connection_manager.py`)

### 2.1 Connection State Machine
Each active WebSocket connection transitions through a deterministic state machine:

```
[CONNECTING] ──(WS Handshake + JWT Auth)──► [AUTHENTICATED]
                                                   │
                ┌──────────────────────────────────┴──────────────────────────────────┐
                ▼                                                                     ▼
           [IN_QUEUE]                                                             [IN_ROOM]
                │                                                                     │
                │ (Technician Admits)                                                 │ (Network Glitch)
                └────────────────────────► [TRANSFERRING]                             ▼
                                                │                              [RECONNECTING]
                                                ▼                                     │
                                            [IN_ROOM] ◄────────(Grace Period OK)──────┘
                                                │
                                                │ (Leave / End Call / Timeout)
                                                ▼
                                          [TERMINATING] ──► [DISCONNECTED]
```

### 2.2 Connection Metadata Schema (`ClientConnection`)
```python
from enum import Enum
from pydantic import BaseModel, Field
from typing import Optional, Dict, Any
from datetime import datetime
import asyncio
from fastapi import WebSocket

class ClientRole(str, Enum):
    EGRESSO = "egresso"           # Citizen / Attendee
    TECNICO = "tecnico"           # Social Worker / Psychologist / Host
    GESTOR = "gestor"             # SEJUS Admin / Supervisor
    DEFENSORIA = "defensoria"     # Legal Observer
    OBSERVER = "observer"         # Audit / Read-only Monitor

class ConnectionState(str, Enum):
    CONNECTING = "connecting"
    AUTHENTICATED = "authenticated"
    IN_QUEUE = "in_queue"
    IN_ROOM = "in_room"
    RECONNECTING = "reconnecting"
    DISCONNECTED = "disconnected"

class MediaState(BaseModel):
    audio_muted: bool = False
    video_muted: bool = False
    screen_sharing: bool = False
    network_quality: str = "good"  # "excellent", "good", "poor", "critical"

class ClientConnection:
    def __init__(
        self,
        websocket: WebSocket,
        client_id: str,
        user_id: int,
        name: str,
        role: ClientRole,
        room_id: Optional[str] = None,
        unit_id: Optional[str] = None,
        municipality: Optional[str] = None,
        metadata: Optional[Dict[str, Any]] = None
    ):
        self.websocket: WebSocket = websocket
        self.client_id: str = client_id
        self.user_id: int = user_id
        self.name: str = name
        self.role: ClientRole = role
        self.room_id: Optional[str] = room_id
        self.unit_id: Optional[str] = unit_id
        self.municipality: Optional[str] = municipality
        self.metadata: Dict[str, Any] = metadata or {}
        self.state: ConnectionState = ConnectionState.AUTHENTICATED
        self.media_state: MediaState = MediaState()
        self.connected_at: datetime = datetime.utcnow()
        self.last_heartbeat: datetime = datetime.utcnow()
        self.send_lock: asyncio.Lock = asyncio.Lock()  # Serializes WS writes per socket
```

### 2.3 Concurrency Control & Thread-Safe Async Locks
In high-concurrency asynchronous environments (FastAPI/Starlette/uvicorn), concurrent `WebSocket.send_text()` or `WebSocket.send_json()` calls on the same socket instance result in `RuntimeError: cannot call send() while send() is already active`.

**Concurrency Guarantees:**
1. **Per-Socket Outbound Lock (`send_lock`):** Every outbound payload sent to a specific client is guarded by `async with client.send_lock:` to guarantee atomic frame serialization.
2. **Per-Room Membership Lock (`room_lock`):** Room member list modifications (joins, leaves, disconnects) acquire a localized `asyncio.Lock` per room ID to eliminate race conditions.
3. **Global Registry Lock (`manager_lock`):** Global registration and unregistration operations are protected via fine-grained dictionary locks.

```python
class WebSocketConnectionManager:
    def __init__(self):
        # Map: room_id -> Dict[client_id, ClientConnection]
        self._rooms: Dict[str, Dict[str, ClientConnection]] = {}
        # Map: client_id -> ClientConnection
        self._connections: Dict[str, ClientConnection] = {}
        # Map: room_id -> asyncio.Lock
        self._room_locks: Dict[str, asyncio.Lock] = {}
        self._global_lock: asyncio.Lock = asyncio.Lock()

    def _get_room_lock(self, room_id: str) -> asyncio.Lock:
        if room_id not in self._room_locks:
            self._room_locks[room_id] = asyncio.Lock()
        return self._room_locks[room_id]

    async def safe_send_json(self, client: ClientConnection, data: dict) -> bool:
        """Thread-safe, non-overlapping WebSocket JSON sender with error trapping."""
        if client.websocket.client_state.name != "CONNECTED":
            return False
        try:
            async with client.send_lock:
                await client.websocket.send_json(data)
            return True
        except Exception as e:
            # Socket already closed or network dropped
            return False
```

### 2.4 Heartbeat Protocol & Inactivity Reaper
Mobile 4G/3G connections across rural Espírito Santo are subject to silent NAT dropouts where TCP sockets fail without sending FIN/RST packets.

1. **Protocol Specifications:**
   - **Ping Interval:** Client sends `{"type": "ping", "timestamp": 1723896000000}` every **15 seconds**.
   - **Pong Response:** Server immediately responds with `{"type": "pong", "timestamp": 1723896000000, "server_time": 1723896000020}`.
   - **Timeout Threshold:** **45 seconds** (3 consecutive missed heartbeats).
2. **Background Reaper Loop (`reaper_task`):**
   - Runs every 10 seconds.
   - Evaluates all active connections: `if (now - conn.last_heartbeat).total_seconds() > 45:`.
   - Flags connection as dead, initiates graceful teardown, emits `peer_left` via Redis Pub/Sub, and closes the physical socket.

---

## 3. WebRTC Signaling Protocol Specification (`signaling.py`)

### 3.1 Signaling Architecture (P2P Mesh with Server Relay)
In SEJUS social service consultations, the predominant topology is **1-on-1** (Técnico ↔ Egresso), with occasional **3-party** conferences (Técnico + Egresso + Defensoria Pública / Advogado / Familiar). A lightweight WebRTC Signaling Mesh with Server Relay guarantees:
- End-to-End Encryption (DTLS-SRTP).
- Zero media decoding overhead on the microservice (ultra-low CPU/RAM usage).
- Instant ICE trickling.

### 3.2 Deterministic Perfect Negotiation (Glare Avoidance)
To prevent WebRTC SDP Glare (race conditions where both peers send SDP Offers concurrently):
- **Polite Peer:** `Egresso` / `Citizen` / `Observer` (Rolls back local offer if offer collision occurs).
- **Impolite Peer:** `Tecnico` / `Host` (Ignores conflicting remote offers and enforces its own offer).

```
                 Técnico (Impolite)                    Egresso (Polite)
                         │                                    │
                         ├───────── SDP Offer ───────────────►│
                         │                                    ├─ Set Remote (Offer)
                         │                                    ├─ Create Answer
                         │◄──────── SDP Answer ───────────────┤
     Set Remote (Answer) ├─                                   │
                         │                                    │
                         ├────── ICE Candidate 1...N ────────►│
                         │◄───── ICE Candidate 1...N ─────────┤
                         │                                    │
                         ▼═══ DTLS-SRTP Media Connected ══════▼
```

### 3.3 Complete JSON Message Schema Catalog

#### Inbound Messages (Client ──► Server)
| Type | Schema / Payload | Description |
|---|---|---|
| `join` | `{"type": "join", "token": "<JWT_TOKEN>", "media_state": {"audio_muted": bool, "video_muted": bool}}` | Joins room, passes JWT token for role/room verification |
| `offer` | `{"type": "offer", "target_client_id": "<UUID>", "sdp": "<SDP_STRING>"}` | Sends SDP Offer to specific peer in the room |
| `answer` | `{"type": "answer", "target_client_id": "<UUID>", "sdp": "<SDP_STRING>"}` | Sends SDP Answer in response to an offer |
| `ice_candidate` | `{"type": "ice_candidate", "target_client_id": "<UUID>", "candidate": {"candidate": "...", "sdpMid": "0", "sdpMLineIndex": 0, "usernameFragment": "..."}}` | Trickles ICE candidate |
| `media_state_change` | `{"type": "media_state_change", "audio_muted": bool, "video_muted": bool, "screen_sharing": bool}` | Notifies room of track toggle |
| `telemetry` | `{"type": "telemetry", "rtt_ms": float, "jitter_ms": float, "packet_loss_pct": float, "bitrate_kbps": int, "fps": int, "resolution": "1280x720"}` | Periodic getStats() metrics (every 5s) |
| `leave` | `{"type": "leave", "reason": "user_action"}` | Voluntary departure from room |
| `terminate_room` | `{"type": "terminate_room", "reason": "attendance_completed", "notes": "..."}` | Host only: closes the consultation permanently |
| `ping` | `{"type": "ping", "timestamp": int}` | Liveness heartbeat |

#### Outbound Messages (Server ──► Client)
| Type | Schema / Payload | Description |
|---|---|---|
| `joined` | `{"type": "joined", "room_id": str, "client_id": str, "user_id": int, "role": str, "polite": bool, "peers": [{"client_id": str, "user_id": int, "name": str, "role": str, "media_state": {...}}], "ice_servers": [...]}` | Acknowledges join, provides roster and Coturn credentials |
| `peer_joined` | `{"type": "peer_joined", "peer": {"client_id": str, "user_id": int, "name": str, "role": str, "media_state": {...}}}` | Broadcast to room members when a new peer connects |
| `offer` | `{"type": "offer", "sender_client_id": str, "sdp": str}` | Relayed SDP Offer from peer |
| `answer` | `{"type": "answer", "sender_client_id": str, "sdp": str}` | Relayed SDP Answer from peer |
| `ice_candidate` | `{"type": "ice_candidate", "sender_client_id": str, "candidate": {...}}` | Relayed ICE candidate |
| `peer_media_updated` | `{"type": "peer_media_updated", "client_id": str, "media_state": {...}}` | Broadcast of peer track mute/unmute/screen |
| `peer_left` | `{"type": "peer_left", "client_id": str, "user_id": int, "reason": str}` | Peer disconnected or left |
| `room_terminated` | `{"type": "room_terminated", "reason": str, "duration_seconds": int}` | Room closed; prompts client to redirect/rate |
| `network_warning` | `{"type": "network_warning", "level": "poor" \| "critical", "suggested_action": "disable_video"}` | Adaptive network recommendation |
| `error` | `{"type": "error", "code": str, "message": str}` | Authentication failure, room full, bad format |
| `pong` | `{"type": "pong", "timestamp": int, "server_time": int}` | Heartbeat response |

### 3.4 Pydantic V2 Models for Signaling Validation
```python
from pydantic import BaseModel, Field
from typing import Optional, List, Dict, Any

class BaseSignalingMessage(BaseModel):
    type: str

class JoinMessage(BaseSignalingMessage):
    type: str = "join"
    token: str
    media_state: Optional[MediaState] = Field(default_factory=MediaState)

class SdpMessage(BaseSignalingMessage):
    type: str  # "offer" or "answer"
    target_client_id: str
    sdp: str

class IceCandidatePayload(BaseModel):
    candidate: str
    sdpMid: Optional[str] = None
    sdpMLineIndex: Optional[int] = None
    usernameFragment: Optional[str] = None

class IceCandidateMessage(BaseSignalingMessage):
    type: str = "ice_candidate"
    target_client_id: str
    candidate: IceCandidatePayload

class MediaStateChangeMessage(BaseSignalingMessage):
    type: str = "media_state_change"
    audio_muted: bool
    video_muted: bool
    screen_sharing: bool

class LeaveMessage(BaseSignalingMessage):
    type: str = "leave"
    reason: Optional[str] = "voluntary"
```

---

## 4. Multi-Instance Room Synchronization via Redis Pub/Sub (`redis_pubsub.py`)

In production, multiple instances of `webrtc_service` run behind Nginx. If Técnico connects to Pod 1 and Egresso connects to Pod 2, signaling frames must bridge across workers via Redis Pub/Sub.

```
       [Client 1: Técnico]                            [Client 2: Egresso]
               │                                               │
               ▼ WebSocket                                     ▼ WebSocket
     ┌───────────────────────┐                       ┌───────────────────────┐
     │ FastAPI Worker Pod A  │                       │ FastAPI Worker Pod B  │
     │ Local Room: [Client1] │                       │ Local Room: [Client2] │
     └───────────┬───────────┘                       └───────────▲───────────┘
                 │                                               │
                 │ PUBLISH room:sala-101:events                  │ SUBSCRIBE room:sala-101:events
                 ▼                                               │
     ┌───────────────────────────────────────────────────────────┴───────────┐
     │                           Redis Pub/Sub Bus                           │
     └───────────────────────────────────────────────────────────────────────┘
```

### 4.1 Redis Key & Channel Architecture
| Key / Channel Pattern | Type | Purpose | TTL |
|---|---|---|---|
| `room:{room_id}:events` | Pub/Sub Channel | Distributes signaling messages (offer, answer, ice, join, leave) | N/A (Transient) |
| `queue:{unit_id}:events` | Pub/Sub Channel | Distributes queue updates (call, admit, position updates) | N/A (Transient) |
| `room:{room_id}:meta` | Redis Hash | Room status (`status`, `host_id`, `created_at`, `unit_id`, `prontuario_id`) | 4 hours |
| `room:{room_id}:members` | Redis Hash | Active members: key=`client_id`, value=`JSON(ClientConnection)` | 4 hours |
| `queue:{unit_id}:zset` | Redis ZSET | Priority & FIFO waiting list (score=timestamp - priority_offset) | Persistent |
| `queue:{unit_id}:ticket:{ticket_id}` | Redis Hash | Detailed ticket metadata (citizen_name, cpf_hash, municipality, etc.) | 24 hours |
| `lock:room:{room_id}` | String / Lock | Distributed lock for atomic room mutations (admission, teardown) | 10 seconds |
| `lock:queue:claim:{ticket_id}` | String / Lock | Distributed lock preventing dual technician claiming | 10 seconds |

### 4.2 Inter-Node Message Envelope
To prevent message loopback (re-delivering an event to the local socket that generated it), all Pub/Sub messages are packaged in an envelope containing the source `worker_id`.

```python
import uuid
import socket

WORKER_ID = f"fastapi-{socket.gethostname()}-{uuid.uuid4().hex[:8]}"

class RedisEnvelope(BaseModel):
    envelope_id: str = Field(default_factory=lambda: str(uuid.uuid4()))
    origin_worker_id: str = WORKER_ID
    channel: str
    target_client_id: Optional[str] = None  # None = broadcast to all in room
    sender_client_id: str
    message_type: str
    payload: Dict[str, Any]
    timestamp: float = Field(default_factory=lambda: datetime.utcnow().timestamp())
```

### 4.3 Redis Pub/Sub Listener Task Lifecycle
Rather than opening a separate Redis connection and subscription for each room (which exhausts Redis connections under high room counts), the microservice uses a **Pattern-Subscribed Multiplexed Background Task** (`psubscribe`):

```python
import redis.asyncio as aioredis
import json
import logging

class RedisPubSubManager:
    def __init__(self, redis_url: str, connection_manager: WebSocketConnectionManager):
        self.redis_url = redis_url
        self.cm = connection_manager
        self.redis_client: Optional[aioredis.Redis] = None
        self.pubsub: Optional[aioredis.client.PubSub] = None
        self._listener_task: Optional[asyncio.Task] = None
        self._running = False

    async def start(self):
        self.redis_client = aioredis.from_url(self.redis_url, decode_responses=True)
        self.pubsub = self.redis_client.pubsub()
        # Pattern subscribe to all room and queue events
        await self.pubsub.psubscribe("room:*:events", "queue:*:events")
        self._running = True
        self._listener_task = asyncio.create_task(self._listen_loop())

    async def _listen_loop(self):
        while self._running:
            try:
                message = await self.pubsub.get_message(ignore_subscribe_messages=True, timeout=1.0)
                if not message:
                    await asyncio.sleep(0.01)
                    continue

                raw_data = message.get("data")
                if not raw_data or not isinstance(raw_data, str):
                    continue

                data = json.loads(raw_data)
                origin_worker = data.get("origin_worker_id")

                # Parse channel
                channel = message.get("channel", "")
                if channel.startswith("room:"):
                    room_id = channel.split(":")[1]
                    await self._handle_room_event(room_id, data, origin_worker)
                elif channel.startswith("queue:"):
                    unit_id = channel.split(":")[1]
                    await self._handle_queue_event(unit_id, data, origin_worker)

            except asyncio.CancelledError:
                break
            except Exception as e:
                logging.error(f"Redis PubSub listener error: {e}", exc_info=True)
                await asyncio.sleep(1.0)

    async def publish_room_event(self, room_id: str, sender_client_id: str, message_type: str, payload: dict, target_client_id: Optional[str] = None):
        envelope = {
            "envelope_id": str(uuid.uuid4()),
            "origin_worker_id": WORKER_ID,
            "target_client_id": target_client_id,
            "sender_client_id": sender_client_id,
            "message_type": message_type,
            "payload": payload,
            "timestamp": datetime.utcnow().timestamp()
        }
        await self.redis_client.publish(f"room:{room_id}:events", json.dumps(envelope))

    async def _handle_room_event(self, room_id: str, data: dict, origin_worker: str):
        target_client_id = data.get("target_client_id")
        sender_client_id = data.get("sender_client_id")
        msg_type = data.get("message_type")
        payload = data.get("payload")

        # Relay to local clients connected to this worker node
        local_clients = self.cm.get_room_clients(room_id)
        for client_id, client in local_clients.items():
            # If target specified, deliver only to target
            if target_client_id and client_id != target_client_id:
                continue
            # If broadcast, skip sender if sender is on this same worker
            if not target_client_id and client_id == sender_client_id and origin_worker == WORKER_ID:
                continue
            
            # Format and send outbound message
            outbound = {"type": msg_type, **payload}
            await self.cm.safe_send_json(client, outbound)

    async def stop(self):
        self._running = False
        if self._listener_task:
            self._listener_task.cancel()
        if self.pubsub:
            await self.pubsub.close()
        if self.redis_client:
            await self.redis_client.close()
```

---

## 5. Real-Time Waiting Room & Queue Manager (`queue_manager.py`)

The Waiting Room system coordinates the flow of egressos and family members seeking remote social, psychological, or legal assistance.

### 5.1 Priority Weighting & ZSET Ranking Algorithm
Every waiting citizen is queued in a Redis Sorted Set (`queue:{unit_id}:zset`).
The score determines FIFO order while allowing priority elevation:

$$\text{Score} = \text{EpochSeconds} - \text{PriorityOffset}$$

| Priority Level | Offset Seconds | Justification |
|---|---|---|
| `URGENTE` | $-86,400,000$ (equivalent to 1000 days prior) | Immediate vulnerability, crisis intervention |
| `PREFERENCIAL` | $-14,400$ (equivalent to 4 hours prior) | Lei 10.048/2000 (Elderly, Pregnant, Disabled, Mothers with infants) |
| `NORMAL` | $0$ | Standard FIFO order based on exact join timestamp |

- **Queue Ranking Query:** `ZRANK queue:{unit_id}:zset {ticket_id}` returns exact 0-indexed position.
- **Estimated Wait Time:** $\text{WaitMinutes} = \text{Rank} \times \frac{\text{AvgConsultationDuration (15 min)}}{\text{ActiveTechniciansCount}}$.

### 5.2 Atomic Technician Claiming (Lua Script)
When multiple technicians are active in the same Escritório Social queue, clicking "Chamar Egresso" must guarantee that exactly **one** technician claims the citizen, avoiding double admission.

```lua
-- queue_claim_ticket.lua
-- KEYS[1]: queue:{unit_id}:zset
-- KEYS[2]: queue:{unit_id}:ticket:{ticket_id}
-- ARGV[1]: ticket_id
-- ARGV[2]: technician_id
-- ARGV[3]: technician_name
-- ARGV[4]: room_id
-- ARGV[5]: current_timestamp

local exists = redis.call('ZSCORE', KEYS[1], ARGV[1])
if not exists then
    return {0, "TICKET_NOT_IN_QUEUE"}
end

local status = redis.call('HGET', KEYS[2], 'status')
if status ~= 'WAITING' then
    return {0, "TICKET_ALREADY_CLAIMED"}
end

-- Atomically update ticket status
redis.call('HSET', KEYS[2], 
    'status', 'CLAIMED',
    'claimed_by_id', ARGV[2],
    'claimed_by_name', ARGV[3],
    'room_id', ARGV[4],
    'claimed_at', ARGV[5]
)

-- Remove from waiting ZSET
redis.call('ZREM', KEYS[1], ARGV[1])

return {1, "SUCCESS"}
```

### 5.3 Waiting Room WebSocket Interaction (`/ws/queue/{unit_id}`)

```
 Citizen (Egresso)             Queue Manager (FastAPI + Redis)           Technician (Técnico)
        │                                      │                                  │
        ├─ WS Connect (/ws/queue/{unit_id}) ──►│                                  │
        │  {"type": "join_queue", ...}         ├─ Add to ZSET & Ticket Hash       │
        │◄─ {"type": "queue_ticket", pos: 3} ──┤                                  │
        │                                      ├─ Broadcast queue_updated ───────►│ (Technician UI updates)
        │                                      │                                  │
        │                                      │◄── {"type": "call_ticket"} ──────┤ (Clicks "Atender")
        │                                      ├─ Atomic Claim (Lua)              │
        │                                      ├─ Create Room: sala-vitoria-101   │
        │◄─ {"type": "admit_to_room",          │                                  │
        │    "room_id": "sala-vitoria-101",    │                                  │
        │    "token": "<JWT>"} ────────────────┤                                  │
        │                                      ├─ {"type": "attendee_admitted",   │
        │                                      │   "room_id": "sala-vitoria-101"}►│
        ▼                                      ▼                                  ▼
   Navigate to Video Room                 Update Redis                      Navigate to Video Room
```

#### Queue WebSocket Message Types:
1. `join_queue`: Citizen submits identification, municipality, priority, and reason for consultation.
2. `queue_ticket`: Confirmation with assigned `ticket_id`, position, and estimated wait time.
3. `queue_update`: Real-time position decrement pushed whenever ahead users are admitted.
4. `call_ticket`: Technician initiates the call.
5. `admit_to_room`: Push instruction to citizen's client containing destination `room_id` and signed entry JWT.
6. `cancel_queue`: Citizen voluntarily exits waiting room.

---

## 6. Mobile Network Resilience, Error Handling & Teardown Procedures

### 6.1 3G/4G/5G Mobile Network Tolerance Strategy
Because 74 of the 78 ES municipalities lack physical Social Offices, citizens in rural regions (e.g. Dores do Rio Preto, Montanha, Ecoporanga) frequently connect over fluctuating mobile signals.

1. **ICE Restart Protocol:**
   - When a peer experiences temporary signal loss, rather than terminating the room immediately, the client triggers an ICE restart:
   - Client sends `{"type": "offer", "sdp": "...", "ice_restart": true}`.
   - The remote peer answers with fresh ICE gathering without disrupting existing DTLS encryption keys.
2. **Reconnection Window (30-second Disconnect Grace Period):**
   - If WebSocket drops abruptly, the server does **not** instantly destroy the room.
   - Server marks peer status as `reconnecting` in Redis (`room:{room_id}:members`).
   - Server broadcasts `{"type": "peer_disconnected_transient", "client_id": "...", "grace_seconds": 30}` to the other party (displaying "Reconectando sinal do egresso...").
   - If client reconnects within 30 seconds with valid session ticket, state is restored seamlessly.
   - If timer expires without reconnection, full teardown is executed.

### 6.2 Standardized Error Response Matrix
All signaling and queue errors follow the unified schema:
`{"type": "error", "code": "<ERROR_CODE>", "message": "<HUMAN_READABLE_TEXT>", "details": {...}}`

| Error Code | HTTP/WS Code | Description | Recovery Action |
|---|---|---|---|
| `AUTH_INVALID_TOKEN` | 4401 | JWT signature invalid or expired | Redirect to login / renew token via Laravel |
| `ROOM_ACCESS_DENIED` | 4403 | User role or ID not permitted in this room | Display unauthorized banner |
| `ROOM_NOT_FOUND` | 4404 | Room does not exist or has expired | Return to dashboard |
| `ROOM_FULL` | 4409 | Room participant limit (max 4) reached | Display "Sala lotada" |
| `SDP_GLARE_COLLISION` | 4420 | Simultaneous SDP offers detected | Polite peer automatically yields |
| `QUEUE_ALREADY_JOINED`| 4421 | User already has an active waiting ticket | Restore existing ticket position |
| `RATE_LIMIT_EXCEEDED` | 4429 | Message burst exceeding 50 msg/sec | Throttle client signaling |
| `INTERNAL_ERROR` | 4500 | Unhandled exception in microservice | Graceful close, log trace to Sentry/stdout |

### 6.3 Room Teardown & Lifecycle Cleanup Sequence
When the consultation concludes (Technician clicks "Encerrar Atendimento" or session times out):

```
Step 1: Technician triggers `terminate_room`
        │
Step 2: Microservice acquires distributed lock `lock:room:{room_id}`
        │
Step 3: Update Redis room status to `TERMINATED`
        │
Step 4: Microservice broadcasts `room_terminated` event via Redis Pub/Sub to all connected peers
        │
Step 5: Telemetry Engine compiles aggregated session stats (duration, avg MOS, min MOS, packet loss)
        │
Step 6: Webhook Dispatcher sends HMAC-SHA256 signed payload `session_ended` to Laravel `/api/webhooks/webrtc`
        │
Step 7: Laravel backend registers session in `video_rooms` table and appends event to `prontuario_timeline`
        │
Step 8: Microservice closes all client WebSockets with code `1000 Normal Closure`
        │
Step 9: Clean up Redis keys:
        - DEL room:{room_id}:members
        - DEL room:{room_id}:meta (or set 1-hour expiration for auditing)
        - RELEASE lock:room:{room_id}
```

---

## 7. Component Interaction & Sequence Diagrams

### 7.1 Complete End-to-End Consultation Sequence
```
Citizen (Vue)        Laravel Backend     Queue WS (:8001)    Signaling WS (:8001)    Technician (Vue)
      │                     │                   │                    │                      │
   1. ├─ Enter Queue ──────►│                   │                    │                      │
   2. │                     ├─ Issue Ticket ───►│                    │                      │
   3. ├─ Connect /ws/queue ────────────────────►│                    │                      │
   4. │◄─ Queue Position: 1 ────────────────────┤                    │                      │
   5. │                     │                   │                    │◄─ View Waiting List ─┤
   6. │                     │                   │◄─ Call Citizen ───────────────────────────┤
   7. │◄─ admit_to_room (Token + room_id) ──────┤                    │                      │
   8. │                     │                   │                    ├─ Open Room ─────────►│
   9. ├─ Connect /ws/signaling/{room_id} ───────────────────────────►│                      │
  10. │                     │                   │                    │◄─ Connect /ws/sig ───┤
  11. │◄───────────────────── WebRTC SDP Offer / Answer Exchange ──────────────────────────►│
  12. │◄───────────────────── Trickle ICE Candidate Exchange ──────────────────────────────►│
  13. ▼══════════════════════ Direct Encrypted Video/Audio Stream (SRTP) ═══════════════════▼
  14. │                     │                   │                    │                      │
  15. │                     │                   │                    │◄─ End Call ──────────┤
  16. │◄─ room_terminated ───────────────────────────────────────────┤                      │
  17. │                     │◄─── Webhook (session_ended + Telemetry)                       │
  18. │                     ├─ Write to Prontuário Timeline                                 │
  19. ▼                     ▼                                                               ▼
```

---

## 8. Microservice Source File Structure

The `webrtc_service/` codebase is structured cleanly to follow asynchronous best practices, strict type validation with Pydantic v2, and 100% modular separation of concerns:

```
webrtc_service/
├── app/
│   ├── __init__.py
│   ├── main.py                     # FastAPI application bootstrap, CORS, lifespan hooks
│   ├── config.py                   # Pydantic BaseSettings (Redis URL, JWT secret, Coturn STUN/TURN, Webhook secret)
│   ├── schemas.py                  # Pydantic v2 signaling, queue, and telemetry message models
│   ├── connection_manager.py       # WebSocket connection registry, async per-socket locks, reaper task
│   ├── signaling.py                # WebRTC SDP/ICE signaling routers and perfect negotiation handler
│   ├── queue_manager.py            # Redis ZSET waiting room, priority scoring, Lua claim scripts
│   ├── redis_pubsub.py             # Multi-instance Redis Pub/Sub listener and publisher
│   ├── telemetry.py                # MOS calculation (ITU-T G.107 E-Model) and stats aggregator
│   └── webhooks.py                 # Asynchronous HMAC-SHA256 signed webhook dispatcher (httpx)
├── tests/
│   ├── __init__.py
│   ├── conftest.py                 # Pytest async fixtures, mock Redis, fake WebSockets
│   ├── test_connection_manager.py  # Unit tests for connection registry and async locks
│   ├── test_signaling.py           # Unit tests for SDP offer/answer relay and ICE trickling
│   ├── test_queue.py               # Unit tests for FIFO/Priority waiting room and atomic claiming
│   ├── test_redis_pubsub.py        # Unit tests for multi-instance message synchronization
│   ├── test_telemetry.py           # Unit tests for MOS formula and quality classification
│   └── test_webhooks.py            # Unit tests for HMAC generation and retry backoff
├── requirements.txt                # FastAPI, Uvicorn, aioredis, pydantic, httpx, PyJWT, pytest-asyncio
└── Dockerfile                      # Production multi-stage Python 3.12 container
```

---

## 9. Next Steps & Handoff
This specification provides the complete structural and algorithmic foundation for Explorer 1 (Spec Miner), Explorer 3 (Telemetry & Webhooks), and subsequent Implementation Engineers in Milestone M4.
