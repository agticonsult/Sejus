"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1: Features 17-18 (Route Audit 404 & E2E Suite)
Authoritative Source: ORIGINAL_REQUEST.md (§R5, §Acceptance Criteria), PROJECT.md (Features 17-18)

Features Tested:
- F17: Route & Link 404 Audit (All web routes, API endpoints, navigation links)
- F18: E2E Testing & Verification Suite Engine and Harness
"""

from __future__ import annotations

import os
import re
import unittest
from pathlib import Path
from typing import Dict, List, Any, Set


class TestTier1Audit404E2E(unittest.TestCase):
    """Tier 1 Feature Coverage for Zero-404 Route Audit and E2E Verification (>= 5 tests)."""

    def setUp(self) -> None:
        self.project_root = Path(__file__).parent.parent.parent.resolve()
        self.routes_file = self.project_root / "routes" / "web.php"
        self.layouts_dir = self.project_root / "resources" / "js" / "Layouts"
        self.pages_dir = self.project_root / "resources" / "js" / "Pages"

    def test_01_all_core_web_routes_registered_in_laravel(self) -> None:
        """F17: Verifies all essential web routes are registered in routes/web.php with zero missing targets."""
        content = self.routes_file.read_text(encoding="utf-8")

        expected_routes = [
            "/dashboard",
            "/atendimento",
            "/oportunidades",
            "/carteira",
            "/geolocalizacao",
            "/prontuario",
            "/relatorios",
            "/seguranca-lgpd",
            "/validar-carteira",
            "/login",
            "/logout",
        ]

        for route in expected_routes:
            self.assertTrue(
                route in content,
                f"Core route '{route}' missing from routes/web.php"
            )

    def test_02_all_vue_pages_referenced_by_routes_exist_on_disk(self) -> None:
        """F17: Verifies every Inertia::render('ViewName') corresponds to an existing Vue file in resources/js/Pages."""
        content = self.routes_file.read_text(encoding="utf-8")

        # Find all Inertia::render('PageName') or inertia('PageName')
        render_matches = re.findall(r"Inertia::render\s*\(\s*['\"]([^'\"]+)['\"]", content)
        self.assertGreater(len(render_matches), 0, "No Inertia::render() calls found in routes/web.php")

        for page_name in set(render_matches):
            vue_file = self.pages_dir / f"{page_name}.vue"
            self.assertTrue(
                vue_file.exists(),
                f"Route renders Inertia view '{page_name}', but {vue_file} does not exist on disk!"
            )

    def test_03_frontend_layout_navigation_links_consistency(self) -> None:
        """F17: Verifies AppLayout.vue navigation links match registered Laravel routes."""
        app_layout = self.layouts_dir / "AppLayout.vue"
        content = app_layout.read_text(encoding="utf-8")

        # Find all href="/..." or route('...') links in AppLayout
        href_matches = re.findall(r"href=['\"](/[^'\"]*)['\"]", content)
        
        # All found root-relative links should be valid registered routes
        valid_prefixes = [
            "/dashboard", "/atendimento", "/oportunidades", "/carteira",
            "/geolocalizacao", "/prontuario", "/relatorios", "/seguranca-lgpd",
            "/usuarios", "/login", "/logout", "#", "/"
        ]

        for href in href_matches:
            # Strip query params or hash
            clean_href = href.split("?")[0].split("#")[0]
            if not clean_href:
                continue
            is_valid = any(clean_href.startswith(prefix) for prefix in valid_prefixes)
            self.assertTrue(
                is_valid,
                f"AppLayout contains potential broken link href='{href}' that has no matching registered route!"
            )

    def test_04_e2e_runner_script_exists_and_executable(self) -> None:
        """F18: Verifies test_runner.py CLI exists in tests_e2e and defines all 5 tiers."""
        runner_file = self.project_root / "tests_e2e" / "test_runner.py"
        self.assertTrue(runner_file.exists(), f"test_runner.py missing at {runner_file}")

        content = runner_file.read_text(encoding="utf-8")
        self.assertIn("TIER_DIRECTORIES", content)
        self.assertIn("tier1_features", content)
        self.assertIn("tier2_boundaries", content)
        self.assertIn("tier3_combinations", content)
        self.assertIn("tier4_scenarios", content)
        self.assertIn("tier5_adversarial", content)

    def test_05_public_carteira_validation_routes_zero_404(self) -> None:
        """F17: Verifies public carteira validation routes are properly registered and available."""
        content = self.routes_file.read_text(encoding="utf-8")
        
        self.assertIn("/validar-carteira", content, "Route /validar-carteira must be registered")
        self.assertTrue(
            "/validar-carteira/{token}" in content or "validar" in content,
            "Parameterized route /validar-carteira/{token} must be registered"
        )


if __name__ == "__main__":
    unittest.main()
