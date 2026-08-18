"""
Waiting Room & Queue Manager for 78 ES Municipalities with Redis ZSETs & Atomic Lua Claiming
"""

import json
import time
import uuid
import asyncio
import logging
from typing import Dict, Optional, List, Any, Tuple
from datetime import datetime
from fastapi import APIRouter, WebSocket, WebSocketDisconnect, Query, status
from .config import settings
from .auth import decode_jwt_token, validate_unit_access, AuthError
from .schemas import (
    ClientRole,
    QueuePriority,
    QueueTicket,
    QueueJoinedResponse,
    QueuePositionUpdate,
    QueueStatusBroadcast,
    QueueItem,
    CallAttendeeMessage,
    AttendeeAdmittedBroadcast,
    ErrorMessage
)
from .redis_bus import RedisBus

logger = logging.getLogger("webrtc_service.queue_manager")

router = APIRouter(tags=["Queue / Waiting Room"])

# Atomic Lua script for claiming waiting room tickets without race conditions
CLAIM_TICKET_LUA = """
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
"""


def calculate_queue_score(priority: QueuePriority | str, timestamp_ms: int) -> float:
    """
    Computes priority-weighted FIFO score for Redis Sorted Set (ZSET).
    Lower scores rank first. Urgente < Preferencial < Normal.
    Within the same priority, earlier timestamp ranks first.
    """
    if isinstance(priority, QueuePriority):
        p_str = priority.value.lower()
    else:
        p_str = str(priority).lower()

    priority_weights = {
        "urgente": 1_000_000_000_000,
        "preferencial": 2_000_000_000_000,
        "normal": 3_000_000_000_000
    }
    base_weight = priority_weights.get(p_str, 3_000_000_000_000)
    return float(base_weight + timestamp_ms)


class QueueSession:
    """Tracks a connected WebSocket client in the queue."""
    def __init__(
        self,
        websocket: WebSocket,
        client_id: str,
        user_id: int,
        name: str,
        role: ClientRole,
        unit_id: str,
        ticket_id: Optional[str] = None
    ):
        self.websocket = websocket
        self.client_id = client_id
        self.user_id = user_id
        self.name = name
        self.role = role
        self.unit_id = unit_id
        self.ticket_id = ticket_id
        self.connected_at = datetime.utcnow()
        self.send_lock = asyncio.Lock()
        self.is_connected = True


class QueueManager:
    """
    Queue Manager coordinating waiting rooms for all 78 ES municipalities,
    using Redis ZSETs with in-memory fallback.
    """
    def __init__(self, redis_bus: Optional[RedisBus] = None):
        self.redis_bus = redis_bus
        # In-memory storage for unit queues: unit_id -> Dict[ticket_id, QueueTicket]
        self._tickets: Dict[str, Dict[str, QueueTicket]] = {}
        # In-memory score sorted lists: unit_id -> List[Tuple[ticket_id, float]]
        self._zsets: Dict[str, List[Tuple[str, float]]] = {}
        # Active client sessions: client_id -> QueueSession
        self._sessions: Dict[str, QueueSession] = {}
        # Unit sessions: unit_id -> Dict[client_id, QueueSession]
        self._unit_sessions: Dict[str, Dict[str, QueueSession]] = {}
        self._lock = asyncio.Lock()

    async def safe_send_json(self, session: QueueSession, data: Dict[str, Any]) -> bool:
        if not session.is_connected:
            return False
        try:
            async with session.send_lock:
                await session.websocket.send_json(data)
            return True
        except Exception as exc:
            logger.debug(f"Error sending JSON to queue session {session.client_id}: {exc}")
            session.is_connected = False
            return False

    async def register_session(
        self,
        websocket: WebSocket,
        client_id: str,
        user_id: int,
        name: str,
        role: ClientRole,
        unit_id: str,
        ticket_id: Optional[str] = None
    ) -> QueueSession:
        async with self._lock:
            session = QueueSession(
                websocket=websocket,
                client_id=client_id,
                user_id=user_id,
                name=name,
                role=role,
                unit_id=unit_id,
                ticket_id=ticket_id
            )
            self._sessions[client_id] = session
            if unit_id not in self._unit_sessions:
                self._unit_sessions[unit_id] = {}
            self._unit_sessions[unit_id][client_id] = session
            return session

    async def unregister_session(self, client_id: str):
        async with self._lock:
            session = self._sessions.pop(client_id, None)
            if session:
                session.is_connected = False
                unit_map = self._unit_sessions.get(session.unit_id, {})
                unit_map.pop(client_id, None)

    async def join_queue(
        self,
        unit_id: str,
        user_id: int,
        name: str,
        municipio: str,
        prioridade: QueuePriority | str = QueuePriority.NORMAL,
        motivo: str = "acolhimento_inicial"
    ) -> QueueTicket:
        """Adds a citizen to the unit waiting queue with priority score."""
        ticket_id = f"TCK-{datetime.utcnow().strftime('%Y%m%d')}-{uuid.uuid4().hex[:6].upper()}"
        now = datetime.utcnow()
        timestamp_ms = int(now.timestamp() * 1000)

        if isinstance(prioridade, str):
            try:
                prioridade = QueuePriority(prioridade.lower())
            except ValueError:
                prioridade = QueuePriority.NORMAL

        ticket = QueueTicket(
            ticket_id=ticket_id,
            unit_id=unit_id,
            user_id=user_id,
            name=name,
            municipio=municipio,
            prioridade=prioridade,
            motivo=motivo,
            created_at=now,
            status="WAITING"
        )
        score = calculate_queue_score(prioridade, timestamp_ms)

        # Store in Redis or in-memory
        if self.redis_bus and self.redis_bus.is_connected and self.redis_bus.redis_client:
            redis_client = self.redis_bus.redis_client
            zset_key = f"queue:{unit_id}:zset"
            ticket_key = f"queue:{unit_id}:ticket:{ticket_id}"

            await redis_client.zadd(zset_key, {ticket_id: score})
            await redis_client.hset(ticket_key, mapping={
                "ticket_id": ticket.ticket_id,
                "unit_id": ticket.unit_id,
                "user_id": str(ticket.user_id),
                "name": ticket.name,
                "municipio": ticket.municipio,
                "prioridade": ticket.prioridade.value,
                "motivo": ticket.motivo,
                "created_at": ticket.created_at.isoformat(),
                "status": ticket.status
            })
        else:
            async with self._lock:
                if unit_id not in self._tickets:
                    self._tickets[unit_id] = {}
                    self._zsets[unit_id] = []
                self._tickets[unit_id][ticket_id] = ticket
                self._zsets[unit_id].append((ticket_id, score))
                self._zsets[unit_id].sort(key=lambda x: x[1])

        # Publish queue update
        if self.redis_bus:
            await self.redis_bus.publish_queue_event(
                unit_id=unit_id,
                message_type="queue_updated",
                payload={"action": "joined", "ticket_id": ticket_id, "user_id": user_id}
            )

        logger.info(f"User {user_id} ({name}) joined queue for unit {unit_id}. Ticket: {ticket_id}, Score: {score}")
        return ticket

    async def get_queue_position(self, unit_id: str, ticket_id: str) -> Tuple[int, int]:
        """
        Returns (1-indexed position, total waiting).
        If not found, returns (0, total waiting).
        """
        if self.redis_bus and self.redis_bus.is_connected and self.redis_bus.redis_client:
            redis_client = self.redis_bus.redis_client
            zset_key = f"queue:{unit_id}:zset"
            rank = await redis_client.zrank(zset_key, ticket_id)
            total = await redis_client.zcard(zset_key)
            if rank is not None:
                return rank + 1, total
            return 0, total
        else:
            async with self._lock:
                zlist = self._zsets.get(unit_id, [])
                total = len(zlist)
                for idx, (t_id, _) in enumerate(zlist):
                    if t_id == ticket_id:
                        return idx + 1, total
                return 0, total

    async def get_queue_status(self, unit_id: str) -> QueueStatusBroadcast:
        """Retrieves list of all waiting attendees in priority order."""
        items: List[QueueItem] = []
        now = datetime.utcnow()

        if self.redis_bus and self.redis_bus.is_connected and self.redis_bus.redis_client:
            redis_client = self.redis_bus.redis_client
            zset_key = f"queue:{unit_id}:zset"
            ticket_ids = await redis_client.zrange(zset_key, 0, -1)
            for t_id in ticket_ids:
                ticket_data = await redis_client.hgetall(f"queue:{unit_id}:ticket:{t_id}")
                if ticket_data:
                    created_at_dt = datetime.fromisoformat(ticket_data.get("created_at", now.isoformat()))
                    wait_sec = int((now - created_at_dt).total_seconds())
                    items.append(QueueItem(
                        ticket_id=t_id,
                        user_id=int(ticket_data.get("user_id", 0)),
                        name=ticket_data.get("name", "Anônimo"),
                        municipio=ticket_data.get("municipio", "Vitória"),
                        prioridade=ticket_data.get("prioridade", "normal"),
                        motivo=ticket_data.get("motivo", "atendimento"),
                        waiting_seconds=max(0, wait_sec),
                        status=ticket_data.get("status", "WAITING")
                    ))
        else:
            async with self._lock:
                zlist = self._zsets.get(unit_id, [])
                ticket_map = self._tickets.get(unit_id, {})
                for t_id, _ in zlist:
                    ticket = ticket_map.get(t_id)
                    if ticket and ticket.status == "WAITING":
                        wait_sec = int((now - ticket.created_at).total_seconds())
                        items.append(QueueItem(
                            ticket_id=t_id,
                            user_id=ticket.user_id,
                            name=ticket.name,
                            municipio=ticket.municipio,
                            prioridade=ticket.prioridade.value,
                            motivo=ticket.motivo,
                            waiting_seconds=max(0, wait_sec),
                            status=ticket.status
                        ))

        return QueueStatusBroadcast(
            unit_id=unit_id,
            total_waiting=len(items),
            items=items
        )

    async def claim_and_admit_attendee(
        self,
        unit_id: str,
        ticket_id: str,
        technician_id: int,
        technician_name: str,
        room_id: str
    ) -> Tuple[bool, str]:
        """
        Atomically claims a ticket using Lua script (in Redis) or async lock (in-memory).
        Guarantees that two technicians cannot claim the same attendee.
        """
        now = datetime.utcnow()
        timestamp_str = now.isoformat()

        if self.redis_bus and self.redis_bus.is_connected and self.redis_bus.redis_client:
            redis_client = self.redis_bus.redis_client
            zset_key = f"queue:{unit_id}:zset"
            ticket_key = f"queue:{unit_id}:ticket:{ticket_id}"

            try:
                res = await redis_client.eval(
                    CLAIM_TICKET_LUA,
                    2,
                    zset_key,
                    ticket_key,
                    ticket_id,
                    str(technician_id),
                    technician_name,
                    room_id,
                    timestamp_str
                )
                success_code, msg = res[0], res[1]
                if success_code == 1:
                    # Notify queue subscribers
                    await self.redis_bus.publish_queue_event(
                        unit_id=unit_id,
                        message_type="attendee_admitted",
                        payload={
                            "ticket_id": ticket_id,
                            "room_id": room_id,
                            "technician_id": technician_id,
                            "technician_name": technician_name
                        }
                    )
                    return True, "SUCCESS"
                return False, str(msg)
            except Exception as exc:
                logger.error(f"Error executing Lua claim script in Redis: {exc}")
                return False, f"REDIS_LUA_ERROR: {exc}"
        else:
            async with self._lock:
                ticket_map = self._tickets.get(unit_id, {})
                ticket = ticket_map.get(ticket_id)
                if not ticket:
                    return False, "TICKET_NOT_IN_QUEUE"
                if ticket.status != "WAITING":
                    return False, "TICKET_ALREADY_CLAIMED"

                ticket.status = "CLAIMED"
                ticket.claimed_by_id = technician_id
                ticket.claimed_by_name = technician_name
                ticket.room_id = room_id
                ticket.claimed_at = now

                # Remove from sorted list
                zlist = self._zsets.get(unit_id, [])
                self._zsets[unit_id] = [item for item in zlist if item[0] != ticket_id]

                if self.redis_bus:
                    await self.redis_bus.publish_queue_event(
                        unit_id=unit_id,
                        message_type="attendee_admitted",
                        payload={
                            "ticket_id": ticket_id,
                            "room_id": room_id,
                            "technician_id": technician_id,
                            "technician_name": technician_name
                        }
                    )
                return True, "SUCCESS"

    async def remove_ticket(self, unit_id: str, ticket_id: str, reason: str = "cancelled"):
        """Removes a ticket from the queue (e.g. user left or cancelled)."""
        if self.redis_bus and self.redis_bus.is_connected and self.redis_bus.redis_client:
            redis_client = self.redis_bus.redis_client
            await redis_client.zrem(f"queue:{unit_id}:zset", ticket_id)
            await redis_client.hset(f"queue:{unit_id}:ticket:{ticket_id}", "status", "CANCELLED")
        else:
            async with self._lock:
                if unit_id in self._tickets and ticket_id in self._tickets[unit_id]:
                    self._tickets[unit_id][ticket_id].status = "CANCELLED"
                if unit_id in self._zsets:
                    self._zsets[unit_id] = [item for item in self._zsets[unit_id] if item[0] != ticket_id]

        if self.redis_bus:
            await self.redis_bus.publish_queue_event(
                unit_id=unit_id,
                message_type="queue_updated",
                payload={"action": "left", "ticket_id": ticket_id, "reason": reason}
            )

    async def broadcast_to_unit(self, unit_id: str, data: Dict[str, Any]):
        """Broadcasts payload to connected clients in the unit queue."""
        sessions = self._unit_sessions.get(unit_id, {})
        tasks = []
        for s in list(sessions.values()):
            if not s.is_connected:
                continue
            if data.get("type") == "queue_status" and s.role not in [ClientRole.TECHNICIAN, ClientRole.GESTOR]:
                continue
            tasks.append(self.safe_send_json(s, data))
        if tasks:
            await asyncio.gather(*tasks, return_exceptions=True)



# Global queue manager instance
queue_manager = QueueManager()


@router.websocket("/ws/queue/{unit_id}")
async def queue_websocket_endpoint(
    websocket: WebSocket,
    unit_id: str,
    token: Optional[str] = Query(None),
    ticket_id: Optional[str] = Query(None)
):
    """
    WebSocket endpoint for real-time queue management across 78 ES municipalities.
    """
    await websocket.accept()

    claims = None
    if isinstance(token, str) and token.strip():
        try:
            claims = decode_jwt_token(token)
            validate_unit_access(claims, unit_id)
        except AuthError as ae:
            await websocket.send_json(ErrorMessage(code=ae.code, message=ae.message).model_dump())
            await websocket.close(code=ae.close_code, reason=ae.message)
            return

    user_id = claims.user_id if claims else 0
    name = claims.name if claims else "Cidadão"
    role = claims.normalized_role if claims else ClientRole.ATTENDEE
    client_id = str(uuid.uuid4())
    t_id = ticket_id if isinstance(ticket_id, str) and ticket_id.strip() else None

    session = await queue_manager.register_session(
        websocket=websocket,
        client_id=client_id,
        user_id=user_id,
        name=name,
        role=role,
        unit_id=unit_id,
        ticket_id=t_id
    )

    try:
        # If client already has a ticket_id, send immediate position
        if t_id:
            pos, total = await queue_manager.get_queue_position(unit_id, t_id)
            if pos > 0:
                await queue_manager.safe_send_json(session, QueuePositionUpdate(
                    ticket_id=t_id,
                    position=pos,
                    estimated_wait_minutes=max(1, pos * 10),
                    total_waiting=total
                ).model_dump())


        # If technician/host, send initial queue status
        if role in [ClientRole.TECHNICIAN, ClientRole.GESTOR]:
            status_msg = await queue_manager.get_queue_status(unit_id)
            await queue_manager.safe_send_json(session, status_msg.model_dump())

        while True:
            raw_msg = await websocket.receive_text()
            try:
                data = json.loads(raw_msg)
            except json.JSONDecodeError:
                await queue_manager.safe_send_json(session, ErrorMessage(
                    code="INVALID_JSON",
                    message="Malformed JSON message"
                ).model_dump())
                continue

            msg_type = data.get("type")

            if msg_type == "join_queue":
                prioridade = data.get("prioridade", "normal")
                motivo = data.get("motivo", "acolhimento_inicial")
                citizen_name = data.get("name", name)
                municipio = data.get("municipio", claims.municipio if claims else "Vitória")

                ticket = await queue_manager.join_queue(
                    unit_id=unit_id,
                    user_id=user_id,
                    name=citizen_name,
                    municipio=municipio,
                    prioridade=prioridade,
                    motivo=motivo
                )
                session.ticket_id = ticket.ticket_id
                pos, _ = await queue_manager.get_queue_position(unit_id, ticket.ticket_id)

                await queue_manager.safe_send_json(session, QueueJoinedResponse(
                    ticket_id=ticket.ticket_id,
                    unit_id=unit_id,
                    position=pos,
                    estimated_wait_minutes=max(1, pos * 10)
                ).model_dump())

                # Broadcast updated queue status to technicians
                status_update = await queue_manager.get_queue_status(unit_id)
                await queue_manager.broadcast_to_unit(unit_id, status_update.model_dump())

            elif msg_type == "admit_attendee":
                if role not in [ClientRole.TECHNICIAN, ClientRole.GESTOR]:
                    await queue_manager.safe_send_json(session, ErrorMessage(
                        code="FORBIDDEN",
                        message="Only technicians can admit attendees from the queue"
                    ).model_dump())
                    continue

                target_ticket_id = data.get("ticket_id")
                target_room_id = data.get("room_id")

                if not target_ticket_id or not target_room_id:
                    await queue_manager.safe_send_json(session, ErrorMessage(
                        code="MISSING_PARAMETERS",
                        message="ticket_id and room_id are required for admission"
                    ).model_dump())
                    continue

                success, msg = await queue_manager.claim_and_admit_attendee(
                    unit_id=unit_id,
                    ticket_id=target_ticket_id,
                    technician_id=user_id,
                    technician_name=name,
                    room_id=target_room_id
                )

                if success:
                    # Notify target attendee session if connected
                    call_msg = CallAttendeeMessage(
                        ticket_id=target_ticket_id,
                        room_id=target_room_id,
                        token="",  # Frontend generates or receives token
                        ws_url=f"/ws/signaling/{target_room_id}",
                        tecnico_name=name
                    ).model_dump()

                    for sess in list(queue_manager._unit_sessions.get(unit_id, {}).values()):
                        if sess.ticket_id == target_ticket_id:
                            await queue_manager.safe_send_json(sess, call_msg)

                    # Broadcast updated queue list
                    status_update = await queue_manager.get_queue_status(unit_id)
                    await queue_manager.broadcast_to_unit(unit_id, status_update.model_dump())
                else:
                    await queue_manager.safe_send_json(session, ErrorMessage(
                        code="ADMIT_FAILED",
                        message=f"Could not admit attendee: {msg}"
                    ).model_dump())

            elif msg_type == "leave_queue":
                target_ticket = data.get("ticket_id", session.ticket_id)
                if target_ticket:
                    await queue_manager.remove_ticket(unit_id, target_ticket, reason="user_cancelled")
                    session.ticket_id = None
                    status_update = await queue_manager.get_queue_status(unit_id)
                    await queue_manager.broadcast_to_unit(unit_id, status_update.model_dump())

            elif msg_type == "ping":
                await queue_manager.safe_send_json(session, {"type": "pong", "timestamp": data.get("timestamp")})

    except WebSocketDisconnect:
        logger.info(f"Queue client {client_id} disconnected from unit {unit_id}")
    finally:
        await queue_manager.unregister_session(client_id)
