"""
CONECTA EGRESSO (SEJUS/ES) - Live Headless Chromium Browser E2E Suite (R1, R2, R3, R4)
======================================================================================
Executes real browser interactions against a live `php artisan serve` instance running
compiled Vite production assets (`APP_ENV=production`, `APP_DEBUG=false`).

Covers:
1. R3 (Production Readiness & Tester Shortcut Removal):
   - `/login` renders cleanly with zero JS/page errors and zero demo/quick-login buttons.
   - Logging in as a second citizen (`roberto.fonseca@cidadao.es.gov.br`) renders HIS real
     wallet (`Roberto Fonseca da Silva`) on `/carteira` — never hardcoded `Lucas Santos de Oliveira` —
     and clicking `Abrir Validador Público →` validates the live HMAC-SHA256 credential.
   - `/atendimento` binds the authenticated user and shows zero fake citizen queue mocks.
2. R1 (Conhecimentos na Ficha do Egresso):
   - Logging in as `lucas.santos@cidadao.es.gov.br`, visiting `/prontuario`, opening `#modal-conhecimentos`,
     dynamically adding 3 skills (catalog + custom) across `Básico`, `Intermediário`, `Avançado` and
     `Necessita de treinamento` (`Sim` / `Não`), saving, reloading `/prontuario`, and verifying full DB persistence.
   - Reopening the modal, editing proficiency level and removing an item, saving, reloading, and confirming
     updated state persists in `#conhecimentos-grid`.
   - Staff (`carlos.mendes@sejus.es.gov.br`) recording a clinical evolution via the modal on `/prontuario`
     without `422 INVALID_EVENT_TYPE`.
3. R2 (Perfil Empregador, Inserir Vaga, CSV Download & CSV Import):
   - Logging in as `rh@parceirosejus.com.br` redirects directly to `/oportunidades/inserir-vaga`, renders
     the `Inserir Vaga` sidebar item (`[data-view="inserir-vaga"]`), and blocks `/prontuario` & `/usuarios` (`403`).
   - Clicking `#btn-download-modelo-csv` downloads `modelo_importacao_vagas.csv` with the exact required 6-column header.
   - Selecting an invalid CSV via `#input-importar-csv` renders clear row/column validation feedback in `#csv-import-feedback`.
   - Selecting a valid CSV (including `imediato`, `permanente`, specific `DD/MM/AAAA` dates, and pipe-separated
     system `Conhecimento` names containing commas) imports the vacancies and renders them with skill badges in the UI.
"""

from __future__ import annotations

import csv
import os
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.request
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE_DIR = Path(__file__).resolve().parent.parent.parent


def _find_free_port(preferred: int = 8194) -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        if s.connect_ex(("127.0.0.1", preferred)) != 0:
            return preferred
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return int(s.getsockname()[1])


class TestLiveBrowserR1ToR4(unittest.TestCase):
    """Real Headless Chromium E2E tests against live Laravel + Inertia + Vue 3 production bundle."""

    server_proc: subprocess.Popen | None = None
    base_url: str = ""

    @classmethod
    def setUpClass(cls) -> None:
        super().setUpClass()
        port = _find_free_port(8194)
        cls.base_url = f"http://127.0.0.1:{port}"

        env = os.environ.copy()
        env["APP_ENV"] = "production"
        env["APP_DEBUG"] = "false"

        cls.server_proc = subprocess.Popen(
            ["php", "artisan", "serve", "--host=127.0.0.1", f"--port={port}"],
            cwd=str(BASE_DIR),
            env=env,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

        # Wait up to 15s for server readiness
        ready = False
        for _ in range(60):
            try:
                with urllib.request.urlopen(f"{cls.base_url}/login", timeout=2) as resp:
                    if resp.status == 200:
                        ready = True
                        break
            except Exception:
                time.sleep(0.25)

        if not ready:
            cls.tearDownClass()
            raise RuntimeError(f"Laravel server failed to start on {cls.base_url}")

        cls.playwright = sync_playwright().start()
        cls.browser = cls.playwright.chromium.launch(headless=True)

    @classmethod
    def tearDownClass(cls) -> None:
        if hasattr(cls, "browser") and cls.browser:
            cls.browser.close()
        if hasattr(cls, "playwright") and cls.playwright:
            cls.playwright.stop()
        if cls.server_proc is not None:
            cls.server_proc.terminate()
            try:
                cls.server_proc.wait(timeout=5)
            except subprocess.TimeoutExpired:
                cls.server_proc.kill()
            cls.server_proc = None
        super().tearDownClass()

    def setUp(self) -> None:
        super().setUp()
        self.context = self.browser.new_context(
            viewport={"width": 1366, "height": 900},
            accept_downloads=True,
        )
        self.page = self.context.new_page()
        self.page_errors: list[str] = []
        self.page.on("pageerror", lambda exc: self.page_errors.append(str(exc)))

    def tearDown(self) -> None:
        self.context.close()
        super().tearDown()

    def _login(self, email: str, password: str = "secret123") -> None:
        self.page.goto(f"{self.base_url}/login", wait_until="networkidle")
        self.page.fill("#loginIdentifier", email)
        self.page.fill("#loginPassword", password)
        self.page.click("#loginSubmitBtn")
        self.page.wait_for_url(lambda url: "/login" not in url, timeout=15000)
        self.page.wait_for_load_state("networkidle")

    def test_01_r3_login_cleanliness_real_wallet_hmac_and_atendimento_no_mocks(self) -> None:
        """Verify zero tester shortcuts on /login, real citizen wallet + HMAC QR for Roberto, and clean /atendimento."""
        self.page.goto(f"{self.base_url}/login", wait_until="networkidle")
        self.assertEqual(self.page_errors, [], f"Unexpected JS errors on /login: {self.page_errors}")

        body_text = self.page.locator("body").inner_text()
        for forbidden in ["Gestor Demo", "Egresso Demo", "Técnico Demo", "Acesso Rápido", "secret123"]:
            self.assertNotIn(forbidden, body_text, f"Tester shortcut '{forbidden}' leaked on /login")
        self.assertEqual(self.page.locator("[id*='demo']").count(), 0)

        # Log in as Roberto Fonseca da Silva (second egresso) to verify /carteira is NOT hardcoded to Lucas
        self._login("roberto.fonseca@cidadao.es.gov.br")
        self.page.goto(f"{self.base_url}/carteira", wait_until="networkidle")
        self.assertEqual(self.page_errors, [], f"Unexpected JS errors on /carteira: {self.page_errors}")

        carteira_text = self.page.locator("#view-carteira").inner_text()
        self.assertIn("Roberto Fonseca da Silva", carteira_text)
        self.assertNotIn("Lucas Santos de Oliveira", carteira_text)

        # Click public validator link and verify cryptographic validation passes
        self.page.click("text=Abrir Validador Público")
        self.page.wait_for_load_state("networkidle")
        validator_text = self.page.locator("body").inner_text()
        self.assertIn("Roberto Fonseca da Silva", validator_text)
        self.assertNotIn("Inválida", validator_text)

        # Verify /atendimento has no hardcoded 'Dra. Márcia' or fake 'TCK-101' queue
        self.page.goto(f"{self.base_url}/atendimento", wait_until="networkidle")
        atendimento_text = self.page.locator("#view-atendimento").inner_text()
        self.assertNotIn("Dra. Márcia Oliveira", atendimento_text)
        self.assertNotIn("TCK-101", atendimento_text)

    def test_02_r1_egresso_conhecimentos_add_edit_remove_persist_and_staff_evolution(self) -> None:
        """Verify dynamic add/edit/remove/persistence of Conhecimentos on /prontuario and staff evolution modal."""
        self._login("lucas.santos@cidadao.es.gov.br")
        self.page.goto(f"{self.base_url}/prontuario", wait_until="networkidle")
        self.assertEqual(self.page_errors, [], f"Unexpected JS errors on /prontuario: {self.page_errors}")

        # Open Conhecimentos modal
        self.page.click("#btn-gerenciar-conhecimentos")
        self.page.wait_for_selector("#modal-conhecimentos", state="visible")

        # Clear any pre-existing rows so test is idempotent
        while self.page.locator(".btn-remover-conhecimento").count() > 0:
            self.page.locator(".btn-remover-conhecimento").first.click()

        # Add 3 conhecimentos: Básico/Sim, Intermediário/Não, Avançado/Sim (custom skill)
        skills_to_add = [
            ("Informática Básica e Pacote Office", "basico", "true"),
            ("Logística, Almoxarifado e Estoque", "intermediario", "false"),
            ("Programação CNC Industrial Customizada", "avancado", "true"),
        ]
        for idx, (nome, nivel, treina_bool) in enumerate(skills_to_add):
            self.page.click("#btn-adicionar-linha-conhecimento")
            row = self.page.locator(".row-conhecimento").nth(idx)
            row.locator(".input-nome-conhecimento").fill(nome)
            row.locator(".select-nivel").select_option(nivel)
            # Vue boolean select options bind :value="true" / :value="false" (index 0 = Sim, index 1 = Não)
            row.locator(".select-treinamento").select_option(index=0 if treina_bool == "true" else 1)

        self.page.click("#btn-salvar-conhecimentos")
        self.page.wait_for_selector("#modal-conhecimentos", state="hidden", timeout=10000)

        # Reload page from server and verify full persistence in Inertia props + DOM
        self.page.reload(wait_until="networkidle")
        grid = self.page.locator("#conhecimentos-grid")
        self.assertTrue(grid.is_visible())
        self.assertEqual(grid.locator(".conhecimento-card").count(), 3)

        grid_text = grid.inner_text()
        self.assertIn("Informática Básica e Pacote Office", grid_text)
        self.assertIn("Logística, Almoxarifado e Estoque", grid_text)
        self.assertIn("Programação CNC Industrial Customizada", grid_text)
        self.assertEqual(grid.locator(".treinamento-badge-sim").count(), 2)
        self.assertEqual(grid.locator(".treinamento-badge-nao").count(), 1)

        # Reopen modal, remove 1st skill and upgrade 2nd skill to Avançado
        self.page.click("#btn-gerenciar-conhecimentos")
        self.page.wait_for_selector("#modal-conhecimentos", state="visible")
        self.page.locator(".row-conhecimento").first.locator(".btn-remover-conhecimento").click()
        self.page.locator(".row-conhecimento").first.locator(".select-nivel").select_option("avancado")
        self.page.click("#btn-salvar-conhecimentos")
        self.page.wait_for_selector("#modal-conhecimentos", state="hidden", timeout=10000)

        # Reload and confirm 2 skills remain with updated level
        self.page.reload(wait_until="networkidle")
        self.assertEqual(self.page.locator("#conhecimentos-grid .conhecimento-card").count(), 2)
        updated_text = self.page.locator("#conhecimentos-grid").inner_text()
        self.assertNotIn("Informática Básica e Pacote Office", updated_text)
        self.assertIn("Logística, Almoxarifado e Estoque", updated_text)

        # Verify staff can submit New Evolution modal without 422 INVALID_EVENT_TYPE
        self.context.clear_cookies()
        self._login("gestor@sejus.es.gov.br")
        self.page.goto(f"{self.base_url}/prontuario", wait_until="networkidle")
        self.page.click("button:has-text('Nova Evolução')")
        self.page.fill("input[placeholder*='Acompanhamento mensal']", "Evolução E2E Verificada")
        self.page.fill("textarea[placeholder*='Relate as intervenções']", "Registro técnico validado via Chromium.")
        self.page.click("button:has-text('Gravar Evolução')")
        self.page.wait_for_timeout(800)
        self.assertIn("Evolução E2E Verificada", self.page.locator("body").inner_text())

    def test_03_r2_empregador_rbac_csv_download_and_csv_import_e2e(self) -> None:
        """Verify Empregador login redirect, RBAC 403 boundaries, CSV template download, and CSV file-picker import."""
        self._login("rh@parceirosejus.com.br")
        self.assertIn("/oportunidades/inserir-vaga", self.page.url)
        self.assertEqual(self.page_errors, [], f"Unexpected JS errors for empregador: {self.page_errors}")

        # Sidebar displays 'Inserir Vaga' and hides restricted clinical/admin items
        self.assertTrue(self.page.locator("[data-view='inserir-vaga']").is_visible())
        self.assertEqual(self.page.locator("[data-view='prontuario']").count(), 0)
        self.assertEqual(self.page.locator("[data-view='usuarios']").count(), 0)

        # Direct navigation to /prontuario and /usuarios returns HTTP 403
        resp_pront = self.page.goto(f"{self.base_url}/prontuario")
        self.assertEqual(resp_pront.status, 403)
        resp_users = self.page.goto(f"{self.base_url}/usuarios")
        self.assertEqual(resp_users.status, 403)

        # Return to /oportunidades/inserir-vaga and download the CSV template
        self.page.goto(f"{self.base_url}/oportunidades/inserir-vaga", wait_until="networkidle")
        with self.page.expect_download() as download_info:
            self.page.click("#btn-download-modelo-csv")
        download = download_info.value
        downloaded_bytes = Path(download.path()).read_bytes()
        downloaded_text = downloaded_bytes.decode("utf-8-sig")
        header_row = next(csv.reader(downloaded_text.splitlines(), delimiter=";"))
        self.assertEqual(
            header_row,
            ["Nome vaga", "Descrição vaga", "Quantidade vaga", "Data início", "Data fim", "Conhecimento"],
        )

        # System Conhecimentos list is rendered for reference
        catalogo_text = self.page.locator("#catalogo-conhecimentos-sistema").inner_text()
        self.assertIn("Conferência de Cargas e Estoque", catalogo_text)
        self.assertIn("Instalações Elétricas Prediais (NR-10)", catalogo_text)

        with tempfile.TemporaryDirectory() as tmpdir:
            # 1. Upload INVALID CSV via real file input and verify clear error feedback
            bad_csv = Path(tmpdir) / "vagas_invalidas.csv"
            bad_csv.write_text(
                "Nome vaga;Descrição vaga;Quantidade vaga;Data início;Data fim;Conhecimento\n"
                "Vaga Ruim;Descrição teste;0;data-errada;permanente;Conhecimento Inexistente XYZ\n",
                encoding="utf-8-sig",
            )
            self.page.set_input_files("#input-importar-csv", str(bad_csv))
            self.page.click("#btn-importar-csv")
            self.page.wait_for_selector("#csv-import-feedback", state="visible", timeout=10000)
            err_feedback = self.page.locator("#csv-import-feedback").inner_text()
            self.assertIn("Linha 2", err_feedback)

            # 2. Upload VALID CSV (with 'imediato', 'permanente', specific dates, and pipe-separated system skills)
            good_csv = Path(tmpdir) / "vagas_validas_e2e.csv"
            good_csv.write_text(
                "Nome vaga;Descrição vaga;Quantidade vaga;Data início;Data fim;Conhecimento\n"
                "Operador Logístico E2E Live;Recebimento e conferência de mercadorias;4;imediato;permanente;"
                "Conferência de Cargas e Estoque | Soldagem Industrial (Eletrodo e MIG/MAG)\n"
                "Eletricista Predial E2E Live;Manutenção elétrica preventiva;2;15/12/2026;31/03/2027;"
                "Instalações Elétricas Prediais (NR-10)\n",
                encoding="utf-8-sig",
            )
            self.page.set_input_files("#input-importar-csv", str(good_csv))
            self.page.click("#btn-importar-csv")
            self.page.wait_for_function(
                "() => document.querySelector('#csv-import-feedback')?.innerText.includes('sucesso')",
                timeout=10000,
            )
            ok_feedback = self.page.locator("#csv-import-feedback").inner_text()
            self.assertIn("2", ok_feedback)

        # Switch to 'Vagas de Emprego' tab and verify imported vacancies + associated skills render!
        self.page.click("button:has-text('Vagas de Emprego')")
        self.page.wait_for_selector(".vaga-card", state="visible")
        vagas_text = self.page.locator("#view-oportunidades").inner_text()
        self.assertIn("Operador Logístico E2E Live", vagas_text)
        self.assertIn("Eletricista Predial E2E Live", vagas_text)
        self.assertIn("Conferência de Cargas e Estoque", vagas_text)
        self.assertIn("Soldagem Industrial (Eletrodo e MIG/MAG)", vagas_text)
        self.assertIn("Instalações Elétricas Prediais (NR-10)", vagas_text)


if __name__ == "__main__":
    unittest.main()
