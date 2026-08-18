# M5 Frontend Investigation Report: Global Shell, Accessibility Toolbar & Public Token Validation

**Explorer ID**: Explorer M5-2  
**Milestone**: M5 — Reactive & Accessible Frontend (Inertia.js + Vue 3)  
**Date**: 2026-08-17  
**Status**: COMPLETE (Read-Only Investigation)  

---

## 1. Observation

Direct observations from authoritative specifications and codebase analysis:

### 1.1 Specification & Requirement Citations
- **`ORIGINAL_REQUEST.md` (R3 & Acceptance Criteria)**:
  - Frontend reativo em Inertia.js + Vue 3 + TailwindCSS.
  - Requisitos estritos de acessibilidade: Alto Contraste nativo (`.high-contrast`), ampliação de fonte (+18% zoom), e modo de Linguagem Simplificada (*Linguagem Fácil* para pessoas com baixo letramento digital).
  - Emissão de Carteira Digital com QR Code criptográfico HMAC-SHA256 e rota pública de verificação `/validar-carteira/{hash}`.
  - Alternância entre 3 perfis RBAC: **Gestor SEJUS**, **Técnico Escritório Social**, **Egresso/Familiar**.

- **`PROJECT.md` (Milestone M5 & Feature Inventory)**:
  - `F34`: Inertia.js + Vue 3 scaffolding com TailwindCSS.
  - `F35`: Global Layout com identidade institucional SEJUS/ES, navegação lateral responsiva, dados do usuário e seletor rápido de perfis.
  - `F36`: Accessibility Toolbar: Alto Contraste (`.high-contrast`).
  - `F37`: Accessibility Toolbar: Escalonamento de fonte (+18% zoom, step-based, range 100% a 150%).
  - `F38`: Accessibility Toolbar: Modo Linguagem Fácil (*Linguagem Simplificada* / `.simplified-lang`).
  - `F47`: Página pública de validação de credenciais (`/validar-carteira/{token}`).

- **`SCOPE.md` (.agents/sub_orch_m5_frontend/SCOPE.md)**:
  - Framework: Vue 3 `<script setup>` Composition API, Inertia.js client (`@inertiajs/vue3`).
  - Paleta institucional Governo do Estado do Espírito Santo: Verde SEJUS `#00875A` / `#047857`, Azul Estado `#0052CC` / `#003366`, Azul Celeste `#38bdf8`, Slate Neutro (`#0f172a` a `#f8fafc`).
  - Conformidade WCAG 2.1 AA e e-MAG (Modelo de Acessibilidade em Governo Eletrônico).

### 1.2 Existing Codebase Inspection
- **`app/Http/Controllers/CarteiraValidationController.php`**:
  - Endpoint público web: `GET /validar-carteira/{token}` e `GET /validar-carteira` (query param `?token=`).
  - Endpoint API JSON: `GET /api/validar-carteira/{token}` (`validarApi`).
  - Auditoria imutável via `AuditService::log($prontuarioId, 'VALIDATE_QR', ...)` capturando IP e User-Agent.
  - `QrCodeSecurityService::verifyToken($token)` retorna estrutura:
    - `valid`: bool
    - `status`: `'VALID_DOCUMENT'` | `'EXPIRED_DOCUMENT'` | `'TAMPERED_DOCUMENT'` | `'MALFORMED_TOKEN'` | `'INVALID_STRUCTURE'`
    - `message`: Descrição legível
    - `payload`: `['doc_id', 'registro_sejus', 'cpf_masked', 'nome', 'municipio', 'issued_at', 'expires_at', 'legal_basis']`

- **`app/Services/QrCodeSecurityService.php`**:
  - Assinatura HMAC-SHA256 canônica sobre JSON ordenado por chaves (`ksort`).
  - Token gerado como base64 URL-safe de `{"p": payload, "s": signature}` ou formato `<payload_b64>.<signature>`.

- **`styles.css` & `index.html` (Existing Prototype Benchmarks)**:
  - Tokens CSS definidos: `--bg-main: #f4f7fb`, `--bg-card: #ffffff`, `--bg-sidebar: #0f172a`, `--es-blue: #003366`, `--primary: #0284c7`, `--font-scale: 1`.
  - Alto contraste define `--bg-main: #000000`, `--bg-card: #121212`, `--bg-sidebar: #000000`, `--text-main: #ffffff`, `--primary: #00ffff`.
  - Linguagem simplificada aplica `--font-scale: 1.18` e `letter-spacing: 0.02em`.

### 1.3 E2E Test Suite Expectations
- **`tests_e2e/tier1_features/test_f34_f47_frontend_views.py`**:
  - Testa presença de classes e identificadores: `top-header`, `sidebar`, `userRoleSelect` com opções `gestor`, `tecnico`, `egresso`, `contrastBtn` (`.high-contrast`), `fontSizeBtn` (`1.18`), `simplifiedTextBtn` (`.simplified-lang`), e rota `/validar-carteira/{hash}`.
- **`tests_e2e/tier2_boundaries/test_frontend_a11y_limits.py`**:
  - `test_01_rapid_toggling_high_contrast_mode_state_persistence`: 50 toggles mantêm sincronismo com `localStorage['conecta_high_contrast']`.
  - `test_02_font_zoom_level_limits`: Zoom step 0.18, clamp estrito entre `1.00` (100%) e `1.50` (150%).
  - `test_03_simplified_language_mode_fallback_on_missing_key`: Dicionário `pt-BR-facil` com fallback gracioso para `pt-BR` padrão e `[key]` sem crash.
  - `test_04_viewport_boundary_responsiveness_metrics`: Touch targets >= 44x44px em telas móveis (< 1024px) por WCAG 2.5.5.
  - `test_06_missing_user_profile_prop_handling_in_ui_navbar`: Props de usuário nulas/vazias geram fallback sem `TypeError` ("Usuário Convidado", "UC", "Visitante").
  - `test_07_wcag_aaa_high_contrast_ratio_boundaries`: Contraste de texto >= 7.0:1 (Amarelo `#FFFF00` e Branco `#FFFFFF` sobre Fundo Preto `#000000`).
- **`tests_e2e/tier3_combinations/test_pdf_qr_validation_chain.py`** & **`scenario_egresso_onboarding_wallet.py`**:
  - Validação pública de carteira exibe selo de autenticidade (`SEJUS-VALID-...`), dados mascarados, status ativo/revogado/expirado, e contagem de verificações.

---

## 2. Logic Chain

From the observed requirements and test constraints, the following architectural inferences and design decisions are derived:

```
[Institutional Req: SEJUS/ES + WCAG 2.1 AA + e-MAG]
                       │
       ┌───────────────┼───────────────┐
       ▼               ▼               ▼
[Accessibility]  [Global Shell]  [Public Validation]
 - High Contrast  - Header/Logo   - Token Route / Form
 - Font Zoom      - Sidebar Nav   - State Engine (Valid/
 - Linguagem Fácil - Role Switcher   Expired/Revoked/Tampered)
 - State Composable- User Avatar   - Accessible Result Card
```

1. **State Persistence & Composable Pattern (`useAccessibility`)**:
   - Accessibility preferences must not be lost upon Inertia page transitions or full reloads.
   - A reactive composable `useAccessibility.js` centrally manages state and writes to `localStorage` (`conecta_high_contrast`, `conecta_font_zoom`, `conecta_simplified_language`).
   - The composable directly manipulates `document.documentElement.classList` (`high-contrast`, `simplified-lang`) and CSS property `--font-scale`.

2. **Defensive Component Design in Global Shell (`AppLayout.vue`)**:
   - Inertia passes `$page.props.auth.user` and `$page.props.auth.role`.
   - The shell must handle scenarios where `auth.user` is null, unauthenticated, or has aliased property names (`name` vs `nome`, `role` vs `perfil`) by calculating initials and display badges defensively.
   - The Role Switcher provides both visual simulation and functional reactive gating of navigation items (Gestor sees reports/analytics; Egresso sees simplified job/wallet/support portals; Técnico sees triage and queue).

3. **Public Token Validation (`ValidarCarteira.vue`)**:
   - Must operate seamlessly as a standalone public view (without requiring user authentication) or within the standard application container.
   - Supports two entry points:
     1. Direct QR Code scan URL: `/validar-carteira/{token}`
     2. Manual lookup: User arrives at `/validar-carteira` and pastes token into search box.
   - Evaluates the 5 cryptographic states (`VALID_DOCUMENT`, `EXPIRED_DOCUMENT`, `REVOKED_DOCUMENT`, `TAMPERED_DOCUMENT`, `MALFORMED_TOKEN`) and presents e-MAG compliant status cards with clear icons, colors, and legal citations (Lei Complementar Estadual nº 182/2021).

---

## 3. Detailed Component Specifications & Interfaces

### 3.1 `useAccessibility.js` (State Composable)

**File Path**: `resources/js/Composables/useAccessibility.js`

```javascript
/**
 * Central Accessibility Composable for Conecta Egresso (SEJUS/ES)
 * Conforms to WCAG 2.1 AA / AAA and e-MAG guidelines.
 */
import { ref, reactive, computed, watch, onMounted } from 'vue'

const MIN_ZOOM = 1.00
const MAX_ZOOM = 1.50
const ZOOM_STEP = 0.18

// Shared singleton state across all component instances
const highContrast = ref(false)
const fontZoom = ref(1.00)
const simplifiedLanguage = ref(false)

// Simplified Language Dictionary with fallback engine
const dictionary = {
  'pt-BR': {
    dashboard_title: 'Painel de Gestão e Monitoramento de Egressos',
    atendimento_title: 'Atendimento Remoto e Videochamadas Seguras',
    oportunidades_title: 'Painel de Oportunidades & Qualificação Profissional',
    carteira_title: 'Carteira de Identificação Digital do Egresso',
    geolocalizacao_title: 'Mapeamento Territorial dos 78 Municípios do ES',
    prontuario_title: 'Prontuário Único do Egresso & Registros Automáticos',
    relatorios_title: 'Relatórios Sintéticos & Detalhados SEJUS',
    seguranca_title: 'Segurança da Informação, LGPD & Níveis de Acesso',
    prontuario_evolution: 'Registro de Evolução Técnica Multidisciplinar',
    affirmative_vacancy: 'Vaga Afirmativa com Cota Legal para Reintegração',
    audiencia_custodia: 'Audiência de Custódia e Acompanhamento Penal',
    validation_status_valid: 'Documento Oficial Autêntico e Homologado pela SEJUS/ES',
  },
  'pt-BR-facil': {
    dashboard_title: 'Página Principal',
    atendimento_title: 'Conversa em Vídeo com Assistente Social',
    oportunidades_title: 'Vagas de Trabalho e Cursos Gratuitos',
    carteira_title: 'Seu Documento Digital',
    geolocalizacao_title: 'Ajuda e Serviços Perto de Você',
    prontuario_title: 'Seu Histórico de Atendimentos',
    relatorios_title: 'Resumo das Atividades',
    seguranca_title: 'Proteção dos Seus Dados (LGPD)',
    prontuario_evolution: 'Anotações do seu Atendimento',
    affirmative_vacancy: 'Vaga de Trabalho Reservada para Você',
    validation_status_valid: 'Documento Verdadeiro e Válido',
  }
}

export function useAccessibility() {
  const initAccessibility = () => {
    if (typeof window === 'undefined') return

    // 1. High Contrast
    const savedContrast = localStorage.getItem('conecta_high_contrast')
    highContrast.value = savedContrast === 'true'
    applyHighContrast(highContrast.value)

    // 2. Font Zoom
    const savedZoom = parseFloat(localStorage.getItem('conecta_font_zoom') || '1.00')
    fontZoom.value = isNaN(savedZoom) ? 1.00 : clampZoom(savedZoom)
    applyFontZoom(fontZoom.value)

    // 3. Simplified Language
    const savedSimplified = localStorage.getItem('conecta_simplified_language')
    simplifiedLanguage.value = savedSimplified === 'true'
    applySimplifiedLanguage(simplifiedLanguage.value)
  }

  const clampZoom = (val) => {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round(val * 100) / 100))
  }

  const applyHighContrast = (active) => {
    if (typeof document === 'undefined') return
    if (active) {
      document.documentElement.classList.add('high-contrast')
      document.body.classList.add('high-contrast')
    } else {
      document.documentElement.classList.remove('high-contrast')
      document.body.classList.remove('high-contrast')
    }
    localStorage.setItem('conecta_high_contrast', active ? 'true' : 'false')
  }

  const applyFontZoom = (val) => {
    if (typeof document === 'undefined') return
    const clamped = clampZoom(val)
    document.documentElement.style.setProperty('--font-scale', clamped.toString())
    localStorage.setItem('conecta_font_zoom', clamped.toFixed(2))
  }

  const applySimplifiedLanguage = (active) => {
    if (typeof document === 'undefined') return
    if (active) {
      document.documentElement.classList.add('simplified-lang')
      document.body.classList.add('simplified-lang')
    } else {
      document.documentElement.classList.remove('simplified-lang')
      document.body.classList.remove('simplified-lang')
    }
    localStorage.setItem('conecta_simplified_language', active ? 'true' : 'false')
  }

  const toggleHighContrast = () => {
    highContrast.value = !highContrast.value
    applyHighContrast(highContrast.value)
    return highContrast.value
  }

  const zoomIn = () => {
    fontZoom.value = clampZoom(fontZoom.value + ZOOM_STEP)
    applyFontZoom(fontZoom.value)
    return fontZoom.value
  }

  const zoomOut = () => {
    fontZoom.value = clampZoom(fontZoom.value - ZOOM_STEP)
    applyFontZoom(fontZoom.value)
    return fontZoom.value
  }

  const resetZoom = () => {
    fontZoom.value = 1.00
    applyFontZoom(1.00)
    return 1.00
  }

  const toggleSimplifiedLanguage = () => {
    simplifiedLanguage.value = !simplifiedLanguage.value
    applySimplifiedLanguage(simplifiedLanguage.value)
    return simplifiedLanguage.value
  }

  const t = (key) => {
    const locale = simplifiedLanguage.value ? 'pt-BR-facil' : 'pt-BR'
    if (dictionary[locale] && dictionary[locale][key]) {
      return dictionary[locale][key]
    }
    if (dictionary['pt-BR'] && dictionary['pt-BR'][key]) {
      return dictionary['pt-BR'][key]
    }
    return `[${key}]`
  }

  return {
    highContrast,
    fontZoom,
    simplifiedLanguage,
    initAccessibility,
    toggleHighContrast,
    zoomIn,
    zoomOut,
    resetZoom,
    toggleSimplifiedLanguage,
    t,
    MIN_ZOOM,
    MAX_ZOOM,
    ZOOM_STEP
  }
}
```

---

### 3.2 `AccessibilityToolbar.vue` Component Interface

**File Path**: `resources/js/Components/AccessibilityToolbar.vue`

#### Component Specification
- **Props**:
  - `floating` (Boolean, default: `false`): Render as compact header strip or floating fixed utility bar.
  - `showLabels` (Boolean, default: `true`): Show text alongside icons for maximum cognitive clarity.
- **Emits**:
  - `@contrast-change(isHighContrast: boolean)`
  - `@zoom-change(currentScale: number)`
  - `@simplified-change(isSimplified: boolean)`
- **Template Structure**:
  ```html
  <nav class="accessibility-toolbar" aria-label="Ferramentas de Acessibilidade">
    <!-- Alto Contraste Toggle -->
    <button 
      id="contrastBtn"
      type="button"
      class="a11y-btn"
      :class="{ 'active': highContrast }"
      :aria-pressed="highContrast"
      title="Ativar ou desativar modo de Alto Contraste (Alt+C)"
      @click="handleToggleContrast"
    >
      <span class="a11y-icon" aria-hidden="true">👁️</span>
      <span v-if="showLabels" class="a11y-label">Alto Contraste</span>
    </button>

    <!-- Font Size Controls (A-, A, A+) -->
    <div class="a11y-zoom-group" role="group" aria-label="Controle de tamanho do texto">
      <button
        id="fontZoomOutBtn"
        type="button"
        class="a11y-btn a11y-btn-sm"
        :disabled="fontZoom <= MIN_ZOOM"
        title="Diminuir tamanho da fonte"
        aria-label="Diminuir fonte"
        @click="handleZoomOut"
      >
        <span aria-hidden="true">A-</span>
      </button>

      <button
        id="fontSizeBtn"
        type="button"
        class="a11y-btn"
        :class="{ 'active': fontZoom > 1.00 }"
        title="Aumentar tamanho da fonte em 18%"
        aria-label="Aumentar fonte"
        @click="handleZoomIn"
      >
        <span aria-hidden="true">A+</span>
        <span v-if="showLabels && fontZoom > 1.00" class="a11y-badge">+{{ Math.round((fontZoom - 1) * 100) }}%</span>
      </button>

      <button
        v-if="fontZoom > 1.00"
        id="fontResetBtn"
        type="button"
        class="a11y-btn a11y-btn-sm"
        title="Redefinir tamanho da fonte para o padrão"
        aria-label="Redefinir fonte"
        @click="handleResetZoom"
      >
        <span aria-hidden="true">100%</span>
      </button>
    </div>

    <!-- Linguagem Fácil Toggle -->
    <button
      id="simplifiedTextBtn"
      type="button"
      class="a11y-btn"
      :class="{ 'active': simplifiedLanguage }"
      :aria-pressed="simplifiedLanguage"
      title="Ativar modo de Linguagem Fácil para facilitar a compreensão"
      @click="handleToggleSimplified"
    >
      <span class="a11y-icon" aria-hidden="true">💬</span>
      <span v-if="showLabels" class="a11y-label">Linguagem Fácil</span>
    </button>
  </nav>
  ```

---

### 3.3 `AppLayout.vue` Global Shell Architecture

**File Path**: `resources/js/Layouts/AppLayout.vue`

#### Component Specification
- **Subcomponents**:
  - `AccessibilityToolbar` (`resources/js/Components/AccessibilityToolbar.vue`)
  - `RoleSwitcher` (`resources/js/Components/RoleSwitcher.vue`)
  - `Breadcrumbs` (`resources/js/Components/Breadcrumbs.vue`)
  - `FlashMessages` (`resources/js/Components/FlashMessages.vue`)
- **Reactive State & Props**:
  - Uses `usePage().props` to extract `auth.user`, `auth.role`, `flash`, `errors`.
  - `isSidebarCollapsed` (Boolean, reactive ref, default: `false`, auto-collapses on screens < 1024px).
  - `activeRole` (String ref: `'gestor' | 'tecnico' | 'egresso'`, synced with `$page.props.auth.role`).
- **Defensive User Profile Logic**:
  ```javascript
  const user = computed(() => page.props.auth?.user || {})
  const displayName = computed(() => {
    return user.value.name || user.value.nome || 'Usuário Convidado'
  })
  const maskedCpf = computed(() => {
    return user.value.cpf_masked || (user.value.cpf ? `***.${user.value.cpf.slice(3,6)}.${user.value.cpf.slice(6,9)}-**` : '---')
  })
  const userInitials = computed(() => {
    const name = displayName.value.trim()
    if (!name || name === 'Usuário Convidado') return 'UC'
    const parts = name.split(' ')
    return parts.length >= 2 ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase() : name.slice(0, 2).toUpperCase()
  })
  ```
- **Navigation Schema & RBAC Gating**:
  ```javascript
  const navigationItems = computed(() => [
    {
      name: 'Dashboard & KPIs',
      route: 'dashboard',
      href: '/dashboard',
      icon: 'LayoutDashboard',
      roles: ['gestor', 'tecnico', 'egresso'],
      badge: 'Principal',
      badgeClass: 'bg-blue-600',
      dataView: 'dashboard'
    },
    {
      name: 'Atendimento Remoto & Vídeo',
      route: 'atendimento',
      href: '/atendimento',
      icon: 'Video',
      roles: ['gestor', 'tecnico', 'egresso'],
      badge: '3 em espera',
      badgeClass: 'bg-emerald-600',
      dataView: 'atendimento'
    },
    {
      name: 'Oportunidades & Trabalho',
      route: 'oportunidades',
      href: '/oportunidades',
      icon: 'Briefcase',
      roles: ['gestor', 'tecnico', 'egresso'],
      badge: '42 Vagas',
      badgeClass: 'bg-purple-600',
      dataView: 'oportunidades'
    },
    {
      name: 'Carteira Digital & Documentos',
      route: 'carteira',
      href: '/carteira',
      icon: 'CreditCard',
      roles: ['gestor', 'tecnico', 'egresso'],
      dataView: 'carteira'
    },
    {
      name: 'Mapeamento dos 78 Municípios',
      route: 'geolocalizacao',
      href: '/geolocalizacao',
      icon: 'MapPin',
      roles: ['gestor', 'tecnico', 'egresso'],
      dataView: 'geolocalizacao'
    },
    {
      name: 'Prontuário & Histórico',
      route: 'prontuario',
      href: '/prontuario',
      icon: 'FileText',
      roles: ['gestor', 'tecnico', 'egresso'],
      dataView: 'prontuario'
    },
    // GESTÃO & GOVERNANÇA (Restricted to Gestor / Técnico)
    {
      name: 'Relatórios & Análise SEJUS',
      route: 'relatorios',
      href: '/relatorios',
      icon: 'BarChart3',
      roles: ['gestor', 'tecnico'],
      section: 'GESTÃO & GOVERNANÇA',
      dataView: 'relatorios'
    },
    {
      name: 'Segurança & LGPD',
      route: 'seguranca-lgpd',
      href: '/seguranca-lgpd',
      icon: 'ShieldCheck',
      roles: ['gestor', 'tecnico', 'egresso'],
      section: 'GESTÃO & GOVERNANÇA',
      dataView: 'lgpd'
    }
  ])
  ```
- **Shell Layout Structure**:
  ```html
  <div id="app" class="app-container min-h-screen flex flex-col bg-slate-50 text-slate-900">
    <!-- Skip to Main Content Link (e-MAG / WCAG) -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:p-4 focus:bg-white focus:z-50">
      Pular para o conteúdo principal
    </a>

    <!-- Top Header Bar -->
    <header class="top-header sticky top-0 z-40 bg-white border-b border-slate-200 h-[70px] px-6 flex items-center justify-between shadow-sm">
      <!-- Left: Sidebar Toggle & Institutional Logo -->
      <div class="header-left flex items-center gap-4">
        <button 
          id="sidebarToggleBtn" 
          type="button" 
          class="btn-icon p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:ring-2 focus:ring-sky-500" 
          aria-label="Alternar Menu Lateral"
          @click="isSidebarCollapsed = !isSidebarCollapsed"
        >
          <MenuIcon class="w-6 h-6" />
        </button>

        <!-- Official Espírito Santo Flag Badge & Brand Title -->
        <div class="brand-logo flex items-center gap-3">
          <div class="es-flag-badge w-6 h-7 rounded overflow-hidden flex flex-col shadow-sm" aria-hidden="true">
            <span class="stripe-pink h-1/3 bg-[#e63946]"></span>
            <span class="stripe-white h-1/3 bg-[#ffffff]"></span>
            <span class="stripe-blue h-1/3 bg-[#003366]"></span>
          </div>
          <div class="brand-text">
            <h1 class="app-title font-extrabold text-lg tracking-tight leading-none text-[#003366]">
              CONECTA <span class="highlight text-sky-600">EGRESSO</span>
            </h1>
            <span class="app-subtitle text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
              SEJUS • Governo do Estado do Espírito Santo
            </span>
          </div>
        </div>
      </div>

      <!-- Center: Global Search Input -->
      <div class="header-center flex-1 max-w-lg mx-6 hidden md:block">
        <div class="search-box relative">
          <SearchIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input 
            id="globalSearchInput" 
            type="text" 
            placeholder="Buscar egressos, atendimentos, vagas ou serviços nos 78 municípios..." 
            class="w-full pl-9 pr-4 py-2 text-sm bg-slate-100 border border-slate-200 rounded-full focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
          />
        </div>
      </div>

      <!-- Right: Accessibility Toolbar, Role Switcher, and Gov.br Auth Badge -->
      <div class="header-right flex items-center gap-4">
        <!-- Accessibility Toolbar Component -->
        <AccessibilityToolbar :show-labels="false" />

        <!-- Role Switcher Component -->
        <div class="profile-switcher">
          <label for="userRoleSelect" class="sr-only">Perfil de Acesso</label>
          <div class="role-badge flex items-center gap-2 bg-sky-50 border border-sky-200 text-sky-700 px-3 py-1.5 rounded-full text-xs font-bold">
            <UserCircleIcon class="w-4 h-4" />
            <select 
              id="userRoleSelect" 
              v-model="activeRole"
              class="bg-transparent border-none text-sky-800 font-bold text-xs outline-none cursor-pointer"
              @change="handleRoleSwitch"
            >
              <option value="gestor">Perfil: Gestor SEJUS</option>
              <option value="tecnico">Perfil: Técnico Escritório Social</option>
              <option value="egresso">Perfil: Egresso / Familiar</option>
            </select>
          </div>
        </div>

        <!-- Gov.br User Card -->
        <div class="govbr-user-card flex items-center gap-3 pl-3 border-l border-slate-200">
          <span class="govbr-badge bg-[#1351b4] text-white text-[11px] font-extrabold px-2 py-0.5 rounded">gov.br</span>
          <div class="user-info text-left hidden lg:block">
            <span id="userNameHeader" class="user-name font-bold text-xs text-slate-800 block leading-tight">{{ displayName }}</span>
            <span id="userCpfHeader" class="user-cpf text-[11px] text-slate-500 block">CPF: {{ maskedCpf }}</span>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Layout (Sidebar + Content Slot) -->
    <div class="main-layout flex flex-1">
      <!-- Responsive Navigation Sidebar -->
      <aside 
        id="sidebar" 
        class="sidebar bg-[#0f172a] text-slate-300 transition-all duration-300 flex flex-col border-r border-slate-800"
        :class="isSidebarCollapsed ? 'w-[70px]' : 'w-[270px]'"
      >
        <!-- User Summary -->
        <div class="sidebar-user-summary p-4 flex items-center gap-3 border-b border-slate-800/80">
          <div id="sidebarAvatar" class="user-avatar w-10 h-10 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-900 text-white font-extrabold flex items-center justify-center text-sm border-2 border-white/20">
            {{ userInitials }}
          </div>
          <div v-if="!isSidebarCollapsed" class="user-details text-left">
            <strong id="sidebarRoleTitle" class="text-white text-xs block font-bold">
              {{ activeRole === 'gestor' ? 'Visão Gestor Estadual' : (activeRole === 'tecnico' ? 'Técnico Escritório Social' : 'Egresso / Familiar') }}
            </strong>
            <small id="sidebarRoleScope" class="text-slate-400 text-[11px] block">
              {{ activeRole === 'gestor' ? '78 Municípios • SEJUS/ES' : (activeRole === 'tecnico' ? 'Atendimento Remoto / Presencial' : 'Acesso Cidadão Remoto') }}
            </small>
          </div>
        </div>

        <!-- Sidebar Navigation List -->
        <nav class="sidebar-nav p-3 flex-1 flex flex-col gap-1 overflow-y-auto" aria-label="Navegação Lateral">
          <template v-for="item in visibleNavigationItems" :key="item.name">
            <div v-if="item.sectionHeader && !isSidebarCollapsed" class="nav-section-title text-[10px] font-extrabold uppercase tracking-wider text-slate-500 px-3 pt-4 pb-1">
              {{ item.sectionHeader }}
            </div>

            <Link 
              :href="item.href" 
              class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition text-slate-300 hover:bg-white/10 hover:text-white"
              :class="{ 'active bg-sky-600 text-white font-semibold shadow-md shadow-sky-600/30': isRouteActive(item.route) }"
              :data-view="item.dataView"
            >
              <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">{{ item.name }}</span>
              <span 
                v-if="!isSidebarCollapsed && item.badge" 
                class="badge-nav ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full text-white"
                :class="item.badgeClass"
              >
                {{ item.badge }}
              </span>
            </Link>
          </template>
        </nav>

        <!-- Sidebar Footer Seal -->
        <div v-if="!isSidebarCollapsed" class="sidebar-footer p-4 border-t border-slate-800">
          <div class="es-seal flex items-center gap-3 bg-white/5 p-2.5 rounded-lg">
            <span class="seal-icon text-lg">🏛️</span>
            <div>
              <strong class="text-white text-xs block">SEJUS / SEGER</strong>
              <p class="text-[11px] text-slate-400">Escritório Social Digital</p>
            </div>
          </div>
        </div>
      </aside>

      <!-- Content Area -->
      <main id="main-content" class="content-area flex-1 p-6 md:p-8 overflow-y-auto">
        <!-- Breadcrumbs Navigation -->
        <Breadcrumbs />

        <!-- Flash Messages & Alerts -->
        <FlashMessages />

        <!-- View Page Slot -->
        <slot />
      </main>
    </div>
  </div>
  ```

---

### 3.4 `ValidarCarteira.vue` Public Validation Component

**File Path**: `resources/js/Pages/ValidarCarteira.vue`  
**Route**: `GET /validar-carteira/{token?}`

#### Component Specification
- **Props**:
  - `result` (Object | null): The validation response payload from `CarteiraValidationController`:
    - `valid` (Boolean)
    - `status` (String: `'VALID_DOCUMENT'` | `'EXPIRED_DOCUMENT'` | `'REVOKED_DOCUMENT'` | `'TAMPERED_DOCUMENT'` | `'MALFORMED_TOKEN'` | `'NOT_FOUND'`)
    - `message` (String)
    - `payload` (Object: `doc_id`, `registro_sejus`, `cpf_masked`, `nome`, `municipio`, `issued_at`, `expires_at`, `legal_basis`)
    - `verification_count` (Number, optional)
  - `token` (String | null): The token passed in route parameter.
- **Local Reactive State**:
  - `inputToken` (String ref): For manual token paste/input if arrived at `/validar-carteira` without param.
  - `isSearching` (Boolean ref).
- **Template Layout**:
  ```html
  <div class="min-h-screen bg-slate-100 flex flex-col justify-center items-center p-4 md:p-6 font-sans">
    <!-- Top Access Bar -->
    <div class="w-full max-w-xl flex justify-between items-center mb-4 text-xs text-slate-600">
      <Link href="/" class="hover:underline flex items-center gap-1 font-semibold text-slate-700">
        ← Voltar para o Portal Conecta Egresso
      </Link>
      <AccessibilityToolbar :show-labels="false" />
    </div>

    <!-- Official Validation Card -->
    <div class="validation-card bg-white rounded-2xl border border-slate-200 shadow-xl max-w-xl w-full overflow-hidden">
      <!-- Header with State Branding -->
      <div class="card-header bg-[#0f172a] text-white p-6 text-center relative">
        <div class="flex items-center justify-center gap-2 mb-2">
          <div class="es-flag-badge w-5 h-6 rounded flex flex-col overflow-hidden shadow-sm" aria-hidden="true">
            <span class="stripe-pink h-1/3 bg-[#e63946]"></span>
            <span class="stripe-white h-1/3 bg-[#ffffff]"></span>
            <span class="stripe-blue h-1/3 bg-[#003366]"></span>
          </div>
          <h1 class="text-sm font-extrabold uppercase tracking-wide text-slate-200">
            Governo do Estado do Espírito Santo
          </h1>
        </div>
        <h2 class="text-xs font-semibold text-sky-400 uppercase tracking-wider">
          Secretaria de Estado da Justiça — SEJUS / Escritório Social Digital
        </h2>
        <p class="text-[11px] text-slate-400 mt-1">
          Validador Público de Autenticidade de Carteira Digital do Egresso
        </p>
      </div>

      <!-- Body Content -->
      <div class="card-body p-6 md:p-8">
        
        <!-- CASE 1: Valid & Authentic Credential -->
        <div v-if="result && result.valid" class="space-y-6">
          <!-- Status Banner -->
          <div class="status-badge status-valid flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl font-bold text-sm" role="status">
            <CheckCircleIcon class="w-6 h-6 text-emerald-600 flex-shrink-0" />
            <div>
              <span class="block font-extrabold text-emerald-900">DOCUMENTO OFICIAL AUTÊNTICO</span>
              <span class="text-xs text-emerald-700 font-medium">{{ result.message || 'Credencial homologada pela SEJUS/ES em conformidade com a Lei Complementar 182/2021.' }}</span>
            </div>
          </div>

          <!-- Document Data Grid -->
          <div class="info-grid grid grid-cols-1 md:grid-cols-2 gap-3" aria-label="Dados da Credencial">
            <div class="info-item bg-slate-50 p-3.5 rounded-lg border-l-4 border-sky-600 col-span-full">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Nome do Titular</span>
              <span class="info-value font-extrabold text-sm text-slate-900 block mt-0.5">{{ result.payload?.nome || '---' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-lg border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">CPF Mascarado (LGPD)</span>
              <span class="info-value font-bold text-sm text-slate-800 block mt-0.5">{{ result.payload?.cpf_masked || '---' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-lg border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Registro Geral SEJUS</span>
              <span class="info-value font-bold text-sm text-slate-800 block mt-0.5">{{ result.payload?.registro_sejus || '---' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-lg border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Município de Referência</span>
              <span class="info-value font-bold text-sm text-slate-800 block mt-0.5">{{ result.payload?.municipio || 'Espírito Santo' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-lg border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Validade do Documento</span>
              <span class="info-value font-bold text-sm text-slate-800 block mt-0.5">
                Até {{ formatDate(result.payload?.expires_at) }}
              </span>
            </div>
          </div>

          <!-- Cryptographic Seal Verification Footer -->
          <div class="crypto-seal-box bg-slate-50 p-3 rounded-lg border border-slate-200 flex items-center justify-between text-xs text-slate-600">
            <div class="flex items-center gap-2">
              <ShieldCheckIcon class="w-5 h-5 text-sky-600" />
              <span>Selo Criptográfico: <strong class="font-mono text-slate-800">{{ getSealFingerprint(token) }}</strong></span>
            </div>
            <span v-if="result.verification_count" class="text-[11px] bg-slate-200 px-2 py-0.5 rounded text-slate-700">
              Consultas: {{ result.verification_count }}
            </span>
          </div>
        </div>

        <!-- CASE 2: Expired Document -->
        <div v-else-if="result && result.status === 'EXPIRED_DOCUMENT'" class="space-y-4" role="alert">
          <div class="status-badge status-expired flex items-center gap-3 p-4 bg-amber-50 border border-amber-300 text-amber-900 rounded-xl font-bold text-sm">
            <AlertTriangleIcon class="w-6 h-6 text-amber-600 flex-shrink-0" />
            <div>
              <span class="block font-extrabold">DOCUMENTO EXPIRADO</span>
              <span class="text-xs text-amber-800 font-medium">{{ result.message || 'A validade oficial de 12 meses foi ultrapassada.' }}</span>
            </div>
          </div>
          <p class="text-xs text-slate-600">
            Solicite a revalidação da credencial junto ao Escritório Social de referência ou através do portal Conecta Egresso.
          </p>
        </div>

        <!-- CASE 3: Revoked Document -->
        <div v-else-if="result && result.status === 'REVOGADO'" class="space-y-4" role="alert">
          <div class="status-badge status-invalid flex items-center gap-3 p-4 bg-red-50 border border-red-300 text-red-900 rounded-xl font-bold text-sm">
            <XCircleIcon class="w-6 h-6 text-red-600 flex-shrink-0" />
            <div>
              <span class="block font-extrabold">DOCUMENTO REVOGADO</span>
              <span class="text-xs text-red-800 font-medium">{{ result.message || 'Documento revogado administrativamente ou judicialmente pela SEJUS/ES.' }}</span>
            </div>
          </div>
          <p v-if="result.motivo_revogacao" class="text-xs text-slate-600">
            Motivo: {{ result.motivo_revogacao }}
          </p>
        </div>

        <!-- CASE 4: Tampered / Invalid Document -->
        <div v-else-if="result && !result.valid" class="space-y-4" role="alert">
          <div class="status-badge status-invalid flex items-center gap-3 p-4 bg-red-50 border border-red-300 text-red-900 rounded-xl font-bold text-sm">
            <XCircleIcon class="w-6 h-6 text-red-600 flex-shrink-0" />
            <div>
              <span class="block font-extrabold">DOCUMENTO INVÁLIDO OU ADULTERADO</span>
              <span class="text-xs text-red-800 font-medium">{{ result.message || 'Assinatura criptográfica incompatível ou documento inexistente.' }}</span>
            </div>
          </div>
          <p class="text-xs text-slate-600">
            Atenção: A falsificação de documentos públicos oficiais constitui crime previsto no Código Penal Brasileiro.
          </p>
        </div>

        <!-- CASE 5: Manual Token Search Form (Empty State) -->
        <div v-else class="space-y-4">
          <p class="text-xs text-slate-600 text-center mb-4">
            Insira o código alfanumérico ou token contido na Carteira Digital para validar sua autenticidade.
          </p>
          <form @submit.prevent="handleManualSearch" class="flex gap-2">
            <input 
              v-model="inputToken" 
              type="text" 
              placeholder="Cole o token de validação aqui..." 
              class="flex-1 px-4 py-2 text-xs border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono"
              required
            />
            <button type="submit" class="px-4 py-2 bg-[#003366] text-white rounded-lg text-xs font-bold hover:bg-[#002244] transition">
              Validar
            </button>
          </form>
        </div>

      </div>

      <!-- Card Footer with Legal Citation -->
      <div class="card-footer bg-slate-50 p-4 border-t border-slate-200 text-center text-[11px] text-slate-500">
        Autenticação Criptográfica Oficial • Amparo na Lei Complementar Estadual nº 182/2021
      </div>
    </div>
  </div>
  ```

---

## 4. Caveats & Architectural Boundaries

1. **State Hydration vs Server-Side RBAC**:
   - The frontend Quick Role Switcher in development/demo allows seamless toggling to test UX views for Gestor, Técnico, and Egresso.
   - However, in production source code, backend route middleware (`RoleMiddleware`, `AuthMiddleware`) remains the authoritative enforcer of database records and mutations.
2. **Simplified Language Coverage**:
   - The simplified dictionary must never cause runtime crashes when a new key is introduced without a simplified translation. It strictly implements the 3-stage fallback: `pt-BR-facil` → `pt-BR` → `[key]`.
3. **High-Contrast CSS Class Isolation**:
   - High contrast styles must be anchored to `:root.high-contrast` and `body.high-contrast` variables, preserving standard light theme when inactive.
   - Contrast ratios must strictly respect the 7.0:1 threshold for standard text in high contrast mode (WCAG 2.1 AAA).

---

## 5. Conclusion & Actionable Summary

This analysis provides the complete architectural design, component contracts, and UX patterns for:
1. `useAccessibility.js` (Composable for High Contrast, Font Scaling +18%, and Linguagem Fácil with `localStorage` persistence).
2. `AccessibilityToolbar.vue` (WCAG 2.1 AA / e-MAG compliant accessible toolbar with ARIA bindings).
3. `AppLayout.vue` (SEJUS/ES branded responsive shell, collapsible sidebar, defensive user profile handling, and reactive role switcher).
4. `ValidarCarteira.vue` (Public QR Code verification page supporting 5 cryptographic states and accessible data grid).

---

## 6. Verification Method

To independently verify all features and boundaries formulated in this report, execute the test runners:

```bash
# 1. Run Tier 1 Feature test suite covering F34-F47
python -m unittest tests_e2e/tier1_features/test_f34_f47_frontend_views.py

# 2. Run Tier 2 Accessibility Boundary & Limits tests
python -m unittest tests_e2e/tier2_boundaries/test_frontend_a11y_limits.py

# 3. Run Tier 3 Combinations (PDF, QR Code & Public Validation Chain)
python -m unittest tests_e2e/tier3_combinations/test_pdf_qr_validation_chain.py

# 4. Run Tier 4 Scenario 2 (Egresso Onboarding & Digital Wallet)
python -m unittest tests_e2e/tier4_scenarios/scenario_egresso_onboarding_wallet.py

# 5. Run Tier 4 Scenario 4 (Interior Territorial Job Application in Linhares)
python -m unittest tests_e2e/tier4_scenarios/scenario_interior_job_application.py

# 6. Run full E2E Test Suite Runner
python tests_e2e/test_runner.py
```
