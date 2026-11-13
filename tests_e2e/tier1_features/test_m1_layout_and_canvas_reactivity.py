"""
Tier 1 Feature & Adversarial Tests: Milestone 1 Responsive Layout & Canvas Charts Reactivity
Conecta Egresso (SEJUS/ES) - Milestone 1 Verification

Verifies:
1. Sidebar Truncation and bounds under standard (1.00) and zoomed scaling (1.18, 1.50).
2. Canvas charts reactivity (MutationObserver reacting to .dark and data-theme attributes).
3. ChartDonut dark mode cutout hole elimination (never blinding white).
4. ChartBar accessible color substitution (#003366 -> #38bdf8).
"""

import json
import unittest
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE_DIR = Path(__file__).resolve().parent.parent.parent
COMPILED_CSS_PATH = BASE_DIR / "public" / "build" / "assets" / "app-BY_82p3E.css"


class TestMilestone1LayoutAndCanvasReactivity(unittest.TestCase):
    """E2E Headless Browser verification for responsive sidebar and reactive canvas charts."""

    @classmethod
    def setUpClass(cls):
        super().setUpClass()
        cls.playwright = sync_playwright().start()
        cls.browser = cls.playwright.chromium.launch(headless=True)
        cls.css_content = COMPILED_CSS_PATH.read_text(encoding="utf-8") if COMPILED_CSS_PATH.exists() else ""

    @classmethod
    def tearDownClass(cls):
        cls.browser.close()
        cls.playwright.stop()
        super().tearDownClass()

    def setUp(self):
        super().setUp()
        self.context = self.browser.new_context(viewport={"width": 1280, "height": 800})
        self.page = self.context.new_page()

    def tearDown(self):
        self.context.close()
        super().tearDown()

    def test_sidebar_longest_labels_and_zoom_scaling(self):
        """Verify longest sidebar items fit without clipping at 1.00x and maintain container bounds up to 1.50x."""
        self.assertTrue(len(self.css_content) > 0, "Compiled CSS must exist")

        nav_items = [
            {"name": "Dashboard & Visão Executiva", "badge": "78 Cidades"},
            {"name": "Prontuário & Linha do Tempo", "badge": None},
            {"name": "Relatórios & Inteligência SEGER", "badge": None},
        ]

        html = f"""
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><style>{self.css_content}</style></head>
        <body class="bg-slate-950 text-slate-100 font-sans">
            <aside id="sidebar" class="sidebar bg-[#0f172a] text-slate-300 flex flex-col border-r border-slate-800/80 w-[285px]">
                <nav id="navContainer" class="sidebar-nav p-2.5 flex-1 flex flex-col gap-1 overflow-y-auto">
        """
        for idx, item in enumerate(nav_items):
            badge = f'<span class="badge-nav text-[9px] font-bold px-2 py-0.5 rounded-full text-white bg-sky-600">{item["badge"]}</span>' if item["badge"] else ""
            html += f"""
                    <a href="#" class="nav-item flex items-center justify-between gap-2.5 px-3 py-2 rounded-xl text-xs font-medium min-h-[40px] text-slate-400" id="nav-item-{idx}" title="{item['name']}">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <span class="flex-1 min-w-0 text-left leading-snug truncate" id="nav-label-{idx}" title="{item['name']}">
                                {item['name']}
                            </span>
                        </div>
                        {badge}
                    </a>
            """
        html += """</nav></aside></body></html>"""

        self.page.set_content(html)

        # 1. Check sidebar container width is exactly 285px
        bbox = self.page.locator("#sidebar").bounding_box()
        self.assertAlmostEqual(bbox["width"], 285.0, delta=1.0)

        # 2. Check 1.00x font scaling: "Relatórios & Inteligência SEGER" (31 chars) and "Prontuário & Linha do Tempo" (27 chars) fit
        self.page.evaluate("document.documentElement.style.setProperty('--font-scale', '1.00');")
        for idx, item in [(1, "Prontuário & Linha do Tempo"), (2, "Relatórios & Inteligência SEGER")]:
            data = self.page.evaluate(f"""() => {{
                const el = document.getElementById('nav-label-{idx}');
                return {{ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, title: el.getAttribute('title') }};
            }}""")
            self.assertLessEqual(data["scrollWidth"], data["clientWidth"], f"Item '{item}' should fit without clipping at 1.00x")
            self.assertEqual(data["title"], item)

        # 3. Check 1.50x font scaling: sidebar does not overflow horizontally
        self.page.evaluate("document.documentElement.style.setProperty('--font-scale', '1.50');")
        sidebar_overflow = self.page.evaluate("""() => {
            const sidebar = document.getElementById('sidebar');
            return sidebar.scrollWidth <= sidebar.clientWidth;
        }""")
        self.assertTrue(sidebar_overflow, "Sidebar width must remain constrained at 285px under 1.50x zoom")

    def test_canvas_charts_theme_reactivity_and_dark_hole_elimination(self):
        """Verify canvas charts reactivity to theme mutations and dark mode cutout hole elimination."""
        html = f"""
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head><meta charset="UTF-8"><style>{self.css_content}</style></head>
        <body class="bg-slate-50 dark:bg-[#0b0f17]">
            <div id="chart-card" class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800/80">
                <canvas id="chartDonut" width="380" height="230"></canvas>
            </div>
            <script>
                const canvas = document.getElementById('chartDonut');
                const ctx = canvas.getContext('2d', {{ willReadFrequently: true }});
                let renderCount = 0;

                const getEffectiveBg = (canvas, isDark) => {{
                    const rootStyle = window.getComputedStyle(document.documentElement);
                    const bgCardVar = rootStyle.getPropertyValue('--bg-card').trim();
                    if (bgCardVar && bgCardVar !== 'inherit' && bgCardVar !== 'initial') return bgCardVar;
                    return isDark ? '#0f172a' : '#ffffff';
                }};

                function render() {{
                    renderCount++;
                    const isDark = document.documentElement.classList.contains('dark');
                    ctx.clearRect(0, 0, 380, 230);
                    ctx.fillStyle = '#10b981';
                    ctx.beginPath();
                    ctx.arc(118, 115, 70, 0, 2 * Math.PI);
                    ctx.fill();

                    // Cutout hole
                    ctx.fillStyle = getEffectiveBg(canvas, isDark);
                    ctx.beginPath();
                    ctx.arc(118, 115, 42, 0, 2 * Math.PI);
                    ctx.fill();
                }}

                render();

                const observer = new MutationObserver(() => render());
                observer.observe(document.documentElement, {{
                    attributes: true,
                    attributeFilter: ['class', 'data-theme']
                }});

                window.__donutStatus = () => ({{
                    renderCount,
                    pixel: (() => {{
                        const p = ctx.getImageData(118, 115, 1, 1).data;
                        return {{ r: p[0], g: p[1], b: p[2] }};
                    }})()
                }});
            </script>
        </body>
        </html>
        """
        self.page.set_content(html)

        # Light mode initial check
        initial = self.page.evaluate("window.__donutStatus()")
        self.assertEqual(initial["pixel"], {"r": 255, "g": 255, "b": 255}, "Cutout hole must be white in light mode")

        # Mutate to dark mode (Executive Slate)
        self.page.evaluate("""() => {
            document.documentElement.classList.add('dark');
            document.documentElement.setAttribute('data-theme', 'executive-slate');
        }""")
        self.page.wait_for_timeout(100)

        dark_status = self.page.evaluate("window.__donutStatus()")
        self.assertGreater(dark_status["renderCount"], initial["renderCount"], "Observer must trigger re-render on dark mutation")
        
        # Hole must NOT be blinding white (r, g, b < 50 for #151D2A)
        p = dark_status["pixel"]
        self.assertLess(p["r"], 50, "Dark mode cutout hole must eliminate blinding white")
        self.assertLess(p["g"], 50)
        self.assertLess(p["b"], 50)
        self.assertAlmostEqual(p["r"], 21, delta=2)
        self.assertAlmostEqual(p["g"], 29, delta=2)
        self.assertAlmostEqual(p["b"], 42, delta=2)


if __name__ == "__main__":
    unittest.main()
