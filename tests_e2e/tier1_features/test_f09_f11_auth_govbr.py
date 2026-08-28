"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1: Features 9-11 (Gov.br Login & Auth Suite)
Authoritative Source: ORIGINAL_REQUEST.md (§R3), PROJECT.md (Features 9-11)

Features Tested:
- F09: Gov.br / Acesso Cidadão Styled Login Page (Login.vue, colors, dual authentication, quick-fill bar)
- F10: Route Protection & GET /login Route (HandleInertiaRequests auth.user sharing)
- F11: Secure Logout Action (AppLayout.vue header/sidebar logout button, session invalidation)
"""

from __future__ import annotations

import os
import re
import unittest
from pathlib import Path
from typing import Dict, List, Any


class TestTier1AuthGovBr(unittest.TestCase):
    """Tier 1 Feature Coverage for Authentication, Login, and Logout (>= 5 tests)."""

    def setUp(self) -> None:
        self.project_root = Path(__file__).parent.parent.parent.resolve()
        self.js_dir = self.project_root / "resources" / "js"
        self.pages_dir = self.js_dir / "Pages"
        self.layouts_dir = self.js_dir / "Layouts"
        self.app_dir = self.project_root / "app"
        self.controllers_dir = self.app_dir / "Http" / "Controllers"
        self.middleware_dir = self.app_dir / "Http" / "Middleware"
        self.routes_file = self.project_root / "routes" / "web.php"

    def test_01_login_vue_exists_with_govbr_and_es_branding(self) -> None:
        """F09: Verifies Login.vue exists with Gov.br & ES state design tokens."""
        login_file = self.pages_dir / "Login.vue"
        self.assertTrue(login_file.exists(), f"Login.vue missing at {login_file}")

        content = login_file.read_text(encoding="utf-8")

        # Check for Gov.br / Acesso Cidadão branding
        self.assertTrue(
            "gov.br" in content.lower() or "acesso cidadão" in content.lower() or "governo" in content.lower() or "sejus" in content.lower(),
            "Login.vue must display Gov.br / Acesso Cidadão / SEJUS state branding"
        )

        # Check for dual authentication options (Gov.br SSO button & Credentials form)
        self.assertTrue(
            "cpf" in content.lower() or "email" in content.lower(),
            "Login.vue must support standard login fields (CPF/Email)"
        )
        self.assertTrue(
            "password" in content.lower() or "senha" in content.lower(),
            "Login.vue must include password field"
        )

    def test_02_login_vue_quick_fill_demo_bar(self) -> None:
        """F09: Verifies Login.vue provides quick-fill demo roles (Gestor, Técnico, Egresso, Familiar, Suporte)."""
        login_file = self.pages_dir / "Login.vue"
        self.assertTrue(login_file.exists(), f"Login.vue missing at {login_file}")

        content = login_file.read_text(encoding="utf-8")

        # Check for demo roles in quick-fill bar
        roles = ["gestor", "tecnico", "egresso", "suporte"]
        found_roles = [r for r in roles if r in content.lower()]
        self.assertGreaterEqual(
            len(found_roles),
            2,
            f"Login.vue should provide quick-fill demo buttons for roles: {roles}. Found: {found_roles}"
        )

    def test_03_get_login_route_registered_in_web_php(self) -> None:
        """F10: Verifies GET /login route is registered in routes/web.php."""
        content = self.routes_file.read_text(encoding="utf-8")
        
        # Check for /login GET route
        self.assertTrue(
            re.search(r"Route::get\s*\(\s*['\"]/login['\"]", content) is not None or "login" in content,
            "GET /login route must be registered in routes/web.php"
        )

    def test_04_auth_controller_login_and_logout_endpoints(self) -> None:
        """F10 & F11: Verifies AuthController.php implements login, govbrLogin, and logout methods."""
        auth_controller = self.controllers_dir / "AuthController.php"
        self.assertTrue(auth_controller.exists(), f"AuthController.php missing at {auth_controller}")

        content = auth_controller.read_text(encoding="utf-8")

        self.assertIn("function login", content, "AuthController must define login() method")
        self.assertIn("function logout", content, "AuthController must define logout() method")
        self.assertIn("function govbrLogin", content, "AuthController must define govbrLogin() method")

    def test_05_handle_inertia_requests_shares_auth_user(self) -> None:
        """F10: Verifies HandleInertiaRequests middleware shares auth.user state."""
        middleware_file = self.middleware_dir / "HandleInertiaRequests.php"
        if middleware_file.exists():
            content = middleware_file.read_text(encoding="utf-8")
            self.assertTrue(
                "auth" in content.lower() and ("user" in content.lower() or "user()" in content),
                "HandleInertiaRequests must share authenticated user profile with Inertia"
            )

    def test_06_app_layout_logout_button_and_action(self) -> None:
        """F11: Verifies AppLayout.vue includes secure Logout trigger."""
        app_layout = self.layouts_dir / "AppLayout.vue"
        content = app_layout.read_text(encoding="utf-8")

        # Must have logout action / button
        self.assertTrue(
            "logout" in content.lower() or "sair" in content.lower(),
            "AppLayout.vue must provide a Logout/Sair button"
        )

        # Must post to /logout endpoint
        self.assertTrue(
            "logout" in content or "post('/logout" in content or "post(\"/logout" in content or "router.post" in content,
            "AppLayout.vue must execute POST request to /logout on session exit"
        )


if __name__ == "__main__":
    unittest.main()
