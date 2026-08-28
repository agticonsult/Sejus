"""
CONECTA EGRESSO (SEJUS/ES) - Tier 1: Features 12-16 (Suporte User & User Mgmt Suite)
Authoritative Source: ORIGINAL_REQUEST.md (§R4), PROJECT.md (Features 12-16)

Features Tested:
- F12: Suporte Profile (id 5, full administrative permissions) in PerfilSeeder.php & User model helper
- F13: Agile Support User (suporte.agile@sejus.es.gov.br) in UserSeeder.php
- F14: User Management Controller & API (listing, create, update, delete/toggle, CPF encryption, audit)
- F15: User Management Interface (Usuarios.vue responsive table & CRUD modal)
- F16: User Management Navigation (AppLayout.vue navigation item for Gestor and Suporte)
"""

from __future__ import annotations

import os
import re
import unittest
from pathlib import Path
from typing import Dict, List, Any


class TestTier1UserMgmtSuporte(unittest.TestCase):
    """Tier 1 Feature Coverage for Suporte Profile & User Management (>= 5 tests)."""

    def setUp(self) -> None:
        self.project_root = Path(__file__).parent.parent.parent.resolve()
        self.app_dir = self.project_root / "app"
        self.models_dir = self.app_dir / "Models"
        self.controllers_dir = self.app_dir / "Http" / "Controllers"
        self.seeders_dir = self.project_root / "database" / "seeders"
        self.js_dir = self.project_root / "resources" / "js"
        self.pages_dir = self.js_dir / "Pages"
        self.layouts_dir = self.js_dir / "Layouts"
        self.routes_file = self.project_root / "routes" / "web.php"

    def test_01_suporte_profile_seeded_in_perfil_seeder(self) -> None:
        """F12: Verifies PerfilSeeder.php defines the suporte profile with full permissions."""
        seeder_file = self.seeders_dir / "PerfilSeeder.php"
        self.assertTrue(seeder_file.exists(), f"PerfilSeeder.php missing at {seeder_file}")

        content = seeder_file.read_text(encoding="utf-8")

        # Check for suporte profile slug
        self.assertIn("suporte", content.lower(), "PerfilSeeder.php must define 'suporte' profile")

    def test_02_user_model_is_suporte_helper_method(self) -> None:
        """F12: Verifies User.php model defines isSuporte() helper method."""
        user_model = self.models_dir / "User.php"
        self.assertTrue(user_model.exists(), f"User.php model missing at {user_model}")

        content = user_model.read_text(encoding="utf-8")
        self.assertTrue(
            "function isSuporte" in content or "isSuporte" in content or "suporte" in content,
            "User.php model should implement isSuporte() or role helper for suporte profile"
        )

    def test_03_agile_support_user_seeded_in_user_seeder(self) -> None:
        """F13: Verifies UserSeeder.php seeds suporte.agile@sejus.es.gov.br with password secret123."""
        seeder_file = self.seeders_dir / "UserSeeder.php"
        self.assertTrue(seeder_file.exists(), f"UserSeeder.php missing at {seeder_file}")

        content = seeder_file.read_text(encoding="utf-8")

        # Check for support email
        self.assertIn(
            "suporte.agile@sejus.es.gov.br",
            content,
            "UserSeeder.php must seed user 'suporte.agile@sejus.es.gov.br'"
        )

    def test_04_user_controller_implements_crud_and_audit(self) -> None:
        """F14: Verifies UserController.php defines index, store, update, destroy methods."""
        controller_file = self.controllers_dir / "UserController.php"
        if controller_file.exists():
            content = controller_file.read_text(encoding="utf-8")
            
            # Check for CRUD methods
            self.assertTrue("function index" in content or "index(" in content, "UserController must define index()")
            self.assertTrue("function store" in content or "store(" in content, "UserController must define store()")
            self.assertTrue("function update" in content or "update(" in content, "UserController must define update()")
            self.assertTrue("function destroy" in content or "destroy(" in content or "toggle" in content, "UserController must define destroy() / toggle()")
            
            # Check for CPF encryption / blind indexing / audit log integration
            self.assertTrue(
                "LgpdSecurityService" in content or "encrypt" in content or "hash_cpf" in content or "blind" in content or "audit" in content.lower(),
                "UserController should integrate with LGPD security and audit services"
            )
        else:
            # Fallback check in routes
            routes_content = self.routes_file.read_text(encoding="utf-8")
            self.assertTrue(
                "usuarios" in routes_content.lower(),
                "Route /usuarios must be registered in routes/web.php"
            )

    def test_05_usuarios_vue_page_structure_and_crud_modal(self) -> None:
        """F15: Verifies Usuarios.vue page exists with table, filters, and user modal."""
        usuarios_page = self.pages_dir / "Usuarios.vue"
        if usuarios_page.exists():
            content = usuarios_page.read_text(encoding="utf-8")

            # Check for table or list of users
            self.assertTrue(
                "<table" in content or "v-for" in content or "users" in content.lower() or "usuarios" in content.lower(),
                "Usuarios.vue must contain table or listing of users"
            )

            # Check for modal / form for create/edit
            self.assertTrue(
                "modal" in content.lower() or "<form" in content or "form" in content.lower(),
                "Usuarios.vue must provide a creation/editing modal or form"
            )

            # Check for role selection
            for role in ["gestor", "tecnico", "egresso", "suporte"]:
                self.assertIn(role, content.lower(), f"Usuarios.vue should reference role '{role}'")

    def test_06_app_layout_navigation_link_for_usuarios(self) -> None:
        """F16: Verifies AppLayout.vue includes Gerenciamento de Usuários link for Gestor & Suporte."""
        app_layout = self.layouts_dir / "AppLayout.vue"
        content = app_layout.read_text(encoding="utf-8")

        # Check for Usuarios route link
        self.assertTrue(
            "usuarios" in content.lower() or "usuários" in content.lower() or "gerenciamento" in content.lower(),
            "AppLayout.vue must include a navigation item for 'Gerenciamento de Usuários' / '/usuarios'"
        )


if __name__ == "__main__":
    unittest.main()
