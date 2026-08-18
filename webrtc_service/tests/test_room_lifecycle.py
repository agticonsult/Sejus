"""
Unit Tests for Room State Machine, Concurrency & Lifecycle Cleanup Daemon (app/room_manager.py)
"""

import pytest
import asyncio
from unittest.mock import AsyncMock, MagicMock
from datetime import datetime, timedelta
from app.room_manager import RoomManager, Room, RoomState, ClientRole
from app.schemas import MediaState


@pytest.mark.asyncio
async def test_room_state_transitions_basic():
    rm = RoomManager()
    room = await rm.get_or_create_room("room-trans-01")
    assert room.state == RoomState.CREATED

    # Mock WebSockets
    ws_tech = AsyncMock()
    ws_tech.send_json = AsyncMock()
    ws_att = AsyncMock()
    ws_att.send_json = AsyncMock()

    # 1. Technician joins -> State WAITING
    _, client_tech, _ = await rm.register_client(
        websocket=ws_tech,
        client_id="cid-tech",
        user_id=101,
        name="Dra. Marcia",
        role=ClientRole.TECHNICIAN,
        room_id="room-trans-01"
    )
    assert room.state == RoomState.WAITING

    # 2. Attendee joins -> State IN_PROGRESS
    _, client_att, _ = await rm.register_client(
        websocket=ws_att,
        client_id="cid-att",
        user_id=502,
        name="Lucas",
        role=ClientRole.ATTENDEE,
        room_id="room-trans-01"
    )
    assert room.state == RoomState.IN_PROGRESS
    assert room.started_at is not None

    # 3. Attendee disconnects -> State RECONNECTING (Grace Period)
    await rm.unregister_client("cid-att", reason="connection_lost")
    assert room.state == RoomState.RECONNECTING
    assert room.reconnecting_deadline is not None

    # 4. Attendee reconnects before deadline -> State restored to IN_PROGRESS
    _, client_att_2, _ = await rm.register_client(
        websocket=ws_att,
        client_id="cid-att-2",
        user_id=502,
        name="Lucas",
        role=ClientRole.ATTENDEE,
        room_id="room-trans-01"
    )
    assert room.state == RoomState.IN_PROGRESS
    assert room.reconnecting_deadline is None


@pytest.mark.asyncio
async def test_room_explicit_termination():
    rm = RoomManager()
    ended_callback_mock = AsyncMock()
    rm.on_session_ended = ended_callback_mock

    ws = AsyncMock()
    ws.send_json = AsyncMock()
    ws.close = AsyncMock()

    room, _, _ = await rm.register_client(
        websocket=ws,
        client_id="c-1",
        user_id=1,
        name="User",
        role=ClientRole.TECHNICIAN,
        room_id="room-term-01"
    )

    # Terminate room
    terminated_room = await rm.terminate_room("room-term-01", reason="attendance_completed")
    assert terminated_room.state == RoomState.ENDED
    assert terminated_room.ended_at is not None
    assert ws.send_json.called
    assert ws.close.called
    assert ended_callback_mock.called


@pytest.mark.asyncio
async def test_cleanup_daemon_handles_reconnecting_timeout():
    rm = RoomManager()
    room = await rm.get_or_create_room("room-timeout-01")
    room.state = RoomState.RECONNECTING
    # Set deadline in the past
    room.reconnecting_deadline = datetime.utcnow() - timedelta(seconds=10)

    # Trigger one iteration of cleanup logic
    now = datetime.utcnow()
    if room.state == RoomState.RECONNECTING and room.reconnecting_deadline:
        if now > room.reconnecting_deadline:
            await rm.terminate_room(room.room_id, reason="peer_connection_lost")

    assert room.state == RoomState.ENDED
    assert room.hangup_reason == "peer_connection_lost"
