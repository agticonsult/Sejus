"""
Authentication and Authorization Module (JWT Verification & RBAC)
"""

import time
import jwt
from typing import Optional, Dict, Any
from datetime import datetime, timedelta
from fastapi import HTTPException, status, WebSocket
from .config import settings
from .schemas import JWTClaims, ClientRole


class AuthError(Exception):
    def __init__(self, message: str, code: str = "AUTH_INVALID_TOKEN", close_code: int = 4001):
        super().__init__(message)
        self.message = message
        self.code = code
        self.close_code = close_code


def decode_jwt_token(token: str) -> JWTClaims:
    """
    Decodes and cryptographically verifies a JWT token.
    Validates HS256 signature, expiration, issuer, and audience.
    """
    if not token or not isinstance(token, str) or not token.strip():
        raise AuthError("Token is required", code="AUTH_TOKEN_MISSING", close_code=4001)

    # Clean Bearer prefix if provided
    clean_token = token.strip()
    if clean_token.lower().startswith("bearer "):
        clean_token = clean_token[7:].strip()

    if not clean_token:
        raise AuthError("Token is required", code="AUTH_TOKEN_MISSING", close_code=4001)

    try:
        payload = jwt.decode(
            clean_token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM],
            issuer=settings.JWT_ISSUER if settings.JWT_ISSUER else None,
            audience=settings.JWT_AUDIENCE if settings.JWT_AUDIENCE else None,
            options={"verify_exp": True, "verify_iss": bool(settings.JWT_ISSUER), "verify_aud": bool(settings.JWT_AUDIENCE)}
        )
        return JWTClaims(**payload)
    except jwt.ExpiredSignatureError:
        raise AuthError("Token has expired", code="AUTH_TOKEN_EXPIRED", close_code=4001)
    except jwt.InvalidIssuerError:
        raise AuthError("Invalid token issuer", code="AUTH_INVALID_ISSUER", close_code=4001)
    except jwt.InvalidAudienceError:
        raise AuthError("Invalid token audience", code="AUTH_INVALID_AUDIENCE", close_code=4001)
    except jwt.InvalidSignatureError:
        raise AuthError("Invalid token signature", code="AUTH_INVALID_SIGNATURE", close_code=4001)
    except jwt.DecodeError:
        raise AuthError("Malformed or invalid token", code="AUTH_DECODE_ERROR", close_code=4001)
    except Exception as exc:
        raise AuthError(f"Authentication failed: {str(exc)}", code="AUTH_ERROR", close_code=4001)


def validate_room_access(claims: JWTClaims, room_id: str) -> bool:
    """
    Validates if the authenticated user has access to the specified room_id.
    Gestores / Admins have global access.
    """
    normalized_role = claims.normalized_role
    if normalized_role in [ClientRole.GESTOR, ClientRole.DEFENSORIA]:
        return True

    if claims.room_id and claims.room_id != room_id:
        raise AuthError(
            f"Token is not authorized for room {room_id} (authorized for {claims.room_id})",
            code="ROOM_ACCESS_DENIED",
            close_code=4003
        )
    return True


def validate_unit_access(claims: JWTClaims, unit_id: str) -> bool:
    """
    Validates if the user has access to the specified unit queue.
    """
    normalized_role = claims.normalized_role
    if normalized_role in [ClientRole.GESTOR, ClientRole.TECHNICIAN]:
        # Technicians and Gestores can access regional/unit queues
        return True

    if claims.unit_id and str(claims.unit_id) != str(unit_id):
        raise AuthError(
            f"Token is not authorized for unit {unit_id}",
            code="UNIT_ACCESS_DENIED",
            close_code=4003
        )
    return True


def is_polite_peer(role: str | ClientRole) -> bool:
    """
    Determines politeness for W3C Perfect Negotiation (Glare Avoidance).
    Technicians are Impolite (take precedence in offer collisions).
    Attendees and Observers are Polite (yield on offer collision).
    """
    if isinstance(role, str):
        try:
            norm_role = ClientRole.normalize(role)
        except ValueError:
            return True
    else:
        norm_role = role

    return norm_role != ClientRole.TECHNICIAN


def create_access_token(
    user_id: int | str,
    name: str,
    role: str,
    room_id: Optional[str] = None,
    unit_id: Optional[str] = None,
    prontuario_id: Optional[str] = None,
    municipio: Optional[str] = None,
    expires_delta: Optional[timedelta] = None,
    secret_key: Optional[str] = None
) -> str:
    """
    Generates a cryptographically signed JWT token for authentication.
    """
    now_ts = int(time.time())
    exp_delta_s = int(expires_delta.total_seconds()) if expires_delta else 7200
    expire_ts = now_ts + exp_delta_s
    
    payload = {
        "iss": settings.JWT_ISSUER,
        "aud": settings.JWT_AUDIENCE,
        "sub": str(user_id),
        "name": name,
        "role": role,
        "iat": now_ts,
        "exp": expire_ts
    }
    if room_id:
        payload["room_id"] = room_id
    if unit_id:
        payload["unit_id"] = str(unit_id)
    if prontuario_id:
        payload["prontuario_id"] = prontuario_id
    if municipio:
        payload["municipio"] = municipio

    key = secret_key or settings.JWT_SECRET_KEY
    return jwt.encode(payload, key, algorithm=settings.JWT_ALGORITHM)

