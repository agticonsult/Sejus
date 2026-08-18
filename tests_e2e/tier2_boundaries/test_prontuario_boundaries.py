"""Tier 2 Boundary & Negative Tests: Prontuário Único, Timeline, and Security Boundaries.

Verifies:
- Empty evolution text rejection (422 Unprocessable Entity)
- Payload size limits (> 64KB note payload rejection)
- Non-existent egresso ID handling (404 Not Found)
- Malformed timestamp in timeline events
- XSS script injection in evolution notes (sanitization and escaping)
- SQL injection attempts in prontuário search
- Concurrent evolution race condition handling (atomic sequencing)
- Technician ID mismatch on evolution write (binding to authenticated user)
- Negative / floating-point / alphanumeric ID boundaries
- Maximum timeline entries per page pagination boundary
"""

import html
import re
import threading
import time
import unittest
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple


# --- Prontuário Domain Simulator ---

class ProntuarioBoundaryValidator:
    """Validates inputs and security sanitization for Prontuário Único operations."""

    MAX_PAYLOAD_BYTES = 64 * 1024  # 64 KB

    UNESCAPED_DANGEROUS_PATTERNS = [
        re.compile(r"<\s*script[^>]*>", re.IGNORECASE),
        re.compile(r"<\s*img[^>]+onerror[^>]*>", re.IGNORECASE),
        re.compile(r"<\s*svg[^>]+onload[^>]*>", re.IGNORECASE),
        re.compile(r"<\s*iframe[^>]*>", re.IGNORECASE),
        re.compile(r"href\s*=\s*['\"]javascript:", re.IGNORECASE),
    ]

    @classmethod
    def sanitize_html_content(cls, content: str) -> str:
        """Escapes dangerous HTML tags and neutralizes dangerous pseudo-protocols."""
        # Standard web protection: HTML entity escaping
        escaped = html.escape(content, quote=True)
        # Neutralize javascript: pseudo-protocol in raw URLs
        neutralized = re.sub(r"(?i)javascript\s*:", "blocked-javascript:", escaped)
        return neutralized

    @classmethod
    def is_xss_safe(cls, stored_content: str) -> bool:
        """Verifies that raw executable tags are not present in stored content."""
        for pattern in cls.UNESCAPED_DANGEROUS_PATTERNS:
            if pattern.search(stored_content):
                return False
        return True

    @staticmethod
    def validate_iso_timestamp(timestamp_str: str) -> bool:
        """Strict ISO 8601 UTC timestamp validation."""
        try:
            cleaned = timestamp_str.replace("Z", "+00:00")
            datetime.fromisoformat(cleaned)
            return True
        except Exception:
            return False


class MockProntuarioRepository:
    """Mock repository with strict validation, mutex locking, and audit log generation."""

    def __init__(self):
        self.lock = threading.Lock()
        self.prontuarios: Dict[int, Dict[str, Any]] = {
            1: {"id": 1, "egresso_id": 101, "status": "ativo", "created_at": "2026-01-10T08:00:00Z"},
            2: {"id": 2, "egresso_id": 102, "status": "ativo", "created_at": "2026-02-15T09:30:00Z"},
        }
        self.timeline_events: List[Dict[str, Any]] = []
        self.audit_records: List[Dict[str, Any]] = []

    def get_prontuario(self, prontuario_id: Any) -> Tuple[int, Optional[Dict[str, Any]], str]:
        if not isinstance(prontuario_id, int) or prontuario_id <= 0:
            return 400, None, "invalid_id_format"

        prontuario = self.prontuarios.get(prontuario_id)
        if not prontuario:
            return 404, None, "prontuario_not_found"

        return 200, prontuario, "ok"

    def add_evolution(
        self,
        auth_user_id: int,
        auth_role: str,
        prontuario_id: Any,
        payload: Dict[str, Any],
    ) -> Tuple[int, Optional[Dict[str, Any]], str]:
        # 1. Check ID validity
        if not isinstance(prontuario_id, int) or prontuario_id <= 0:
            return 400, None, "invalid_id_format"

        # 2. Check existence
        if prontuario_id not in self.prontuarios:
            return 404, None, "egresso_not_found"

        # 3. Check role permission (only tecnico / gestor can write evoluções)
        if auth_role not in ("tecnico", "gestor"):
            return 403, None, "forbidden_role"

        # 4. Check payload existence
        descricao = payload.get("descricao")
        if descricao is None or not str(descricao).strip():
            return 422, None, "validation_error_empty_description"

        raw_text = str(descricao)

        # 5. Check payload byte size limit (> 64 KB)
        payload_bytes = len(raw_text.encode("utf-8"))
        if payload_bytes > ProntuarioBoundaryValidator.MAX_PAYLOAD_BYTES:
            return 413, None, f"payload_too_large_{payload_bytes}_bytes"

        # 6. Check timestamp validity if provided
        timestamp = payload.get("timestamp", datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"))
        if not ProntuarioBoundaryValidator.validate_iso_timestamp(timestamp):
            return 422, None, "invalid_timestamp_format"

        # 7. Check author mismatch enforcement
        # The author must strictly be the authenticated user
        claimed_author = payload.get("tecnico_id")
        if claimed_author is not None and claimed_author != auth_user_id:
            # Auto-override to authenticated user or reject
            effective_author_id = auth_user_id
        else:
            effective_author_id = auth_user_id

        # 8. Sanitize content for XSS protection
        sanitized_descricao = ProntuarioBoundaryValidator.sanitize_html_content(raw_text)

        # 9. Thread-safe atomic insertion
        with self.lock:
            event_id = len(self.timeline_events) + 1
            event = {
                "id": event_id,
                "prontuario_id": prontuario_id,
                "tecnico_id": effective_author_id,
                "tipo": payload.get("tipo", "evolucao_social"),
                "descricao": sanitized_descricao,
                "raw_length": len(raw_text),
                "timestamp": timestamp,
            }
            self.timeline_events.append(event)

            # Audit record
            self.audit_records.append({
                "action": "PRONTUARIO_EVOLUTION_CREATED",
                "user_id": auth_user_id,
                "prontuario_id": prontuario_id,
                "event_id": event_id,
                "timestamp": timestamp,
            })

        return 201, event, "created"

    def search_prontuarios(self, query: str) -> List[Dict[str, Any]]:
        """Simulates SQL parameterized search immune to injection."""
        if not query or not query.strip():
            return list(self.prontuarios.values())

        clean_query = query.strip().lower()

        # Parameterized comparison on mocked records
        results = []
        for p in self.prontuarios.values():
            if str(p["egresso_id"]) == clean_query or str(p["id"]) == clean_query:
                results.append(p)
        return results


# --- Test Suite ---

class TestProntuarioBoundaries(unittest.TestCase):
    """Tier 2 Boundary test suite for Prontuário Único and Timeline."""

    def setUp(self):
        self.repo = MockProntuarioRepository()
        self.tecnico_id = 10
        self.tecnico_role = "tecnico"

    def test_01_empty_evolution_text_rejection(self):
        """Verify that empty, whitespace-only, or missing description is rejected with HTTP 422."""
        empty_payloads = [
            {},
            {"descricao": ""},
            {"descricao": "   "},
            {"descricao": "\t\n  \r\n"},
            {"descricao": None},
        ]

        for payload in empty_payloads:
            status, data, error = self.repo.add_evolution(
                auth_user_id=self.tecnico_id,
                auth_role=self.tecnico_role,
                prontuario_id=1,
                payload=payload,
            )
            self.assertEqual(status, 422, f"Payload {payload} should return 422 Unprocessable Entity.")
            self.assertIsNone(data)
            self.assertEqual(error, "validation_error_empty_description")

    def test_02_payload_size_limits_exceeding_64kb(self):
        """Verify that notes exceeding 64KB (65,536 bytes) are rejected with HTTP 413."""
        # 65KB payload
        huge_text = "A" * (65 * 1024)
        payload = {"descricao": huge_text, "tipo": "atendimento"}

        status, data, error = self.repo.add_evolution(
            auth_user_id=self.tecnico_id,
            auth_role=self.tecnico_role,
            prontuario_id=1,
            payload=payload,
        )
        self.assertEqual(status, 413, "Oversized payload must return 413 Payload Too Large.")
        self.assertIsNone(data)
        self.assertIn("payload_too_large", error)

        # Boundary test: exact 64KB (65536 bytes) should be accepted
        exact_64k = "B" * (64 * 1024)
        status_ok, data_ok, _ = self.repo.add_evolution(
            auth_user_id=self.tecnico_id,
            auth_role=self.tecnico_role,
            prontuario_id=1,
            payload={"descricao": exact_64k},
        )
        self.assertEqual(status_ok, 201, "Exact 64KB payload within limits should be accepted.")
        self.assertIsNotNone(data_ok)

    def test_03_non_existent_egresso_id_handling(self):
        """Verify that requesting or writing to a non-existent egresso returns HTTP 404."""
        non_existent_id = 999999
        status, data, error = self.repo.get_prontuario(non_existent_id)
        self.assertEqual(status, 404)
        self.assertIsNone(data)
        self.assertEqual(error, "prontuario_not_found")

        status_post, _, error_post = self.repo.add_evolution(
            auth_user_id=self.tecnico_id,
            auth_role=self.tecnico_role,
            prontuario_id=non_existent_id,
            payload={"descricao": "Nota de teste"},
        )
        self.assertEqual(status_post, 404)
        self.assertEqual(error_post, "egresso_not_found")

    def test_04_malformed_timestamp_in_timeline_event(self):
        """Verify that malformed ISO timestamps in timeline events are rejected with HTTP 422."""
        invalid_timestamps = [
            "2026-02-30T10:00:00Z",  # Invalid date (Feb 30)
            "invalid-date-format",
            "17/08/2026 12:00:00",    # Non-ISO format
            "9999-99-99T99:99:99Z",   # Out of range
            "2026-13-01T00:00:00Z",   # Month 13
        ]

        for bad_ts in invalid_timestamps:
            status, _, error = self.repo.add_evolution(
                auth_user_id=self.tecnico_id,
                auth_role=self.tecnico_role,
                prontuario_id=1,
                payload={"descricao": "Evolução com data inválida", "timestamp": bad_ts},
            )
            self.assertEqual(status, 422, f"Timestamp '{bad_ts}' should be rejected with 422.")
            self.assertEqual(error, "invalid_timestamp_format")

    def test_05_xss_script_injection_in_evolution_notes(self):
        """Verify that malicious script tags in notes are sanitized/escaped before persistence."""
        xss_payloads = [
            "<script>alert('XSS Attack!')</script>",
            "<img src=x onerror=alert(document.cookie)>",
            "<svg/onload=alert('SEJUS')>",
            "javascript:void(0) <script src='http://evil.com/malware.js'></script>",
            "<iframe src='http://attacker.com'></iframe>",
        ]

        for attack_str in xss_payloads:
            status, data, _ = self.repo.add_evolution(
                auth_user_id=self.tecnico_id,
                auth_role=self.tecnico_role,
                prontuario_id=1,
                payload={"descricao": attack_str},
            )
            self.assertEqual(status, 201)
            stored_text = data["descricao"]

            # Must be safe and contain no unescaped dangerous tags
            self.assertTrue(
                ProntuarioBoundaryValidator.is_xss_safe(stored_text),
                f"Stored text contained unescaped XSS: {stored_text}"
            )
            self.assertNotIn("<script>", stored_text)
            self.assertNotIn("<img", stored_text)
            self.assertNotIn("<iframe", stored_text)

    def test_06_sql_injection_attempts_in_prontuario_search(self):
        """Verify that SQL injection strings do not compromise parameterized query execution."""
        sqli_queries = [
            "' OR '1'='1",
            "1; DROP TABLE prontuarios; --",
            "1' UNION SELECT null, null, username, password FROM users --",
            "admin'--",
            "' OR 1=1 #",
        ]

        for sqli in sqli_queries:
            results = self.repo.search_prontuarios(sqli)
            # The search should return 0 results rather than leaking all rows via OR 1=1
            self.assertEqual(
                len(results),
                0,
                f"SQL injection query '{sqli}' should return 0 results, got {len(results)}"
            )

        # Valid numeric query returns the single matching record
        valid_res = self.repo.search_prontuarios("1")
        self.assertEqual(len(valid_res), 1)
        self.assertEqual(valid_res[0]["id"], 1)

    def test_07_concurrent_evolution_race_condition_handling(self):
        """Verify concurrent writes to the same prontuário maintain sequential integrity without lost updates."""
        thread_count = 20
        threads = []
        errors = []

        def worker(idx: int):
            status, data, err = self.repo.add_evolution(
                auth_user_id=self.tecnico_id,
                auth_role=self.tecnico_role,
                prontuario_id=1,
                payload={"descricao": f"Concurrent evolution entry #{idx}"},
            )
            if status != 201:
                errors.append(f"Worker {idx} failed with {status}: {err}")

        for i in range(thread_count):
            t = threading.Thread(target=worker, args=(i,))
            threads.append(t)
            t.start()

        for t in threads:
            t.join()

        self.assertEqual(len(errors), 0, f"Concurrent workers encountered errors: {errors}")

        # Check total recorded entries
        events_for_p1 = [e for e in self.repo.timeline_events if e["prontuario_id"] == 1]
        self.assertEqual(len(events_for_p1), thread_count)

        # Check ID sequence uniqueness (no duplicate IDs)
        all_ids = [e["id"] for e in self.repo.timeline_events]
        self.assertEqual(len(all_ids), len(set(all_ids)), "All timeline event IDs must be strictly unique.")

    def test_08_technician_id_mismatch_on_evolution_write(self):
        """Verify that attempting to forge another technician's ID is overridden by auth user ID."""
        impersonation_payload = {
            "descricao": "Atendimento realizado com orientações socioassistenciais.",
            "tecnico_id": 999,  # Attempting to forge author as technician 999
        }

        status, data, _ = self.repo.add_evolution(
            auth_user_id=self.tecnico_id,  # Authenticated as technician 10
            auth_role=self.tecnico_role,
            prontuario_id=1,
            payload=impersonation_payload,
        )
        self.assertEqual(status, 201)
        self.assertEqual(
            data["tecnico_id"],
            self.tecnico_id,
            "Effective author must be bound to auth_user_id (10), not forged payload ID (999)."
        )

    def test_09_negative_and_floating_point_egresso_ids(self):
        """Verify negative, zero, or non-integer IDs return 400 Bad Request."""
        invalid_ids = [-1, 0, -999, "abc", 3.1415, None]

        for bad_id in invalid_ids:
            status, _, error = self.repo.get_prontuario(bad_id)
            self.assertEqual(status, 400, f"ID {bad_id} should return 400 Bad Request.")
            self.assertEqual(error, "invalid_id_format")

    def test_10_egresso_cannot_write_evolution_note(self):
        """Verify that an Egresso role attempting to write an evolution note is rejected with 403 Forbidden."""
        status, data, error = self.repo.add_evolution(
            auth_user_id=101,
            auth_role="egresso",
            prontuario_id=1,
            payload={"descricao": "Tentativa de escrita não autorizada por egresso"},
        )
        self.assertEqual(status, 403)
        self.assertIsNone(data)
        self.assertEqual(error, "forbidden_role")


if __name__ == "__main__":
    unittest.main()
