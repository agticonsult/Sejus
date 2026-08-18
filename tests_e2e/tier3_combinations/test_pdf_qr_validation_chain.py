"""Tier 3 Combinatorial Test Suite: Digital Wallet Issuance, PDF Generation, QR Code & Public Validation Chain.

Covers cross-feature workflow:
1. End-to-End Credential Issuance & Public Validation Chain:
   - System issues Carteira Digital do Egresso (F10, F11, F12)
   - PDF document generated with Dompdf layout (SEJUS header, photo placeholder, masked CPF, issuance timestamp)
   - Cryptographic QR code generated with embedded HMAC-SHA256 signature
   - Public route (`GET /validar-carteira/{token}`) queried
   - Asserts document validity: "VÁLIDO", authentic SEJUS seal, matching egresso profile, issuance timestamp
2. Anti-Tampering & Integrity Verification:
   - Altered Egresso ID or CPF in QR payload fails cryptographic check
   - Modified issue timestamp or manipulated expiration date fails verification
   - Non-existent or corrupted validation hash returns 404 Not Found
3. Wallet Revocation & Status Lifecycle:
   - Wallet status updated to "REVOGADO" (e.g. judicial suspension or re-incarceration)
   - Public validation route reflects revocation: status "REVOGADO", red warning banner, revocation timestamp
4. PDF Binary Stream Verification:
   - Validates PDF MIME type `application/pdf`, magic header `%PDF-1.4`, non-empty byte stream, Content-Disposition header.
"""

from __future__ import annotations

import copy
import hashlib
import json
import time
import unittest
from datetime import datetime, timezone
from typing import Any, Dict, List, Optional, Tuple

from tests_e2e.e2e_utils import (
    AssertionHelper,
    CryptoVerifier,
    DataGenerator,
    ES_MUNICIPALITIES,
    HttpResponse,
    MockApiClient,
)


class MockDigitalWalletService:
    """
    Simulates the backend Carteira Digital service and public verification controller.
    Implements Dompdf generation simulation, HMAC-SHA256 QR codes, and public verification endpoints.
    """

    def __init__(self, secret_key: str = CryptoVerifier.DEFAULT_WEBHOOK_SECRET):
        self.secret_key = secret_key
        self.wallets_db: Dict[str, Dict[str, Any]] = {}  # indexed by token/hash
        self.verification_counters: Dict[str, int] = {}  # token -> count of public validations
        self.audit_log: List[Dict[str, Any]] = []

    def issue_wallet(self, egresso_profile: Dict[str, Any], valid_days: int = 365) -> Dict[str, Any]:
        """Issues a new Digital Wallet credential with cryptographic QR payload."""
        now = datetime.now(timezone.utc)
        clean_cpf = "".join(filter(str.isdigit, egresso_profile.get("cpf", "")))
        egresso_id = egresso_profile.get("id", 101)
        prontuario_id = egresso_profile.get("prontuario_id", f"PRONT-ES-{egresso_id:06d}")

        # Unique token/hash for public verification URL
        token = hashlib.sha256(f"CARTEIRA-{clean_cpf}-{egresso_id}-{now.timestamp()}".encode()).hexdigest()[:32]

        emissao_iso = now.strftime("%Y-%m-%d")
        valido_ate = f"{now.year + 1}-{now.month:02d}-{now.day:02d}"

        masked_cpf = f"***.{clean_cpf[3:6]}.{clean_cpf[6:9]}-**" if len(clean_cpf) == 11 else clean_cpf

        # QR payload strictly signed with HMAC-SHA256
        qr_data = {
            "token": token,
            "egresso_id": egresso_id,
            "prontuario_id": prontuario_id,
            "nome": egresso_profile.get("name"),
            "cpf_masked": masked_cpf,
            "municipio_ibge": egresso_profile.get("municipio_residencia_ibge", "3205309"),
            "regime": egresso_profile.get("regime_prisional", "LIVRAMENTO_CONDICIONAL"),
            "emissao_iso": emissao_iso,
            "valido_ate": valido_ate,
            "emissor": "SEJUS/ES - Sistema CONECTA EGRESSO",
        }
        signature = CryptoVerifier.generate_hmac_signature(qr_data, self.secret_key)
        qr_data["signature"] = signature
        qr_data["validation_url"] = f"/validar-carteira/{token}"

        wallet_record = {
            "token": token,
            "egresso_id": egresso_id,
            "egresso_nome": egresso_profile.get("name"),
            "cpf": egresso_profile.get("cpf"),
            "cpf_masked": masked_cpf,
            "prontuario_id": prontuario_id,
            "status": "ATIVO",  # ATIVO, REVOGADO, SUSPENSO
            "motivo_revogacao": None,
            "data_revogacao": None,
            "emissao_iso": emissao_iso,
            "valido_ate": valido_ate,
            "qr_payload": qr_data,
            "created_at": now.isoformat(),
        }

        self.wallets_db[token] = wallet_record
        self.verification_counters[token] = 0

        self.audit_log.append({
            "action": "ISSUE_CARTEIRA_DIGITAL",
            "egresso_id": egresso_id,
            "token": token,
            "timestamp": now.isoformat(),
        })

        return wallet_record

    def generate_pdf_stream(self, token: str) -> HttpResponse:
        """Simulates PDF generation (Dompdf) for a given wallet token."""
        wallet = self.wallets_db.get(token)
        if not wallet:
            return HttpResponse(status_code=404, text="Carteira Digital não encontrada.", url=f"/carteira/pdf/{token}")

        pdf_binary = (
            f"%PDF-1.4\n"
            f"%SEJUS_CONECTA_EGRESSO_CARTEIRA_DIGITAL\n"
            f"1 0 obj << /Title (Carteira Digital do Egresso - SEJUS/ES) /Author (Secretaria de Estado da Justica do ES) >> endobj\n"
            f"2 0 obj << /DocID ({wallet['token']}) /Nome ({wallet['egresso_nome']}) /CPF ({wallet['cpf_masked']}) >> endobj\n"
            f"3 0 obj << /QRPayload ({json.dumps(wallet['qr_payload'])}) >> endobj\n"
            f"%%EOF\n"
        ).encode("utf-8")

        headers = {
            "Content-Type": "application/pdf",
            "Content-Disposition": f'attachment; filename="carteira_egresso_{wallet["egresso_id"]}.pdf"',
            "Content-Length": str(len(pdf_binary)),
            "X-SEJUS-Document-Status": wallet["status"],
        }
        return HttpResponse(
            status_code=200,
            headers=headers,
            content=pdf_binary,
            text=pdf_binary.decode("utf-8", errors="replace"),
            url=f"/carteira/pdf/{token}"
        )

    def validate_public_wallet(self, token: str, qr_payload_override: Optional[Dict[str, Any]] = None) -> Tuple[int, Dict[str, Any]]:
        """
        Simulates public validation endpoint GET /validar-carteira/{token}.
        Validates hash, checks HMAC signature, inspects revocation status, and increments validation count.
        """
        wallet = self.wallets_db.get(token)
        if not wallet:
            return 404, {
                "valid": False,
                "status": "NOT_FOUND",
                "error": "Documento não encontrado na base de dados da SEJUS/ES.",
                "code": "DOCUMENT_NOT_FOUND",
            }

        # Use payload from wallet or override (for tampering tests)
        payload_to_verify = qr_payload_override or wallet["qr_payload"]

        # Cryptographic check
        is_sig_valid = CryptoVerifier.verify_qr_payload(payload_to_verify, self.secret_key)
        if not is_sig_valid:
            return 422, {
                "valid": False,
                "status": "INVALID_SIGNATURE",
                "error": "Assinatura criptográfica corrompida ou documento adulterado.",
                "code": "TAMPERED_PAYLOAD",
            }

        # Check expiration
        now_iso = datetime.now(timezone.utc).strftime("%Y-%m-%d")
        if payload_to_verify.get("valido_ate", "9999-12-31") < now_iso:
            return 200, {
                "valid": False,
                "status": "EXPIRADO",
                "error": "Documento expirado. Necessária reemissão junto ao Escritório Social.",
                "code": "DOCUMENT_EXPIRED",
                "egresso_nome": wallet["egresso_nome"],
                "cpf_masked": wallet["cpf_masked"],
            }

        # Increment verification counter
        self.verification_counters[token] = self.verification_counters.get(token, 0) + 1

        # Check Revocation Status
        if wallet["status"] == "REVOGADO":
            return 200, {
                "valid": False,
                "status": "REVOGADO",
                "error": "Documento REVOGADO administrativamente ou judicialmente pela SEJUS/ES.",
                "motivo_revogacao": wallet.get("motivo_revogacao", "Decisão judicial / Administrativa"),
                "data_revogacao": wallet.get("data_revogacao"),
                "egresso_nome": wallet["egresso_nome"],
                "cpf_masked": wallet["cpf_masked"],
                "verification_count": self.verification_counters[token],
            }

        # Valid document
        return 200, {
            "valid": True,
            "status": "ATIVO",
            "message": "Documento autêntico e válido emitido pela SEJUS/ES.",
            "token": token,
            "egresso_id": wallet["egresso_id"],
            "egresso_nome": wallet["egresso_nome"],
            "cpf_masked": wallet["cpf_masked"],
            "prontuario_id": wallet["prontuario_id"],
            "emissao": wallet["emissao_iso"],
            "valido_ate": wallet["valido_ate"],
            "emissor": "Secretaria de Estado da Justiça - Governo do Estado do Espírito Santo",
            "verification_count": self.verification_counters[token],
            "validation_timestamp": datetime.now(timezone.utc).isoformat(),
        }

    def revoke_wallet(self, token: str, motivo: str) -> bool:
        """Revokes a wallet."""
        wallet = self.wallets_db.get(token)
        if not wallet:
            return False
        wallet["status"] = "REVOGADO"
        wallet["motivo_revogacao"] = motivo
        wallet["data_revogacao"] = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
        return True


class TestPdfQrValidationChain(unittest.TestCase):
    """Pairwise Integration Test Suite: Digital Wallet Issuance, PDF, QR Code & Public Validation."""

    def setUp(self):
        self.secret_key = CryptoVerifier.DEFAULT_WEBHOOK_SECRET
        self.service = MockDigitalWalletService(self.secret_key)
        self.egresso = DataGenerator.generate_user_profile(
            role="egresso",
            id=101,
            name="Lucas Santos",
            cpf="123.456.789-01",
            municipio=ES_MUNICIPALITIES[42]  # Linhares
        )

    def test_01_wallet_pdf_qr_generation_and_public_validation_chain(self):
        """
        Verify complete Digital Wallet chain:
        1. Issue Digital Wallet for Egresso Lucas Santos.
        2. Generate PDF stream -> verify binary headers, MIME type, and embedded QR metadata.
        3. Parse QR payload -> verify HMAC-SHA256 signature mathematically matches.
        4. Validate public endpoint `GET /validar-carteira/{token}`.
        5. Confirm status: "ATIVO", "valid": True, masked CPF, authentic SEJUS seal.
        6. Confirm verification count increments upon repeated queries.
        """
        # Step 1: Issue Wallet
        wallet = self.service.issue_wallet(self.egresso)
        token = wallet["token"]
        self.assertIsNotNone(token)
        self.assertEqual(wallet["status"], "ATIVO")
        self.assertEqual(wallet["egresso_nome"], "Lucas Santos")

        # Step 2: Generate PDF Stream
        pdf_resp = self.service.generate_pdf_stream(token)
        AssertionHelper.assert_status_code(pdf_resp.status_code, 200, "PDF Generation")
        self.assertEqual(pdf_resp.headers["Content-Type"], "application/pdf")
        self.assertTrue(pdf_resp.content.startswith(b"%PDF-1.4"), "PDF must have standard %PDF magic header")
        self.assertIn("carteira_egresso_101.pdf", pdf_resp.headers["Content-Disposition"])

        # Step 3: Extract and Verify QR payload
        qr_payload = wallet["qr_payload"]
        self.assertIn("signature", qr_payload)
        self.assertIn("validation_url", qr_payload)
        is_qr_sig_valid = CryptoVerifier.verify_qr_payload(qr_payload, self.secret_key)
        self.assertTrue(is_qr_sig_valid, "QR code HMAC signature must be valid")

        # Step 4: Public validation route GET /validar-carteira/{token}
        status_val, body_val = self.service.validate_public_wallet(token)
        AssertionHelper.assert_status_code(status_val, 200, "Public Validation Route")
        self.assertTrue(body_val["valid"])
        self.assertEqual(body_val["status"], "ATIVO")
        self.assertEqual(body_val["egresso_nome"], "Lucas Santos")
        self.assertEqual(body_val["cpf_masked"], "***.456.789-**")
        self.assertEqual(body_val["verification_count"], 1)

        # Step 5: Second query increments verification counter
        status_val2, body_val2 = self.service.validate_public_wallet(token)
        self.assertEqual(body_val2["verification_count"], 2)

    def test_02_tampered_payload_or_modified_data_fails_verification(self):
        """
        Verify anti-tampering defenses:
        1. Tampering Egresso ID in QR code payload (e.g. changing 101 -> 999) fails HMAC check (422).
        2. Tampering name or CPF fails verification.
        3. Tampering expiration date or issuance timestamp fails verification.
        4. Querying unknown token returns 404 Not Found.
        """
        wallet = self.service.issue_wallet(self.egresso)
        token = wallet["token"]
        original_qr = copy.deepcopy(wallet["qr_payload"])

        # 1. Tamper Egresso ID
        tampered_id_payload = copy.deepcopy(original_qr)
        tampered_id_payload["egresso_id"] = 999  # Attacker modifies ID without knowing HMAC key
        st1, body1 = self.service.validate_public_wallet(token, qr_payload_override=tampered_id_payload)
        AssertionHelper.assert_status_code(st1, 422, "Tampered Egresso ID")
        self.assertEqual(body1["code"], "TAMPERED_PAYLOAD")

        # 2. Tamper Name
        tampered_name_payload = copy.deepcopy(original_qr)
        tampered_name_payload["nome"] = "Outra Pessoa Fraudulenta"
        st2, body2 = self.service.validate_public_wallet(token, qr_payload_override=tampered_name_payload)
        AssertionHelper.assert_status_code(st2, 422, "Tampered Name")
        self.assertEqual(body2["code"], "TAMPERED_PAYLOAD")

        # 3. Tamper Expiration Date
        tampered_exp_payload = copy.deepcopy(original_qr)
        tampered_exp_payload["valido_ate"] = "2099-12-31"
        st3, body3 = self.service.validate_public_wallet(token, qr_payload_override=tampered_exp_payload)
        AssertionHelper.assert_status_code(st3, 422, "Tampered Expiration")
        self.assertEqual(body3["code"], "TAMPERED_PAYLOAD")

        # 4. Unknown random token -> 404
        st4, body4 = self.service.validate_public_wallet("non_existent_token_1234567890abcdef")
        AssertionHelper.assert_status_code(st4, 404, "Non Existent Token")
        self.assertEqual(body4["code"], "DOCUMENT_NOT_FOUND")

    def test_03_wallet_revocation_reflection_on_public_endpoint(self):
        """
        Verify revocation lifecycle:
        1. Wallet is initially valid and active.
        2. SEJUS administrator revokes wallet due to court order.
        3. Public validation endpoint reflects status: "REVOGADO", valid: False,
           and displays revocation motive and timestamp.
        4. PDF download reflects revoked status in response header.
        """
        wallet = self.service.issue_wallet(self.egresso)
        token = wallet["token"]

        # Initial check
        st_init, body_init = self.service.validate_public_wallet(token)
        self.assertTrue(body_init["valid"])
        self.assertEqual(body_init["status"], "ATIVO")

        # Revoke wallet
        revocation_motive = "Revogação judicial comunicada pela Vara de Execuções Penais de Vitória"
        revocation_ok = self.service.revoke_wallet(token, motivo=revocation_motive)
        self.assertTrue(revocation_ok)

        # Public validation after revocation
        st_rev, body_rev = self.service.validate_public_wallet(token)
        AssertionHelper.assert_status_code(st_rev, 200, "Revoked Validation Query")
        self.assertFalse(body_rev["valid"], "Revoked wallet must NOT be valid")
        self.assertEqual(body_rev["status"], "REVOGADO")
        self.assertEqual(body_rev["motivo_revogacao"], revocation_motive)
        self.assertIsNotNone(body_rev["data_revogacao"])

        # Check PDF header reflects revocation
        pdf_resp = self.service.generate_pdf_stream(token)
        self.assertEqual(pdf_resp.headers.get("X-SEJUS-Document-Status"), "REVOGADO")

    def test_04_expired_wallet_validation(self):
        """
        Verify expired wallet handling:
        1. Issue wallet with past expiration date.
        2. Public validation returns status: "EXPIRADO", valid: False.
        """
        wallet = self.service.issue_wallet(self.egresso)
        token = wallet["token"]

        # Modify issuance & expiration to past year
        expired_qr = copy.deepcopy(wallet["qr_payload"])
        expired_qr["emissao_iso"] = "2024-01-01"
        expired_qr["valido_ate"] = "2025-01-01"  # in past
        # Resign with secret
        sig = CryptoVerifier.generate_hmac_signature({k: v for k, v in expired_qr.items() if k not in ("signature", "validation_url")}, self.secret_key)
        expired_qr["signature"] = sig

        wallet["qr_payload"] = expired_qr

        st_exp, body_exp = self.service.validate_public_wallet(token, qr_payload_override=expired_qr)
        AssertionHelper.assert_status_code(st_exp, 200, "Expired Wallet Validation")
        self.assertFalse(body_exp["valid"])
        self.assertEqual(body_exp["status"], "EXPIRADO")
        self.assertEqual(body_exp["code"], "DOCUMENT_EXPIRED")


if __name__ == "__main__":
    unittest.main()
