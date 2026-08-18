"""
CONECTA EGRESSO (SEJUS/ES) - WebRTC Signaling & Telemetry Microservice
FastAPI Application Entrypoint, Lifespan Hooks, CORS Middleware & Healthcheck.
"""

import logging
from contextlib import asynccontextmanager
from fastapi import FastAPI, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from .config import settings
from .signaling import router as signaling_router, room_manager, redis_bus, webhook_dispatcher
from .queue_manager import router as queue_router, queue_manager

logging.basicConfig(
    level=getattr(logging, settings.LOG_LEVEL.upper(), logging.INFO),
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("webrtc_service.main")


@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    Application lifespan startup and shutdown event coordinator.
    """
    logger.info("Initializing CONECTA EGRESSO WebRTC Microservice...")

    # Start Redis Bus
    await redis_bus.start()

    # Link RedisBus to QueueManager
    queue_manager.redis_bus = redis_bus

    # Start Room Manager cleanup daemon
    await room_manager.start()

    logger.info(f"Microservice running on {settings.HOST}:{settings.PORT} ({settings.ENVIRONMENT})")
    yield

    logger.info("Shutting down WebRTC Microservice...")
    await room_manager.stop()
    await redis_bus.stop()
    await webhook_dispatcher.close()
    logger.info("Cleanup completed. Server stopped.")


def create_app() -> FastAPI:
    """FastAPI Application Factory."""
    app = FastAPI(
        title="CONECTA EGRESSO - WebRTC Microservice",
        description="Asynchronous WebRTC Signaling, 78 Municipalities Waiting Room & ITU-T G.107 MOS Telemetry Engine",
        version="1.0.0",
        lifespan=lifespan
    )

    # Configure CORS
    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.cors_origins_list,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    # Healthcheck Route
    @app.get("/health", tags=["System"])
    async def health_check():
        return {
            "status": "healthy",
            "environment": settings.ENVIRONMENT,
            "redis_connected": redis_bus.is_connected,
            "active_rooms": len(room_manager._rooms),
            "active_signaling_clients": len(room_manager._clients),
            "active_queue_sessions": len(queue_manager._sessions),
            "ice_servers": settings.ice_servers
        }

    # Mount Routers
    app.include_router(signaling_router)
    app.include_router(queue_router)

    return app


app = create_app()
