# Project: Conecta Egresso (SEJUS/ES) - Dark Mode UI/UX Redesign, Contrast Bug Remediation & WCAG 2.1 AA Theme Catalog

## Architecture
- **Framework & Runtime**: Laravel 11 (PHP 8.2+), Inertia.js 1.2+, Vue 3.4+ (Composition API `<script setup>`), Tailwind CSS 3.4.4 (`darkMode: 'class'`), Vite 5.3+.
- **Theming Architecture**:
  - Semantic CSS Variables mapped by theme attribute: `html.dark[data-theme="<theme_id>"]` in `resources/css/app.css`.
  - Master Composable `resources/js/Composables/useTheme.js` controlling reactive theme state, dark mode toggle, DOM sync (`data-theme` attribute and `.dark` class on `document.documentElement`), and persistence in `localStorage['conecta_theme_mode']` / `localStorage['conecta_active_theme']`.
  - Coexistence with High Contrast mode: `.high-contrast` rules in `app.css` maintain absolute accessibility priority when enabled, seamlessly restoring the active dark theme when disabled.
- **Visual Design Standard**:
  - 6 authoritative, government-grade dark themes tailored for SEJUS/ES (Executive Slate, Midnight Navy, Forest Emerald, Deep Titanium, Royal Indigo, Obsidian Gold).
  - Clean institutional aesthetic: zero generic AI neon/magenta borders, zero uncalibrated muddy grays.
  - Strict compliance with WCAG 2.1 AA (normal text contrast $\ge 4.5:1$, large text and UI components $\ge 3.0:1$; majority achieving AAA $\ge 7:1$).
- **Component Boundaries**:
  - `AppLayout.vue`: Sticky top header, global search bar, responsive sidebar navigation (285px width), header quick theme switcher, user Gov.br badge.
  - `Dashboard.vue`: KPI metric cards, Executive Banner, Recent Activity Trail (`Trilha de Atividades Recentes`), reactive canvas charts.
  - `CatalogoTemas.vue`: Dedicated interactive showcase displaying all 6 themes side-by-side with palette swatches, WCAG contrast scorecards, live mini-dashboard previews, and instant theme activation.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Theme Tokens & CSS Variable System | Define semantic CSS variables in `resources/css/app.css` for 6 themes using `html.dark[data-theme="..."]`, plus fix high-contrast input/opacity specificity | M1 | ORIGINAL_REQUEST §R1, §R3 |
| 2 | useTheme Composable | Create reactive Vue 3 composable managing theme state, dark mode toggle, DOM sync, and localStorage persistence | M1 | ORIGINAL_REQUEST §R1 |
| 3 | Header Superior Contrast Fix | Harmonize `AppLayout.vue:12` header with dark body and sidebar (`dark:bg-slate-900/95 dark:border-slate-800/80`), eliminating white header on dark body | M1 | ORIGINAL_REQUEST §R2 |
| 4 | Global Search Field Contrast Fix | Fix `AppLayout.vue:48-56` search input with explicit text, placeholder, and dark background, eliminating yellow text on white (1.07:1) | M1 | ORIGINAL_REQUEST §R2 |
| 5 | Sidebar Label Truncation Fix | Expand sidebar to `w-[285px]`, add `title` tooltips, use `flex-1 min-w-0`, eliminating truncation of navigation labels even under zoom | M1 | ORIGINAL_REQUEST §R2 |
| 6 | Trilha de Atividades Recentes Contrast Fix | Harmonize `Dashboard.vue:159-195` activity cards, icons, and badges with dark theme tokens, eliminating washed-out off-white boxes | M1 | ORIGINAL_REQUEST §R2 |
| 7 | Canvas Charts Dark Mode Reactivity | Update `ChartBar.vue` and `ChartDonut.vue` to observe dark mode state and redraw with theme-aware gridlines, labels, and center hole | M1 | Survey Discovery |
| 8 | Global Header Quick Theme Switcher | Implement dropdown switcher in `AppLayout.vue` header next to `AccessibilityToolbar` with live preview and switching of all 6 themes | M2 | ORIGINAL_REQUEST §R1 |
| 9 | Interactive Theme Catalog Page | Create `/catalogo-temas` route and `Pages/CatalogoTemas.vue` showcasing the 6 themes with swatches, WCAG scorecards, live mini-previews, and activation | M2 | ORIGINAL_REQUEST §R1 |
| 10 | Sidebar Link for Theme Catalog | Add "Catálogo de Temas" navigation link with `Palette` icon in `AppLayout.vue` sidebar menu | M2 | ORIGINAL_REQUEST §R1 |
| 11 | Automated WCAG 2.1 Contrast Runner | Implement `tests/verify_wcag_contrast.js` calculating W3C relative luminance and contrast ratio for all 114 color pairs (exit code 0/1) | M3 | ORIGINAL_REQUEST §R3 |
| 12 | NPM & CI Test Script Integration | Add `"test:wcag"` script to `package.json` and generate structured JSON report in `tests/reports/wcag_contrast_report.json` | M3 | ORIGINAL_REQUEST §R3 |
| 13 | E2E Automated Verification Test Suite | Create automated tests verifying catalog route, live switching, localStorage persistence, and accessibility bounds | M3 | ORIGINAL_REQUEST §Acceptance Criteria |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Theme Engine Foundation & Dashboard Visual Contrast Bug Fixes (R2) | `app.css` tokens, `useTheme.js`, `AppLayout.vue` header, search input, sidebar width, `Dashboard.vue` recent activities, chart reactivity | none | DONE |
| 2 | Interactive Theme Catalog & Demonstration Showcase (R1) | `/catalogo-temas` route in `routes/web.php`, `Pages/CatalogoTemas.vue` interactive page with 6 themes, live mini-previews, header switcher & sidebar link | M1 | IN_PROGRESS |
| 3 | Automated WCAG 2.1 AA Contrast Verification Engine & E2E Validation (R3) | `tests/verify_wcag_contrast.js` W3C algorithm, `npm run test:wcag`, E2E automated test suite, CI report generation | M1, M2 | PLANNED |

## Interface Contracts

### Theme Management API (`useTheme.js`)
- `currentTheme: Ref<string>` — Active theme ID: `'executive-slate'` (default), `'midnight-navy'`, `'forest-emerald'`, `'deep-titanium'`, `'royal-indigo'`, `'obsidian-gold'`.
- `isDarkMode: Ref<boolean>` — True when dark mode is enabled.
- `AVAILABLE_THEMES: Array<{ id: string, name: string, subtitle: string, accent: string, bgCard: string, bgMain: string }>` — Master metadata registry.
- `applyTheme(themeId: string): void` — Applies `data-theme="themeId"` and adds `.dark` to `document.documentElement`, saving to `localStorage`.
- `toggleDarkMode(): void` — Toggles between light mode and the active dark theme.
- `initTheme(): void` — Restores saved theme from `localStorage` on initial mount, defaulting safely to `executive-slate`.

### CSS Token Contract (`resources/css/app.css`)
Each theme defines the following standard CSS custom properties under `html.dark[data-theme="<id>"]`:
```css
--bg-main: <hex>;            /* Overall page background */
--bg-card: <hex>;            /* Cards, containers, panels */
--bg-header: <hex>;          /* Top header bar */
--bg-sidebar: <hex>;         /* Sidebar navigation background */
--border-color: <hex>;        /* Dividers and borders (>= 3.0:1) */
--input-bg: <hex>;           /* Form inputs, search bar */
--input-border: <hex>;       /* Input resting border (>= 3.0:1) */
--input-border-focus: <hex>; /* Input focus outline (>= 3.0:1) */
--text-primary: <hex>;       /* Headings, metrics, active text (>= 4.5:1) */
--text-secondary: <hex>;     /* Body text, labels (>= 4.5:1) */
--text-muted: <hex>;         /* Placeholders, captions, timestamps (>= 4.5:1) */
--accent-primary: <hex>;     /* Primary button background (>= 4.5:1 against #FFFFFF) */
--accent-hover: <hex>;       /* Button hover state */
--accent-text: <hex>;        /* Highlight links and text accents (>= 4.5:1) */
--status-success: <hex>;     /* Positive KPIs, active badges */
--status-warning: <hex>;     /* Alerts, pending tasks */
--status-danger: <hex>;      /* Errors, critical items */
--status-info: <hex>;         /* Info badges and chips */
```

### Route & Navigation Contract
- `GET /catalogo-temas` -> Inertia::render('CatalogoTemas')
- Sidebar Nav Item: `{ name: 'Catálogo de Temas', route: 'catalogo.temas', href: '/catalogo-temas', icon: Palette, dataView: 'catalogo-temas' }`

## Code Layout
- `resources/css/app.css` - Theme tokens, CSS variables, high-contrast override hardening
- `resources/js/Composables/useTheme.js` - Reactive theme state and persistence composable
- `resources/views/app.blade.php` - Blade layout wrapper with responsive dark body classes
- `resources/js/Layouts/AppLayout.vue` - Header, search bar, sidebar width, theme switcher
- `resources/js/Pages/Dashboard.vue` - Dashboard cards, Trilha de Atividades Recentes
- `resources/js/Components/ChartBar.vue` - Dark-aware bar chart canvas
- `resources/js/Components/ChartDonut.vue` - Dark-aware donut chart canvas
- `resources/js/Pages/CatalogoTemas.vue` - Interactive 6-theme catalog showcase with live previews
- `routes/web.php` - Web routes including `/catalogo-temas`
- `tests/verify_wcag_contrast.js` - Automated W3C relative luminance & contrast ratio test engine
- `tests/reports/wcag_contrast_report.json` - Generated CI contrast audit artifact
- `tests_e2e/tier1_features/test_dark_mode_catalog.py` - E2E verification test suite
