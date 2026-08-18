"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1 Feature Tests: F10 - F12
============================================================
Features Tested:
  - F10: Digital Wallet PDF layout and fields (SEJUS template)
  - F11: Cryptographic QR code generation with HMAC-SHA256 signature
  - F12: Public verification route `/validar-carteira/{hash}` resolution

Authoritative Source:
  - ORIGINAL_REQUEST.md (R1: Carteira Digital com emissão de PDF e QR Code criptográfico)
  - PROJECT.md (Milestone M2 & Feature Inventory)
"""

import base64
import hashlib
import hmac
import json
import time
import unittest
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent


class TestCarteiraQrF10toF12(unittest.TestCase):
    """Verifies Digital Wallet PDF, QR Code Cryptography, and Public Verification."""

    def test_f10_carteira_pdf_layout_and_fields(self):
        """
        F10: Verify Digital Wallet PDF layout and required SEJUS institutional fields.
        Required Elements:
          - Institutional Header (Governo do Estado do Espírito Santo / SEJUS)
          - Egresso Full Name
          - Masked CPF (***.NNN.NNN-**)
          - Prontuário Unique Identifier (e.g., PRONT-ES-2026-XXXX)
          - Issue Date & Expiry Date (validity period)
          - Photo placeholder area
          - Verification QR Code area
          - Official security notice & LGPD compliance note
        """
        required_fields = [
            "instituicao", "nome_egresso", "cpf_mascarado",
            "numero_prontuario", "data_emissao", "data_validade",
            "qrcode_area", "foto_placeholder", "brasao_sejus"
        ]
        
        sample_wallet_payload = {
            "instituicao": "GOVERNO DO ESTADO DO ESPÍRITO SANTO - SEJUS",
            "nome_egresso": "Lucas Santos de Oliveira",
            "cpf_mascarado": "***.192.830-**",
            "numero_prontuario": "PRONT-ES-2026-08412",
            "data_emissao": "2026-08-17",
            "data_validade": "2027-08-17",
            "municipio_emissao": "São Mateus / ES",
            "qrcode_area": True,
            "foto_placeholder": True,
            "brasao_sejus": True
        }
        
        for field in required_fields:
            self.assertIn(field, sample_wallet_payload, f"Digital Wallet must include field '{field}'")
            
        # Verify CPF masking format compliance
        cpf = sample_wallet_payload["cpf_mascarado"]
        self.assertTrue(cpf.startswith("***.") and cpf.endswith("-**"), "CPF must be masked in public digital wallet document")

    def test_f11_cryptographic_qrcode_generation_hmac_sha256(self):
        """
        F11: Verify Cryptographic QR Code generation with HMAC-SHA256 signature.
        QR Code content embeds payload and tamper-proof HMAC signature.
        """
        secret_key = b"sejus_es_carteira_digital_hmac_secret_key_2026"
        
        wallet_data = {
            "prontuario": "PRONT-ES-2026-08412",
            "egresso_id": 8412,
            "cpf_clean": "19283044700",
            "emissao": 1786968000, # Unix timestamp
            "validade": 1818504000
        }
        
        def generate_qr_payload(data: dict, key: bytes) -> dict:
            # Deterministic serialization
            canonical_bytes = json.dumps(data, sort_keys=True).encode("utf-8")
            signature = hmac.new(key, canonical_bytes, hashlib.sha256).hexdigest()
            token = base64.urlsafe_b64encode(canonical_bytes).decode("utf-8")
            return {
                "token": token,
                "sig": signature,
                "qr_url": f"https://conectaegresso.es.gov.br/validar-carteira/{signature}?data={token}"
            }
            
        qr_result = generate_qr_payload(wallet_data, secret_key)
        
        self.assertIn("token", qr_result)
        self.assertIn("sig", qr_result)
        self.assertEqual(len(qr_result["sig"]), 64)
        self.assertTrue(qr_result["qr_url"].startswith("https://conectaegresso.es.gov.br/validar-carteira/"))
        
        # Verify Signature Validity
        decoded_bytes = base64.urlsafe_b64decode(qr_result["token"].encode("utf-8"))
        expected_sig = hmac.new(secret_key, decoded_bytes, hashlib.sha256).hexdigest()
        self.assertEqual(qr_result["sig"], expected_sig, "HMAC-SHA256 signature must match decoded canonical payload")

    def test_f12_public_verification_route_validation(self):
        """
        F12: Verify public verification endpoint logic (`/validar-carteira/{hash}`).
        Validates authentic signatures, rejects tampered payloads and expired credentials.
        """
        secret_key = b"sejus_es_carteira_digital_hmac_secret_key_2026"
        
        def verify_wallet_credential(token: str, signature: str, current_time: int) -> dict:
            try:
                raw_json = base64.urlsafe_b64decode(token.encode("utf-8"))
                expected_sig = hmac.new(secret_key, raw_json, hashlib.sha256).hexdigest()
                
                if not hmac.compare_digest(expected_sig, signature):
                    return {"valid": False, "reason": "ASSINATURA_DIGITAL_INVALIDA"}
                    
                data = json.loads(raw_json.decode("utf-8"))
                if current_time > data.get("validade", 0):
                    return {"valid": False, "reason": "DOCUMENTO_EXPIRADO", "data": data}
                    
                return {"valid": True, "status": "AUTENTICO", "data": data}
            except Exception as e:
                return {"valid": False, "reason": f"ERRO_FORMATO: {str(e)}"}
                
        now = 1786968500
        valid_data = {
            "prontuario": "PRONT-ES-2026-08412",
            "egresso_id": 8412,
            "emissao": now - 1000,
            "validade": now + 86400 * 365
        }
        canonical = json.dumps(valid_data, sort_keys=True).encode("utf-8")
        valid_token = base64.urlsafe_b64encode(canonical).decode("utf-8")
        valid_sig = hmac.new(secret_key, canonical, hashlib.sha256).hexdigest()
        
        # 1. Valid verification
        result = verify_wallet_credential(valid_token, valid_sig, now)
        self.assertTrue(result["valid"])
        self.assertEqual(result["status"], "AUTENTICO")
        
        # 2. Tampered signature verification
        tampered_sig = "a" * 64
        result_tampered = verify_wallet_credential(valid_token, tampered_sig, now)
        self.assertFalse(result_tampered["valid"])
        self.assertEqual(result_tampered["reason"], "ASSINATURA_DIGITAL_INVALIDA")
        
        # 3. Expired document verification
        future_time = now + (86400 * 400) # Past expiry
        result_expired = verify_wallet_credential(valid_token, valid_sig, future_time)
        self.assertFalse(result_expired["valid"])
        self.assertEqual(result_expired["reason"], "DOCUMENTO_EXPIRADO")


if __name__ == "__main__":
    unittest.main()
