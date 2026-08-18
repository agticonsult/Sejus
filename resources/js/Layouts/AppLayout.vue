<template>
  <div id="app" class="app-container min-h-screen flex flex-col bg-slate-50 text-slate-900">
    <!-- Skip to Main Content Link (e-MAG / WCAG 2.4.1) -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:p-3 focus:bg-white focus:text-sky-900 focus:font-bold focus:shadow-xl focus:rounded-lg focus:ring-2 focus:ring-sky-500"
    >
      Pular para o conteúdo principal
    </a>

    <!-- Top Header Bar -->
    <header class="top-header sticky top-0 z-40 bg-white border-b border-slate-200 h-[70px] px-4 md:px-6 flex items-center justify-between shadow-xs">
      <!-- Left: Sidebar Toggle & Institutional Logo -->
      <div class="header-left flex items-center gap-3 md:gap-4">
        <button
          id="sidebarToggleBtn"
          type="button"
          class="btn-icon p-2 rounded-lg text-slate-600 hover:bg-slate-100 focus:ring-2 focus:ring-sky-500 transition cursor-pointer min-w-[44px] min-h-[44px] flex items-center justify-center"
          aria-label="Alternar Menu Lateral"
          @click="isSidebarCollapsed = !isSidebarCollapsed"
        >
          <span class="text-xl" aria-hidden="true">☰</span>
        </button>

        <!-- Official Espírito Santo Flag Badge & Brand Title -->
        <Link href="/dashboard" class="brand-logo flex items-center gap-3 group">
          <div class="es-flag-badge w-6 h-7 rounded overflow-hidden flex flex-col shadow-xs" aria-hidden="true">
            <span class="stripe-pink h-1/3 bg-[#e63946]"></span>
            <span class="stripe-white h-1/3 bg-[#ffffff]"></span>
            <span class="stripe-blue h-1/3 bg-[#003366]"></span>
          </div>
          <div class="brand-text">
            <h1 class="app-title font-extrabold text-base md:text-lg tracking-tight leading-none text-[#003366]">
              CONECTA <span class="highlight text-sky-600">EGRESSO</span>
            </h1>
            <span class="app-subtitle text-[10px] md:text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
              SEJUS • Governo do Estado do Espírito Santo
            </span>
          </div>
        </Link>
      </div>

      <!-- Center: Global Search Input -->
      <div class="header-center flex-1 max-w-md mx-4 hidden lg:block">
        <div class="search-box relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
          <input
            id="globalSearchInput"
            type="text"
            placeholder="Buscar egressos, atendimentos, vagas ou serviços nos 78 municípios..."
            class="w-full pl-9 pr-4 py-2 text-xs bg-slate-100 border border-slate-200 rounded-full focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
          />
        </div>
      </div>

      <!-- Right: Accessibility Toolbar, Role Switcher, and Gov.br Auth Badge -->
      <div class="header-right flex items-center gap-2 md:gap-4">
        <!-- Accessibility Toolbar Component -->
        <AccessibilityToolbar :show-labels="false" />

        <!-- Role Switcher Component -->
        <div class="profile-switcher">
          <label for="userRoleSelect" class="sr-only">Perfil de Acesso</label>
          <div class="role-badge flex items-center gap-1.5 bg-sky-50 border border-sky-200 text-sky-700 px-2.5 py-1.5 rounded-full text-xs font-bold shadow-2xs">
            <span aria-hidden="true" class="text-sm">👤</span>
            <select
              id="userRoleSelect"
              v-model="currentRole"
              class="bg-transparent border-none text-sky-900 font-bold text-xs outline-none cursor-pointer pr-1"
              aria-label="Selecionar Perfil de Acesso"
              @change="handleRoleChange"
            >
              <option value="gestor">Perfil: Gestor SEJUS</option>
              <option value="tecnico">Perfil: Técnico Escritório Social</option>
              <option value="egresso">Perfil: Egresso / Familiar</option>
            </select>
          </div>
        </div>

        <!-- Gov.br User Card -->
        <div class="govbr-user-card flex items-center gap-2 pl-2 md:pl-3 border-l border-slate-200">
          <span class="govbr-badge bg-[#1351b4] text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded shadow-2xs">gov.br</span>
          <div class="user-info text-left hidden sm:block">
            <span id="userNameHeader" class="user-name font-bold text-xs text-slate-800 block leading-tight truncate max-w-[160px]">
              {{ userProfile.displayName }}
            </span>
            <span id="userCpfHeader" class="user-cpf text-[10px] text-slate-500 block truncate">
              {{ userProfile.roleSubtitle }}
            </span>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Layout (Sidebar + Content Area) -->
    <div class="main-layout flex flex-1 overflow-hidden">
      <!-- Responsive Navigation Sidebar -->
      <aside
        id="sidebar"
        class="sidebar bg-[#0f172a] text-slate-300 transition-all duration-300 flex flex-col border-r border-slate-800 flex-shrink-0 z-30"
        :class="isSidebarCollapsed ? 'w-[70px]' : 'w-[270px]'"
        role="navigation"
        aria-label="Navegação do Sistema CONECTA EGRESSO"
      >
        <!-- User Summary -->
        <div class="sidebar-user-summary p-3.5 flex items-center gap-3 border-b border-slate-800/80">
          <div
            id="sidebarAvatar"
            class="user-avatar w-10 h-10 rounded-full bg-gradient-to-tr from-sky-600 to-indigo-900 text-white font-extrabold flex items-center justify-center text-sm border-2 border-white/20 shadow-md flex-shrink-0"
          >
            {{ userProfile.initials }}
          </div>
          <div v-if="!isSidebarCollapsed" class="user-details text-left overflow-hidden">
            <strong id="sidebarRoleTitle" class="text-white text-xs block font-bold truncate">
              {{ userProfile.roleTitle }}
            </strong>
            <small id="sidebarRoleScope" class="text-slate-400 text-[10px] block truncate">
              {{ userProfile.roleScope }}
            </small>
          </div>
        </div>

        <!-- Sidebar Navigation List -->
        <nav class="sidebar-nav p-2 flex-1 flex flex-col gap-1 overflow-y-auto" aria-label="Navegação Lateral">
          <template v-for="item in visibleNavigationItems" :key="item.name">
            <div
              v-if="item.sectionHeader && !isSidebarCollapsed"
              class="nav-section-title text-[9px] font-extrabold uppercase tracking-wider text-slate-500 px-3 pt-3 pb-1"
            >
              {{ item.sectionHeader }}
            </div>

            <Link
              :href="item.href"
              class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition cursor-pointer min-h-[44px]"
              :class="isRouteActive(item.route) ? 'active bg-sky-600 text-white font-bold shadow-md shadow-sky-600/30' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
              :data-view="item.dataView"
              :aria-current="isRouteActive(item.route) ? 'page' : undefined"
            >
              <span class="text-base flex-shrink-0" aria-hidden="true">{{ item.iconEmoji }}</span>
              <span v-if="!isSidebarCollapsed" class="truncate">{{ item.name }}</span>
              <span
                v-if="!isSidebarCollapsed && item.badge"
                class="badge-nav ml-auto text-[9px] font-extrabold px-2 py-0.5 rounded-full text-white shadow-2xs"
                :class="item.badgeClass"
              >
                {{ item.badge }}
              </span>
            </Link>
          </template>
        </nav>

        <!-- Sidebar Footer Seal -->
        <div v-if="!isSidebarCollapsed" class="sidebar-footer p-3 border-t border-slate-800/90">
          <div class="es-seal flex items-center gap-2.5 bg-white/5 p-2.5 rounded-lg border border-white/5">
            <span class="seal-icon text-lg" aria-hidden="true">🏛️</span>
            <div class="overflow-hidden">
              <strong class="text-white text-[11px] block font-bold">SEJUS / SEGER</strong>
              <p class="text-[10px] text-slate-400 truncate">Escritório Social Digital</p>
            </div>
          </div>
        </div>
      </aside>

      <!-- Content Area -->
      <main id="main-content" class="content-area flex-1 p-4 md:p-6 lg:p-8 overflow-y-auto bg-slate-50 text-slate-800" role="main">
        <!-- Flash Messages & Alerts -->
        <div v-if="flashMessage" class="mb-4 p-3 bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-xl text-xs font-semibold flex items-center justify-between" role="status">
          <span>{{ flashMessage }}</span>
          <button type="button" @click="flashMessage = ''" class="text-emerald-900 font-bold ml-2">✕</button>
        </div>

        <!-- Breadcrumbs Navigation -->
        <div v-if="breadcrumbs && breadcrumbs.length" class="breadcrumbs mb-4 flex items-center gap-1.5 text-xs text-slate-500" aria-label="Trilha de Navegação">
          <Link href="/dashboard" class="hover:text-sky-600">Início</Link>
          <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
            <span class="text-slate-300">/</span>
            <Link v-if="crumb.href" :href="crumb.href" class="hover:text-sky-600">{{ crumb.name }}</Link>
            <span v-else class="text-slate-800 font-bold">{{ crumb.name }}</span>
          </template>
        </div>

        <!-- Page View Slot -->
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AccessibilityToolbar from '../Components/AccessibilityToolbar.vue';

const page = usePage();

const props = defineProps({
  breadcrumbs: {
    type: Array,
    default: () => [],
  },
});

const isSidebarCollapsed = ref(false);
const currentRole = ref('gestor');
const flashMessage = ref('');

onMounted(() => {
  // Sync initial role with page props or fallback
  const roleProp = page.props.auth?.role || page.props.auth?.user?.role || page.props.auth?.user?.perfil;
  if (roleProp && ['gestor', 'tecnico', 'egresso'].includes(roleProp.toLowerCase())) {
    currentRole.value = roleProp.toLowerCase();
  }
});

// Defensive user profile handling
const userProfile = computed(() => {
  const user = page.props.auth?.user || {};
  const rawRole = currentRole.value;

  if (rawRole === 'gestor') {
    return {
      displayName: user.name || user.nome || 'Carlos Eduardo Silva (Gestor)',
      initials: 'CS',
      roleTitle: 'Visão Gestor Estadual',
      roleScope: '78 Municípios • SEJUS/ES',
      roleSubtitle: 'SEJUS / Subsecretaria de Reintegração',
    };
  } else if (rawRole === 'tecnico') {
    return {
      displayName: user.name || user.nome || 'Dra. Márcia Oliveira (Técnica)',
      initials: 'MO',
      roleTitle: 'Técnico Escritório Social',
      roleScope: 'Atendimento Remoto / Presencial',
      roleSubtitle: 'Assistente Social • CRESS 4891/ES',
    };
  } else {
    return {
      displayName: user.name || user.nome || 'Lucas Santos (Egresso)',
      initials: 'LS',
      roleTitle: 'Visão Egresso / Familiar',
      roleScope: 'São Mateus / ES (Acesso Remoto)',
      roleSubtitle: user.cpf_masked || 'CPF: ***.192.830-** • Gov.br',
    };
  }
});

const navigationItems = [
  {
    name: 'Dashboard & KPIs',
    route: 'dashboard',
    href: '/dashboard',
    iconEmoji: '📊',
    roles: ['gestor', 'tecnico', 'egresso'],
    badge: '78 Cidades',
    badgeClass: 'bg-sky-600',
    dataView: 'dashboard',
  },
  {
    name: 'Atendimento Remoto & Vídeo',
    route: 'atendimento',
    href: '/atendimento',
    iconEmoji: '📹',
    roles: ['gestor', 'tecnico', 'egresso'],
    badge: '3 em espera',
    badgeClass: 'bg-emerald-600',
    dataView: 'atendimento',
  },
  {
    name: 'Oportunidades & Trabalho',
    route: 'oportunidades',
    href: '/oportunidades',
    iconEmoji: '💼',
    roles: ['gestor', 'tecnico', 'egresso'],
    badge: '42 Vagas',
    badgeClass: 'bg-purple-600',
    dataView: 'oportunidades',
  },
  {
    name: 'Carteira Digital & Documentos',
    route: 'carteira',
    href: '/carteira',
    iconEmoji: '💳',
    roles: ['gestor', 'tecnico', 'egresso'],
    badge: 'QR Code OK',
    badgeClass: 'bg-blue-700',
    dataView: 'carteira',
  },
  {
    name: 'Mapeamento dos 78 Municípios',
    route: 'geolocalizacao',
    href: '/geolocalizacao',
    iconEmoji: '📍',
    roles: ['gestor', 'tecnico', 'egresso'],
    dataView: 'geolocalizacao',
  },
  {
    name: 'Prontuário & Histórico',
    route: 'prontuario',
    href: '/prontuario',
    iconEmoji: '📁',
    roles: ['gestor', 'tecnico', 'egresso'],
    dataView: 'prontuario',
  },
  // GESTÃO & GOVERNANÇA
  {
    name: 'Relatórios & Análise SEJUS',
    route: 'relatorios',
    href: '/relatorios',
    iconEmoji: '📈',
    roles: ['gestor', 'tecnico'],
    sectionHeader: 'GESTÃO & GOVERNANÇA',
    dataView: 'relatorios',
  },
  {
    name: 'Segurança & LGPD',
    route: 'seguranca-lgpd',
    href: '/seguranca-lgpd',
    iconEmoji: '🛡️',
    roles: ['gestor', 'tecnico', 'egresso'],
    dataView: 'lgpd',
  },
];

const visibleNavigationItems = computed(() => {
  return navigationItems.filter(item => item.roles.includes(currentRole.value));
});

const isRouteActive = (routeKey) => {
  const currentUrl = page.url || '';
  if (routeKey === 'dashboard' && (currentUrl === '/' || currentUrl.startsWith('/dashboard'))) return true;
  if (routeKey === 'seguranca-lgpd' && currentUrl.startsWith('/seguranca-lgpd')) return true;
  return currentUrl.startsWith(`/${routeKey}`);
};

const handleRoleChange = () => {
  flashMessage.value = `Perfil alterado para: ${userProfile.value.roleTitle}. Interface atualizada com permissões estritas.`;
};
</script>
