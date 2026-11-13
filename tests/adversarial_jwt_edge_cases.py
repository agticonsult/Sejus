"""
Adversarial Edge Cases & Stress Test Suite for WebRTC JWT Decoding (Milestone 1 Iteration 2)
Tests: Floats, String Integers, Null Values, Negative Values, Non-ASCII Characters,
and Deterministic Exception Handling in FastAPI's decode_jwt_token.
"""

import sys
import time
import json
import jwt
from pathlib import Path
from typing import Any, Dict

# Setup paths
PROJECT_ROOT = Path(__file__).resolve().parent.parent
WEBRTC_SERVICE_DIR = PROJECT_ROOT / "webrtc_service"
sys.path.insert(0, str(WEBRTC_SERVICE_DIR))

from app.config import settings
from app.auth import (
    decode_jwt_token,
    validate_room_access,
    validate_unit_access,
    is_polite_peer,
    AuthError,
)
from app.schemas import ClientRole, JWTClaims

passed_count = 0
failed_count = 0
results_log = []


def record_assertion(success: bool, desc: str, details: str = ""):
    global passed_count, failed_count
    if success:
        passed_count += 1
        status = "PASS"
        print(f"  [PASS] {desc}")
    else:
        failed_count += 1
        status = "FAIL"
        print(f"  [FAIL] {desc} -> {details}")
    results_log.append({"status": status, "desc": desc, "details": details})


def create_raw_token(payload: Dict[str, Any], secret: str = settings.JWT_SECRET_KEY, algorithm: str = "HS256") -> str:
    """Helper to create raw JWT tokens without Pydantic preprocessing."""
    base_payload = {
        "iss": settings.JWT_ISSUER,
        "aud": settings.JWT_AUDIENCE,
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600,
    }
    base_payload.update(payload)
    return jwt.encode(base_payload, secret, algorithm=algorithm)


def test_float_values():
    print("\n--- 1. Testing Float Values in Claims ---")
    
    # 1.1 unit_id as positive float (e.g. 10.5)
    token = create_raw_token({
        "sub": "1001",
        "name": "Float User",
        "role": "tecnico",
        "unit_id": 10.5,
        "prontuario_id": 50.75,
    })
    try:
        claims = decode_jwt_token(token)
        record_assertion(claims.unit_id == "10.5", f"unit_id float 10.5 coerced to '10.5' (got {claims.unit_id})")
        record_assertion(claims.prontuario_id == "50.75", f"prontuario_id float 50.75 coerced to '50.75' (got {claims.prontuario_id})")
    except Exception as e:
        record_assertion(False, "Coerce float unit_id and prontuario_id", str(e))

    # 1.2 unit_id as float 0.0
    token_zero = create_raw_token({
        "sub": "1002",
        "name": "Zero Float User",
        "role": "tecnico",
        "unit_id": 0.0,
        "prontuario_id": 0.0,
    })
    try:
        claims_zero = decode_jwt_token(token_zero)
        record_assertion(claims_zero.unit_id == "0.0", f"unit_id float 0.0 coerced to '0.0' (got {claims_zero.unit_id})")
        record_assertion(claims_zero.prontuario_id == "0.0", f"prontuario_id float 0.0 coerced to '0.0' (got {claims_zero.prontuario_id})")
    except Exception as e:
        record_assertion(False, "Coerce float 0.0", str(e))

    # 1.3 sub as non-string float (RFC 7519 requires sub to be string) -> deterministic AuthError
    token_sub_float = create_raw_token({
        "sub": 123.45,
        "name": "Sub Numeric Float User",
        "role": "attendee",
    })
    sub_num_rejected = False
    try:
        decode_jwt_token(token_sub_float)
    except AuthError as ae:
        sub_num_rejected = ("Subject must be a string" in ae.message and ae.code == "AUTH_ERROR")
    record_assertion(sub_num_rejected, "Non-string float 'sub' deterministically rejected with AuthError(AUTH_ERROR)")

    # 1.4 sub as string float (e.g. "123.45")
    token_sub_str_float = create_raw_token({
        "sub": "123.45",
        "name": "Sub String Float User",
        "role": "attendee",
    })
    try:
        claims_sub = decode_jwt_token(token_sub_str_float)
        record_assertion(claims_sub.sub == "123.45", f"sub string float '123.45' preserved (got {claims_sub.sub})")
        record_assertion(isinstance(claims_sub.user_id, int), f"user_id property returns integer via hash fallback (got {claims_sub.user_id})")
    except Exception as e:
        record_assertion(False, "Coerce string float sub and safe user_id fallback", str(e))

    # 1.5 Large scientific notation float (e.g. 1e6)
    token_sci = create_raw_token({
        "sub": "1003",
        "name": "Sci Float User",
        "role": "tecnico",
        "unit_id": 1e6,
    })
    try:
        claims_sci = decode_jwt_token(token_sci)
        record_assertion(claims_sci.unit_id in ["1000000.0", "1e+06", "1000000"], f"Scientific notation float coerced to string (got {claims_sci.unit_id})")
    except Exception as e:
        record_assertion(False, "Coerce scientific float", str(e))


def test_string_integers():
    print("\n--- 2. Testing String Integers in Claims ---")
    
    # 2.1 Standard string integers
    token_str_int = create_raw_token({
        "sub": "2001",
        "name": "String Int User",
        "role": "egresso",
        "unit_id": "10",
        "prontuario_id": "50",
    })
    try:
        claims = decode_jwt_token(token_str_int)
        record_assertion(claims.unit_id == "10", f"unit_id string '10' preserved (got {claims.unit_id})")
        record_assertion(claims.prontuario_id == "50", f"prontuario_id string '50' preserved (got {claims.prontuario_id})")
        record_assertion(claims.user_id == 2001, f"user_id integer converted from sub '2001' (got {claims.user_id})")
    except Exception as e:
        record_assertion(False, "String integers in claims", str(e))

    # 2.2 Leading zero string integers (e.g. "00123")
    token_leading_zero = create_raw_token({
        "sub": "00123",
        "name": "Leading Zero User",
        "role": "egresso",
        "unit_id": "0045",
        "prontuario_id": "00999",
    })
    try:
        claims_lz = decode_jwt_token(token_leading_zero)
        record_assertion(claims_lz.unit_id == "0045", f"Leading zero unit_id '0045' preserved without stripping (got {claims_lz.unit_id})")
        record_assertion(claims_lz.prontuario_id == "00999", f"Leading zero prontuario_id '00999' preserved (got {claims_lz.prontuario_id})")
        record_assertion(claims_lz.user_id == 123, f"user_id converted to int 123 (got {claims_lz.user_id})")
    except Exception as e:
        record_assertion(False, "Leading zero string integers", str(e))

    # 2.3 Arbitrarily large integer string
    huge_int_str = "999999999999999999999999999999"
    token_huge = create_raw_token({
        "sub": huge_int_str,
        "name": "Huge Int User",
        "role": "gestor",
        "unit_id": huge_int_str,
    })
    try:
        claims_huge = decode_jwt_token(token_huge)
        record_assertion(claims_huge.sub == huge_int_str, "Huge integer string sub preserved")
        record_assertion(claims_huge.unit_id == huge_int_str, "Huge integer string unit_id preserved")
        record_assertion(claims_huge.user_id == int(huge_int_str), "user_id property parses large int safely")
    except Exception as e:
        record_assertion(False, "Huge integer string", str(e))


def test_null_values():
    print("\n--- 3. Testing Null / None Values in Claims ---")
    
    # 3.1 Optional fields set to None/null
    token_nulls = create_raw_token({
        "sub": "3001",
        "name": "Null User",
        "role": "tecnico",
        "room_id": None,
        "unit_id": None,
        "prontuario_id": None,
        "municipio": None,
        "cpf_masked": None,
    })
    try:
        claims = decode_jwt_token(token_nulls)
        record_assertion(claims.room_id is None, "room_id is None")
        record_assertion(claims.unit_id is None, "unit_id is None")
        record_assertion(claims.prontuario_id is None, "prontuario_id is None")
        record_assertion(claims.municipio is None, "municipio is None")
        record_assertion(claims.cpf_masked is None, "cpf_masked is None")
    except Exception as e:
        record_assertion(False, "Optional fields set to None", str(e))

    # 3.2 Required field 'sub' set to None -> should raise AuthError deterministically
    token_sub_null = create_raw_token({
        "sub": None,
        "name": "Null Sub User",
        "role": "tecnico",
    })
    sub_null_caught = False
    try:
        decode_jwt_token(token_sub_null)
    except AuthError as ae:
        sub_null_caught = (ae.code == "AUTH_ERROR")
    except Exception:
        pass
    record_assertion(sub_null_caught, "Required field 'sub' set to null raises AuthError(AUTH_ERROR)")

    # 3.3 Required field 'role' set to None -> should raise AuthError deterministically
    token_role_null = create_raw_token({
        "sub": "3002",
        "name": "Null Role User",
        "role": None,
    })
    role_null_caught = False
    try:
        decode_jwt_token(token_role_null)
    except AuthError as ae:
        role_null_caught = (ae.code == "AUTH_ERROR")
    except Exception:
        pass
    record_assertion(role_null_caught, "Required field 'role' set to null raises AuthError(AUTH_ERROR)")


def test_negative_values():
    print("\n--- 4. Testing Negative Values in Claims ---")
    
    # 4.1 Negative integer IDs for unit_id and prontuario_id (with standard string sub)
    token_neg_ids = create_raw_token({
        "sub": "4001",
        "name": "Negative IDs User",
        "role": "tecnico",
        "unit_id": -10,
        "prontuario_id": -50,
    })
    try:
        claims = decode_jwt_token(token_neg_ids)
        record_assertion(claims.unit_id == "-10", f"unit_id -10 coerced to '-10' (got {claims.unit_id})")
        record_assertion(claims.prontuario_id == "-50", f"prontuario_id -50 coerced to '-50' (got {claims.prontuario_id})")
    except Exception as e:
        record_assertion(False, "Negative integer unit_id/prontuario_id coercion", str(e))

    # 4.2 Negative float IDs for unit_id and prontuario_id
    token_neg_float = create_raw_token({
        "sub": "4002",
        "name": "Negative Float User",
        "role": "egresso",
        "unit_id": -10.5,
        "prontuario_id": -50.25,
    })
    try:
        claims_nf = decode_jwt_token(token_neg_float)
        record_assertion(claims_nf.unit_id == "-10.5", f"unit_id -10.5 coerced to '-10.5' (got {claims_nf.unit_id})")
        record_assertion(claims_nf.prontuario_id == "-50.25", f"prontuario_id -50.25 coerced to '-50.25' (got {claims_nf.prontuario_id})")
    except Exception as e:
        record_assertion(False, "Negative float unit_id/prontuario_id coercion", str(e))

    # 4.3 Negative string sub (e.g. sub: "-1")
    token_neg_str_sub = create_raw_token({
        "sub": "-1",
        "name": "Negative Sub String User",
        "role": "tecnico",
    })
    try:
        claims_ns = decode_jwt_token(token_neg_str_sub)
        record_assertion(claims_ns.sub == "-1", f"sub '-1' preserved (got {claims_ns.sub})")
        record_assertion(claims_ns.user_id == -1, f"user_id property returns integer -1 (got {claims_ns.user_id})")
    except Exception as e:
        record_assertion(False, "Negative string sub", str(e))

    # 4.4 Non-string negative numeric sub (e.g. sub: -1) -> deterministic AuthError per RFC 7519
    token_neg_num_sub = create_raw_token({
        "sub": -1,
        "name": "Negative Numeric Sub User",
        "role": "tecnico",
    })
    neg_num_sub_rejected = False
    try:
        decode_jwt_token(token_neg_num_sub)
    except AuthError as ae:
        neg_num_sub_rejected = ("Subject must be a string" in ae.message and ae.code == "AUTH_ERROR")
    record_assertion(neg_num_sub_rejected, "Non-string negative numeric 'sub' deterministically rejected with AuthError(AUTH_ERROR)")


def test_non_ascii_characters():
    print("\n--- 5. Testing Non-ASCII Characters & Unicode ---")
    
    # 5.1 Latin-1 Accents & Emojis in Name & Municipio
    token_unicode = create_raw_token({
        "sub": "5001",
        "name": "José Gonçalves da Conceição 👨‍⚖️🛡️",
        "role": "tecnico",
        "municipio": "São Mateus - ES / Cachoeiro de Itapemirim",
        "unit_id": "Unidade Central Vitória - Prédio 1",
        "prontuario_id": "PRONT-Â-999",
    })
    try:
        claims = decode_jwt_token(token_unicode)
        record_assertion(claims.name == "José Gonçalves da Conceição 👨‍⚖️🛡️", "Full name with accents and emojis preserved")
        record_assertion(claims.municipio == "São Mateus - ES / Cachoeiro de Itapemirim", "municipio with accents preserved")
        record_assertion(claims.unit_id == "Unidade Central Vitória - Prédio 1", "unit_id with non-ASCII text preserved")
        record_assertion(claims.prontuario_id == "PRONT-Â-999", "prontuario_id with non-ASCII text preserved")
    except Exception as e:
        record_assertion(False, "Unicode characters in name, municipio, unit_id, prontuario_id", str(e))

    # 5.2 Non-ASCII in sub
    token_sub_unicode = create_raw_token({
        "sub": "usuário_egresso_01",
        "name": "Unicode Sub User",
        "role": "egresso",
    })
    try:
        claims_sub = decode_jwt_token(token_sub_unicode)
        record_assertion(claims_sub.sub == "usuário_egresso_01", "sub with non-ASCII text preserved")
        record_assertion(isinstance(claims_sub.user_id, int), f"user_id hash fallback works for non-ASCII sub (got {claims_sub.user_id})")
    except Exception as e:
        record_assertion(False, "Non-ASCII sub claim", str(e))

    # 5.3 Zero-width characters & Bidi overrides
    token_bidi = create_raw_token({
        "sub": "5002",
        "name": "Carlos\u200b\u200cSilva\u202eSEJUS",
        "role": "observer",
        "room_id": "sala\u200b01",
    })
    try:
        claims_bidi = decode_jwt_token(token_bidi)
        record_assertion("\u200b" in claims_bidi.name, "Zero-width character preserved in name without decoding failure")
        record_assertion(claims_bidi.room_id == "sala\u200b01", "Zero-width character preserved in room_id")
    except Exception as e:
        record_assertion(False, "Bidi and zero-width characters in claims", str(e))

    # 5.4 Non-ASCII in role (e.g. "técnico" with accent)
    token_role_accent = create_raw_token({
        "sub": "5003",
        "name": "Accented Role User",
        "role": "técnico",
    })
    try:
        claims_ra = decode_jwt_token(token_role_accent)
        record_assertion(claims_ra.role == "técnico", "Raw role string 'técnico' preserved")
        # is_polite_peer should handle invalid normalized role gracefully without exception
        record_assertion(is_polite_peer(claims_ra.role) is True, "is_polite_peer handles accented role without crashing (returns True)")
    except Exception as e:
        record_assertion(False, "Accented role handling", str(e))


def test_deterministic_exception_handling():
    print("\n--- 6. Testing Deterministic Exception Handling & Boundary Attacks ---")
    
    # 6.1 Non-string inputs to decode_jwt_token
    for bad_input in [None, 12345, [], {}, True]:
        caught = False
        try:
            decode_jwt_token(bad_input)  # type: ignore
        except AuthError as ae:
            caught = (ae.code == "AUTH_TOKEN_MISSING")
        except Exception:
            pass
        record_assertion(caught, f"Non-string input ({type(bad_input).__name__}) raises AuthError(AUTH_TOKEN_MISSING)")

    # 6.2 Malformed JWT strings
    malformed_cases = [
        ("whitespace only", "    \t\n   ", "AUTH_TOKEN_MISSING"),
        ("no dots", "justarandomstringwithoutdots", "AUTH_DECODE_ERROR"),
        ("only one dot", "header.payload", "AUTH_DECODE_ERROR"),
        ("four dots", "part1.part2.part3.part4", "AUTH_DECODE_ERROR"),
        ("invalid base64", "header.%%%badbase64%%%.signature", "AUTH_DECODE_ERROR"),
        ("invalid json payload", "eyJhbGciOiJIUzI1NiJ9.bm90LWpzb24.signature", "AUTH_DECODE_ERROR"),
    ]
    for desc, tok, expected_code in malformed_cases:
        caught = False
        try:
            decode_jwt_token(tok)
        except AuthError as ae:
            caught = (ae.code == expected_code)
        except Exception:
            pass
        record_assertion(caught, f"Malformed token ({desc}) raises AuthError({expected_code})")

    # 6.3 Cryptographic mismatch attacks
    # Forged signature
    valid_token = create_raw_token({"sub": "6001", "name": "Crypto User", "role": "tecnico"})
    parts = valid_token.split(".")
    tampered_sig_token = f"{parts[0]}.{parts[1]}.badsignature"
    sig_caught = False
    try:
        decode_jwt_token(tampered_sig_token)
    except AuthError as ae:
        sig_caught = (ae.code == "AUTH_INVALID_SIGNATURE")
    record_assertion(sig_caught, "Tampered signature raises AuthError(AUTH_INVALID_SIGNATURE)")

    # Wrong secret
    wrong_secret_token = create_raw_token(
        {"sub": "6002", "name": "Attacker", "role": "gestor"},
        secret="attacker_secret_different_key_2026"
    )
    wrong_sec_caught = False
    try:
        decode_jwt_token(wrong_secret_token)
    except AuthError as ae:
        wrong_sec_caught = (ae.code == "AUTH_INVALID_SIGNATURE")
    record_assertion(wrong_sec_caught, "Token signed with attacker secret raises AuthError(AUTH_INVALID_SIGNATURE)")

    # 6.4 Boolean coercion in unit_id and prontuario_id
    token_bool = create_raw_token({
        "sub": "6003",
        "name": "Bool User",
        "role": "egresso",
        "unit_id": True,
        "prontuario_id": False,
    })
    try:
        claims_b = decode_jwt_token(token_bool)
        record_assertion(claims_b.unit_id == "True", f"Boolean True in unit_id coerced to 'True' (got {claims_b.unit_id})")
        record_assertion(claims_b.prontuario_id == "False", f"Boolean False in prontuario_id coerced to 'False' (got {claims_b.prontuario_id})")
    except Exception as e:
        record_assertion(False, "Boolean values in unit_id and prontuario_id", str(e))

    # 6.5 Large payload resistance (e.g. 10KB name)
    huge_name = "A" * 10240
    token_huge_payload = create_raw_token({
        "sub": "6004",
        "name": huge_name,
        "role": "tecnico",
    })
    try:
        claims_hp = decode_jwt_token(token_huge_payload)
        record_assertion(len(claims_hp.name) == 10240, "10KB string payload decoded without error or truncation")
    except Exception as e:
        record_assertion(False, "10KB payload decoding", str(e))


def main():
    print("===============================================================================")
    print("CONECTA EGRESSO (SEJUS/ES) - ADVERSARIAL JWT TOKEN CHALLENGER SUITE")
    print("===============================================================================\n")
    
    test_float_values()
    test_string_integers()
    test_null_values()
    test_negative_values()
    test_non_ascii_characters()
    test_deterministic_exception_handling()
    
    print("\n===============================================================================")
    print(f"ADVERSARIAL JWT SUITE SUMMARY: Passed: {passed_count} | Failed: {failed_count}")
    print("===============================================================================")
    
    report_path = PROJECT_ROOT / "tests" / "adversarial_jwt_edge_cases_report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump({
            "passed": passed_count,
            "failed": failed_count,
            "results": results_log
        }, f, indent=2)
    print(f"Report saved to {report_path}")
    
    return 1 if failed_count > 0 else 0


if __name__ == "__main__":
    sys.exit(main())
