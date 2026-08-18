"""
Scenario 2: Egresso Digital Onboarding & Credential Issuance (F08, F10, F11, F12, F17, F42, F47)
================================================================================================
Target Profile: Lucas Santos (Egresso em São Mateus/ES - IBGE 3204906)

Complete End-to-End Operational Workflow:
1. Newly registered Egresso logs in to portal via Gov.br / Acesso Cidadão simulation.
2. Validates encrypted PII / blind index storage (CPF masked in UI: ***.830.457-**).
3. Consults initial Prontuário Único welcome record (Acolhimento Inicial).
4. Accesses Digital Wallet (Carteira Digital do Egresso) page.
5. Generates and downloads official SEJUS PDF credential.
6. Extracts embedded QR Code containing HMAC-SHA256 signature.
7. Performs public verification request against /validar-carteira/{hash}.
8. Confirms valid credential status, issue timestamp, and official SEJUS validation seal.
"""

import unittest
import json
import hashlib
import hmac
import time
import base64
import re
from typing import Dict, List, Any, Optional, Tuple


class EgressoWalletSimulationEngine:
    """
    High-fidelity simulation backend for Egresso Onboarding, PII Blind Indexing,
    Prontuário Único, Digital Wallet PDF emission and Cryptographic QR Code verification.
    """
    def __init__(self,
                 app_key: str = "base64:SEJUS_SECRET_KEY_AES256_GCM_2026_ES",
                 hmac_pepper: str = "SEJUS_LGPD_BLIND_INDEX_PEPPER_2026",
                 wallet_secret: str = "SEJUS_CARTEIRA_HMAC_SHA256_OFFICIAL_KEY_2026"):
        self.app_key = app_key
        self.hmac_pepper = hmac_pepper
        self.wallet_secret = wallet_secret

        # In-memory mock database
        self.users_db: Dict[int, Dict[str, Any]] = {}
        self.prontuarios_db: Dict[int, Dict[str, Any]] = {}
        self.carteiras_db: Dict[str, Dict[str, Any]] = {}
        self.audit_logs: List[Dict[str, Any]] = []

        self._seed_initial_data()

    def _seed_initial_data(self):
        """Seed newly registered Egresso profile."""
        raw_cpf = "19283045789"
        cpf_blind_index = self.compute_blind_index(raw_cpf)
        encrypted_pii = self.simulate_aes_encryption({
            "nome_completo": "Lucas Santos de Oliveira",
            "nome_social": "Lucas Santos",
            "rg": "3.842.910-ES",
            "mae": "Maria das Graças Santos",
            "data_nascimento": "1994-06-15",
            "endereco": "Rua São Mateus, 102, Bairro Centro, São Mateus/ES",
            "cep": "29930-000",
        })

        user_id = 10842
        self.users_db[user_id] = {
            "id": user_id,
            "cpf_blind_index": cpf_blind_index,
            "cpf_masked": self.mask_cpf(raw_cpf),
            "encrypted_pii": encrypted_pii,
            "nome_exibicao": "Lucas Santos",
            "municipio_ibge": "3204906",
            "municipio_nome": "São Mateus",
            "perfil": "egresso",
            "status_cadastro": "ATIVO",
            "criado_em": "2026-08-17T09:00:00Z",
        }

        # Initialize Prontuário Único
        self.prontuarios_db[user_id] = {
            "prontuario_id": f"PRON-2026-3204906-{user_id}",
            "egresso_id": user_id,
            "status": "ATIVO",
            "unidade_responsavel": "Escritório Social Virtual - Polo Norte",
            "timeline": [
                {
                    "evento_id": "EVT-001",
                    "tipo": "ACOLHIMENTO_INICIAL",
                    "titulo": "Cadastro Inicial e Acolhimento no Conecta Egresso",
                    "descricao": "Egresso realizou cadastro via Acesso Cidadão/Gov.br. Perfil integrado à política pública SEJUS.",
                    "data": "2026-08-17T09:05:00Z",
                    "responsavel": "Sistema Central SEJUS",
                    "imutavel": True,
                }
            ]
        }

    def compute_blind_index(self, raw_cpf: str) -> str:
        """Computes deterministic HMAC-SHA256 blind index for secure searchable encryption."""
        clean_cpf = re.sub(r"\D", "", raw_cpf)
        return hmac.new(self.hmac_pepper.encode(), clean_cpf.encode(), hashlib.sha256).hexdigest()

    def mask_cpf(self, raw_cpf: str) -> str:
        """Masks CPF for LGPD data minimization: ***.830.457-**."""
        clean_cpf = re.sub(r"\D", "", raw_cpf).zfill(11)
        return f"***.{clean_cpf[3:6]}.{clean_cpf[6:9]}-**"

    def simulate_aes_encryption(self, data: Dict[str, Any]) -> Dict[str, str]:
        """Simulates AES-256-GCM envelope encryption for sensitive fields."""
        serialized = json.dumps(data)
        # Mock IV (12 bytes) and auth tag (16 bytes)
        iv_b64 = base64.b64encode(b"\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c").decode()
        ciphertext_b64 = base64.b64encode(serialized.encode("utf-8")).decode()
        tag_b64 = base64.b64encode(hashlib.sha256(serialized.encode()).digest()[:16]).decode()
        return {
            "cipher": "AES-256-GCM",
            "iv": iv_b64,
            "ciphertext": ciphertext_b64,
            "tag": tag_b64,
        }

    def authenticate_egresso(self, raw_cpf: str) -> Dict[str, Any]:
        """Simulates Gov.br login for Egresso."""
        blind_idx = self.compute_blind_index(raw_cpf)
        user = next((u for u in self.users_db.values() if u["cpf_blind_index"] == blind_idx), None)
        if not user:
            return {"status": "UNAUTHORIZED", "error": "USER_NOT_FOUND"}

        # Log authentication event
        self.audit_logs.append({
            "action": "AUTH_LOGIN_EGRESSO",
            "user_id": user["id"],
            "cpf_masked": user["cpf_masked"],
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        })

        return {
            "status": "AUTHENTICATED",
            "token": f"bearer_token_egresso_{user['id']}",
            "user": {
                "id": user["id"],
                "nome": user["nome_exibicao"],
                "cpf_masked": user["cpf_masked"],
                "perfil": user["perfil"],
                "municipio": user["municipio_nome"],
                "municipio_ibge": user["municipio_ibge"],
            }
        }

    def get_prontuario(self, user_id: int) -> Dict[str, Any]:
        """Fetches Prontuário Único and logs LGPD audit access."""
        prontuario = self.prontuarios_db.get(user_id)
        if not prontuario:
            return {"status": "NOT_FOUND"}

        self.audit_logs.append({
            "action": "PRONTUARIO_VIEW_BY_OWNER",
            "user_id": user_id,
            "prontuario_id": prontuario["prontuario_id"],
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        })

        return {
            "status": "SUCCESS",
            "prontuario": prontuario,
        }

    def get_or_create_carteira_digital(self, user_id: int) -> Dict[str, Any]:
        """Generates or retrieves Carteira Digital do Egresso."""
        user = self.users_db.get(user_id)
        if not user:
            return {"status": "USER_NOT_FOUND"}

        carteira_num = f"SEJUS-EG-2026-{user['municipio_ibge']}-{user_id:05d}"
        now_ts = int(time.time())
        exp_ts = now_ts + (365 * 24 * 3600)  # 1 year validity

        payload_data = {
            "cid": carteira_num,
            "uid": user_id,
            "nome": user["nome_exibicao"],
            "cpf_masked": user["cpf_masked"],
            "municipio": user["municipio_nome"],
            "ibge": user["municipio_ibge"],
            "status": "REGULAR",
            "orgao_emissor": "SEJUS/ES - Subsecretaria de Reintegração Social",
            "iat": now_ts,
            "exp": exp_ts,
        }

        # Canonical JSON string for HMAC signing
        canonical_json = json.dumps(payload_data, sort_keys=True, separators=(',', ':'))
        signature = hmac.new(self.wallet_secret.encode(), canonical_json.encode(), hashlib.sha256).hexdigest()

        # Build QR code token: base64(canonical_json) + "." + signature
        qr_token_b64 = base64.urlsafe_b64encode(canonical_json.encode()).decode().rstrip("=")
        validation_token = f"{qr_token_b64}.{signature}"

        carteira_record = {
            "carteira_numero": carteira_num,
            "egresso_id": user_id,
            "nome_titular": user["nome_exibicao"],
            "cpf_mascarado": user["cpf_masked"],
            "municipio": user["municipio_nome"],
            "status": "REGULAR",
            "data_emissao": time.strftime("%d/%m/%Y", time.gmtime(now_ts)),
            "data_validade": time.strftime("%d/%m/%Y", time.gmtime(exp_ts)),
            "qr_token": validation_token,
            "qr_payload": payload_data,
            "qr_signature": signature,
            "url_validacao_publica": f"/validar-carteira/{validation_token}",
        }
        self.carteiras_db[carteira_num] = carteira_record

        return {
            "status": "SUCCESS",
            "carteira": carteira_record,
        }

    def generate_carteira_pdf(self, user_id: int) -> Dict[str, Any]:
        """
        Generates simulated official SEJUS PDF document with binary PDF header (%PDF-1.4),
        official layout stream, and embedded cryptographic QR Code.
        """
        carteira_res = self.get_or_create_carteira_digital(user_id)
        carteira = carteira_res["carteira"]

        # Build realistic binary PDF payload
        pdf_header = b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n"
        pdf_body = (
            f"1 0 obj\n<< /Title (Carteira Digital do Egresso - SEJUS/ES) /Author (Governo do Estado do Espirito Santo) >>\nendobj\n"
            f"2 0 obj\n<< /Type /Catalog /Pages 3 0 R >>\nendobj\n"
            f"3 0 obj\n<< /Type /Pages /Kids [4 0 R] /Count 1 >>\nendobj\n"
            f"4 0 obj\n<< /Type /Page /Parent 3 0 R /MediaBox [0 0 595 842] /Contents 5 0 R >>\nendobj\n"
            f"5 0 obj\n<< /Length 400 >>\nstream\n"
            f"BT /F1 16 Tf 50 800 Td (GOVERNO DO ESTADO DO ESPIRITO SANTO) Tj ET\n"
            f"BT /F1 14 Tf 50 780 Td (SECRETARIA DE ESTADO DA JUSTICA - SEJUS/ES) Tj ET\n"
            f"BT /F2 12 Tf 50 750 Td (CARTEIRA DIGITAL DO EGRESSO - CONECTA EGRESSO) Tj ET\n"
            f"BT /F3 10 Tf 50 720 Td (TITULAR: {carteira['nome_titular']}) Tj ET\n"
            f"BT /F3 10 Tf 50 700 Td (CPF: {carteira['cpf_mascarado']}) Tj ET\n"
            f"BT /F3 10 Tf 50 680 Td (NUMERO: {carteira['carteira_numero']}) Tj ET\n"
            f"BT /F3 10 Tf 50 660 Td (STATUS: {carteira['status']} - ACOMPANHAMENTO ATIVO) Tj ET\n"
            f"BT /F3 8 Tf 50 620 Td (QR CODE VALIDATION TOKEN: {carteira['qr_token']}) Tj ET\n"
            f"endstream\nendobj\n"
            f"xref\n0 6\n0000000000 65535 f \ntrailer\n<< /Size 6 /Root 2 0 R >>\nstartxref\n500\n%%EOF\n"
        ).encode("utf-8")

        pdf_bytes = pdf_header + pdf_body

        return {
            "status": "SUCCESS",
            "content_type": "application/pdf",
            "filename": f"carteira_digital_{carteira['carteira_numero']}.pdf",
            "size_bytes": len(pdf_bytes),
            "pdf_bytes": pdf_bytes,
            "carteira_numero": carteira["carteira_numero"],
            "qr_token": carteira["qr_token"],
        }

    def validate_public_carteira_hash(self, token_or_hash: str) -> Dict[str, Any]:
        """
        Public verification endpoint `/validar-carteira/{hash}`.
        Decodes token, validates HMAC-SHA256 signature, validates expiration, and returns verification status.
        """
        try:
            parts = token_or_hash.split(".")
            if len(parts) != 2:
                return {
                    "valido": False,
                    "erro": "FORMATO_INVALIDO",
                    "mensagem": "Token de autenticação possui estrutura corrompida ou inválida.",
                }

            payload_b64, signature = parts
            # Fix base64 padding
            padded_b64 = payload_b64 + "=" * ((4 - len(payload_b64) % 4) % 4)
            canonical_json_bytes = base64.urlsafe_b64decode(padded_b64)
            canonical_json = canonical_json_bytes.decode("utf-8")
            payload = json.loads(canonical_json)

            # Recompute HMAC-SHA256 signature
            expected_sig = hmac.new(self.wallet_secret.encode(), canonical_json.encode(), hashlib.sha256).hexdigest()
            if not hmac.compare_digest(expected_sig, signature):
                return {
                    "valido": False,
                    "erro": "ASSINATURA_HMAC_INVALIDA",
                    "mensagem": "Assinatura criptográfica não confere com o padrão oficial SEJUS/ES.",
                }

            # Check expiration
            now_ts = int(time.time())
            if payload.get("exp") and now_ts > payload["exp"]:
                return {
                    "valido": False,
                    "erro": "DOCUMENTO_EXPIRADO",
                    "mensagem": "A credencial digital expirou seu prazo de validade de 12 meses.",
                }

            # Valid credential
            return {
                "valido": True,
                "status_documento": "REGULAR",
                "carteira_numero": payload["cid"],
                "titular": payload["nome"],
                "documento_mascarado": payload["cpf_masked"],
                "municipio": payload["municipio"],
                "ibge": payload["ibge"],
                "orgao_emissor": payload["orgao_emissor"],
                "emitido_em": time.strftime("%d/%m/%Y %H:%M:%S", time.gmtime(payload["iat"])),
                "valido_ate": time.strftime("%d/%m/%Y %H:%M:%S", time.gmtime(payload["exp"])),
                "selo_autenticidade": f"SEJUS-VALID-{signature[:16].upper()}",
                "mensagem": "Documento Oficial Autêntico - Governo do Estado do Espírito Santo.",
            }
        except Exception as e:
            return {
                "valido": False,
                "erro": "ERRO_PROCESSAMENTO",
                "mensagem": f"Falha ao validar documento: {str(e)}",
            }


def run_scenario_egresso_onboarding_wallet() -> Dict[str, Any]:
    """
    Executes Scenario 2 complete end-to-end user journey workflow.
    """
    engine = EgressoWalletSimulationEngine()
    results = {}

    # Step 1: Egresso Login via Gov.br / Acesso Cidadão
    auth = engine.authenticate_egresso("19283045789")
    results["step1_auth"] = auth
    assert auth["status"] == "AUTHENTICATED"
    assert auth["user"]["cpf_masked"] == "***.830.457-**"
    assert auth["user"]["municipio_ibge"] == "3204906"

    # Step 2: Encrypted PII and Blind Index Validation
    user_rec = engine.users_db[auth["user"]["id"]]
    results["step2_pii_security"] = {
        "cpf_blind_index": user_rec["cpf_blind_index"],
        "cipher": user_rec["encrypted_pii"]["cipher"],
    }
    assert len(user_rec["cpf_blind_index"]) == 64  # SHA-256 hex
    assert user_rec["encrypted_pii"]["cipher"] == "AES-256-GCM"
    assert "19283045789" not in str(user_rec["encrypted_pii"])  # Plaintext CPF not stored

    # Step 3: Prontuário Único Welcome Record
    prontuario_res = engine.get_prontuario(auth["user"]["id"])
    results["step3_prontuario"] = prontuario_res
    assert prontuario_res["status"] == "SUCCESS"
    assert len(prontuario_res["prontuario"]["timeline"]) >= 1
    assert prontuario_res["prontuario"]["timeline"][0]["tipo"] == "ACOLHIMENTO_INICIAL"

    # Step 4: Access Digital Wallet Page
    wallet_res = engine.get_or_create_carteira_digital(auth["user"]["id"])
    results["step4_wallet"] = wallet_res
    assert wallet_res["status"] == "SUCCESS"
    assert wallet_res["carteira"]["status"] == "REGULAR"
    assert "SEJUS-EG-2026-3204906" in wallet_res["carteira"]["carteira_numero"]

    # Step 5: Generate and Download PDF Credential
    pdf_res = engine.generate_carteira_pdf(auth["user"]["id"])
    results["step5_pdf"] = {
        "filename": pdf_res["filename"],
        "size_bytes": pdf_res["size_bytes"],
        "header": pdf_res["pdf_bytes"][:8].decode("latin-1"),
    }
    assert pdf_res["status"] == "SUCCESS"
    assert pdf_res["pdf_bytes"].startswith(b"%PDF-1.4")
    assert pdf_res["size_bytes"] > 200

    # Step 6: Extract Embedded QR Code HMAC Token
    qr_token = pdf_res["qr_token"]
    results["step6_qr_token"] = qr_token
    assert "." in qr_token

    # Step 7: Public Verification Request against /validar-carteira/{hash}
    validation_res = engine.validate_public_carteira_hash(qr_token)
    results["step7_public_validation"] = validation_res
    assert validation_res["valido"] is True
    assert validation_res["status_documento"] == "REGULAR"
    assert validation_res["documento_mascarado"] == "***.830.457-**"
    assert "SEJUS-VALID-" in validation_res["selo_autenticidade"]

    # Step 8: Tampering / Forgery Resistance Verification
    # Attempt 1: Tampered signature
    tampered_sig_token = f"{qr_token.split('.')[0]}.{'0'*64}"
    tampered_sig_res = engine.validate_public_carteira_hash(tampered_sig_token)
    assert tampered_sig_res["valido"] is False
    assert tampered_sig_res["erro"] == "ASSINATURA_HMAC_INVALIDA"

    # Attempt 2: Modified payload (escalating status or changing name)
    tampered_payload = dict(wallet_res["carteira"]["qr_payload"])
    tampered_payload["status"] = "FORGED_STATUS"
    tampered_json = json.dumps(tampered_payload, sort_keys=True, separators=(',', ':'))
    tampered_b64 = base64.urlsafe_b64encode(tampered_json.encode()).decode().rstrip("=")
    tampered_payload_token = f"{tampered_b64}.{wallet_res['carteira']['qr_signature']}"
    tampered_payload_res = engine.validate_public_carteira_hash(tampered_payload_token)
    assert tampered_payload_res["valido"] is False
    assert tampered_payload_res["erro"] == "ASSINATURA_HMAC_INVALIDA"

    return {"status": "SUCCESS", "scenario": "Egresso Digital Onboarding & Credential Issuance", "details": results}


class TestScenarioEgressoOnboardingWallet(unittest.TestCase):
    """
    Unit and Scenario test case for Egresso Onboarding & Wallet Verification.
    """
    def setUp(self):
        self.engine = EgressoWalletSimulationEngine()

    def test_complete_egresso_onboarding_workflow(self):
        """Executes full Scenario 2 user journey."""
        res = run_scenario_egresso_onboarding_wallet()
        self.assertEqual(res["status"], "SUCCESS")

    def test_step1_and_step2_login_and_blind_index_security(self):
        """Verifies blind index generation and masked CPF output."""
        auth = self.engine.authenticate_egresso("19283045789")
        self.assertEqual(auth["status"], "AUTHENTICATED")
        self.assertEqual(auth["user"]["cpf_masked"], "***.830.457-**")

        blind_idx = self.engine.compute_blind_index("19283045789")
        self.assertEqual(len(blind_idx), 64)

        # Same CPF must produce same blind index (deterministic searchability)
        self.assertEqual(blind_idx, self.engine.compute_blind_index("192.830.457-89"))

        # Different CPF produces completely different blind index
        diff_idx = self.engine.compute_blind_index("99988877766")
        self.assertNotEqual(blind_idx, diff_idx)

    def test_step3_prontuario_welcome_event(self):
        """Verifies initial welcome timeline event in Prontuário Único."""
        res = self.engine.get_prontuario(10842)
        self.assertEqual(res["status"], "SUCCESS")
        timeline = res["prontuario"]["timeline"]
        self.assertEqual(timeline[0]["tipo"], "ACOLHIMENTO_INICIAL")
        self.assertTrue(timeline[0]["imutavel"])

        # Confirm audit log was generated
        recent_log = self.engine.audit_logs[-1]
        self.assertEqual(recent_log["action"], "PRONTUARIO_VIEW_BY_OWNER")

    def test_step4_and_step5_carteira_digital_and_pdf_generation(self):
        """Verifies Digital Wallet issuance and binary PDF generation."""
        pdf_res = self.engine.generate_carteira_pdf(10842)
        self.assertEqual(pdf_res["status"], "SUCCESS")
        self.assertEqual(pdf_res["content_type"], "application/pdf")
        self.assertTrue(pdf_res["pdf_bytes"].startswith(b"%PDF-1.4"))
        self.assertIn(b"SECRETARIA DE ESTADO DA JUSTICA", pdf_res["pdf_bytes"])
        self.assertIn(b"CARTEIRA DIGITAL DO EGRESSO", pdf_res["pdf_bytes"])

    def test_step6_step7_and_step8_qr_code_hmac_and_public_validation(self):
        """Verifies QR Code HMAC-SHA256 verification and public validation route."""
        wallet = self.engine.get_or_create_carteira_digital(10842)["carteira"]
        token = wallet["qr_token"]

        val_res = self.engine.validate_public_carteira_hash(token)
        self.assertTrue(val_res["valido"])
        self.assertEqual(val_res["status_documento"], "REGULAR")
        self.assertEqual(val_res["titular"], "Lucas Santos")
        self.assertEqual(val_res["documento_mascarado"], "***.830.457-**")
        self.assertIn("SEJUS-VALID-", val_res["selo_autenticidade"])

    def test_tampered_token_rejection(self):
        """Verifies that modified signatures or manipulated claims are rejected."""
        wallet = self.engine.get_or_create_carteira_digital(10842)["carteira"]
        token = wallet["qr_token"]

        # Corrupted token structure
        self.assertFalse(self.engine.validate_public_carteira_hash("invalid-token")["valido"])

        # Corrupted signature
        bad_sig_token = f"{token.split('.')[0]}.deadbeef"
        self.assertFalse(self.engine.validate_public_carteira_hash(bad_sig_token)["valido"])


if __name__ == "__main__":
    unittest.main()
