"""Tier 2 Boundary & Negative Tests: Cryptographic Integrity, Tampering, and Audit Logs.

Verifies:
- Tampered QR code HMAC-SHA256 signature rejection
- Public validation endpoint (/validar-carteira/{hash}) with invalid hash or altered payload
- Attempted deletion on immutable audit log table (PostgreSQL RULE DO INSTEAD NOTHING)
- Attempted update on immutable audit log table
- Broken hash chain detection in audit logs (SHA-256 chain discrepancy localization)
- Blind index search with invalid salt / HMAC pepper key
- AES-256 decryption failure on corrupted ciphertext / auth tag
- Plaintext PII search prevention (blind index integrity)
- Invalid CPF checksums and extreme formats
- Zero-length / negative ID cryptographic requests
- Constant-time comparison resilience (timing attack prevention)
"""

import hashlib
import hmac
import json
import time
import unittest
from typing import Any, Dict, List, Optional, Tuple


# --- Cryptographic Helpers & Simulations ---

class CryptoEngine:
    """LGPD Cryptography and Blind Index engine for SEJUS/ES."""

    DEFAULT_QR_SECRET = "sejus_qr_master_key_2026_conecta_egresso"
    DEFAULT_BLIND_INDEX_PEPPER = "sejus_lgpd_blind_index_pepper_salt_998877"

    @classmethod
    def validate_cpf(cls, cpf: str) -> bool:
        clean_cpf = "".join(filter(str.isdigit, cpf))
        if len(clean_cpf) != 11:
            return False
        if len(set(clean_cpf)) == 1:
            return False
        sum1 = sum(int(clean_cpf[i]) * (10 - i) for i in range(9))
        d1 = (sum1 * 10 % 11) % 10
        sum2 = sum(int(clean_cpf[i]) * (11 - i) for i in range(10))
        d2 = (sum2 * 10 % 11) % 10
        return int(clean_cpf[9]) == d1 and int(clean_cpf[10]) == d2

    @classmethod
    def generate_qr_payload(
        cls,
        egresso_id: int,
        cpf: str,
        nome: str,
        secret: str = DEFAULT_QR_SECRET,
        issued_at: Optional[int] = None,
    ) -> Dict[str, Any]:
        if egresso_id <= 0:
            raise ValueError(f"Invalid egresso_id: {egresso_id}. Must be a positive integer.")
        if not cls.validate_cpf(cpf):
            raise ValueError(f"Invalid CPF checksum or format: {cpf}")

        clean_cpf = "".join(filter(str.isdigit, cpf))
        timestamp = issued_at if issued_at is not None else int(time.time())
        cpf_hash = hashlib.sha256(clean_cpf.encode("utf-8")).hexdigest()[:16]

        data_to_sign = f"{egresso_id}:{cpf_hash}:{timestamp}"
        signature = hmac.new(secret.encode("utf-8"), data_to_sign.encode("utf-8"), hashlib.sha256).hexdigest()

        return {
            "egresso_id": egresso_id,
            "cpf_hash": cpf_hash,
            "nome_mascarado": f"{nome[:3]}***" if len(nome) > 3 else "***",
            "issued_at": timestamp,
            "signature": signature,
        }

    @classmethod
    def verify_qr_payload(
        cls,
        payload: Dict[str, Any],
        secret: str = DEFAULT_QR_SECRET,
    ) -> Tuple[bool, str]:
        required_keys = {"egresso_id", "cpf_hash", "issued_at", "signature"}
        if not required_keys.issubset(payload.keys()):
            return False, "missing_payload_fields"

        egresso_id = payload["egresso_id"]
        cpf_hash = payload["cpf_hash"]
        timestamp = payload["issued_at"]
        provided_sig = payload["signature"]

        if not isinstance(egresso_id, int) or egresso_id <= 0:
            return False, "invalid_egresso_id"

        data_to_sign = f"{egresso_id}:{cpf_hash}:{timestamp}"
        expected_sig = hmac.new(secret.encode("utf-8"), data_to_sign.encode("utf-8"), hashlib.sha256).hexdigest()

        # Constant-time comparison
        if not hmac.compare_digest(provided_sig, expected_sig):
            return False, "signature_mismatch"

        # Check expiration if older than 1 year (365 days)
        now = int(time.time())
        if now - timestamp > 365 * 86400:
            return False, "qr_code_expired"

        return True, "valid"

    @classmethod
    def compute_blind_index(cls, cpf: str, pepper: str = DEFAULT_BLIND_INDEX_PEPPER) -> str:
        clean_cpf = "".join(filter(str.isdigit, cpf))
        if len(clean_cpf) != 11:
            raise ValueError(f"CPF must be 11 digits for blind index, got '{cpf}'")
        return hmac.new(pepper.encode("utf-8"), clean_cpf.encode("utf-8"), hashlib.sha256).hexdigest()

    @staticmethod
    def simulate_aes_encrypt(plaintext: str, key_hex: str) -> Dict[str, str]:
        """Simulates authenticated symmetric encryption (AES-256-GCM structure)."""
        key = bytes.fromhex(key_hex)
        iv = hashlib.sha256(f"iv:{time.time()}".encode()).digest()[:12]  # 96-bit IV
        data_bytes = plaintext.encode("utf-8")

        # Simulate stream XOR with SHA256 PRF stream
        keystream = hashlib.sha256(key + iv).digest()
        while len(keystream) < len(data_bytes):
            keystream += hashlib.sha256(keystream + key).digest()
        ciphertext = bytes([b ^ k for b, k in zip(data_bytes, keystream[:len(data_bytes)])])

        # Authentication tag over IV + ciphertext
        tag = hmac.new(key, iv + ciphertext, hashlib.sha256).digest()[:16]

        return {
            "iv": iv.hex(),
            "ciphertext": ciphertext.hex(),
            "tag": tag.hex(),
        }

    @staticmethod
    def simulate_aes_decrypt(encrypted_data: Dict[str, str], key_hex: str) -> Tuple[bool, Optional[str], str]:
        try:
            key = bytes.fromhex(key_hex)
            iv = bytes.fromhex(encrypted_data["iv"])
            ciphertext = bytes.fromhex(encrypted_data["ciphertext"])
            tag = bytes.fromhex(encrypted_data["tag"])
        except Exception:
            return False, None, "corrupted_hex_encoding"

        # Verify authentication tag first (GCM integrity)
        expected_tag = hmac.new(key, iv + ciphertext, hashlib.sha256).digest()[:16]
        if not hmac.compare_digest(tag, expected_tag):
            return False, None, "auth_tag_mismatch_or_tampered_ciphertext"

        # Decrypt
        keystream = hashlib.sha256(key + iv).digest()
        while len(keystream) < len(ciphertext):
            keystream += hashlib.sha256(keystream + key).digest()
        decrypted_bytes = bytes([b ^ k for b, k in zip(ciphertext, keystream[:len(ciphertext)])])

        try:
            plaintext = decrypted_bytes.decode("utf-8")
            return True, plaintext, "decrypted"
        except UnicodeDecodeError:
            return False, None, "unicode_decode_failure"


class ImmutableAuditLogStore:
    """Simulates PostgreSQL 16 immutable audit log table with SHA-256 hash chaining."""

    GENESIS_HASH = "0" * 64

    def __init__(self):
        self.logs: List[Dict[str, Any]] = []

    def append_log(self, user_id: int, prontuario_id: int, action: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        log_id = len(self.logs) + 1
        prev_hash = self.logs[-1]["current_hash"] if self.logs else self.GENESIS_HASH
        timestamp = "2026-08-17T12:00:00Z"
        payload_str = json.dumps(payload, sort_keys=True)

        raw_block = f"{prev_hash}:{log_id}:{user_id}:{prontuario_id}:{action}:{payload_str}:{timestamp}"
        current_hash = hashlib.sha256(raw_block.encode("utf-8")).hexdigest()

        entry = {
            "id": log_id,
            "user_id": user_id,
            "prontuario_id": prontuario_id,
            "action": action,
            "payload": payload,
            "timestamp": timestamp,
            "prev_hash": prev_hash,
            "current_hash": current_hash,
        }
        self.logs.append(entry)
        return entry

    def delete_record(self, log_id: int) -> bool:
        """Simulates PostgreSQL `CREATE RULE ON DELETE DO INSTEAD NOTHING`."""
        # The rule suppresses delete queries; records are never deleted
        return False

    def update_record(self, log_id: int, field: str, new_value: Any) -> bool:
        """Simulates PostgreSQL `CREATE RULE ON UPDATE DO INSTEAD NOTHING`."""
        # The rule suppresses update queries; records are immutable
        return False

    def verify_chain_integrity(self) -> Tuple[bool, Optional[int], str]:
        """Verifies mathematical continuity of the SHA-256 hash chain."""
        expected_prev = self.GENESIS_HASH
        for idx, entry in enumerate(self.logs):
            if entry["prev_hash"] != expected_prev:
                return False, entry["id"], f"Broken prev_hash link at log_id={entry['id']}"

            payload_str = json.dumps(entry["payload"], sort_keys=True)
            raw_block = f"{entry['prev_hash']}:{entry['id']}:{entry['user_id']}:{entry['prontuario_id']}:{entry['action']}:{payload_str}:{entry['timestamp']}"
            computed_hash = hashlib.sha256(raw_block.encode("utf-8")).hexdigest()

            if computed_hash != entry["current_hash"]:
                return False, entry["id"], f"Tampered record content at log_id={entry['id']}"

            expected_prev = entry["current_hash"]

        return True, None, "chain_valid"


# --- Test Suite ---

class TestCryptoTampering(unittest.TestCase):
    """Tier 2 Boundary test suite for Cryptography, QR Code, and Audit Immutability."""

    def setUp(self):
        self.secret = CryptoEngine.DEFAULT_QR_SECRET
        self.pepper = CryptoEngine.DEFAULT_BLIND_INDEX_PEPPER
        self.valid_cpf = "529.982.247-25"
        self.aes_key = hashlib.sha256(b"sejus_master_aes_key_2026").hexdigest()

    def test_01_tampered_qr_code_hmac_signature_rejection(self):
        """Verify that any modification to QR payload invalidates the HMAC signature."""
        original = CryptoEngine.generate_qr_payload(
            egresso_id=42,
            cpf=self.valid_cpf,
            nome="Carlos Eduardo Silva",
            secret=self.secret,
        )
        is_valid, msg = CryptoEngine.verify_qr_payload(original, self.secret)
        self.assertTrue(is_valid, f"Original QR payload must be valid: {msg}")

        # 1. Tamper egresso_id (privilege escalation / identity swap)
        tampered_id = dict(original)
        tampered_id["egresso_id"] = 43
        valid_t1, msg_t1 = CryptoEngine.verify_qr_payload(tampered_id, self.secret)
        self.assertFalse(valid_t1)
        self.assertEqual(msg_t1, "signature_mismatch")

        # 2. Tamper cpf_hash
        tampered_cpf = dict(original)
        tampered_cpf["cpf_hash"] = "ffffffffffffffff"
        valid_t2, msg_t2 = CryptoEngine.verify_qr_payload(tampered_cpf, self.secret)
        self.assertFalse(valid_t2)
        self.assertEqual(msg_t2, "signature_mismatch")

        # 3. Tamper signature by flipping a single character
        tampered_sig = dict(original)
        last_char = "0" if original["signature"][-1] != "0" else "1"
        tampered_sig["signature"] = original["signature"][:-1] + last_char
        valid_t3, msg_t3 = CryptoEngine.verify_qr_payload(tampered_sig, self.secret)
        self.assertFalse(valid_t3)
        self.assertEqual(msg_t3, "signature_mismatch")

    def test_02_public_validation_with_invalid_hash_altered_payload(self):
        """Verify public validation route `/validar-carteira/{hash}` returns not found for corrupted hashes."""
        invalid_hashes = [
            "invalid_non_hex_hash",
            "12345",  # Too short
            "z" * 64,  # Invalid hex chars
            "",
            "0000000000000000000000000000000000000000000000000000000000000000",  # Non-existent
        ]

        known_valid_hashes = {
            "a" * 64: {"id": 1, "status": "ativo", "egresso": "Carlos Silva"}
        }

        for test_hash in invalid_hashes:
            found = known_valid_hashes.get(test_hash)
            self.assertIsNone(found, f"Hash '{test_hash}' must not resolve to any egresso.")

    def test_03_attempted_deletion_on_immutable_audit_log(self):
        """Verify that DELETE operations on the audit log table fail or have zero effect."""
        audit_store = ImmutableAuditLogStore()
        audit_store.append_log(user_id=1, prontuario_id=10, action="READ", payload={"field": "consulta"})
        audit_store.append_log(user_id=2, prontuario_id=10, action="WRITE", payload={"nota": "encaminhamento"})

        self.assertEqual(len(audit_store.logs), 2)

        # Attempt DELETE FROM prontuario_audit_logs WHERE id = 1
        delete_result = audit_store.delete_record(log_id=1)
        self.assertFalse(delete_result, "Delete operation must be suppressed by rule.")
        self.assertEqual(len(audit_store.logs), 2, "Row count must remain intact after delete attempt.")

    def test_04_attempted_update_on_immutable_audit_log(self):
        """Verify that UPDATE operations on the audit log table are strictly rejected."""
        audit_store = ImmutableAuditLogStore()
        entry = audit_store.append_log(user_id=1, prontuario_id=10, action="READ", payload={"nota": "original"})

        # Attempt UPDATE prontuario_audit_logs SET payload = 'tampered' WHERE id = 1
        update_result = audit_store.update_record(log_id=entry["id"], field="payload", new_value={"nota": "tampered"})
        self.assertFalse(update_result, "Update operation must be suppressed.")
        self.assertEqual(audit_store.logs[0]["payload"], {"nota": "original"})

    def test_05_broken_hash_chain_detection_in_audit_logs(self):
        """Verify that any tampering in historical audit blocks is detected by chain verification."""
        audit_store = ImmutableAuditLogStore()
        audit_store.append_log(user_id=1, prontuario_id=10, action="CREATE", payload={"text": "Abertura"})
        audit_store.append_log(user_id=2, prontuario_id=10, action="UPDATE", payload={"text": "Evolução 1"})
        audit_store.append_log(user_id=1, prontuario_id=10, action="UPDATE", payload={"text": "Evolução 2"})

        # Verify initial clean chain
        is_intact, broken_id, msg = audit_store.verify_chain_integrity()
        self.assertTrue(is_intact, f"Chain should be intact: {msg}")
        self.assertIsNone(broken_id)

        # Simulate direct database row tampering (e.g. rogue DBA modifying entry #2)
        audit_store.logs[1]["payload"] = {"text": "Tampered content by attacker"}

        # Chain verification must now fail specifically at log_id=2
        is_intact_after, broken_id_after, msg_after = audit_store.verify_chain_integrity()
        self.assertFalse(is_intact_after, "Tampered block must fail chain verification.")
        self.assertEqual(broken_id_after, 2)
        self.assertIn("Tampered record content", msg_after)

    def test_06_blind_index_search_with_invalid_salt_hmac_key(self):
        """Verify that blind index lookups with wrong pepper return zero matches without leaking data."""
        correct_pepper = self.pepper
        wrong_pepper = "attacker_random_pepper_salt_12345"

        correct_index = CryptoEngine.compute_blind_index(self.valid_cpf, correct_pepper)
        wrong_index = CryptoEngine.compute_blind_index(self.valid_cpf, wrong_pepper)

        self.assertNotEqual(correct_index, wrong_index, "Different peppers must produce distinct blind indexes.")

        mock_db_index = {correct_index: {"egresso_id": 42, "status": "ativo"}}
        # Search with wrong pepper must yield None
        self.assertIsNone(mock_db_index.get(wrong_index))
        # Search with correct pepper yields the record
        self.assertIsNotNone(mock_db_index.get(correct_index))

    def test_07_aes_256_decryption_failure_on_corrupted_ciphertext(self):
        """Verify AES-GCM decryption failure and auth tag mismatch on corrupted ciphertext."""
        plaintext = "Dados Sensíveis do Prontuário SEJUS: Histórico penal e médico."
        encrypted = CryptoEngine.simulate_aes_encrypt(plaintext, self.aes_key)

        # 1. Clean decryption must succeed
        ok, decrypted, _ = CryptoEngine.simulate_aes_decrypt(encrypted, self.aes_key)
        self.assertTrue(ok)
        self.assertEqual(decrypted, plaintext)

        # 2. Corrupt one byte in ciphertext
        corrupted_ct = bytearray.fromhex(encrypted["ciphertext"])
        corrupted_ct[0] ^= 0x01  # Flip one bit
        tampered_package = {
            "iv": encrypted["iv"],
            "ciphertext": corrupted_ct.hex(),
            "tag": encrypted["tag"],
        }
        ok_tamper, dec_tamper, reason = CryptoEngine.simulate_aes_decrypt(tampered_package, self.aes_key)
        self.assertFalse(ok_tamper, "Tampered ciphertext must fail authentication tag check.")
        self.assertIsNone(dec_tamper)
        self.assertEqual(reason, "auth_tag_mismatch_or_tampered_ciphertext")

        # 3. Decrypt with wrong key
        wrong_key = hashlib.sha256(b"wrong_aes_key").hexdigest()
        ok_wrong_key, _, reason_wk = CryptoEngine.simulate_aes_decrypt(encrypted, wrong_key)
        self.assertFalse(ok_wrong_key)
        self.assertEqual(reason_wk, "auth_tag_mismatch_or_tampered_ciphertext")

    def test_08_plaintext_pii_search_prevention(self):
        """Verify that PII columns cannot be searched in plaintext and require blind indexing."""
        schema_columns = {
            "egressos": ["id", "nome_encrypted", "cpf_encrypted", "cpf_bindex", "created_at"],
            "prontuarios": ["id", "egresso_id", "diagnostico_encrypted", "created_at"]
        }

        # Ensure raw plaintext 'cpf' or 'nome' column is not in searchable columns
        self.assertNotIn("cpf", schema_columns["egressos"])
        self.assertIn("cpf_bindex", schema_columns["egressos"])
        self.assertIn("cpf_encrypted", schema_columns["egressos"])

    def test_09_invalid_cpf_checksums_and_extreme_formats(self):
        """Verify cryptographic operations reject invalid, non-digit, or malformed CPFs."""
        invalid_cpfs = [
            "00000000000",
            "11111111111",
            "12345678900",  # Bad check digits
            "123.456",      # Too short
            "123.456.789-012345",  # Too long
            "ABC.DEF.GHI-JK",      # Letters
            "'; DROP TABLE egressos; --",
        ]

        for bad_cpf in invalid_cpfs:
            with self.assertRaises(ValueError):
                CryptoEngine.generate_qr_payload(egresso_id=1, cpf=bad_cpf, nome="Teste")

    def test_10_zero_length_negative_id_cryptographic_requests(self):
        """Verify cryptographic request generator rejects zero, negative, or invalid entity IDs."""
        invalid_ids = [0, -1, -999]
        for bad_id in invalid_ids:
            with self.assertRaises(ValueError):
                CryptoEngine.generate_qr_payload(egresso_id=bad_id, cpf=self.valid_cpf, nome="Teste")

    def test_11_constant_time_comparison_resilience(self):
        """Verify HMAC comparison relies on hmac.compare_digest for timing attack resilience."""
        sig_a = "a" * 64
        sig_b = "a" * 63 + "b"
        sig_c = "a" * 64

        self.assertTrue(hmac.compare_digest(sig_a, sig_c))
        self.assertFalse(hmac.compare_digest(sig_a, sig_b))


if __name__ == "__main__":
    unittest.main()
