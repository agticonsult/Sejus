"""
Pytest Fixtures for CONECTA EGRESSO WebRTC Microservice Test Suite
"""

import sys
import os
import json
import asyncio
import pytest
import pytest_asyncio
import respx
import jwt
from datetime import datetime, timedelta
from unittest.mock import AsyncMock, MagicMock
from fastapi import WebSocketDisconnect
from fastapi.testclient import TestClient

# Ensure app package is importable
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app.config import settings
from app.auth import create_access_token
from app.telemetry import EModelMOSCalculator, SessionAggregator
from app.webhooks import WebhookDispatcher
from app.main import create_app, app
from app.room_manager import RoomManager
from app.queue_manager import QueueManager
from app.redis_bus import RedisBus


@pytest.fixture(scope="session")
def test_jwt_secret():
    return "test_jwt_secret_conecta_egresso_2026"


@pytest.fixture(scope="session")
def test_webhook_secret():
    return "test_webhook_secret_conecta_egresso_2026"


@pytest.fixture(scope="session")
def test_webhook_url():
    return "http://localhost:8000/api/webhooks/webrtc"


@pytest.fixture(autouse=True)
def reset_global_state():
    """Cleans up in-memory room and queue state between test runs."""
    from app.signaling import room_manager, session_aggregators
    from app.queue_manager import queue_manager
    room_manager._rooms.clear()
    room_manager._clients.clear()
    session_aggregators.clear()
    queue_manager._tickets.clear()
    queue_manager._zsets.clear()
    queue_manager._sessions.clear()
    queue_manager._unit_sessions.clear()
    yield


@pytest.fixture
def mock_redis():
    """Provides a fully functional AsyncMock simulating Redis."""
    redis_mock = AsyncMock()
    redis_mock.ping = AsyncMock(return_value=True)
    redis_mock.publish = AsyncMock(return_value=1)
    redis_mock.rpush = AsyncMock(return_value=1)
    redis_mock.zadd = AsyncMock(return_value=1)
    redis_mock.zrange = AsyncMock(return_value=[])
    redis_mock.zrank = AsyncMock(return_value=0)
    redis_mock.zrem = AsyncMock(return_value=1)
    redis_mock.zcard = AsyncMock(return_value=1)
    redis_mock.hset = AsyncMock(return_value=1)
    redis_mock.hget = AsyncMock(return_value="WAITING")
    redis_mock.hgetall = AsyncMock(return_value={})
    redis_mock.eval = AsyncMock(return_value=[1, "SUCCESS"])
    redis_mock.close = AsyncMock(return_value=None)
    
    # PubSub mock
    pubsub_mock = AsyncMock()
    pubsub_mock.psubscribe = AsyncMock(return_value=None)
    pubsub_mock.get_message = AsyncMock(return_value=None)
    pubsub_mock.close = AsyncMock(return_value=None)
    redis_mock.pubsub = MagicMock(return_value=pubsub_mock)
    
    return redis_mock


@pytest.fixture
def mos_calculator():
    return EModelMOSCalculator(
        r0=94.2,
        is_impairment=0.0,
        ie_codec=0.0,
        b_pl=15.0,
        advantage_factor=0.0
    )


@pytest.fixture
def test_client():
    test_app = create_app()
    with TestClient(test_app) as client:
        yield client


@pytest.fixture
def sample_perfect_report():
    return {
        "connection": {"rtt_ms": 10.0, "bytes_sent": 100000, "bytes_received": 200000},
        "audio": {"jitter_ms": 1.0, "packet_loss_pct": 0.0, "bitrate_kbps": 32.0},
        "video": {
            "fps": 30.0,
            "bitrate_kbps": 800.0,
            "frame_width": 1280,
            "frame_height": 720,
            "freeze_count": 0,
            "total_freeze_duration_s": 0.0
        }
    }


@pytest.fixture
def sample_cellular_report():
    return {
        "connection": {"rtt_ms": 50.0, "bytes_sent": 120000, "bytes_received": 220000},
        "audio": {"jitter_ms": 8.0, "packet_loss_pct": 0.5, "bitrate_kbps": 32.0},
        "video": {
            "fps": 30.0,
            "bitrate_kbps": 600.0,
            "frame_width": 1280,
            "frame_height": 720,
            "freeze_count": 0,
            "total_freeze_duration_s": 0.0
        }
    }


@pytest.fixture
def sample_degraded_report():
    return {
        "connection": {"rtt_ms": 380.0, "bytes_sent": 150000, "bytes_received": 250000},
        "audio": {"jitter_ms": 65.0, "packet_loss_pct": 12.5, "bitrate_kbps": 24.0},
        "video": {
            "fps": 12.0,
            "bitrate_kbps": 180.0,
            "frame_width": 640,
            "frame_height": 360,
            "freeze_count": 3,
            "total_freeze_duration_s": 4.5
        }
    }


@pytest.fixture
def token_factory():
    def _make_token(
        user_id: int | str = 101,
        name: str = "Dra. Márcia Oliveira",
        role: str = "tecnico",
        room_id: str = "sala-vitoria-101",
        unit_id: str = "3205002",
        prontuario_id: str = "550e8400-e29b-41d4-a716-446655440000",
        municipio: str = "Vitória",
        expires_delta: timedelta = timedelta(hours=2),
        secret: str = settings.JWT_SECRET_KEY
    ) -> str:
        return create_access_token(
            user_id=user_id,
            name=name,
            role=role,
            room_id=room_id,
            unit_id=unit_id,
            prontuario_id=prontuario_id,
            municipio=municipio,
            expires_delta=expires_delta,
            secret_key=secret
        )
    return _make_token


class AsyncWebSocketSession:
    """Mock WebSocket for purely asynchronous multi-client testing."""
    def __init__(self):
        self.inbox = asyncio.Queue()
        self.outbox = asyncio.Queue()
        self.client_state = MagicMock()
        self.client_state.name = "CONNECTED"
        self.is_closed = False

    async def accept(self):
        pass

    async def send_json(self, data: dict):
        if not self.is_closed:
            await self.outbox.put(data)

    async def send_text(self, text: str):
        if not self.is_closed:
            await self.outbox.put(text)

    async def receive_text(self) -> str:
        if self.is_closed:
            raise WebSocketDisconnect(code=1000)
        item = await self.inbox.get()
        if item is None:
            raise WebSocketDisconnect(code=1000)
        if isinstance(item, dict):
            return json.dumps(item)
        return str(item)

    async def receive_json(self) -> dict:
        item = await self.inbox.get()
        if item is None:
            raise WebSocketDisconnect(code=1000)
        if isinstance(item, dict):
            return item
        return json.loads(item)

    async def close(self, code: int = 1000, reason: str = ""):
        self.is_closed = True
        self.client_state.name = "DISCONNECTED"
        await self.inbox.put(None)


@pytest.fixture
def ws_session_factory():
    def _create():
        return AsyncWebSocketSession()
    return _create
