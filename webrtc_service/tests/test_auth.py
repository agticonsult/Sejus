"""
Unit Tests for JWT Authentication, Authorization & Role Validation (app/auth.py)
"""

import pytest
import jwt
from datetime import datetime, timedelta
from app.auth import (
    decode_jwt_token,
    validate_room_access,
    validate_unit_access,
    is_polite_peer,
    create_access_token,
    AuthError
)
from app.schemas import ClientRole
from app.config import settings


def test_decode_valid_technician_token(token_factory):
    token = token_factory(user_id=101, role="tecnico", room_id="sala-101")
    claims = decode_jwt_token(token)

    assert claims.user_id == 101
    assert claims.normalized_role == ClientRole.TECHNICIAN
    assert claims.room_id == "sala-101"
    assert claims.name == "Dra. Márcia Oliveira"


def test_decode_valid_attendee_token(token_factory):
    token = token_factory(user_id=502, name="Lucas Santos", role="egresso", room_id="sala-101")
    claims = decode_jwt_token(token)

    assert claims.user_id == 502
    assert claims.normalized_role == ClientRole.ATTENDEE
    assert claims.name == "Lucas Santos"


def test_decode_bearer_prefix_handled(token_factory):
    raw_token = token_factory(user_id=101, role="tecnico")
    bearer_token = f"Bearer {raw_token}"
    claims = decode_jwt_token(bearer_token)
    assert claims.user_id == 101


def test_decode_expired_token(token_factory):
    expired_token = token_factory(expires_delta=timedelta(seconds=-10))
    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(expired_token)
    assert exc_info.value.code == "AUTH_TOKEN_EXPIRED"
    assert exc_info.value.close_code == 4001


def test_decode_bad_signature_token(token_factory):
    bad_token = token_factory(secret="wrong_secret_key_12345")
    with pytest.raises(AuthError) as exc_info:
        decode_jwt_token(bad_token)
    assert exc_info.value.code == "AUTH_INVALID_SIGNATURE"
    assert exc_info.value.close_code == 4001


def test_decode_empty_or_malformed_token():
    with pytest.raises(AuthError) as exc1:
        decode_jwt_token("")
    assert exc1.value.code == "AUTH_TOKEN_MISSING"

    with pytest.raises(AuthError) as exc2:
        decode_jwt_token("not-a-valid-jwt-string")
    assert exc2.value.code == "AUTH_DECODE_ERROR"


def test_validate_room_access_matching(token_factory):
    token = token_factory(role="egresso", room_id="sala-vitoria-01")
    claims = decode_jwt_token(token)
    assert validate_room_access(claims, "sala-vitoria-01") is True


def test_validate_room_access_mismatched(token_factory):
    token = token_factory(role="egresso", room_id="sala-vitoria-01")
    claims = decode_jwt_token(token)
    with pytest.raises(AuthError) as exc_info:
        validate_room_access(claims, "sala-colatina-99")
    assert exc_info.value.code == "ROOM_ACCESS_DENIED"
    assert exc_info.value.close_code == 4003


def test_validate_room_access_elevated_roles(token_factory):
    # Gestor and Defensoria can access any room
    token_gestor = token_factory(role="gestor", room_id="sala-01")
    claims_gestor = decode_jwt_token(token_gestor)
    assert validate_room_access(claims_gestor, "any-other-room-id") is True

    token_defensoria = token_factory(role="defensoria", room_id="sala-01")
    claims_defensoria = decode_jwt_token(token_defensoria)
    assert validate_room_access(claims_defensoria, "any-other-room-id") is True


def test_validate_unit_access(token_factory):
    token_citizen = token_factory(role="egresso", unit_id="3205002")
    claims_citizen = decode_jwt_token(token_citizen)
    assert validate_unit_access(claims_citizen, "3205002") is True

    with pytest.raises(AuthError) as exc_info:
        validate_unit_access(claims_citizen, "3201506")
    assert exc_info.value.code == "UNIT_ACCESS_DENIED"

    # Technicians and Gestores have global queue access
    token_tech = token_factory(role="tecnico", unit_id="3205002")
    claims_tech = decode_jwt_token(token_tech)
    assert validate_unit_access(claims_tech, "3201506") is True


def test_is_polite_peer_classification():
    # Impolite peer (Host/Technician takes precedence in SDP Glare)
    assert is_polite_peer("technician") is False
    assert is_polite_peer("tecnico") is False
    assert is_polite_peer(ClientRole.TECHNICIAN) is False

    # Polite peers (yield in SDP Glare)
    assert is_polite_peer("attendee") is True
    assert is_polite_peer("egresso") is True
    assert is_polite_peer("observer") is True
    assert is_polite_peer("defensoria") is True
