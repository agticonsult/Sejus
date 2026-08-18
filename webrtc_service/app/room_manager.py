"""
Room Lifecycle & WebSocket Connection Manager with Thread-Safe Locks & State Machine
"""

import asyncio
import logging
from typing import Dict, Optional, List, Any, Callable, Awaitable
from datetime import datetime, timedelta
from fastapi import WebSocket
from .config import settings
from .schemas import (
    RoomState,
    ClientRole,
    MediaState,
    ParticipantInfo,
    PeerJoinedMessage,
    PeerLeftMessage,
    PeerMediaUpdatedMessage,
    RoomTerminatedMessage
)

logger = logging.getLogger("webrtc_service.room_manager")


class ClientSession:
    """Represents a connected client session inside a room."""
    def __init__(
        self,
        websocket: WebSocket,
        client_id: str,
        user_id: int,
        name: str,
        role: ClientRole,
        room_id: str,
        unit_id: Optional[str] = None,
        prontuario_id: Optional[str] = None,
        municipio: Optional[str] = None
    ):
        self.websocket = websocket
        self.client_id = client_id
        self.user_id = user_id
        self.name = name
        self.role = role
        self.room_id = room_id
        self.unit_id = unit_id
        self.prontuario_id = prontuario_id
        self.municipio = municipio
        self.media_state = MediaState()
        self.joined_at = datetime.utcnow()
        self.last_heartbeat = datetime.utcnow()
        self.send_lock = asyncio.Lock()  # Serializes WS writes per socket
        self.is_connected = True

    def to_participant_info(self) -> ParticipantInfo:
        return ParticipantInfo(
            client_id=self.client_id,
            user_id=self.user_id,
            name=self.name,
            role=self.role.value if isinstance(self.role, ClientRole) else str(self.role),
            media_state=self.media_state,
            joined_at=self.joined_at
        )


class Room:
    """Represents a video consultation room and its state machine."""
    def __init__(
        self,
        room_id: str,
        unit_id: Optional[str] = None,
        prontuario_id: Optional[str] = None,
        room_code: Optional[str] = None
    ):
        self.room_id = room_id
        self.unit_id = unit_id
        self.prontuario_id = prontuario_id
        self.room_code = room_code or f"SAL-{room_id[:8].upper()}"
        self.state = RoomState.CREATED
        self.created_at = datetime.utcnow()
        self.started_at: Optional[datetime] = None
        self.ended_at: Optional[datetime] = None
        self.clients: Dict[str, ClientSession] = {}
        self.reconnecting_deadline: Optional[datetime] = None
        self.hangup_reason: Optional[str] = None
        self.lock = asyncio.Lock()

    @property
    def technician_count(self) -> int:
        return sum(1 for c in self.clients.values() if c.role == ClientRole.TECHNICIAN and c.is_connected)

    @property
    def attendee_count(self) -> int:
        return sum(1 for c in self.clients.values() if c.role == ClientRole.ATTENDEE and c.is_connected)

    @property
    def total_active_participants(self) -> int:
        return sum(1 for c in self.clients.values() if c.is_connected)

    @property
    def duration_seconds(self) -> int:
        if not self.started_at:
            return 0
        end_time = self.ended_at or datetime.utcnow()
        return max(0, int((end_time - self.started_at).total_seconds()))

    def update_state(self) -> RoomState:
        """Evaluates and transitions the room state based on active participants."""
        if self.state in [RoomState.ENDED, RoomState.EXPIRED, RoomState.ABORTED]:
            return self.state

        active_count = self.total_active_participants
        has_tech = self.technician_count > 0
        has_att = self.attendee_count > 0

        if has_tech and has_att:
            if self.state != RoomState.IN_PROGRESS:
                self.state = RoomState.IN_PROGRESS
                if not self.started_at:
                    self.started_at = datetime.utcnow()
                self.reconnecting_deadline = None
        elif active_count >= 1:
            if self.state == RoomState.IN_PROGRESS:
                # Transition to reconnecting
                self.state = RoomState.RECONNECTING
                self.reconnecting_deadline = datetime.utcnow() + timedelta(
                    seconds=settings.ROOM_GRACE_PERIOD_SECONDS
                )
            elif self.state != RoomState.RECONNECTING:
                self.state = RoomState.WAITING
        elif active_count == 0:
            if self.state == RoomState.IN_PROGRESS:
                self.state = RoomState.RECONNECTING
                self.reconnecting_deadline = datetime.utcnow() + timedelta(
                    seconds=settings.ROOM_GRACE_PERIOD_SECONDS
                )
            elif self.state == RoomState.WAITING:
                # Room empty while waiting
                pass

        return self.state


class RoomManager:
    """
    Central Room and Connection Registry with concurrency locks,
    multi-participant tracking, disconnect grace periods, and cleanup daemons.
    """
    def __init__(self):
        self._rooms: Dict[str, Room] = {}
        self._clients: Dict[str, ClientSession] = {}
        self._lock = asyncio.Lock()
        self._cleanup_task: Optional[asyncio.Task] = None
        self._running = False
        self.on_session_started: Optional[Callable[[Room], Awaitable[None]]] = None
        self.on_session_ended: Optional[Callable[[Room], Awaitable[None]]] = None

    async def start(self):
        """Starts background cleanup loop."""
        self._running = True
        self._cleanup_task = asyncio.create_task(self._cleanup_daemon())
        logger.info("RoomManager and cleanup daemon initialized.")

    async def stop(self):
        """Stops background cleanup loop."""
        self._running = False
        if self._cleanup_task:
            self._cleanup_task.cancel()
            try:
                await self._cleanup_task
            except asyncio.CancelledError:
                pass
        logger.info("RoomManager stopped.")

    async def get_or_create_room(
        self,
        room_id: str,
        unit_id: Optional[str] = None,
        prontuario_id: Optional[str] = None,
        room_code: Optional[str] = None
    ) -> Room:
        async with self._lock:
            if room_id not in self._rooms:
                self._rooms[room_id] = Room(
                    room_id=room_id,
                    unit_id=unit_id,
                    prontuario_id=prontuario_id,
                    room_code=room_code
                )
            return self._rooms[room_id]

    def get_room(self, room_id: str) -> Optional[Room]:
        return self._rooms.get(room_id)

    async def register_client(
        self,
        websocket: WebSocket,
        client_id: str,
        user_id: int,
        name: str,
        role: ClientRole,
        room_id: str,
        unit_id: Optional[str] = None,
        prontuario_id: Optional[str] = None,
        municipio: Optional[str] = None
    ) -> tuple[Room, ClientSession, List[ParticipantInfo]]:
        """
        Registers a new client session in the room.
        Returns the room, client session, and list of existing peers.
        """
        room = await self.get_or_create_room(
            room_id=room_id,
            unit_id=unit_id,
            prontuario_id=prontuario_id
        )

        async with room.lock:
            # Check if client_id already exists (reconnection)
            client = ClientSession(
                websocket=websocket,
                client_id=client_id,
                user_id=user_id,
                name=name,
                role=role,
                room_id=room_id,
                unit_id=unit_id,
                prontuario_id=prontuario_id,
                municipio=municipio
            )
            # Peers currently in room before adding this client
            peers = [c.to_participant_info() for c in room.clients.values() if c.is_connected and c.client_id != client_id]

            room.clients[client_id] = client
            self._clients[client_id] = client

            prev_state = room.state
            room.update_state()

            # Trigger on_session_started callback if entered IN_PROGRESS
            if prev_state != RoomState.IN_PROGRESS and room.state == RoomState.IN_PROGRESS:
                if self.on_session_started:
                    asyncio.create_task(self.on_session_started(room))

            logger.info(f"Client {client_id} (user {user_id}, {role}) joined room {room_id}. State: {room.state}")
            return room, client, peers

    async def unregister_client(self, client_id: str, reason: str = "disconnected") -> Optional[Room]:
        """
        Marks client as disconnected and handles room state transitions and grace periods.
        """
        client = self._clients.get(client_id)
        if not client:
            return None

        client.is_connected = False
        room = self._rooms.get(client.room_id)
        if not room:
            return None

        async with room.lock:
            # Notify remaining peers
            if client_id in room.clients:
                del room.clients[client_id]
            if client_id in self._clients:
                del self._clients[client_id]

            prev_state = room.state
            room.update_state()
            logger.info(f"Client {client_id} left room {room.room_id}. Reason: {reason}. State: {room.state}")
            return room

    async def safe_send_json(self, client: ClientSession, data: Dict[str, Any]) -> bool:
        """
        Thread-safe outbound JSON sender.
        Guarded by per-socket send_lock to eliminate Starlette concurrency errors.
        """
        if not client.is_connected:
            return False
        try:
            async with client.send_lock:
                await client.websocket.send_json(data)
            return True
        except Exception as exc:
            logger.debug(f"Error sending JSON to client {client.client_id}: {exc}")
            client.is_connected = False
            return False

    async def broadcast_room(
        self,
        room_id: str,
        data: Dict[str, Any],
        exclude_client_id: Optional[str] = None
    ):
        """Broadcasts a message to all active clients in the specified room."""
        room = self.get_room(room_id)
        if not room:
            return

        tasks = []
        for cid, client in list(room.clients.items()):
            if exclude_client_id and cid == exclude_client_id:
                continue
            if client.is_connected:
                tasks.append(self.safe_send_json(client, data))

        if tasks:
            await asyncio.gather(*tasks, return_exceptions=True)

    async def terminate_room(self, room_id: str, reason: str = "technician_ended") -> Optional[Room]:
        """
        Explicitly terminates a room and notifies all connected clients.
        """
        room = self.get_room(room_id)
        if not room:
            return None

        async with room.lock:
            if room.state in [RoomState.ENDED, RoomState.EXPIRED]:
                return room

            room.state = RoomState.ENDED
            room.ended_at = datetime.utcnow()
            room.hangup_reason = reason

            termination_msg = RoomTerminatedMessage(
                reason=reason,
                duration_seconds=room.duration_seconds
            ).model_dump()

            # Broadcast termination
            for client in list(room.clients.values()):
                if client.is_connected:
                    await self.safe_send_json(client, termination_msg)
                    try:
                        await client.websocket.close(code=1000, reason="Room terminated")
                    except Exception:
                        pass
                    client.is_connected = False

            room.clients.clear()

            if self.on_session_ended:
                asyncio.create_task(self.on_session_ended(room))

            logger.info(f"Room {room_id} terminated. Reason: {reason}. Duration: {room.duration_seconds}s")
            return room

    async def _cleanup_daemon(self):
        """Periodic background task that reaps dead sockets, timed-out reconnects, and expired rooms."""
        while self._running:
            try:
                await asyncio.sleep(settings.ROOM_CLEANUP_INTERVAL_SECONDS)
                now = datetime.utcnow()

                for room_id, room in list(self._rooms.items()):
                    # 1. Check reconnecting grace period timeout
                    if room.state == RoomState.RECONNECTING and room.reconnecting_deadline:
                        if now > room.reconnecting_deadline:
                            logger.info(f"Room {room_id} reconnecting grace period expired. Terminating.")
                            await self.terminate_room(room_id, reason="peer_connection_lost")

                    # 2. Check max duration limit (2h)
                    if room.started_at and (now - room.started_at).total_seconds() > settings.ROOM_MAX_DURATION_SECONDS:
                        logger.info(f"Room {room_id} exceeded max duration. Auto-terminating.")
                        await self.terminate_room(room_id, reason="max_duration_exceeded")

                    # 3. Check empty room idle timeout (>5 min without participants)
                    if room.state == RoomState.WAITING and room.total_active_participants == 0:
                        if (now - room.created_at).total_seconds() > 300:
                            logger.info(f"Room {room_id} idle without participants for 5min. Terminating.")
                            await self.terminate_room(room_id, reason="idle_timeout")

                    # 4. Remove expired rooms from memory
                    if room.state == RoomState.ENDED and room.ended_at:
                        if (now - room.ended_at).total_seconds() > 60:
                            room.state = RoomState.EXPIRED
                            async with self._lock:
                                if room_id in self._rooms:
                                    del self._rooms[room_id]
                            logger.debug(f"Room {room_id} expired and removed from memory.")

            except asyncio.CancelledError:
                break
            except Exception as exc:
                logger.error(f"Error in room cleanup daemon: {exc}", exc_info=False)
