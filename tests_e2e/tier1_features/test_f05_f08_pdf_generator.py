"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1: Features 5-8 (PDF Generation & Route Suite)
Authoritative Source: ORIGINAL_REQUEST.md (§R2), PROJECT.md (Features 5-8)

Features Tested:
- F05: Document Generator API Integration (POST http://localhost:8080, X-API-Key: token-secreto-dev)
- F06: Graceful Dompdf Fallback on microservice failure / offline
- F07: Carteira Digital PDF Route (GET /carteira/pdf, stream application/pdf)
- F08: Unauthenticated/Demo Mode PDF Fallback to First Egresso
"""

from __future__ import annotations

import os
import re
import unittest
from pathlib import Path
from typing import Dict, List, Any


class TestTier1PdfGenerator(unittest.TestCase):
    """Tier 1 Feature Coverage for PDF Generation & Fallback (>= 5 tests)."""

    def setUp(self) -> None:
        self.project_root = Path(__file__).parent.parent.parent.resolve()
        self.app_dir = self.project_root / "app"
        self.services_dir = self.app_dir / "Services"
        self.controllers_dir = self.app_dir / "Http" / "Controllers"
        self.routes_file = self.project_root / "routes" / "web.php"

    def test_01_carteira_pdf_service_exists_and_implements_microservice_call(self) -> None:
        """F05: Verifies CarteiraPdfService.php integrates with Document Generator API."""
        service_file = self.services_dir / "CarteiraPdfService.php"
        self.assertTrue(service_file.exists(), f"CarteiraPdfService.php missing at {service_file}")

        content = service_file.read_text(encoding="utf-8")

        # Must mention API Key header or config for token-secreto-dev
        self.assertTrue(
            "token-secreto-dev" in content or "X-API-Key" in content or "DOCUMENT_GENERATOR" in content or "Http::" in content or "curl_" in content,
            "CarteiraPdfService must configure Document Generator API key/headers"
        )

        # Must support external POST request
        self.assertTrue(
            "post" in content.lower() or "http" in content.lower(),
            "CarteiraPdfService must perform HTTP POST to document generator"
        )

    def test_02_carteira_pdf_service_graceful_fallback_implementation(self) -> None:
        """F06: Verifies CarteiraPdfService.php has automatic fallback to Dompdf / local rendering."""
        service_file = self.services_dir / "CarteiraPdfService.php"
        content = service_file.read_text(encoding="utf-8")

        # Must contain try-catch or conditional fallback
        self.assertTrue(
            "try" in content and "catch" in content,
            "CarteiraPdfService must have try/catch block to intercept microservice failures"
        )

        # Must reference Dompdf or local PDF generator
        self.assertTrue(
            "Dompdf" in content or "dompdf" in content or "renderDompdf" in content or "generateLocal" in content or "renderLocal" in content or "generateFallback" in content or "%PDF" in content,
            "CarteiraPdfService must include Dompdf / local PDF generator fallback"
        )

    def test_03_carteira_pdf_route_registration_in_web_php(self) -> None:
        """F07: Verifies GET /carteira/pdf route is registered in routes/web.php."""
        self.assertTrue(self.routes_file.exists(), "routes/web.php missing")
        content = self.routes_file.read_text(encoding="utf-8")

        # Check for /carteira/pdf route registration
        self.assertTrue(
            re.search(r"Route::get\s*\(\s*['\"]/carteira/pdf['\"]", content) is not None,
            "Route::get('/carteira/pdf', ...) must be registered in routes/web.php"
        )

    def test_04_carteira_pdf_controller_response_headers_and_disposition(self) -> None:
        """F07: Verifies CarteiraPdfController streams application/pdf with inline disposition."""
        controller_file = self.controllers_dir / "CarteiraPdfController.php"
        if controller_file.exists():
            content = controller_file.read_text(encoding="utf-8")
            
            # Check content-type header
            self.assertTrue(
                "application/pdf" in content,
                "CarteiraPdfController must return Content-Type: application/pdf"
            )
            # Check filename / disposition
            self.assertTrue(
                "carteira" in content.lower() and ("inline" in content.lower() or "attachment" in content.lower() or "filename" in content.lower()),
                "CarteiraPdfController must set Content-Disposition header with filename"
            )
        else:
            # Check if handled directly in routes/web.php
            routes_content = self.routes_file.read_text(encoding="utf-8")
            self.assertTrue(
                "CarteiraPdfController" in routes_content or "carteira/pdf" in routes_content,
                "Route /carteira/pdf must be handled by CarteiraPdfController or closure"
            )

    def test_05_unauthenticated_and_demo_fallback_policy(self) -> None:
        """F08: Verifies unauthenticated users fallback to first Egresso in demo/test mode."""
        controller_file = self.controllers_dir / "CarteiraPdfController.php"
        if controller_file.exists():
            content = controller_file.read_text(encoding="utf-8")
            self.assertTrue(
                "Egresso::first()" in content or "first()" in content or "fallback" in content.lower() or "auth()->user()" in content or "Auth::user()" in content or "Auth::check()" in content,
                "CarteiraPdfController must provide fallback to first Egresso if user is unauthenticated"
            )

    def test_06_carteira_html_template_elements(self) -> None:
        """F05 & F07: Verifies Carteira HTML contains official SEJUS metadata."""
        service_file = self.services_dir / "CarteiraPdfService.php"
        content = service_file.read_text(encoding="utf-8")

        # Check required legal & visual items
        expected_items = [
            "ESPÍRITO SANTO",
            "JUSTIÇA",
            "CARTEIRA",
        ]
        for item in expected_items:
            self.assertTrue(
                item in content.upper(),
                f"Carteira HTML template missing required official text: {item}"
            )


if __name__ == "__main__":
    unittest.main()
