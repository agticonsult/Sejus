<template>
  <div id="app" class="app-container min-h-screen flex flex-col bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased transition-colors duration-200">
    <!-- Skip to Main Content Link (e-MAG / WCAG 2.4.1) -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:p-3 focus:bg-white dark:focus:bg-slate-800 focus:text-sky-900 dark:focus:text-sky-400 focus:font-bold focus:shadow-xl focus:rounded-lg focus:ring-2 focus:ring-sky-500"
    >
      Pular para o conteúdo principal
    </a>

    <!-- Top Header Bar -->
    <header class="top-header sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200/80 dark:border-slate-800/80 h-16 px-4 md:px-6 flex items-center justify-between shadow-xs transition-colors duration-200">
      <!-- Left: Sidebar Toggle & Institutional Logo -->
      <div class="header-left flex items-center gap-3 md:gap-4">
        <button
          id="sidebarToggleBtn"
          type="button"
          class="btn-icon p-2 rounded-lg text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 focus:ring-2 focus:ring-sky-500 transition cursor-pointer min-w-[40px] min-h-[40px] flex items-center justify-center"
          aria-label="Alternar Menu Lateral"
          @click="isSidebarCollapsed = !isSidebarCollapsed"
        >
          <Menu v-if="isSidebarCollapsed" class="w-5 h-5 stroke-[1.75]" />
          <X v-else class="w-5 h-5 stroke-[1.75]" />
        </button>

        <!-- Official Espírito Santo Flag Badge & Brand Title -->
        <Link href="/dashboard" class="brand-logo flex items-center gap-3 group">
          <div class="es-flag-badge w-6 h-7 rounded overflow-hidden flex flex-col shadow-xs flex-shrink-0" aria-hidden="true">
            <span class="stripe-pink h-1/3 bg-[#e63946]"></span>
            <span class="stripe-white h-1/3 bg-[#ffffff]"></span>
            <span class="stripe-blue h-1/3 bg-[#003366]"></span>
          </div>
          <div class="brand-text">
            <h1 class="app-title font-extrabold text-sm md:text-base tracking-tight leading-none text-[#003366] dark:text-slate-100">
              CONECTA <span class="highlight text-sky-600 dark:text-sky-400">EGRESSO</span>
            </h1>
            <span class="app-subtitle text-[10px] md:text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider block mt-0.5">
              SEJUS • Governo do Estado do Espírito Santo
            </span>
          </div>
        </Link>
      </div>

      <!-- Center: Global Search Input -->
      <div class="header-center flex-1 max-w-md mx-6 hidden lg:block">
        <div class="search-box relative">
          <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-400 stroke-[2] pointer-events-none" />
          <input
            id="globalSearchInput"
            type="text"
            placeholder="Buscar egressos, prontuários, vagas nos 78 municípios..."
            aria-label="Buscar egressos, prontuários, vagas nos 78 municípios"
            class="w-full pl-9 pr-12 py-2 text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-400 bg-slate-100/80 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl focus:bg-white dark:focus:bg-slate-800 focus:border-sky-500 dark:focus:border-sky-400 focus:ring-2 focus:ring-sky-100 dark:focus:ring-sky-500 outline-none transition"
          />
          <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-mono font-semibold text-slate-400 dark:text-slate-400 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-2xs">
            ⌘K
          </kbd>
        </div>
      </div>

      <!-- Right: Cluster Status, Accessibility, Role Switcher, Gov.br Badge & Logout -->
      <div class="header-right flex items-center gap-2 md:gap-3">
        <!-- Live Cluster Health Indicator (Hidden on small screens) -->
        <div class="hidden xl:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 text-xs font-medium">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Cluster Ativo (S1 + S2 WebRTC)</span>
        </div>

        <!-- Accessibility Toolbar Component -->
        <AccessibilityToolbar :show-labels="false" />

        <!-- Header Quick Theme Switcher Dropdown (WCAG 2.1 AA Theming Engine) -->
        <div ref="themeDropdownRef" class="theme-switcher relative">
          <button
            id="themeSwitcherBtn"
            type="button"
            class="theme-switcher-btn flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200/70 dark:bg-slate-800 dark:hover:bg-slate-700/70 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 shadow-2xs transition cursor-pointer min-h-[38px]"
            :aria-expanded="isThemeDropdownOpen"
            aria-haspopup="true"
            aria-label="Selecionar Tema Visual"
            title="Alterar Tema Visual (WCAG 2.1 AA)"
            @click="toggleThemeDropdown"
          >
            <Palette class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 stroke-[2] flex-shrink-0" />
            <span
              class="w-2.5 h-2.5 rounded-full border border-white/20 shadow-2xs flex-shrink-0"
              :style="{ backgroundColor: activeThemeObj?.accent || '#38BDF8' }"
              aria-hidden="true"
            ></span>
            <span class="hidden md:inline font-semibold text-xs text-slate-800 dark:text-slate-100 truncate max-w-[110px]">
              {{ activeThemeObj?.name || 'Executive Slate' }}
            </span>
            <ChevronDown
              class="w-3 h-3 text-slate-400 dark:text-slate-400 transition-transform duration-200 flex-shrink-0"
              :class="{ 'rotate-180': isThemeDropdownOpen }"
            />
          </button>

          <!-- Dropdown Popover Menu -->
          <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
          >
            <div
              v-if="isThemeDropdownOpen"
              id="themeDropdownMenu"
              class="theme-dropdown-menu absolute right-0 mt-2 w-72 sm:w-80 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl py-2 z-50 backdrop-blur-md"
              role="menu"
              aria-orientation="vertical"
              aria-labelledby="themeSwitcherBtn"
            >
              <!-- Dropdown Header -->
              <div class="px-3.5 py-2 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                  <Palette class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 stroke-[2]" />
                  <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Temas Governamentais
                  </span>
                </div>
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-950/80 text-sky-700 dark:text-sky-300">
                  WCAG AA
                </span>
              </div>

              <!-- Theme Options List -->
              <div class="p-1.5 space-y-1 max-h-80 overflow-y-auto">
                <button
                  v-for="theme in AVAILABLE_THEMES"
                  :key="theme.id"
                  :id="`themeOption-${theme.id}`"
                  :data-theme-option="theme.id"
                  type="button"
                  role="menuitem"
                  class="theme-option-btn w-full text-left flex items-center justify-between p-2 rounded-xl transition cursor-pointer group"
                  :class="currentTheme === theme.id 
                    ? 'bg-sky-50 dark:bg-slate-800 text-sky-900 dark:text-sky-100 font-semibold ring-1 ring-sky-500/30' 
                    : 'hover:bg-slate-100 dark:hover:bg-slate-800/60 text-slate-700 dark:text-slate-300'"
                  @click="handleThemeSelect(theme.id)"
                >
                  <div class="flex items-center gap-2.5 min-w-0">
                    <!-- Colored Swatch Dot -->
                    <div
                      class="theme-swatch-badge w-6 h-6 rounded-lg flex items-center justify-center border shadow-2xs flex-shrink-0"
                      :style="{ backgroundColor: theme.bgCard, borderColor: theme.border }"
                      aria-hidden="true"
                    >
                      <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: theme.accent }"></span>
                    </div>

                    <!-- Theme Title & Subtitle -->
                    <div class="min-w-0">
                      <div class="text-xs font-bold leading-tight truncate text-slate-800 dark:text-slate-100 group-hover:text-sky-600 dark:group-hover:text-sky-400">
                        {{ theme.name }}
                      </div>
                      <div class="text-[10px] text-slate-400 dark:text-slate-400 truncate leading-tight mt-0.5">
                        {{ theme.subtitle }}
                      </div>
                    </div>
                  </div>

                  <!-- Contrast Ratio Badge & Active Checkmark -->
                  <div class="flex items-center gap-1.5 flex-shrink-0 ml-2">
                    <span
                      class="contrast-chip text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400"
                      :title="`Taxa de contraste com texto primário: ${theme.contrastRatioText}:1`"
                    >
                      {{ theme.contrastRatioText }}:1
                    </span>
                    <Check
                      v-if="currentTheme === theme.id"
                      class="active-check w-4 h-4 text-sky-600 dark:text-sky-400 stroke-[2.5]"
                      aria-hidden="true"
                    />
                  </div>
                </button>
              </div>

              <!-- Footer: Link to Interactive Theme Catalog -->
              <div class="p-1.5 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-950/30 rounded-b-2xl">
                <Link
                  id="openFullCatalogLink"
                  href="/catalogo-temas"
                  class="w-full flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg text-xs font-semibold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/50 transition cursor-pointer"
                  @click="isThemeDropdownOpen = false"
                >
                  <Palette class="w-3.5 h-3.5" />
                  <span>Ver Catálogo Completo & WCAG</span>
                  <ArrowRight class="w-3.5 h-3.5" />
                </Link>
              </div>
            </div>
          </transition>
        </div>

        <!-- Authenticated Profile Badge -->
        <div class="role-badge hidden md:flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 px-2.5 py-1.5 rounded-xl text-xs font-semibold shadow-2xs">
          <Users class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 stroke-[2]" />
          <span class="text-slate-800 dark:text-slate-200 font-semibold text-xs">{{ userProfile.roleTitle }}</span>
        </div>

        <!-- Gov.br User Card -->
        <div class="govbr-user-card flex items-center gap-2.5 pl-2 md:pl-3 border-l border-slate-200 dark:border-slate-800">
          <span class="govbr-badge bg-[#1351b4] text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded shadow-2xs">
            gov.br
          </span>
          <div class="user-info text-left hidden sm:block">
            <span id="userNameHeader" class="user-name font-bold text-xs text-slate-800 dark:text-slate-100 block leading-tight truncate max-w-[150px]">
              {{ userProfile.displayName }}
            </span>
            <span id="userCpfHeader" class="user-cpf text-[10px] text-slate-400 dark:text-slate-400 block truncate">
              {{ userProfile.roleSubtitle }}
            </span>
          </div>
        </div>

        <!-- Header Logout / Sair Button -->
        <button
          id="headerLogoutBtn"
          type="button"
          class="logout-btn p-2 rounded-xl text-slate-500 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-transparent hover:border-rose-100 dark:hover:border-rose-900/60 transition cursor-pointer min-h-[38px] min-w-[38px] flex items-center justify-center"
          aria-label="Encerrar Sessão / Logout"
          title="Encerrar Sessão com Segurança"
          @click="handleLogout"
        >
          <LogOut class="w-4 h-4 stroke-[1.75]" />
        </button>
      </div>
    </header>

    <!-- Main Layout (Sidebar + Content Area) -->
    <div class="main-layout flex flex-1 overflow-hidden">
      <!-- Responsive Navigation Sidebar -->
      <aside
        id="sidebar"
        class="sidebar bg-[#0f172a] text-slate-300 transition-all duration-300 flex flex-col border-r border-slate-800/80 flex-shrink-0 z-30"
        :class="isSidebarCollapsed ? 'w-[70px]' : 'w-[285px]'"
        role="navigation"
        aria-label="Navegação do Sistema CONECTA EGRESSO"
      >
        <!-- User Summary -->
        <div class="sidebar-user-summary p-3.5 flex items-center gap-3 border-b border-slate-800/70 bg-slate-950/40">
          <div
            id="sidebarAvatar"
            class="user-avatar w-9 h-9 rounded-xl bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-extrabold flex items-center justify-center text-xs border border-white/20 shadow-md flex-shrink-0"
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
        <nav class="sidebar-nav p-2.5 flex-1 flex flex-col gap-1 overflow-y-auto" aria-label="Navegação Lateral">
          <template v-for="item in visibleNavigationItems" :key="item.name">
            <div
              v-if="item.sectionHeader && !isSidebarCollapsed"
              class="nav-section-title text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 px-3 pt-3 pb-1"
            >
              {{ item.sectionHeader }}
            </div>

            <Link
              :href="item.href"
              class="nav-item flex items-center justify-between gap-2.5 px-3 py-2 rounded-xl text-xs font-medium transition-all cursor-pointer min-h-[40px]"
              :class="isRouteActive(item.route) ? 'active bg-sky-600 dark:bg-sky-700 text-white font-semibold shadow-md shadow-sky-600/30' : 'text-slate-400 hover:bg-slate-800/70 hover:text-slate-100'"
              :data-view="item.dataView"
              :title="item.name"
              :aria-current="isRouteActive(item.route) ? 'page' : undefined"
            >
              <div class="flex items-center gap-2.5 min-w-0 flex-1">
                <component :is="item.icon" class="w-4 h-4 flex-shrink-0 stroke-[1.75]" />
                <span
                  v-if="!isSidebarCollapsed"
                  class="flex-1 min-w-0 text-left leading-snug truncate"
                  :title="item.name"
                >
                  {{ item.name }}
                </span>
              </div>
              <span
                v-if="!isSidebarCollapsed && item.badge"
                class="badge-nav flex-shrink-0 ml-auto text-[9px] font-bold px-2 py-0.5 rounded-full text-white shadow-2xs whitespace-nowrap"
                :class="item.badgeClass"
              >
                {{ item.badge }}
              </span>
            </Link>
          </template>
        </nav>

        <!-- Sidebar Footer Seal & Logout -->
        <div class="sidebar-footer p-3 border-t border-slate-800/80 bg-slate-950/30 flex flex-col gap-2">
          <div v-if="!isSidebarCollapsed" class="es-seal flex items-center gap-2.5 bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/60">
            <Building2 class="w-4 h-4 text-sky-400 stroke-[1.75] flex-shrink-0" />
            <div class="overflow-hidden">
              <strong class="text-white text-[11px] block font-semibold leading-tight">SEJUS / SEGER</strong>
              <p class="text-[10px] text-slate-400 truncate">Escritório Social Digital</p>
            </div>
          </div>

          <!-- Sidebar Logout Button -->
          <div class="sidebar-logout">
            <button
              id="sidebarLogoutBtn"
              type="button"
              class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition cursor-pointer"
              :class="isSidebarCollapsed ? 'justify-center' : ''"
              aria-label="Sair / Logout do Sistema"
              title="Encerrar sessão com segurança"
              @click="handleLogout"
            >
              <LogOut class="w-4 h-4 stroke-[1.75] flex-shrink-0" />
              <span v-if="!isSidebarCollapsed" class="truncate">Sair do Sistema</span>
            </button>
          </div>
        </div>
      </aside>

      <!-- Content Area -->
      <main id="main-content" class="content-area flex-1 p-4 md:p-6 lg:p-8 overflow-y-auto bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors duration-200" role="main">
        <!-- Flash Messages & Alerts -->
        <div v-if="flashMessage" class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-medium flex items-center justify-between shadow-2xs" role="status">
          <span>{{ flashMessage }}</span>
          <button type="button" @click="flashMessage = ''" class="text-emerald-900 font-bold ml-2 hover:opacity-75">✕</button>
        </div>

        <!-- Breadcrumbs Navigation -->
        <div v-if="breadcrumbs && breadcrumbs.length" class="breadcrumbs mb-5 flex items-center gap-2 text-xs text-slate-400" aria-label="Trilha de Navegação">
          <Link href="/dashboard" class="hover:text-sky-600 transition">Início</Link>
          <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
            <span class="text-slate-300">/</span>
            <Link v-if="crumb.href" :href="crumb.href" class="hover:text-sky-600 transition">{{ crumb.name }}</Link>
            <span v-else class="text-slate-800 dark:text-slate-200 font-semibold">{{ crumb.name }}</span>
          </template>
        </div>

        <!-- Page View Slot -->
        <slot />
      </main>
    </div>

    <!-- Global Reactive Toast Container -->
    <ToastContainer />

    <!-- LGPD Cookie Consent Banner -->
    <CookieConsentBanner @open-privacy="isPrivacyModalOpen = true" />

    <!-- Legal Terms of Use & Privacy Policy Modal -->
    <PrivacyTermsModal :is-open="isPrivacyModalOpen" @close="isPrivacyModalOpen = false" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import {
  LayoutDashboard,
  Video,
  Briefcase,
  CreditCard,
  MapPin,
  FileText,
  BarChart3,
  Users,
  ShieldCheck,
  LogOut,
  Search,
  Menu,
  X,
  Building2,
  Palette,
  Check,
  ChevronDown,
  ArrowRight,
} from 'lucide-vue-next';
import AccessibilityToolbar from '../Components/AccessibilityToolbar.vue';
import ToastContainer from '../Components/ToastContainer.vue';
import CookieConsentBanner from '../Components/CookieConsentBanner.vue';
import PrivacyTermsModal from '../Components/PrivacyTermsModal.vue';
import { useToast } from '../Composables/useToast';
import { useTheme } from '../Composables/useTheme';

const page = usePage();
const toast = useToast();
const isPrivacyModalOpen = ref(false);
const {
  currentTheme,
  activeThemeObj,
  AVAILABLE_THEMES,
  applyTheme,
  initTheme,
} = useTheme();

const props = defineProps({
  breadcrumbs: {
    type: Array,
    default: () => [],
  },
});

const isSidebarCollapsed = ref(false);
const currentRole = ref('gestor');
const flashMessage = ref('');

// Quick Theme Switcher Dropdown State & Handlers
const isThemeDropdownOpen = ref(false);
const themeDropdownRef = ref(null);

const toggleThemeDropdown = () => {
  isThemeDropdownOpen.value = !isThemeDropdownOpen.value;
};

const handleThemeSelect = (themeId) => {
  applyTheme(themeId);
  toast.success('Tema Atualizado', `Tema aplicado: ${activeThemeObj.value?.name || themeId}.`);
  isThemeDropdownOpen.value = false;
};

const handleClickOutside = (event) => {
  if (themeDropdownRef.value && !themeDropdownRef.value.contains(event.target)) {
    isThemeDropdownOpen.value = false;
  }
};

const handleKeyDown = (event) => {
  if (event.key === 'Escape' && isThemeDropdownOpen.value) {
    isThemeDropdownOpen.value = false;
  }
};

onMounted(() => {
  initTheme();
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeyDown);

  const roleProp = page.props.auth?.role || page.props.auth?.user?.role || page.props.auth?.user?.perfil;
  if (roleProp && ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte', 'empregador'].includes(roleProp.toLowerCase())) {
    currentRole.value = roleProp.toLowerCase();
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeyDown);
});

const activeRole = computed(() => {
  const roleProp = page.props.auth?.role || page.props.auth?.user?.role || page.props.auth?.user?.perfil;
  if (roleProp) {
    return roleProp.toLowerCase();
  }
  return currentRole.value;
});

// Authenticated user profile display without hardcoded fake citizen data
const userProfile = computed(() => {
  const user = page.props.auth?.user || {};
  const rawRole = activeRole.value;

  const userName = user.name || user.nome || 'Usuário Autenticado';
  const userEmail = user.email || '';

  const getInitials = (name) => {
    if (!name) return 'CE';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  };

  if (rawRole === 'empregador') {
    return {
      displayName: userName,
      email: userEmail,
      initials: getInitials(userName),
      roleTitle: 'Perfil Empregador',
      roleScope: 'Portal de Vagas • SEJUS/ES',
      roleSubtitle: userEmail || 'Empresa Parceira',
    };
  } else if (rawRole === 'suporte') {
    return {
      displayName: userName,
      email: userEmail,
      initials: getInitials(userName),
      roleTitle: 'Suporte Técnico',
      roleScope: 'Administração • SEJUS/ES',
      roleSubtitle: 'Administrador do Sistema',
    };
  } else if (rawRole === 'gestor') {
    return {
      displayName: userName,
      email: userEmail,
      initials: getInitials(userName),
      roleTitle: 'Gestor Estadual SEJUS',
      roleScope: '78 Municípios • SEJUS/ES',
      roleSubtitle: 'Subsecretaria de Reintegração',
    };
  } else if (rawRole === 'tecnico') {
    return {
      displayName: userName,
      email: userEmail,
      initials: getInitials(userName),
      roleTitle: 'Técnico Escritório Social',
      roleScope: 'Atendimento Multiprofissional',
      roleSubtitle: 'Equipe Técnica SEJUS/ES',
    };
  } else {
    return {
      displayName: userName,
      email: userEmail,
      initials: getInitials(userName),
      roleTitle: rawRole === 'familiar' ? 'Familiar Autorizado' : 'Cidadão Egresso',
      roleScope: 'Espírito Santo',
      roleSubtitle: user.cpf_masked || 'Sessão Autenticada',
    };
  }
});

const navigationItems = [
  {
    name: 'Dashboard & Visão Executiva',
    route: 'dashboard',
    href: '/dashboard',
    icon: LayoutDashboard,
    roles: ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte', 'empregador'],
    badge: '78 Cidades',
    badgeClass: 'bg-sky-700',
    dataView: 'dashboard',
  },
  {
    name: 'Inserir Vaga',
    route: 'oportunidades/inserir-vaga',
    href: '/oportunidades/inserir-vaga',
    icon: Briefcase,
    roles: ['empregador', 'gestor', 'suporte'],
    badge: 'CSV',
    badgeClass: 'bg-amber-600',
    dataView: 'inserir-vaga',
  },
  {
    name: 'Atendimentos & Vídeo WebRTC',
    route: 'atendimento',
    href: '/atendimento',
    icon: Video,
    roles: ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte'],
    badgeClass: 'bg-emerald-700',
    dataView: 'atendimento',
  },
  {
    name: 'Oportunidades & Vagas',
    route: 'oportunidades',
    href: '/oportunidades',
    icon: Briefcase,
    roles: ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte', 'empregador'],
    badgeClass: 'bg-slate-700',
    dataView: 'oportunidades',
  },
  {
    name: 'Carteira Digital & QR Code',
    route: 'carteira',
    href: '/carteira',
    icon: CreditCard,
    roles: ['gestor', 'tecnico', 'egresso', 'suporte'],
    badge: 'HMAC OK',
    badgeClass: 'bg-sky-700',
    dataView: 'carteira',
  },
  {
    name: 'Mapeamento Territorial',
    route: 'geolocalizacao',
    href: '/geolocalizacao',
    icon: MapPin,
    roles: ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte'],
    dataView: 'geolocalizacao',
  },
  {
    name: 'Prontuário & Linha do Tempo',
    route: 'prontuario',
    href: '/prontuario',
    icon: FileText,
    roles: ['gestor', 'tecnico', 'egresso', 'suporte'],
    dataView: 'prontuario',
  },
  // GESTÃO & GOVERNANÇA
  {
    name: 'Relatórios & Inteligência SEGER',
    route: 'relatorios',
    href: '/relatorios',
    icon: BarChart3,
    roles: ['gestor', 'tecnico', 'suporte'],
    sectionHeader: 'GESTÃO & GOVERNANÇA',
    dataView: 'relatorios',
  },
  {
    name: 'Gerenciamento de Usuários',
    route: 'usuarios',
    href: '/usuarios',
    icon: Users,
    roles: ['gestor', 'suporte'],
    dataView: 'usuarios',
  },
  {
    name: 'Segurança & LGPD',
    route: 'seguranca-lgpd',
    href: '/seguranca-lgpd',
    icon: ShieldCheck,
    roles: ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte', 'empregador'],
    dataView: 'lgpd',
  },
  // DESIGN SYSTEM & ACESSIBILIDADE
  {
    name: 'Catálogo de Temas',
    route: 'catalogo.temas',
    href: '/catalogo-temas',
    icon: Palette,
    roles: ['gestor', 'tecnico', 'egresso', 'suporte', 'empregador'],
    sectionHeader: 'DESIGN SYSTEM & ACESSIBILIDADE',
    badge: '6 Temas',
    badgeClass: 'bg-sky-600 dark:bg-sky-700',
    dataView: 'catalogo-temas',
  },
];

const visibleNavigationItems = computed(() => {
  return navigationItems.filter(item => item.roles.includes(activeRole.value));
});

const isRouteActive = (routeKey) => {
  const currentUrl = page.url || '';
  if (routeKey === 'dashboard' && (currentUrl === '/' || currentUrl.startsWith('/dashboard'))) return true;
  if (routeKey === 'oportunidades/inserir-vaga') return currentUrl.startsWith('/oportunidades/inserir-vaga');
  if (routeKey === 'oportunidades') {
    return currentUrl.startsWith('/oportunidades') && !currentUrl.startsWith('/oportunidades/inserir-vaga');
  }
  if (routeKey === 'seguranca-lgpd' && currentUrl.startsWith('/seguranca-lgpd')) return true;
  if (routeKey === 'catalogo.temas' && currentUrl.startsWith('/catalogo-temas')) return true;
  return currentUrl.startsWith(`/${routeKey}`);
};

const handleLogout = () => {
  toast.info('Encerrando Sessão', 'Desconectando com segurança...');
  router.post('/logout', {}, {
    onSuccess: () => {
      toast.success('Sessão Encerrada', 'Você saiu com segurança.');
    },
    onError: () => {
      toast.error('Erro ao Sair', 'Não foi possível desconectar do sistema.');
    },
  });
};

watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) return;
    if (flash.success) toast.success('Sucesso', flash.success);
    if (flash.error) toast.error('Atenção', flash.error);
    if (flash.warning) toast.warning('Aviso', flash.warning);
    if (flash.info || flash.message) toast.info('Informação', flash.info || flash.message);
  },
  { deep: true, immediate: true }
);
</script>
