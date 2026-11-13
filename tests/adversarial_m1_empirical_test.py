"""
Empirical Adversarial Test Suite for Milestone 1: Topologia 2 Servidores & WebRTC/Coturn
Conecta Egresso (SEJUS/ES) - Challenger Role Verification
"""

import os
import sys
import json
import time
import hmac
import hashlib
import base64
import subprocess
from pathlib import Path

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
    create_access_token,
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


def run_php_suite():
    print("\n--- 0. Running PHP Companion Test Harness ---")
    php_script = PROJECT_ROOT / "tests" / "adversarial_m1_turn_php.php"
    res = subprocess.run(["php", str(php_script)], capture_output=True, text=True)
    print(res.stdout)
    if res.stderr:
        print("PHP Stderr:", res.stderr)
    record_assertion(res.returncode == 0, "PHP test harness ran with exit code 0", res.stderr)


def test_python_ephemeral_turn_credentials():
    print("\n--- 1. Python Ephemeral TURN Credentials Verification (RFC 5766) ---")
    
    # 1.1 Format and Generation
    creds = settings.generate_ephemeral_turn_credentials(username_prefix="challenger_test", ttl=1800)
    username = creds["username"]
    credential = creds["credential"]
    ttl = creds["ttl"]
    expires_at = creds["expires_at"]
    
    parts = username.split(":")
    record_assertion(len(parts) == 2, "Username has format {timestamp}:{prefix}", f"Username: {username}")
    ts = int(parts[0])
    prefix = parts[1]
    
    now = int(time.time())
    record_assertion(ts > now, f"Timestamp is strictly in the future (ts={ts}, now={now})")
    record_assertion(abs(ts - (now + 1800)) <= 2, f"Timestamp accurately reflects TTL 1800s ({ts} vs {now + 1800})")
    record_assertion(prefix == "challenger_test", f"Username prefix correctly preserved ('{prefix}')")
    
    # 1.2 Exact HMAC-SHA1 verification
    expected_digest = hmac.new(
        settings.COTURN_SECRET.encode("utf-8"),
        username.encode("utf-8"),
        hashlib.sha1
    ).digest()
    expected_b64 = base64.b64encode(expected_digest).decode("utf-8")
    record_assertion(credential == expected_b64, "Generated TURN credential matches independent HMAC-SHA1 oracle")
    
    # 1.3 Tamper Resistance: modifying timestamp
    tampered_ts_user = f"{ts + 10}:{prefix}"
    tampered_digest = base64.b64encode(
        hmac.new(settings.COTURN_SECRET.encode(), tampered_ts_user.encode(), hashlib.sha1).digest()
    ).decode()
    record_assertion(tampered_digest != credential, "Tampering with timestamp invalidates HMAC credential")
    
    # 1.4 Tamper Resistance: modifying prefix
    tampered_user = f"{ts}:{prefix}_tampered"
    tampered_digest2 = base64.b64encode(
        hmac.new(settings.COTURN_SECRET.encode(), tampered_user.encode(), hashlib.sha1).digest()
    ).decode()
    record_assertion(tampered_digest2 != credential, "Tampering with username prefix invalidates HMAC credential")
    
    # 1.5 Tamper Resistance: wrong secret
    attacker_digest = base64.b64encode(
        hmac.new(b"attacker_fake_secret_key", username.encode(), hashlib.sha1).digest()
    ).decode()
    record_assertion(attacker_digest != credential, "Attacker secret produces non-matching credential")
    
    # 1.6 Settings.ice_servers contains ephemeral credentials
    ice_servers = settings.ice_servers
    record_assertion(len(ice_servers) >= 2, f"settings.ice_servers contains {len(ice_servers)} endpoints")
    turn_endpoints = [s for s in ice_servers if "turn:" in s.get("urls", "")]
    record_assertion(len(turn_endpoints) >= 1, "settings.ice_servers includes TURN endpoints")
    if turn_endpoints:
        first_turn = turn_endpoints[0]
        record_assertion("username" in first_turn and "credential" in first_turn, "TURN endpoint includes ephemeral credentials")
        parts = first_turn["username"].split(":")
        record_assertion(len(parts) == 2 and int(parts[0]) > now, "TURN endpoint username has valid future timestamp")


def test_cross_language_turn_validation():
    print("\n--- 2. Cross-Language TURN Validation (PHP <-> Python) ---")
    json_path = PROJECT_ROOT / "tests" / "adversarial_m1_exported_tokens.json"
    if not json_path.exists():
        record_assertion(False, "Exported tokens JSON exists", "adversarial_m1_exported_tokens.json not found")
        return
    
    with open(json_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    php_turn = data["turn_sample"]
    php_username = php_turn["username"]
    php_credential = php_turn["credential"]
    coturn_secret = data["coturn_secret"]
    
    # Python recomputes PHP's credential
    python_digest = hmac.new(
        coturn_secret.encode("utf-8"),
        php_username.encode("utf-8"),
        hashlib.sha1
    ).digest()
    python_credential = base64.b64encode(python_digest).decode("utf-8")
    
    record_assertion(
        php_credential == python_credential,
        "Python independently validates TURN credential generated by PHP WebRtcJwtService",
        f"PHP: {php_credential}, Python: {python_credential}"
    )
    
    # Check timestamp validity in Python
    parts = php_username.split(":")
    ts = int(parts[0])
    now = int(time.time())
    record_assertion(ts > now, f"PHP-generated timestamp {ts} is in future relative to Python time {now}")
    record_assertion(ts <= now + 86400 + 5, f"PHP-generated timestamp {ts} is within 24h TTL limit")


def test_webrtc_token_decoding_and_claims():
    print("\n--- 3. WebRTC JWT Token Decoding & Claims Cross-Validation ---")
    json_path = PROJECT_ROOT / "tests" / "adversarial_m1_exported_tokens.json"
    with open(json_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    valid_tokens = data["valid_tokens"]
    edge_tokens = data["edge_tokens"]
    
    # 3.1 Decode Tecnico Token
    tecnico_jwt = valid_tokens["tecnico"]
    try:
        claims = decode_jwt_token(tecnico_jwt)
        record_assertion(True, "decode_jwt_token decodes PHP-generated 'tecnico' token")
        record_assertion(claims.sub == "101", f"sub claim matches '101' (got {claims.sub})")
        record_assertion(claims.user_id == 101, f"user_id property returns integer 101 (got {claims.user_id})")
        record_assertion(claims.name == "Carlos Silva", f"name claim matches 'Carlos Silva' (got {claims.name})")
        record_assertion(claims.role == "tecnico", f"role claim matches 'tecnico' (got {claims.role})")
        record_assertion(claims.normalized_role == ClientRole.TECHNICIAN, f"normalized_role maps 'tecnico' to TECHNICIAN")
        record_assertion(claims.room_id == "room-vix-01", f"room_id matches 'room-vix-01'")
        record_assertion(not is_polite_peer(claims.role), "Technician is Impolite peer for W3C Perfect Negotiation")
    except Exception as e:
        record_assertion(False, "decode_jwt_token decodes PHP-generated 'tecnico' token", str(e))
    
    # 3.2 Decode Egresso Token
    egresso_jwt = valid_tokens["egresso"]
    try:
        claims_egr = decode_jwt_token(egresso_jwt)
        record_assertion(True, "decode_jwt_token decodes PHP-generated 'egresso' token")
        record_assertion(claims_egr.normalized_role == ClientRole.ATTENDEE, "normalized_role maps 'egresso' to ATTENDEE")
        record_assertion(is_polite_peer(claims_egr.role), "Egresso/Attendee is Polite peer for W3C Perfect Negotiation")
    except Exception as e:
        record_assertion(False, "decode_jwt_token decodes PHP-generated 'egresso' token", str(e))
    
    # 3.3 Decode Gestor and Defensoria Tokens
    try:
        claims_gestor = decode_jwt_token(valid_tokens["gestor"])
        claims_def = decode_jwt_token(valid_tokens["defensoria"])
        record_assertion(claims_gestor.normalized_role == ClientRole.GESTOR, "normalized_role maps 'gestor' to GESTOR")
        record_assertion(claims_def.normalized_role == ClientRole.DEFENSORIA, "normalized_role maps 'defensoria' to DEFENSORIA")
    except Exception as e:
        record_assertion(False, "decode_jwt_token decodes Gestor/Defensoria tokens", str(e))
    
    # 3.4 Room Access Validation
    try:
        # Tecnico in room-vix-01 -> True
        record_assertion(validate_room_access(claims, "room-vix-01"), "Tecnico access granted for assigned room-vix-01")
        # Tecnico in room-vix-02 -> AuthError
        denied = False
        try:
            validate_room_access(claims, "room-vix-02")
        except AuthError as ae:
            denied = (ae.code == "ROOM_ACCESS_DENIED")
        record_assertion(denied, "Tecnico access denied for unassigned room-vix-02 (ROOM_ACCESS_DENIED)")
        
        # Gestor in room-vix-02 -> Global Access True
        record_assertion(validate_room_access(claims_gestor, "room-vix-02"), "Gestor granted global room access")
        # Defensoria in room-vix-02 -> Global Access True
        record_assertion(validate_room_access(claims_def, "room-vix-02"), "Defensoria granted global room access")
    except Exception as e:
        record_assertion(False, "Room access validation checks", str(e))
    
    # 3.5 Unit Access Validation
    try:
        # Tecnico has global unit access
        record_assertion(validate_unit_access(claims, "999"), "Tecnico granted access to any unit queue")
        record_assertion(validate_unit_access(claims_gestor, "999"), "Gestor granted access to any unit queue")
        
        # Egresso has assigned unit '10'
        record_assertion(validate_unit_access(claims_egr, "10"), "Egresso access granted for assigned unit 10")
        egr_unit_denied = False
        try:
            validate_unit_access(claims_egr, "99")
        except AuthError as ae:
            egr_unit_denied = (ae.code == "UNIT_ACCESS_DENIED")
        record_assertion(egr_unit_denied, "Egresso access denied for unassigned unit 99 (UNIT_ACCESS_DENIED)")
    except Exception as e:
        record_assertion(False, "Unit access validation checks", str(e))
    
    # 3.6 Bearer prefix handling
    bearer_jwt = f"Bearer {tecnico_jwt}"
    try:
        b_claims = decode_jwt_token(bearer_jwt)
        record_assertion(b_claims.sub == "101", "decode_jwt_token seamlessly handles 'Bearer <token>' prefix")
    except Exception as e:
        record_assertion(False, "Bearer prefix handling", str(e))


def test_adversarial_security_attacks():
    print("\n--- 4. Adversarial Attack Scenarios on WebRTC JWT ---")
    json_path = PROJECT_ROOT / "tests" / "adversarial_m1_exported_tokens.json"
    with open(json_path, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    edge_tokens = data["edge_tokens"]
    
    # 4.1 Expired token
    expired_rejected = False
    try:
        decode_jwt_token(edge_tokens["expired"])
    except AuthError as ae:
        expired_rejected = (ae.code == "AUTH_TOKEN_EXPIRED")
    record_assertion(expired_rejected, "Rejects expired token with AUTH_TOKEN_EXPIRED")
    
    # 4.2 Wrong secret key (forged signature)
    wrong_sec_rejected = False
    try:
        decode_jwt_token(edge_tokens["wrong_secret"])
    except AuthError as ae:
        wrong_sec_rejected = (ae.code == "AUTH_INVALID_SIGNATURE")
    record_assertion(wrong_sec_rejected, "Rejects forged signature with AUTH_INVALID_SIGNATURE")
    
    # 4.3 Wrong issuer
    wrong_iss_rejected = False
    try:
        decode_jwt_token(edge_tokens["wrong_issuer"])
    except AuthError as ae:
        wrong_iss_rejected = (ae.code == "AUTH_INVALID_ISSUER")
    record_assertion(wrong_iss_rejected, "Rejects forged issuer with AUTH_INVALID_ISSUER")
    
    # 4.4 Wrong audience
    wrong_aud_rejected = False
    try:
        decode_jwt_token(edge_tokens["wrong_audience"])
    except AuthError as ae:
        wrong_aud_rejected = (ae.code == "AUTH_INVALID_AUDIENCE")
    record_assertion(wrong_aud_rejected, "Rejects forged audience with AUTH_INVALID_AUDIENCE")
    
    # 4.5 None algorithm attack
    none_header = base64.urlsafe_b64encode(b'{"alg":"none","typ":"JWT"}').decode().rstrip("=")
    none_payload = base64.urlsafe_b64encode(
        b'{"iss":"conecta-egresso-laravel","aud":"conecta-egresso-webrtc","sub":"999","name":"Attacker","role":"gestor"}'
    ).decode().rstrip("=")
    none_jwt = f"{none_header}.{none_payload}."
    none_rejected = False
    try:
        decode_jwt_token(none_jwt)
    except AuthError as ae:
        none_rejected = (ae.code in ["AUTH_INVALID_SIGNATURE", "AUTH_DECODE_ERROR", "AUTH_ERROR"])
    record_assertion(none_rejected, "Rejects 'none' algorithm forgery attack")
    
    # 4.6 Empty / Malformed tokens
    empty_rejected = False
    try:
        decode_jwt_token("   ")
    except AuthError as ae:
        empty_rejected = (ae.code == "AUTH_TOKEN_MISSING")
    record_assertion(empty_rejected, "Rejects whitespace/empty token with AUTH_TOKEN_MISSING")
    
    garbage_rejected = False
    try:
        decode_jwt_token("gibberish.token.format.overflow")
    except AuthError as ae:
        garbage_rejected = (ae.code in ["AUTH_DECODE_ERROR", "AUTH_INVALID_SIGNATURE", "AUTH_ERROR"])
    record_assertion(garbage_rejected, "Rejects malformed gibberish token with AUTH_DECODE_ERROR")
    
    # 4.7 EMPIRICAL TEST: Integer IDs vs String Schema in JWTClaims
    # This tests the exact contract mismatch found between Laravel (?int $prontuarioId, ?int $unitId)
    # and FastAPI JWTClaims (unit_id: Optional[str] = None, prontuario_id: Optional[str] = None)
    print("\n  [STRESS TEST] Integer claims in JWT payload:")
    int_token = edge_tokens["int_ids"]
    int_ids_passed = False
    int_ids_error_msg = ""
    try:
        claims_int = decode_jwt_token(int_token)
        int_ids_passed = True
    except Exception as e:
        int_ids_error_msg = str(e)
    
    # We record this finding empirically!
    if not int_ids_passed:
        record_assertion(
            False,
            "JWT token containing integer 'unit_id' and 'prontuario_id' decoded by FastAPI",
            f"Observed Pydantic ValidationError: {int_ids_error_msg}"
        )
    else:
        record_assertion(True, "JWT token containing integer 'unit_id' and 'prontuario_id' decoded by FastAPI")


def test_docker_and_coturn_config_integrity():
    print("\n--- 5. Docker Infrastructure & Coturn Config Integrity ---")
    
    # 5.1 Coturn config
    conf_path = PROJECT_ROOT / "docker" / "coturn" / "turnserver.conf"
    record_assertion(conf_path.exists(), "turnserver.conf exists")
    conf_text = conf_path.read_text(encoding="utf-8")
    
    record_assertion("min-port=49152" in conf_text, "turnserver.conf specifies min-port=49152")
    record_assertion("max-port=65535" in conf_text, "turnserver.conf specifies max-port=65535")
    record_assertion("realm=sejus.es.gov.br" in conf_text, "turnserver.conf specifies realm=sejus.es.gov.br")
    record_assertion("use-auth-secret" in conf_text, "turnserver.conf specifies use-auth-secret")
    record_assertion("static-auth-secret=sejus_turn_secret_key_2026" in conf_text, "turnserver.conf specifies static-auth-secret=sejus_turn_secret_key_2026")
    
    port_count = 65535 - 49152 + 1
    record_assertion(port_count == 16384, f"Dynamic UDP media port pool size is exactly 16384 ports (got {port_count})")
    
    # 5.2 Server 1 Manifest
    s1_path = PROJECT_ROOT / "docker-compose.server1.yml"
    record_assertion(s1_path.exists(), "docker-compose.server1.yml exists")
    s1_text = s1_path.read_text(encoding="utf-8")
    record_assertion("postgres:" in s1_text, "Server 1 manifest defines postgres")
    record_assertion("redis:" in s1_text, "Server 1 manifest defines redis")
    record_assertion("php:" in s1_text, "Server 1 manifest defines php")
    record_assertion("nginx:" in s1_text, "Server 1 manifest defines nginx")
    record_assertion("python:" not in s1_text, "Server 1 manifest excludes python (FastAPI)")
    record_assertion("coturn:" not in s1_text, "Server 1 manifest excludes coturn")
    
    # 5.3 Server 2 Manifest
    s2_path = PROJECT_ROOT / "docker-compose.server2.yml"
    record_assertion(s2_path.exists(), "docker-compose.server2.yml exists")
    s2_text = s2_path.read_text(encoding="utf-8")
    record_assertion("python:" in s2_text, "Server 2 manifest defines python (FastAPI)")
    record_assertion("coturn:" in s2_text, "Server 2 manifest defines coturn")
    record_assertion("postgres:" not in s2_text, "Server 2 manifest excludes postgres (relational DB decoupling)")
    record_assertion("49152-65535:49152-65535/udp" in s2_text, "Server 2 manifest maps 49152-65535/udp")
    record_assertion("JWT_SECRET_KEY" in s2_text, "Server 2 manifest sets JWT_SECRET_KEY")
    
    # 5.4 Unified Compose Profiles
    compose_path = PROJECT_ROOT / "docker-compose.yml"
    c_text = compose_path.read_text(encoding="utf-8")
    record_assertion("profiles:" in c_text, "docker-compose.yml defines profiles for 2-server orchestration")
    record_assertion("server1" in c_text and "server2" in c_text, "docker-compose.yml includes server1 and server2 profiles")
    record_assertion("49152-65535:49152-65535/udp" in c_text, "docker-compose.yml updates coturn UDP port range to 49152-65535")


def main():
    print("===============================================================================")
    print("CONECTA EGRESSO (SEJUS/ES) - ADVERSARIAL M1 EMPIRICAL CHALLENGER (PYTHON)")
    print("===============================================================================\n")
    
    run_php_suite()
    test_python_ephemeral_turn_credentials()
    test_cross_language_turn_validation()
    test_webrtc_token_decoding_and_claims()
    test_adversarial_security_attacks()
    test_docker_and_coturn_config_integrity()
    
    print("\n===============================================================================")
    print(f"ADVERSARIAL SUITE SUMMARY: Passed: {passed_count} | Failed: {failed_count}")
    print("===============================================================================")
    
    report_file = PROJECT_ROOT / "tests" / "adversarial_m1_report.json"
    with open(report_file, "w", encoding="utf-8") as f:
        json.dump({
            "passed": passed_count,
            "failed": failed_count,
            "results": results_log
        }, f, indent=2)
    print(f"Detailed results saved to {report_file}")
    
    return 1 if failed_count > 0 else 0


if __name__ == "__main__":
    sys.exit(main())
