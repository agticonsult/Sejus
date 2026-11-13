"""
Configuration & Settings Management using Pydantic Settings
"""

import base64
import hashlib
import hmac
import time
from typing import List, Dict, Any, Optional
from pydantic import Field, AliasChoices
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
    JWT_SECRET_KEY: str = Field(
        default="sejus_jwt_shared_secret_2026",
        validation_alias=AliasChoices("JWT_SECRET_KEY", "JWT_SECRET", "WEBRTC_JWT_SECRET")
    )
    JWT_ALGORITHM: str = Field(default="HS256")
    JWT_ISSUER: str = Field(default="conecta-egresso-laravel")
    JWT_AUDIENCE: str = Field(default="conecta-egresso-webrtc")

    # Redis Connection
    REDIS_URL: str = Field(default="redis://localhost:6379/0")

    # Webhook Dispatcher
    LARAVEL_WEBHOOK_URL: str = Field(default="http://localhost:8000/api/webhooks/webrtc")
    WEBHOOK_SECRET: str = Field(
        default="sejus_webrtc_webhook_secret_2026",
        validation_alias=AliasChoices("WEBHOOK_SECRET", "WEBRTC_WEBHOOK_SECRET")
    )
    WEBHOOK_MAX_RETRIES: int = Field(default=5)
    WEBHOOK_BASE_DELAY_SECONDS: float = Field(default=1.0)
    WEBHOOK_MAX_DELAY_SECONDS: float = Field(default=30.0)
    WEBHOOK_TIMEOUT_SECONDS: float = Field(default=8.0)

    # Coturn STUN/TURN Settings (RFC 5766 Ephemeral TURN REST API)
    STUN_SERVER_URL: str = Field(default="stun:stun.l.google.com:19302")
    TURN_SERVER_URL: str = Field(default="turn:localhost:3478")
    TURN_USERNAME: str = Field(default="conecta_user")
    TURN_CREDENTIAL: str = Field(default="conecta_password")

    COTURN_HOST: Optional[str] = Field(default=None)
    COTURN_PORT: int = Field(default=3478)
    COTURN_SECRET: str = Field(default="sejus_turn_secret_key_2026")
    COTURN_REALM: str = Field(default="sejus.es.gov.br")
    COTURN_TTL: int = Field(default=86400)
    USE_EPHEMERAL_TURN: bool = Field(default=True)

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

    def generate_ephemeral_turn_credentials(
        self, username_prefix: str = "webrtc_user", ttl: Optional[int] = None
    ) -> Dict[str, Any]:
        """
        Generates ephemeral TURN credentials per RFC 5766 TURN REST API.
        Username format: {timestamp}:{username_prefix}
        Credential format: base64(hmac-sha1(username, secret))
        Compatible with Coturn static-auth-secret / use-auth-secret.
        """
        turn_ttl = ttl or self.COTURN_TTL
        timestamp = int(time.time()) + turn_ttl
        username = f"{timestamp}:{username_prefix}"
        secret_bytes = self.COTURN_SECRET.encode("utf-8")
        username_bytes = username.encode("utf-8")
        digest = hmac.new(secret_bytes, username_bytes, hashlib.sha1).digest()
        credential = base64.b64encode(digest).decode("utf-8")

        return {
            "username": username,
            "credential": credential,
            "ttl": turn_ttl,
            "expires_at": timestamp,
        }

    @property
    def ice_servers(self) -> List[Dict[str, Any]]:
        servers: List[Dict[str, Any]] = []
        stun_url = self.STUN_SERVER_URL
        if self.COTURN_HOST:
            stun_url = f"stun:{self.COTURN_HOST}:{self.COTURN_PORT}"
        if stun_url:
            servers.append({"urls": stun_url})

        turn_base = self.TURN_SERVER_URL
        if self.COTURN_HOST:
            turn_base = f"turn:{self.COTURN_HOST}:{self.COTURN_PORT}"

        if self.USE_EPHEMERAL_TURN and self.COTURN_SECRET:
            creds = self.generate_ephemeral_turn_credentials()
            username = creds["username"]
            credential = creds["credential"]
        else:
            username = self.TURN_USERNAME
            credential = self.TURN_CREDENTIAL

        if turn_base:
            if "?" in turn_base:
                servers.append({
                    "urls": turn_base,
                    "username": username,
                    "credential": credential
                })
            else:
                servers.append({
                    "urls": f"{turn_base}?transport=udp",
                    "username": username,
                    "credential": credential
                })
                servers.append({
                    "urls": f"{turn_base}?transport=tcp",
                    "username": username,
                    "credential": credential
                })
        return servers


# Global singleton instance
settings = Settings()
