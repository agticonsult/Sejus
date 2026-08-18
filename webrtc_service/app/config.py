"""
Configuration & Settings Management using Pydantic Settings
"""

from typing import List, Dict, Any, Optional
from pydantic import Field, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
        case_sensitive=False
    )

    # Server Info
    ENVIRONMENT: str = Field(default="development")
    HOST: str = Field(default="0.0.0.0")
    PORT: int = Field(default=8001)
    LOG_LEVEL: str = Field(default="INFO")

    # JWT Authentication & Authorization
    JWT_SECRET_KEY: str = Field(default="conecta_egresso_jwt_secret_dev_2026")
    JWT_ALGORITHM: str = Field(default="HS256")
    JWT_ISSUER: str = Field(default="conecta-egresso-laravel")
    JWT_AUDIENCE: str = Field(default="conecta-egresso-webrtc")

    # Redis Connection
    REDIS_URL: str = Field(default="redis://localhost:6379/0")

    # Webhook Dispatcher
    LARAVEL_WEBHOOK_URL: str = Field(default="http://localhost:8000/api/webhooks/webrtc")
    WEBHOOK_SECRET: str = Field(default="conecta_egresso_webhook_secret_dev_2026")
    WEBHOOK_MAX_RETRIES: int = Field(default=5)
    WEBHOOK_BASE_DELAY_SECONDS: float = Field(default=1.0)
    WEBHOOK_MAX_DELAY_SECONDS: float = Field(default=30.0)
    WEBHOOK_TIMEOUT_SECONDS: float = Field(default=8.0)

    # Coturn STUN/TURN Settings
    STUN_SERVER_URL: str = Field(default="stun:stun.l.google.com:19302")
    TURN_SERVER_URL: str = Field(default="turn:localhost:3478")
    TURN_USERNAME: str = Field(default="conecta_user")
    TURN_CREDENTIAL: str = Field(default="conecta_password")

    # CORS Settings
    CORS_ALLOWED_ORIGINS: str = Field(
        default="http://localhost:8000,http://127.0.0.1:8000,http://localhost:5173,http://localhost:3000,*"
    )

    # Room Lifecycle & Inactivity Thresholds
    ROOM_GRACE_PERIOD_SECONDS: int = Field(default=45)
    ROOM_MAX_DURATION_SECONDS: int = Field(default=7200)  # 2 hours max
    ROOM_CLEANUP_INTERVAL_SECONDS: int = Field(default=15)
    QUEUE_DISCONNECT_GRACE_SECONDS: int = Field(default=60)

    @property
    def cors_origins_list(self) -> List[str]:
        if not self.CORS_ALLOWED_ORIGINS:
            return ["*"]
        return [origin.strip() for origin in self.CORS_ALLOWED_ORIGINS.split(",") if origin.strip()]

    @property
    def ice_servers(self) -> List[Dict[str, Any]]:
        servers: List[Dict[str, Any]] = []
        if self.STUN_SERVER_URL:
            servers.append({"urls": self.STUN_SERVER_URL})
        if self.TURN_SERVER_URL:
            servers.append({
                "urls": self.TURN_SERVER_URL,
                "username": self.TURN_USERNAME,
                "credential": self.TURN_CREDENTIAL
            })
        return servers


# Global singleton instance
settings = Settings()
