"""
Asynchronous Redis Pub/Sub Bus for Multi-Instance Horizontal Scalability
Includes Loopback Prevention and Graceful In-Memory Fallback.
"""

import json
import uuid
import socket
import asyncio
import logging
from typing import Optional, Dict, Any, Callable, Awaitable, List
from datetime import datetime
from pydantic import BaseModel, Field

try:
    import redis.asyncio as aioredis
except ImportError:
    aioredis = None

logger = logging.getLogger("webrtc_service.redis_bus")
WORKER_ID = f"worker-{socket.gethostname()}-{uuid.uuid4().hex[:8]}"


class RedisEnvelope(BaseModel):
    envelope_id: str = Field(default_factory=lambda: str(uuid.uuid4()))
    origin_worker_id: str = WORKER_ID
    channel: str
    target_client_id: Optional[str] = None
    sender_client_id: Optional[str] = None
    message_type: str
    payload: Dict[str, Any] = Field(default_factory=dict)
    timestamp: float = Field(default_factory=lambda: datetime.utcnow().timestamp())


class RedisBus:
    """
    Async Redis Pub/Sub message broker supporting pattern subscriptions,
    envelope wrapping, loopback avoidance, and in-memory mock fallback.
    """
    def __init__(self, redis_url: str):
        self.redis_url = redis_url
        self.redis_client: Optional[Any] = None
        self.pubsub: Optional[Any] = None
        self.worker_id = WORKER_ID
        self._running = False
        self._listener_task: Optional[asyncio.Task] = None
        self._handlers: Dict[str, List[Callable[[str, Dict[str, Any]], Awaitable[None]]]] = {
            "room": [],
            "queue": []
        }
        self._fallback_mode = False
        self._local_subscribers: Dict[str, List[Callable[[Dict[str, Any]], Awaitable[None]]]] = {}

    @property
    def is_connected(self) -> bool:
        return self._running and not self._fallback_mode and self.redis_client is not None

    async def start(self) -> bool:
        """
        Initializes Redis connection and launches listener task.
        Falls back to in-memory mode if Redis connection fails.
        """
        self._running = True
        if not aioredis:
            logger.warning("redis-py not installed, activating in-memory bus fallback.")
            self._fallback_mode = True
            return False

        try:
            self.redis_client = aioredis.from_url(
                self.redis_url,
                decode_responses=True,
                socket_timeout=5.0,
                socket_connect_timeout=3.0
            )
            # Test ping
            await self.redis_client.ping()
            self.pubsub = self.redis_client.pubsub()
            await self.pubsub.psubscribe("room:*:events", "queue:*:events")
            self._listener_task = asyncio.create_task(self._listen_loop())
            self._fallback_mode = False
            logger.info(f"Redis Pub/Sub Bus connected to {self.redis_url} (Worker: {self.worker_id})")
            return True
        except Exception as exc:
            logger.warning(f"Unable to connect to Redis at {self.redis_url}: {exc}. Operating in local memory mode.")
            self._fallback_mode = True
            if self.redis_client:
                try:
                    await self.redis_client.close()
                except Exception:
                    pass
                self.redis_client = None
            return False

    def register_room_handler(self, handler: Callable[[str, Dict[str, Any]], Awaitable[None]]):
        """Registers callback for room events: handler(room_id, envelope_dict)"""
        self._handlers["room"].append(handler)

    def register_queue_handler(self, handler: Callable[[str, Dict[str, Any]], Awaitable[None]]):
        """Registers callback for queue events: handler(unit_id, envelope_dict)"""
        self._handlers["queue"].append(handler)

    async def _listen_loop(self):
        """Background listening loop reading pattern-subscribed channels."""
        while self._running and self.pubsub:
            try:
                message = await self.pubsub.get_message(ignore_subscribe_messages=True, timeout=1.0)
                if not message:
                    await asyncio.sleep(0.01)
                    continue

                raw_data = message.get("data")
                channel = message.get("channel", "")
                if not raw_data or not isinstance(raw_data, str):
                    continue

                envelope = json.loads(raw_data)
                origin_worker = envelope.get("origin_worker_id")

                # Dispatch based on channel prefix
                if channel.startswith("room:"):
                    parts = channel.split(":")
                    if len(parts) >= 2:
                        room_id = parts[1]
                        for handler in self._handlers["room"]:
                            asyncio.create_task(handler(room_id, envelope))

                elif channel.startswith("queue:"):
                    parts = channel.split(":")
                    if len(parts) >= 2:
                        unit_id = parts[1]
                        for handler in self._handlers["queue"]:
                            asyncio.create_task(handler(unit_id, envelope))

            except asyncio.CancelledError:
                break
            except Exception as exc:
                if self._running:
                    logger.error(f"Error in Redis listener loop: {exc}", exc_info=False)
                    await asyncio.sleep(1.0)

    async def publish_room_event(
        self,
        room_id: str,
        message_type: str,
        payload: Dict[str, Any],
        sender_client_id: Optional[str] = None,
        target_client_id: Optional[str] = None
    ) -> bool:
        """Publishes an event to room channel: room:{room_id}:events"""
        channel = f"room:{room_id}:events"
        envelope = RedisEnvelope(
            origin_worker_id=self.worker_id,
            channel=channel,
            sender_client_id=sender_client_id,
            target_client_id=target_client_id,
            message_type=message_type,
            payload=payload
        ).model_dump()

        if self._fallback_mode or not self.redis_client:
            # Deliver locally in-memory
            for handler in self._handlers["room"]:
                asyncio.create_task(handler(room_id, envelope))
            return True

        try:
            await self.redis_client.publish(channel, json.dumps(envelope))
            return True
        except Exception as exc:
            logger.error(f"Failed to publish to Redis room channel {channel}: {exc}")
            # Fallback to local delivery
            for handler in self._handlers["room"]:
                asyncio.create_task(handler(room_id, envelope))
            return False

    async def publish_queue_event(
        self,
        unit_id: str,
        message_type: str,
        payload: Dict[str, Any],
        sender_client_id: Optional[str] = None,
        target_client_id: Optional[str] = None
    ) -> bool:
        """Publishes an event to queue channel: queue:{unit_id}:events"""
        channel = f"queue:{unit_id}:events"
        envelope = RedisEnvelope(
            origin_worker_id=self.worker_id,
            channel=channel,
            sender_client_id=sender_client_id,
            target_client_id=target_client_id,
            message_type=message_type,
            payload=payload
        ).model_dump()

        if self._fallback_mode or not self.redis_client:
            # Deliver locally in-memory
            for handler in self._handlers["queue"]:
                asyncio.create_task(handler(unit_id, envelope))
            return True

        try:
            await self.redis_client.publish(channel, json.dumps(envelope))
            return True
        except Exception as exc:
            logger.error(f"Failed to publish to Redis queue channel {channel}: {exc}")
            for handler in self._handlers["queue"]:
                asyncio.create_task(handler(unit_id, envelope))
            return False

    async def stop(self):
        """Stops the Redis bus and closes subscriptions gracefully."""
        self._running = False
        if self._listener_task:
            self._listener_task.cancel()
            try:
                await self._listener_task
            except asyncio.CancelledError:
                pass
        if self.pubsub:
            try:
                await self.pubsub.close()
            except Exception:
                pass
            self.pubsub = None
        if self.redis_client:
            try:
                await self.redis_client.close()
            except Exception:
                pass
            self.redis_client = None
        logger.info("Redis Pub/Sub Bus stopped.")
