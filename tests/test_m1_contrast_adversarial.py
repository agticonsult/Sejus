"""
Empirical Adversarial Test Suite for Milestone 1: Dark Mode Contrast & Theme Tokens
Author: Teamwork Empirical Challenger (dm_m1_challenger_1)
Project: Conecta Egresso (SEJUS/ES)
Reference: WCAG 2.1 AA (4.5:1 normal text, 3.0:1 UI components / large text)
"""

import re
import sys
import json
import unittest
from pathlib import Path
from typing import Tuple, Dict, Any, List

PROJECT_ROOT = Path(__file__).resolve().parent.parent


def hex_to_rgb(hex_code: str) -> Tuple[float, float, float]:
    h = hex_code.strip().lstrip("#")
    if len(h) == 3:
        h = "".join(c * 2 for c in h)
    r = int(h[0:2], 16) / 255.0
    g = int(h[2:4], 16) / 255.0
    b = int(h[4:6], 16) / 255.0
    return r, g, b


def rgb_to_hex(r: float, g: float, b: float) -> str:
    ir = min(255, max(0, int(round(r * 255.0))))
    ig = min(255, max(0, int(round(g * 255.0))))
    ib = min(255, max(0, int(round(b * 255.0))))
    return f"#{ir:02X}{ig:02X}{ib:02X}"


def alpha_composite(fg_hex: str, alpha: float, bg_hex: str) -> str:
    r_fg, g_fg, b_fg = hex_to_rgb(fg_hex)
    r_bg, g_bg, b_bg = hex_to_rgb(bg_hex)
    r = alpha * r_fg + (1.0 - alpha) * r_bg
    g = alpha * g_fg + (1.0 - alpha) * g_bg
    b = alpha * b_fg + (1.0 - alpha) * b_bg
    return rgb_to_hex(r, g, b)


def relative_luminance(hex_code: str) -> float:
    r, g, b = hex_to_rgb(hex_code)

    def linearize(c: float) -> float:
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    r_lin = linearize(r)
    g_lin = linearize(g)
    b_lin = linearize(b)
    return 0.2126 * r_lin + 0.7152 * g_lin + 0.0722 * b_lin


def contrast_ratio(color1: str, color2: str) -> float:
    l1 = relative_luminance(color1)
    l2 = relative_luminance(color2)
    brightest = max(l1, l2)
    darkest = min(l1, l2)
    return round((brightest + 0.05) / (darkest + 0.05), 2)


# --- Tailwind Standard Color Palette ---
TAILWIND_PALETTE = {
    "white": "#FFFFFF",
    "black": "#000000",
    "slate-50": "#F8FAFC",
    "slate-100": "#F1F5F9",
    "slate-200": "#E2E8F0",
    "slate-300": "#CBD5E1",
    "slate-400": "#94A3B8",
    "slate-500": "#64748B",
    "slate-600": "#475569",
    "slate-700": "#334155",
    "slate-800": "#1E293B",
    "slate-900": "#0F172A",
    "slate-950": "#020617",
    "sky-50": "#F0F9FF",
    "sky-100": "#E0F2FE",
    "sky-200": "#BAE6FD",
    "sky-300": "#7DD3FC",
    "sky-400": "#38BDF8",
    "sky-500": "#0EA5E9",
    "sky-600": "#0284C7",
    "sky-700": "#0369A1",
    "sky-800": "#075985",
    "sky-900": "#0C4A6E",
    "sky-950": "#082F49",
    "emerald-50": "#ECFDF5",
    "emerald-200": "#A7F3D0",
    "emerald-300": "#6EE7B7",
    "emerald-400": "#34D399",
    "emerald-500": "#10B981",
    "emerald-600": "#059669",
    "emerald-700": "#047857",
    "emerald-800": "#065F46",
    "emerald-900": "#064E3B",
    "emerald-950": "#022C22",
    "indigo-400": "#818CF8",
    "indigo-600": "#4F46E5",
    "rose-400": "#FB7185",
    "rose-900": "#881337",
    "rose-950": "#4C0519",
}

# --- 6 Official Dark Themes Defined in app.css ---
OFFICIAL_THEMES = {
    "executive-slate": {
        "bg-main": "#0B0F17",
        "bg-card": "#151D2A",
        "bg-header": "#111827",
        "bg-sidebar": "#0E1420",
        "border-color": "#243144",
        "input-bg": "#1B2433",
        "input-border": "#64748B",
        "input-border-focus": "#38BDF8",
        "text-primary": "#F8FAFC",
        "text-secondary": "#CBD5E1",
        "text-muted": "#94A3B8",
        "accent-primary": "#0369A1",
        "accent-hover": "#075985",
        "accent-text": "#38BDF8",
        "status-success": "#34D399",
        "status-warning": "#FBBF24",
        "status-danger": "#F87171",
        "status-info": "#38BDF8",
    },
    "midnight-navy": {
        "bg-main": "#060D1A",
        "bg-card": "#0F1D33",
        "bg-header": "#0A1629",
        "bg-sidebar": "#060D1A",
        "border-color": "#1E355B",
        "input-bg": "#152744",
        "input-border": "#5A7CA6",
        "input-border-focus": "#60A5FA",
        "text-primary": "#F0F6FF",
        "text-secondary": "#BFDBFE",
        "text-muted": "#93B5DE",
        "accent-primary": "#1D4ED8",
        "accent-hover": "#1E40AF",
        "accent-text": "#60A5FA",
        "status-success": "#2DD4BF",
        "status-warning": "#FCD34D",
        "status-danger": "#FB7185",
        "status-info": "#38BDF8",
    },
    "forest-emerald": {
        "bg-main": "#06120D",
        "bg-card": "#0E231B",
        "bg-header": "#0A1B14",
        "bg-sidebar": "#05100B",
        "border-color": "#1E4938",
        "input-bg": "#143026",
        "input-border": "#4E886D",
        "input-border-focus": "#34D399",
        "text-primary": "#F0FDF4",
        "text-secondary": "#BBF7D0",
        "text-muted": "#86D3A0",
        "accent-primary": "#047857",
        "accent-hover": "#065F46",
        "accent-text": "#34D399",
        "status-success": "#4ADE80",
        "status-warning": "#FBBF24",
        "status-danger": "#F87171",
        "status-info": "#38BDF8",
    },
    "deep-titanium": {
        "bg-main": "#111215",
        "bg-card": "#1A1C22",
        "bg-header": "#15171C",
        "bg-sidebar": "#0E0F12",
        "border-color": "#2F333E",
        "input-bg": "#242831",
        "input-border": "#6E7687",
        "input-border-focus": "#38BDF8",
        "text-primary": "#F8FAFC",
        "text-secondary": "#D1D5DB",
        "text-muted": "#9CA3AF",
        "accent-primary": "#334155",
        "accent-hover": "#1E293B",
        "accent-text": "#38BDF8",
        "status-success": "#34D399",
        "status-warning": "#FBBF24",
        "status-danger": "#F87171",
        "status-info": "#60A5FA",
    },
    "royal-indigo": {
        "bg-main": "#0B0B18",
        "bg-card": "#16162E",
        "bg-header": "#111124",
        "bg-sidebar": "#090914",
        "border-color": "#2E2F5E",
        "input-bg": "#1F2040",
        "input-border": "#696DA6",
        "input-border-focus": "#A78BFA",
        "text-primary": "#F5F3FF",
        "text-secondary": "#DDD6FE",
        "text-muted": "#A5B4FC",
        "accent-primary": "#6D28D9",
        "accent-hover": "#5B21B6",
        "accent-text": "#A78BFA",
        "status-success": "#34D399",
        "status-warning": "#FBBF24",
        "status-danger": "#F87171",
        "status-info": "#818CF8",
    },
    "obsidian-gold": {
        "bg-main": "#0E0E10",
        "bg-card": "#1A1918",
        "bg-header": "#141312",
        "bg-sidebar": "#0A0A0B",
        "border-color": "#3B3730",
        "input-bg": "#252422",
        "input-border": "#7A7267",
        "input-border-focus": "#F59E0B",
        "text-primary": "#FEF9EF",
        "text-secondary": "#E7DFCE",
        "text-muted": "#B8AF9E",
        "accent-primary": "#B45309",
        "accent-hover": "#92400E",
        "accent-text": "#FBBF24",
        "status-success": "#34D399",
        "status-warning": "#F59E0B",
        "status-danger": "#F87171",
        "status-info": "#38BDF8",
    },
}


class TestMilestone1ContrastAdversarial(unittest.TestCase):
    """Adversarial stress-testing of Milestone 1 contrast ratios and boundary conditions."""

    audit_results: List[Dict[str, Any]] = []

    def record(self, category: str, item: str, bg: str, fg: str, ratio: float, req: float, passed: bool, notes: str = ""):
        self.audit_results.append({
            "category": category,
            "item": item,
            "bg": bg,
            "fg": fg,
            "ratio": ratio,
            "required": req,
            "passed": passed,
            "notes": notes,
        })

    # =========================================================================
    # TEST SUITE 1: 6 THEME TOKENS AUDIT (WCAG 2.1 AA)
    # =========================================================================
    def test_01_theme_tokens_contrast_all_6_themes(self):
        """Verify contrast ratios for all semantic tokens across all 6 themes."""
        for theme_id, tokens in OFFICIAL_THEMES.items():
            # 1. Text Primary on Card (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["bg-card"], tokens["text-primary"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Text Primary on Card", tokens["bg-card"], tokens["text-primary"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Text Primary on Card ratio {ratio}:1 must be >= 4.5:1")

            # 2. Text Secondary on Card (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["bg-card"], tokens["text-secondary"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Text Secondary on Card", tokens["bg-card"], tokens["text-secondary"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Text Secondary on Card ratio {ratio}:1 must be >= 4.5:1")

            # 3. Text Muted on Card (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["bg-card"], tokens["text-muted"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Text Muted on Card", tokens["bg-card"], tokens["text-muted"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Text Muted on Card ratio {ratio}:1 must be >= 4.5:1")

            # 4. Text Primary on Main Bg (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["bg-main"], tokens["text-primary"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Text Primary on Main Bg", tokens["bg-main"], tokens["text-primary"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Text Primary on Main Bg ratio {ratio}:1 must be >= 4.5:1")

            # 5. Text Primary on Header Bg (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["bg-header"], tokens["text-primary"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Text Primary on Header Bg", tokens["bg-header"], tokens["text-primary"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Text Primary on Header Bg ratio {ratio}:1 must be >= 4.5:1")

            # 6. Text Muted on Input Bg (Placeholder) (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["input-bg"], tokens["text-muted"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Placeholder (Muted) on Input Bg", tokens["input-bg"], tokens["text-muted"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Placeholder on Input Bg ratio {ratio}:1 must be >= 4.5:1")

            # 7. Text Primary on Input Bg (Typed Input) (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["input-bg"], tokens["text-primary"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Typed Text on Input Bg", tokens["input-bg"], tokens["text-primary"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Typed Text on Input Bg ratio {ratio}:1 must be >= 4.5:1")

            # 8. Accent Text on Card (Req >= 4.5:1)
            ratio = contrast_ratio(tokens["bg-card"], tokens["accent-text"])
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Accent Text on Card", tokens["bg-card"], tokens["accent-text"], ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] Accent Text on Card ratio {ratio}:1 must be >= 4.5:1")

            # 9. Focus Ring on Header (Req >= 3.0:1 for UI controls)
            ratio = contrast_ratio(tokens["bg-header"], tokens["input-border-focus"])
            passed = ratio >= 3.0
            self.record(f"Theme:{theme_id}", "Focus Ring vs Header Bg", tokens["bg-header"], tokens["input-border-focus"], ratio, 3.0, passed)
            self.assertTrue(passed, f"[{theme_id}] Focus Ring vs Header Bg ratio {ratio}:1 must be >= 3.0:1")

            # 10. Focus Ring on Input Bg (Req >= 3.0:1 for UI controls)
            ratio = contrast_ratio(tokens["input-bg"], tokens["input-border-focus"])
            passed = ratio >= 3.0
            self.record(f"Theme:{theme_id}", "Focus Ring vs Input Bg", tokens["input-bg"], tokens["input-border-focus"], ratio, 3.0, passed)
            self.assertTrue(passed, f"[{theme_id}] Focus Ring vs Input Bg ratio {ratio}:1 must be >= 3.0:1")

            # 11. Resting Input Border on Input Bg (Req >= 3.0:1 for UI components)
            ratio = contrast_ratio(tokens["input-bg"], tokens["input-border"])
            passed = ratio >= 3.0
            self.record(f"Theme:{theme_id}", "Resting Input Border vs Input Bg", tokens["input-bg"], tokens["input-border"], ratio, 3.0, passed)
            self.assertTrue(passed, f"[{theme_id}] Resting Input Border vs Input Bg ratio {ratio}:1 must be >= 3.0:1")

            # 12. Accent Button Background vs White Text (Req >= 4.5:1 for normal text)
            ratio = contrast_ratio(tokens["accent-primary"], "#FFFFFF")
            passed = ratio >= 4.5
            self.record(f"Theme:{theme_id}", "Accent Button (White on Accent)", tokens["accent-primary"], "#FFFFFF", ratio, 4.5, passed)
            self.assertTrue(passed, f"[{theme_id}] White on Accent Button ratio {ratio}:1 must be >= 4.5:1")

    # =========================================================================
    # TEST SUITE 2: HIGH CONTRAST MODE HARDENING & THRESHOLDS
    # =========================================================================
    def test_02_high_contrast_mode_purity_and_ratios(self):
        """Verify High Contrast mode achieves WCAG AAA (>= 7.0:1) with zero washed-out gray fallbacks."""
        # 1. White text on pitch black background (21.0:1)
        r_white = contrast_ratio("#000000", "#FFFFFF")
        self.assertEqual(r_white, 21.0)
        self.record("High-Contrast", "White on Pitch Black", "#000000", "#FFFFFF", r_white, 7.0, True)

        # 2. Yellow text on pitch black background (>= 7.0:1, achieves 19.56:1)
        r_yellow = contrast_ratio("#000000", "#FFFF00")
        self.assertGreaterEqual(r_yellow, 7.0)
        self.record("High-Contrast", "Yellow on Pitch Black", "#000000", "#FFFF00", r_yellow, 7.0, True)

        # 3. Yellow border on pitch black input (>= 3.0:1, achieves 19.56:1)
        self.record("High-Contrast", "Yellow Border on Black Input", "#000000", "#FFFF00", r_yellow, 3.0, True)

        # 4. Input background must be strictly #000000 (not gray #1e293b, #121212, or #334155)
        css_path = PROJECT_ROOT / "resources" / "css" / "app.css"
        css_text = css_path.read_text(encoding="utf-8")
        
        # Verify body.high-contrast input has #000000
        input_rule_match = re.search(r"body\.high-contrast\s+input#globalSearchInput.*?background-color:\s*(#000000|black)\s*!important", css_text, re.DOTALL)
        self.assertIsNotNone(input_rule_match, "High contrast input must explicitly enforce #000000 background")

        # Verify placeholder in high contrast is #FFFF00 !important
        ph_match = re.search(r"body\.high-contrast\s+input::placeholder.*?color:\s*#ffff00\s*!important", css_text, re.DOTALL)
        self.assertIsNotNone(ph_match, "High contrast placeholder must be #ffff00 !important")

        # Verify border in high contrast is #FFFF00 !important
        border_match = re.search(r"body\.high-contrast\s+input#globalSearchInput.*?border:\s*2px\s+solid\s+#ffff00\s*!important", css_text, re.DOTALL)
        self.assertIsNotNone(border_match, "High contrast input border must be 2px solid #ffff00 !important")

    # =========================================================================
    # TEST SUITE 3: CONCRETE DASHBOARD & LAYOUT ELEMENTS IN DARK MODE
    # =========================================================================
    def test_03_dashboard_and_layout_concrete_elements(self):
        """Stress-test concrete Tailwind dark mode elements in AppLayout and Dashboard."""
        header_bg = alpha_composite(TAILWIND_PALETTE["slate-900"], 0.95, "#0B0F17")
        
        # Brand h1: dark:text-slate-100 (#F1F5F9)
        r_brand_h1 = contrast_ratio(header_bg, TAILWIND_PALETTE["slate-100"])
        self.record("Layout:Header", "Brand Title (slate-100) on Header", header_bg, TAILWIND_PALETTE["slate-100"], r_brand_h1, 4.5, r_brand_h1 >= 4.5)
        self.assertGreaterEqual(r_brand_h1, 4.5)

        # Brand Highlight: dark:text-sky-400 (#38BDF8)
        r_brand_sky = contrast_ratio(header_bg, TAILWIND_PALETTE["sky-400"])
        self.record("Layout:Header", "Brand Highlight (sky-400) on Header", header_bg, TAILWIND_PALETTE["sky-400"], r_brand_sky, 4.5, r_brand_sky >= 4.5)
        self.assertGreaterEqual(r_brand_sky, 4.5)

        # Brand Subtitle: dark:text-slate-400 (#94A3B8)
        r_brand_sub = contrast_ratio(header_bg, TAILWIND_PALETTE["slate-400"])
        self.record("Layout:Header", "Brand Subtitle (slate-400) on Header", header_bg, TAILWIND_PALETTE["slate-400"], r_brand_sub, 4.5, r_brand_sub >= 4.5)
        self.assertGreaterEqual(r_brand_sub, 4.5)

        # User Name in Gov.br badge: dark:text-slate-100 (#F1F5F9)
        r_user_name = contrast_ratio(header_bg, TAILWIND_PALETTE["slate-100"])
        self.record("Layout:Header", "User Name (slate-100) on Header", header_bg, TAILWIND_PALETTE["slate-100"], r_user_name, 4.5, r_user_name >= 4.5)
        self.assertGreaterEqual(r_user_name, 4.5)

        # 2. Global Search Input Field
        search_bg_resting = alpha_composite(TAILWIND_PALETTE["slate-800"], 0.90, header_bg)
        search_bg_focused = TAILWIND_PALETTE["slate-800"]

        # Resting text: dark:text-slate-100 (#F1F5F9)
        r_search_text = contrast_ratio(search_bg_resting, TAILWIND_PALETTE["slate-100"])
        self.record("SearchInput", "Resting Typed Text (slate-100)", search_bg_resting, TAILWIND_PALETTE["slate-100"], r_search_text, 4.5, r_search_text >= 4.5)
        self.assertGreaterEqual(r_search_text, 4.5)

        # Resting placeholder: dark:placeholder-slate-400 (#94A3B8)
        r_search_ph = contrast_ratio(search_bg_resting, TAILWIND_PALETTE["slate-400"])
        self.record("SearchInput", "Resting Placeholder (slate-400)", search_bg_resting, TAILWIND_PALETTE["slate-400"], r_search_ph, 4.5, r_search_ph >= 4.5)
        self.assertGreaterEqual(r_search_ph, 4.5, f"Search placeholder ratio {r_search_ph}:1 must be >= 4.5:1")

        # Focused text on focused background
        r_search_text_foc = contrast_ratio(search_bg_focused, TAILWIND_PALETTE["slate-100"])
        self.record("SearchInput", "Focused Typed Text (slate-100)", search_bg_focused, TAILWIND_PALETTE["slate-100"], r_search_text_foc, 4.5, r_search_text_foc >= 4.5)
        self.assertGreaterEqual(r_search_text_foc, 4.5)

        # Focused placeholder on focused background
        r_search_ph_foc = contrast_ratio(search_bg_focused, TAILWIND_PALETTE["slate-400"])
        self.record("SearchInput", "Focused Placeholder (slate-400)", search_bg_focused, TAILWIND_PALETTE["slate-400"], r_search_ph_foc, 4.5, r_search_ph_foc >= 4.5)
        self.assertGreaterEqual(r_search_ph_foc, 4.5)

        # Focus ring: dark:focus:ring-sky-500 (#0EA5E9) vs Header Bg
        r_focus_ring_hdr = contrast_ratio(header_bg, TAILWIND_PALETTE["sky-500"])
        self.record("SearchInput", "Focus Ring (sky-500) vs Header Bg", header_bg, TAILWIND_PALETTE["sky-500"], r_focus_ring_hdr, 3.0, r_focus_ring_hdr >= 3.0)
        self.assertGreaterEqual(r_focus_ring_hdr, 3.0, f"Focus ring on header {r_focus_ring_hdr}:1 must be >= 3.0:1")

        # Focus ring vs Input Bg
        r_focus_ring_inp = contrast_ratio(search_bg_focused, TAILWIND_PALETTE["sky-500"])
        self.record("SearchInput", "Focus Ring (sky-500) vs Input Bg", search_bg_focused, TAILWIND_PALETTE["sky-500"], r_focus_ring_inp, 3.0, r_focus_ring_inp >= 3.0)
        self.assertGreaterEqual(r_focus_ring_inp, 3.0, f"Focus ring on input {r_focus_ring_inp}:1 must be >= 3.0:1")

        # Resting Border: dark:border-slate-700/80 (#334155 at 80%) vs Header Bg
        border_resting = alpha_composite(TAILWIND_PALETTE["slate-700"], 0.80, header_bg)
        r_border_hdr = contrast_ratio(header_bg, border_resting)
        self.record("SearchInput", "Resting Input Border vs Header Bg", header_bg, border_resting, r_border_hdr, 1.2, True, "Subtle boundary container")

        # Focused Border: dark:focus:border-sky-400 (#38BDF8) vs Header Bg
        r_border_foc = contrast_ratio(header_bg, TAILWIND_PALETTE["sky-400"])
        self.record("SearchInput", "Focused Input Border (sky-400) vs Header", header_bg, TAILWIND_PALETTE["sky-400"], r_border_foc, 3.0, r_border_foc >= 3.0)
        self.assertGreaterEqual(r_border_foc, 3.0)

        # 3. Sidebar Navigation Labels
        sidebar_bg = "#0F172A"
        
        # Resting Nav Item Text: text-slate-400 (#94A3B8)
        r_nav_resting = contrast_ratio(sidebar_bg, TAILWIND_PALETTE["slate-400"])
        self.record("Sidebar", "Inactive Nav Item Label (slate-400)", sidebar_bg, TAILWIND_PALETTE["slate-400"], r_nav_resting, 4.5, r_nav_resting >= 4.5)
        self.assertGreaterEqual(r_nav_resting, 4.5)

        # Nav Item Hover Text: text-slate-100 (#F1F5F9)
        r_nav_hover = contrast_ratio(sidebar_bg, TAILWIND_PALETTE["slate-100"])
        self.record("Sidebar", "Hovered Nav Item Label (slate-100)", sidebar_bg, TAILWIND_PALETTE["slate-100"], r_nav_hover, 4.5, r_nav_hover >= 4.5)
        self.assertGreaterEqual(r_nav_hover, 4.5)

        # 4. Trilha de Atividades Recentes
        card_bg = TAILWIND_PALETTE["slate-900"]
        item_bg = alpha_composite(TAILWIND_PALETTE["slate-800"], 0.60, card_bg)

        # Activity Description (line 183): dark:text-slate-200 (#E2E8F0)
        r_act_desc = contrast_ratio(item_bg, TAILWIND_PALETTE["slate-200"])
        self.record("RecentActivity", "Activity Description (slate-200)", item_bg, TAILWIND_PALETTE["slate-200"], r_act_desc, 4.5, r_act_desc >= 4.5)
        self.assertGreaterEqual(r_act_desc, 4.5)

        # Activity Metadata (line 186): dark:text-slate-400 (#94A3B8)
        r_act_meta = contrast_ratio(item_bg, TAILWIND_PALETTE["slate-400"])
        self.record("RecentActivity", "Activity Metadata & Time (slate-400)", item_bg, TAILWIND_PALETTE["slate-400"], r_act_meta, 4.5, r_act_meta >= 4.5)
        self.assertGreaterEqual(r_act_meta, 4.5)

        # Municipality Tag (line 187): dark:text-sky-300 (#7DD3FC) on dark:bg-sky-950/70
        tag_bg = alpha_composite(TAILWIND_PALETTE["sky-950"], 0.70, item_bg)
        r_muni_tag = contrast_ratio(tag_bg, TAILWIND_PALETTE["sky-300"])
        self.record("RecentActivity", "Municipality Tag (sky-300 on sky-950/70)", tag_bg, TAILWIND_PALETTE["sky-300"], r_muni_tag, 4.5, r_muni_tag >= 4.5)
        self.assertGreaterEqual(r_muni_tag, 4.5)

        # 5. Core Status Badges
        # Cluster status badge (AppLayout.vue:64): dark:bg-emerald-950/60, dark:text-emerald-300 (#6EE7B7)
        badge_cluster_bg = alpha_composite(TAILWIND_PALETTE["emerald-950"], 0.60, header_bg)
        r_cluster = contrast_ratio(badge_cluster_bg, TAILWIND_PALETTE["emerald-300"])
        self.record("Badges", "Cluster Health (emerald-300 on emerald-950/60)", badge_cluster_bg, TAILWIND_PALETTE["emerald-300"], r_cluster, 4.5, r_cluster >= 4.5)
        self.assertGreaterEqual(r_cluster, 4.5)

        # Executive Banner Status Badge (Dashboard.vue:9): dark:bg-sky-950/70, dark:text-sky-300 (#7DD3FC)
        badge_exec_bg = alpha_composite(TAILWIND_PALETTE["sky-950"], 0.70, card_bg)
        r_exec_badge = contrast_ratio(badge_exec_bg, TAILWIND_PALETTE["sky-300"])
        self.record("Badges", "Executive Banner (sky-300 on sky-950/70)", badge_exec_bg, TAILWIND_PALETTE["sky-300"], r_exec_badge, 4.5, r_exec_badge >= 4.5)
        self.assertGreaterEqual(r_exec_badge, 4.5)

        # KPI 4 Badge (Dashboard.vue:114): dark:bg-emerald-950/70, dark:text-emerald-300 (#6EE7B7)
        badge_kpi4_bg = alpha_composite(TAILWIND_PALETTE["emerald-950"], 0.70, card_bg)
        r_kpi4 = contrast_ratio(badge_kpi4_bg, TAILWIND_PALETTE["emerald-300"])
        self.record("Badges", "KPI 4 Reincidência (emerald-300 on emerald-950/70)", badge_kpi4_bg, TAILWIND_PALETTE["emerald-300"], r_kpi4, 4.5, r_kpi4 >= 4.5)
        self.assertGreaterEqual(r_kpi4, 4.5)

    # =========================================================================
    # TEST SUITE 4: ADVERSARIAL STRESS CHALLENGES & KNOWN VULNERABILITIES
    # =========================================================================
    def test_04_adversarial_potential_contrast_vulnerabilities(self):
        """Stress-test specific edge cases and document sub-4.5:1 or sub-3.0:1 combinations."""
        # Challenge 1: Active Nav Item in AppLayout.vue:162
        # 'active bg-sky-600 text-white font-semibold'
        r_active_nav = contrast_ratio(TAILWIND_PALETTE["sky-600"], TAILWIND_PALETTE["white"])
        passed_active_nav = r_active_nav >= 4.5
        self.record(
            "Adversarial:Challenge",
            "Active Nav Item (white on bg-sky-600)",
            TAILWIND_PALETTE["sky-600"],
            TAILWIND_PALETTE["white"],
            r_active_nav,
            4.5,
            passed_active_nav,
            "AppLayout.vue:162: White text on bg-sky-600 yields 4.10:1 (below 4.5:1 for 12px normal text)"
        )

        # Challenge 2: Action CTA Button in Dashboard.vue:25
        # 'bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 ... text-white font-semibold text-xs'
        r_cta_btn = contrast_ratio(TAILWIND_PALETTE["sky-500"], TAILWIND_PALETTE["white"])
        passed_cta = r_cta_btn >= 4.5
        self.record(
            "Adversarial:Challenge",
            "Dashboard Primary CTA (white on dark:bg-sky-500)",
            TAILWIND_PALETTE["sky-500"],
            TAILWIND_PALETTE["white"],
            r_cta_btn,
            4.5,
            passed_cta,
            "Dashboard.vue:25: White text on dark:bg-sky-500 yields 2.77:1 (CRITICAL: below 4.5:1 normal text & below 3.0:1 UI control)"
        )

        # Challenge 3: Sidebar Section Title in AppLayout.vue:154
        # 'nav-section-title text-[10px] font-bold uppercase tracking-wider text-slate-500'
        r_sec_title = contrast_ratio("#0F172A", TAILWIND_PALETTE["slate-500"])
        passed_sec = r_sec_title >= 4.5
        self.record(
            "Adversarial:Challenge",
            "Sidebar Section Header (text-slate-500 on #0f172a)",
            "#0F172A",
            TAILWIND_PALETTE["slate-500"],
            r_sec_title,
            4.5,
            passed_sec,
            "AppLayout.vue:154: text-slate-500 on #0f172a yields 3.75:1 (below 4.5:1 for 10px text)"
        )


def print_summary_table(results: List[Dict[str, Any]]):
    print("\n" + "=" * 90)
    print("EMPIRICAL WCAG 2.1 CONTRAST AUDIT MATRIX - MILESTONE 1 CHALLENGER REPORT")
    print("=" * 90)
    print(f"{'Category / Element':<38} | {'Bg':<9} | {'Fg':<9} | {'Ratio':<7} | {'Req':<5} | {'Verdict':<8}")
    print("-" * 90)
    
    fails = []
    passes = 0
    for r in results:
        status = "PASS" if r["passed"] else "FAIL"
        if not r["passed"]:
            fails.append(r)
        else:
            passes += 1
        name = f"[{r['category']}] {r['item']}"
        if len(name) > 37:
            name = name[:34] + "..."
        print(f"{name:<38} | {r['bg']:<9} | {r['fg']:<9} | {r['ratio']:>5.2f}:1 | {r['required']:>3.1f}:1 | {status:<8}")

    print("=" * 90)
    print(f"TOTAL AUDITED PAIRS: {len(results)} | PASSING: {passes} | FAILING: {len(fails)}")
    print("=" * 90)
    
    if fails:
        print("\n[CRITICAL ADVERSARIAL FINDINGS / DEFECTS DETECTED]:")
        for f in fails:
            print(f"  • {f['item']}: {f['ratio']}:1 (Required: {f['required']}:1)")
            print(f"    Details: {f['notes']}")
    print("\n")


if __name__ == "__main__":
    suite = unittest.TestLoader().loadTestsFromTestCase(TestMilestone1ContrastAdversarial)
    runner = unittest.TextTestRunner(verbosity=1)
    result = runner.run(suite)
    
    # Save JSON report
    report_file = PROJECT_ROOT / "tests" / "m1_challenger_empirical_report.json"
    with open(report_file, "w", encoding="utf-8") as f:
        json.dump(TestMilestone1ContrastAdversarial.audit_results, f, indent=2)
    print(f"\n[REPORT SAVED] Empirical contrast audit saved to {report_file}")

    print_summary_table(TestMilestone1ContrastAdversarial.audit_results)
