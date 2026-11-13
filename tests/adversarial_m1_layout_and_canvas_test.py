"""
Empirical Adversarial Test Suite for Milestone 1: Responsive Layout & Canvas Reactivity
Conecta Egresso (SEJUS/ES) - Challenger Role Verification

Focus Areas:
1. Sidebar Truncation Stress Test (w-[285px] vs standard/zoomed font scaling).
2. Canvas Charts Reactivity Stress Test (ChartBar.vue and ChartDonut.vue MutationObserver + cutout hole).
3. Rapid Mutation and Edge Case Resiliency.
"""

import os
import sys
import json
import time
from pathlib import Path
from playwright.sync_api import sync_playwright

PROJECT_ROOT = Path(__file__).resolve().parent.parent
COMPILED_CSS_PATH = PROJECT_ROOT / "public" / "build" / "assets" / "app-BY_82p3E.css"
RAW_CSS_PATH = PROJECT_ROOT / "resources" / "css" / "app.css"

results = {
    "passed": 0,
    "failed": 0,
    "tests": [],
}

def record_test(name: str, passed: bool, details: str = ""):
    if passed:
        results["passed"] += 1
        status = "PASS"
        print(f"  [PASS] {name}")
    else:
        results["failed"] += 1
        status = "FAIL"
        print(f"  [FAIL] {name} -> {details}")
    results["tests"].append({
        "name": name,
        "status": status,
        "details": details
    })


def run_sidebar_truncation_tests(page):
    print("\n=======================================================")
    print("1. SIDEBAR TRUNCATION & RESPONSIVE LAYOUT STRESS TEST")
    print("=======================================================")

    # Read compiled CSS
    css_content = COMPILED_CSS_PATH.read_text(encoding="utf-8")

    # Navigation items from AppLayout.vue
    nav_items = [
        {"name": "Dashboard & Visão Executiva", "badge": "78 Cidades", "badgeClass": "bg-sky-600"},
        {"name": "Atendimentos & Vídeo WebRTC", "badge": "3 ao vivo", "badgeClass": "bg-emerald-600"},
        {"name": "Oportunidades & Vagas", "badge": "42 Vagas", "badgeClass": "bg-slate-700"},
        {"name": "Carteira Digital & QR Code", "badge": "HMAC OK", "badgeClass": "bg-sky-700"},
        {"name": "Mapeamento Territorial", "badge": None},
        {"name": "Prontuário & Linha do Tempo", "badge": None},
        {"name": "Relatórios & Inteligência SEGER", "badge": None},
        {"name": "Gerenciamento de Usuários", "badge": None},
        {"name": "Segurança & LGPD", "badge": None},
    ]

    html_template = f"""
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <style>
            {css_content}
        </style>
    </head>
    <body class="bg-slate-950 text-slate-100 font-sans">
        <div id="test-wrapper" class="flex">
            <aside id="sidebar" class="sidebar bg-[#0f172a] text-slate-300 flex flex-col border-r border-slate-800/80 flex-shrink-0 z-30 w-[285px]">
                <nav id="navContainer" class="sidebar-nav p-2.5 flex-1 flex flex-col gap-1 overflow-y-auto">
    """

    for idx, item in enumerate(nav_items):
        badge_html = f'<span class="badge-nav flex-shrink-0 ml-auto text-[9px] font-bold px-2 py-0.5 rounded-full text-white shadow-2xs whitespace-nowrap {item["badgeClass"]}">{item["badge"]}</span>' if item["badge"] else ""
        html_template += f"""
                    <a href="#" class="nav-item flex items-center justify-between gap-2.5 px-3 py-2 rounded-xl text-xs font-medium min-h-[40px] text-slate-400" id="nav-item-{idx}" title="{item['name']}">
                        <div class="flex items-center gap-2.5 min-w-0 flex-1">
                            <svg class="w-4 h-4 flex-shrink-0 stroke-[1.75]" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect width="18" height="18" x="3" y="3" rx="2"/></svg>
                            <span class="flex-1 min-w-0 text-left leading-snug truncate" id="nav-label-{idx}" title="{item['name']}">
                                {item['name']}
                            </span>
                        </div>
                        {badge_html}
                    </a>
        """

    html_template += """
                </nav>
            </aside>
            <main class="flex-1 p-6">
                <h1>Main Content</h1>
            </main>
        </div>
    </body>
    </html>
    """

    page.set_content(html_template)

    # 1.1 Verify Sidebar Container width
    sidebar_bbox = page.locator("#sidebar").bounding_box()
    record_test(
        "Sidebar width is strictly 285px",
        abs(sidebar_bbox["width"] - 285.0) < 1.0,
        f"Actual width: {sidebar_bbox['width']}px"
    )

    # 1.2 Test text fit at default font scaling (--font-scale: 1.00)
    print("\n  --- Evaluating at --font-scale: 1.00 ---")
    page.evaluate("document.documentElement.style.setProperty('--font-scale', '1.00');")
    page.wait_for_timeout(100)

    for idx, item in enumerate(nav_items):
        data = page.evaluate(f"""() => {{
            const el = document.getElementById('nav-label-{idx}');
            const parent = document.getElementById('nav-item-{idx}');
            return {{
                name: el.innerText.trim(),
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
                isTruncated: el.scrollWidth > el.clientWidth,
                hasTitle: el.getAttribute('title') === el.innerText.trim(),
                parentHasTitle: parent.getAttribute('title') === el.innerText.trim()
            }};
        }}""")

        # Longest items: "Relatórios & Inteligência SEGER" (31 chars) and "Prontuário & Linha do Tempo" (27 chars)
        if item["name"] in ["Relatórios & Inteligência SEGER", "Prontuário & Linha do Tempo"]:
            record_test(
                f"Item '{item['name']}' fits without clipping at 1.00x scale",
                not data["isTruncated"],
                f"scrollWidth={data['scrollWidth']}px vs clientWidth={data['clientWidth']}px (diff={data['scrollWidth'] - data['clientWidth']})"
            )
        else:
            status_desc = "truncated with title" if data["isTruncated"] else "fits fully"
            print(f"    Item '{item['name']}': scrollWidth={data['scrollWidth']}, clientWidth={data['clientWidth']} -> {status_desc}")

        # Check title attribute accessibility tooltip
        record_test(
            f"Item '{item['name']}' has exact title tooltip for discovery",
            data["hasTitle"] and data["parentHasTitle"],
            f"hasTitle={data['hasTitle']}, parentHasTitle={data['parentHasTitle']}"
        )

    # 1.3 Test behavior under zoom font scaling: --font-scale: 1.18
    print("\n  --- Evaluating at --font-scale: 1.18 (+18% Zoom) ---")
    page.evaluate("document.documentElement.style.setProperty('--font-scale', '1.18');")
    page.wait_for_timeout(100)

    for idx, item in enumerate(nav_items):
        data = page.evaluate(f"""() => {{
            const el = document.getElementById('nav-label-{idx}');
            const sidebar = document.getElementById('sidebar');
            return {{
                name: el.innerText.trim(),
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
                isTruncated: el.scrollWidth > el.clientWidth,
                sidebarScrollWidth: sidebar.scrollWidth,
                sidebarClientWidth: sidebar.clientWidth
            }};
        }}""")
        # Verify sidebar itself does not blow out horizontally
        record_test(
            f"Item '{item['name']}' at 1.18x does not cause sidebar container blowout",
            data["sidebarScrollWidth"] <= data["sidebarClientWidth"],
            f"sidebarScrollWidth={data['sidebarScrollWidth']} vs sidebarClientWidth={data['sidebarClientWidth']}"
        )
        print(f"    [1.18x] '{item['name']}': scrollWidth={data['scrollWidth']}, clientWidth={data['clientWidth']} (truncated={data['isTruncated']})")

    # 1.4 Test behavior under maximum zoom font scaling: --font-scale: 1.50
    print("\n  --- Evaluating at --font-scale: 1.50 (+50% Zoom - WCAG Maximum) ---")
    page.evaluate("document.documentElement.style.setProperty('--font-scale', '1.50');")
    page.wait_for_timeout(100)

    for idx, item in enumerate(nav_items):
        data = page.evaluate(f"""() => {{
            const el = document.getElementById('nav-label-{idx}');
            const sidebar = document.getElementById('sidebar');
            const style = window.getComputedStyle(el);
            return {{
                name: el.innerText.trim(),
                scrollWidth: el.scrollWidth,
                clientWidth: el.clientWidth,
                isTruncated: el.scrollWidth > el.clientWidth,
                overflow: style.overflow,
                textOverflow: style.textOverflow,
                sidebarScrollWidth: sidebar.scrollWidth,
                sidebarClientWidth: sidebar.clientWidth
            }};
        }}""")
        # At 1.50x, verify graceful truncation with ellipsis and zero container blowout
        record_test(
            f"Item '{item['name']}' at 1.50x has active text-overflow ellipsis protection",
            data["textOverflow"] == "ellipsis" and data["overflow"] == "hidden",
            f"overflow={data['overflow']}, textOverflow={data['textOverflow']}"
        )
        record_test(
            f"Sidebar container bounds preserved at 1.50x (no horizontal overflow)",
            data["sidebarScrollWidth"] <= data["sidebarClientWidth"],
            f"sidebarScrollWidth={data['sidebarScrollWidth']} vs sidebarClientWidth={data['sidebarClientWidth']}"
        )

    # 1.5 Extreme Adversarial Label Stress Test
    print("\n  --- Adversarial Stress Test: 120-Character Giant Label ---")
    page.evaluate("""() => {
        const nav = document.getElementById('navContainer');
        const giantItem = document.createElement('a');
        giantItem.id = 'giant-nav-item';
        giantItem.className = 'nav-item flex items-center justify-between gap-2.5 px-3 py-2 rounded-xl text-xs font-medium min-h-[40px] text-slate-400';
        giantItem.title = 'Departamento Penitenciário Nacional e Secretaria de Estado da Justiça e Reintegração Social do Espírito Santo';
        giantItem.innerHTML = `
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3"/></svg>
                <span class="flex-1 min-w-0 text-left leading-snug truncate" id="giant-label" title="${giantItem.title}">
                    ${giantItem.title}
                </span>
            </div>
            <span class="badge-nav flex-shrink-0 ml-auto text-[9px] font-bold px-2 py-0.5 rounded-full text-white bg-rose-600">ADVERSARIAL</span>
        `;
        nav.appendChild(giantItem);
    }""")
    page.wait_for_timeout(50)

    giant_data = page.evaluate("""() => {
        const label = document.getElementById('giant-label');
        const sidebar = document.getElementById('sidebar');
        return {
            scrollWidth: label.scrollWidth,
            clientWidth: label.clientWidth,
            isTruncated: label.scrollWidth > label.clientWidth,
            sidebarScrollWidth: sidebar.scrollWidth,
            sidebarClientWidth: sidebar.clientWidth
        };
    }""")
    record_test(
        "Giant 120-char label is cleanly truncated without blowing out 285px sidebar container",
        giant_data["isTruncated"] and giant_data["sidebarScrollWidth"] <= giant_data["sidebarClientWidth"],
        f"label scrollWidth={giant_data['scrollWidth']}, clientWidth={giant_data['clientWidth']}, sidebar diff={giant_data['sidebarScrollWidth'] - giant_data['sidebarClientWidth']}"
    )


def run_canvas_charts_reactivity_tests(page):
    print("\n=======================================================")
    print("2. CANVAS CHARTS REACTIVITY & MUTATIONOBSERVER STRESS TEST")
    print("=======================================================")

    css_content = COMPILED_CSS_PATH.read_text(encoding="utf-8")

    # Build page containing ChartBar and ChartDonut replica scripts matching the exact components
    html = f"""
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <style>
            {css_content}
        </style>
    </head>
    <body class="bg-slate-50 dark:bg-[#0b0f17] text-slate-800 dark:text-slate-100">
        <div id="chart-card" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 p-6 rounded-2xl max-w-4xl mx-auto my-8">
            <h2 id="card-title">Monitoramento de Reintegração</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div class="chart-donut-container relative w-full h-[230px]" id="donutContainer">
                    <canvas id="chartDonut" class="w-full h-full block"></canvas>
                </div>
                <div class="chart-bar-container relative w-full h-[230px]" id="barContainer">
                    <canvas id="chartBar" class="w-full h-full block"></canvas>
                </div>
            </div>
        </div>

        <script>
            // Exact replica of ChartDonut.vue logic
            const donutCanvas = document.getElementById('chartDonut');
            const barCanvas = document.getElementById('chartBar');

            let donutRenderCount = 0;
            let barRenderCount = 0;

            const checkIsDark = () => {{
                return document.documentElement.classList.contains('dark') ||
                       document.documentElement.classList.contains('high-contrast');
            }};

            const getEffectiveBg = (canvas, isDark) => {{
                // Check CSS custom property --bg-card first if defined by active theme
                const rootStyle = window.getComputedStyle(document.documentElement);
                const bgCardVar = rootStyle.getPropertyValue('--bg-card').trim();
                if (bgCardVar && bgCardVar !== 'inherit' && bgCardVar !== 'initial') {{
                    return bgCardVar;
                }}

                // Walk up DOM to find containing card background
                let el = canvas.parentElement;
                while (el && el !== document.body) {{
                    const bg = window.getComputedStyle(el).backgroundColor;
                    if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {{
                        return bg;
                    }}
                    el = el.parentElement;
                }}

                return isDark ? '#0f172a' : '#ffffff';
            }};

            function renderDonut() {{
                donutRenderCount++;
                const canvas = donutCanvas;
                const ctx = canvas.getContext('2d', {{ willReadFrequently: true }});
                const width = 380;
                const height = 230;
                canvas.width = width;
                canvas.height = height;

                ctx.clearRect(0, 0, width, height);

                const dark = checkIsDark();
                const centerX = width / 3.2;
                const centerY = height / 2;
                const radius = 70;
                const innerRadius = radius * 0.6;

                const slices = [
                    {{ label: 'Emprego & Renda', val: 0.42, color: '#10b981' }},
                    {{ label: 'Cursos & Capacitação', val: 0.28, color: '#8b5cf6' }},
                    {{ label: 'Apoio Psicossocial', val: 0.18, color: '#0284c7' }},
                    {{ label: 'Documentação Emitida', val: 0.12, color: '#f59e0b' }},
                ];

                let startAngle = -Math.PI / 2;
                slices.forEach(slice => {{
                    const sliceAngle = slice.val * 2 * Math.PI;
                    ctx.fillStyle = slice.color;
                    ctx.beginPath();
                    ctx.moveTo(centerX, centerY);
                    ctx.arc(centerX, centerY, radius, startAngle, startAngle + sliceAngle);
                    ctx.closePath();
                    ctx.fill();
                    startAngle += sliceAngle;
                }});

                // Cutout hole
                const cutoutColor = getEffectiveBg(canvas, dark);
                ctx.fillStyle = cutoutColor;
                ctx.beginPath();
                ctx.arc(centerX, centerY, innerRadius, 0, 2 * Math.PI);
                ctx.fill();

                // Center text
                ctx.fillStyle = dark ? '#f8fafc' : '#0f172a';
                ctx.font = 'bold 15px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('100%', centerX, centerY - 2);
            }}

            function renderBar() {{
                barRenderCount++;
                const canvas = barCanvas;
                const ctx = canvas.getContext('2d', {{ willReadFrequently: true }});
                const width = 380;
                const height = 230;
                canvas.width = width;
                canvas.height = height;

                ctx.clearRect(0, 0, width, height);
                const dark = checkIsDark();

                // Gridlines
                ctx.strokeStyle = dark ? '#334155' : '#e2e8f0';
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(40, 180);
                ctx.lineTo(360, 180);
                ctx.stroke();

                // Bar 1: Vitória (#003366 replaced by #38bdf8 in dark mode)
                let barColor = '#003366';
                if (dark && barColor.toLowerCase() === '#003366') {{
                    barColor = '#38bdf8';
                }}
                ctx.fillStyle = barColor;
                ctx.fillRect(50, 60, 30, 120);

                // Bar label
                ctx.fillStyle = dark ? '#f1f5f9' : '#1e293b';
                ctx.fillText('Vitória', 50, 200);
            }}

            // Initial render
            renderDonut();
            renderBar();

            // Component MutationObserver setup (exact match to components)
            const themeObserver = new MutationObserver((mutations) => {{
                renderDonut();
                renderBar();
            }});

            themeObserver.observe(document.documentElement, {{
                attributes: true,
                attributeFilter: ['class', 'data-theme']
            }});

            window.__chartMetrics = () => ({{
                donutRenderCount,
                barRenderCount,
                isDark: checkIsDark(),
                effectiveBg: getEffectiveBg(donutCanvas, checkIsDark())
            }});
        </script>
    </body>
    </html>
    """

    page.set_content(html)
    page.wait_for_timeout(100)

    # 2.1 Initial Light Mode Check
    metrics = page.evaluate("window.__chartMetrics()")
    record_test("Initial donut render count is 1", metrics["donutRenderCount"] == 1)
    record_test("Initial bar render count is 1", metrics["barRenderCount"] == 1)
    record_test("Light mode effective cutout bg is white", metrics["effectiveBg"].lower() in ["#ffffff", "rgb(255, 255, 255)"], f"bg={metrics['effectiveBg']}")

    # Helper function to sample center pixel of chartDonut
    def get_center_pixel_color():
        return page.evaluate("""() => {
            const canvas = document.getElementById('chartDonut');
            const ctx = canvas.getContext('2d');
            const width = 380;
            const height = 230;
            const centerX = Math.round(width / 3.2);
            const centerY = Math.round(height / 2);
            const pixel = ctx.getImageData(centerX, centerY, 1, 1).data;
            return {
                r: pixel[0],
                g: pixel[1],
                b: pixel[2],
                a: pixel[3],
                hex: '#' + [pixel[0], pixel[1], pixel[2]].map(x => x.toString(16).padStart(2, '0')).join('')
            };
        }""")

    # Check light mode center pixel
    light_pixel = get_center_pixel_color()
    record_test(
        "Light mode donut hole pixel is pure white (rgb(255, 255, 255))",
        light_pixel["r"] == 255 and light_pixel["g"] == 255 and light_pixel["b"] == 255,
        f"Actual pixel: {light_pixel}"
    )

    # 2.2 Mutation to Dark Mode (Executive Slate)
    print("\n  --- Testing MutationObserver on Dark Mode Toggle ---")
    page.evaluate("""() => {
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'executive-slate');
    }""")
    page.wait_for_timeout(150)

    metrics_dark = page.evaluate("window.__chartMetrics()")
    record_test(
        "MutationObserver reacted to dark mode class and theme attribute mutation",
        metrics_dark["donutRenderCount"] >= 2 and metrics_dark["barRenderCount"] >= 2,
        f"Donut renders: {metrics_dark['donutRenderCount']}, Bar renders: {metrics_dark['barRenderCount']}"
    )

    exec_pixel = get_center_pixel_color()
    # Executive Slate card background: #151D2A -> r=21, g=29, b=42
    record_test(
        "Executive Slate: Donut cutout hole matches card bg #151D2A (eliminates blinding white hole)",
        exec_pixel["hex"].lower() == "#151d2a" or (abs(exec_pixel["r"] - 21) <= 2 and abs(exec_pixel["g"] - 29) <= 2 and abs(exec_pixel["b"] - 42) <= 2),
        f"Actual pixel: {exec_pixel['hex']} (rgb: {exec_pixel['r']}, {exec_pixel['g']}, {exec_pixel['b']})"
    )
    record_test(
        "Executive Slate: Blinding white hole is strictly eliminated (r, g, b << 200)",
        exec_pixel["r"] < 50 and exec_pixel["g"] < 50 and exec_pixel["b"] < 50,
        f"Pixel values: {exec_pixel}"
    )

    # 2.3 Theme Cycling Stress Test across all 6 Government Dark Themes
    print("\n  --- Testing Reactivity across all 6 Dark Themes ---")
    themes = [
        {"id": "midnight-navy", "expected_hex": "#0f1d33", "rgb": (15, 29, 51)},
        {"id": "forest-emerald", "expected_hex": "#0e231b", "rgb": (14, 35, 27)},
        {"id": "deep-titanium", "expected_hex": "#1a1c22", "rgb": (26, 28, 34)},
        {"id": "royal-indigo", "expected_hex": "#16162e", "rgb": (22, 22, 46)},
        {"id": "obsidian-gold", "expected_hex": "#1a1918", "rgb": (26, 25, 24)},
        {"id": "executive-slate", "expected_hex": "#151d2a", "rgb": (21, 29, 42)},
    ]

    for th in themes:
        prev_renders = page.evaluate("window.__chartMetrics().donutRenderCount")
        page.evaluate(f"document.documentElement.setAttribute('data-theme', '{th['id']}');")
        page.wait_for_timeout(100)

        cur_metrics = page.evaluate("window.__chartMetrics()")
        pixel = get_center_pixel_color()

        record_test(
            f"Theme '{th['id']}': MutationObserver triggered re-render",
            cur_metrics["donutRenderCount"] > prev_renders,
            f"Previous renders: {prev_renders}, Current renders: {cur_metrics['donutRenderCount']}"
        )
        record_test(
            f"Theme '{th['id']}': Cutout hole dynamically matched --bg-card {th['expected_hex']}",
            pixel["hex"].lower() == th["expected_hex"].lower() or (
                abs(pixel["r"] - th["rgb"][0]) <= 2 and
                abs(pixel["g"] - th["rgb"][1]) <= 2 and
                abs(pixel["b"] - th["rgb"][2]) <= 2
            ),
            f"Expected: {th['expected_hex']} ({th['rgb']}), Got: {pixel['hex']} ({pixel['r']}, {pixel['g']}, {pixel['b']})"
        )

    # 2.4 High-Contrast Mode Reactivity & Hole Test
    print("\n  --- Testing High Contrast Mode Reactivity ---")
    page.evaluate("""() => {
        document.documentElement.classList.add('high-contrast');
    }""")
    page.wait_for_timeout(100)

    hc_pixel = get_center_pixel_color()
    # High contrast card bg is #121212 -> r=18, g=18, b=18 or #0f172a fallback
    record_test(
        "High-Contrast Mode: Donut hole renders dark card background (no white hole)",
        hc_pixel["r"] < 30 and hc_pixel["g"] < 30 and hc_pixel["b"] < 30,
        f"Actual pixel: {hc_pixel}"
    )

    # 2.5 Toggle back to Light Mode
    print("\n  --- Testing Reversion back to Light Mode ---")
    page.evaluate("""() => {
        document.documentElement.classList.remove('dark');
        document.documentElement.classList.remove('high-contrast');
        document.documentElement.removeAttribute('data-theme');
    }""")
    page.wait_for_timeout(100)

    revert_pixel = get_center_pixel_color()
    record_test(
        "Reversion: Donut hole seamlessly restores pure white (#ffffff) in Light Mode",
        revert_pixel["r"] == 255 and revert_pixel["g"] == 255 and revert_pixel["b"] == 255,
        f"Actual pixel: {revert_pixel}"
    )

    # 2.6 ChartBar Color Adaptation Verification
    print("\n  --- Testing ChartBar Dark Mode Color Adaptation ---")
    # In light mode: sample pixel inside Bar 1 (x=65, y=100)
    def get_bar_pixel():
        return page.evaluate("""() => {
            const canvas = document.getElementById('chartBar');
            const ctx = canvas.getContext('2d');
            const pixel = ctx.getImageData(65, 100, 1, 1).data;
            return {
                r: pixel[0], g: pixel[1], b: pixel[2], a: pixel[3],
                hex: '#' + [pixel[0], pixel[1], pixel[2]].map(x => x.toString(16).padStart(2, '0')).join('')
            };
        }""")

    light_bar_pixel = get_bar_pixel()
    record_test(
        "ChartBar light mode renders navy #003366 (r=0, g=51, b=102)",
        light_bar_pixel["hex"].lower() == "#003366" or (light_bar_pixel["r"] == 0 and abs(light_bar_pixel["g"] - 51) <= 2 and abs(light_bar_pixel["b"] - 102) <= 2),
        f"Got: {light_bar_pixel['hex']}"
    )

    # Switch to dark mode
    page.evaluate("""() => {
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'executive-slate');
    }""")
    page.wait_for_timeout(100)

    dark_bar_pixel = get_bar_pixel()
    # In dark mode, #003366 is replaced by #38bdf8 (r=56, g=189, b=248)
    record_test(
        "ChartBar dark mode replaces #003366 with vibrant accessible sky-400 (#38bdf8)",
        dark_bar_pixel["hex"].lower() == "#38bdf8" or (abs(dark_bar_pixel["r"] - 56) <= 2 and abs(dark_bar_pixel["g"] - 189) <= 2 and abs(dark_bar_pixel["b"] - 248) <= 2),
        f"Got: {dark_bar_pixel['hex']} (rgb: {dark_bar_pixel['r']}, {dark_bar_pixel['g']}, {dark_bar_pixel['b']})"
    )


def run_rapid_mutation_stress_test(page):
    print("\n=======================================================")
    print("3. RAPID MUTATION STRESS & STABILITY HARNESS")
    print("=======================================================")

    # 100 rapid consecutive theme mutations
    theme_ids = ["executive-slate", "midnight-navy", "forest-emerald", "deep-titanium", "royal-indigo", "obsidian-gold"]
    
    start_time = time.time()
    for i in range(60):
        target = theme_ids[i % len(theme_ids)]
        page.evaluate(f"document.documentElement.setAttribute('data-theme', '{target}');")
    
    elapsed = time.time() - start_time
    page.wait_for_timeout(200)

    final_metrics = page.evaluate("window.__chartMetrics()")
    record_test(
        f"Canvas handles 60 rapid theme mutations in {elapsed:.3f}s without runtime errors",
        final_metrics["donutRenderCount"] >= 60,
        f"Total renders: {final_metrics['donutRenderCount']} in {elapsed:.3f}s"
    )

    # Final pixel check
    last_pixel = page.evaluate("""() => {
        const canvas = document.getElementById('chartDonut');
        const ctx = canvas.getContext('2d');
        const pixel = ctx.getImageData(Math.round(380/3.2), Math.round(230/2), 1, 1).data;
        return '#' + [pixel[0], pixel[1], pixel[2]].map(x => x.toString(16).padStart(2, '0')).join('');
    }""")
    record_test(
        "Final canvas state reflects the active theme correctly after rapid thrashing",
        last_pixel.lower() == "#1a1918",  # last theme was obsidian-gold (#1a1918)
        f"Actual pixel hex: {last_pixel}"
    )


def main():
    print("Starting Milestone 1 Responsive & Canvas Adversarial Challenge Suite...")
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={"width": 1280, "height": 800})
        page = context.new_page()

        try:
            run_sidebar_truncation_tests(page)
            run_canvas_charts_reactivity_tests(page)
            run_rapid_mutation_stress_test(page)
        finally:
            browser.close()

    print("\n=======================================================")
    print(f"SUITE COMPLETE: {results['passed']} PASSED, {results['failed']} FAILED (TOTAL: {len(results['tests'])})")
    print("=======================================================")

    report_path = PROJECT_ROOT / "tests" / "adversarial_m1_layout_and_canvas_report.json"
    report_path.write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Report saved to: {report_path}")

    if results["failed"] > 0:
        sys.exit(1)
    else:
        sys.exit(0)


if __name__ == "__main__":
    main()
