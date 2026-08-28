<template>
  <div class="login-page min-h-screen bg-gradient-to-br from-slate-900 via-[#003366] to-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden font-sans">
    <!-- Background Ambient Glows & ES Geometry -->
    <div class="absolute top-0 right-0 -mr-32 -mt-32 w-96 h-96 rounded-full bg-[#1351b4]/20 blur-3xl pointer-events-none" aria-hidden="true"></div>
    <div class="absolute bottom-0 left-0 -ml-32 -mb-32 w-96 h-96 rounded-full bg-[#e63946]/15 blur-3xl pointer-events-none" aria-hidden="true"></div>

    <!-- Top Header / Accessibility Bar -->
    <header class="top-nav w-full px-4 sm:px-8 py-4 flex items-center justify-between z-10 border-b border-white/10 backdrop-blur-md bg-slate-900/40">
      <!-- Institutional Branding -->
      <div class="flex items-center gap-3">
        <!-- ES Flag Ribbon -->
        <div class="es-flag w-6 h-7 rounded overflow-hidden flex flex-col shadow-xs flex-shrink-0" aria-hidden="true">
          <span class="h-1/3 bg-[#e63946]"></span>
          <span class="h-1/3 bg-[#ffffff]"></span>
          <span class="h-1/3 bg-[#003366]"></span>
        </div>
        <div>
          <span class="text-xs sm:text-sm font-extrabold tracking-wider text-white uppercase block leading-tight">
            Governo do Estado do Espírito Santo
          </span>
          <span class="text-[10px] sm:text-[11px] font-semibold text-sky-300 uppercase tracking-widest block">
            SEJUS • Secretaria de Estado da Justiça
          </span>
        </div>
      </div>

      <!-- Accessibility Toolbar -->
      <div class="flex items-center gap-2">
        <AccessibilityToolbar :show-labels="false" />
      </div>
    </header>

    <!-- Main Container / Center Login Card -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 z-10 my-4 sm:my-8" role="main">
      <div class="w-full max-w-xl bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/20 text-slate-800 p-6 sm:p-8 md:p-10 transition-all duration-300">
        
        <!-- Header: Portal Title & Seal -->
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center gap-2 mb-2">
            <span class="text-2xl" aria-hidden="true">🏛️</span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#003366] tracking-tight">
              CONECTA <span class="text-sky-600">EGRESSO</span>
            </h1>
          </div>
          <p class="text-xs sm:text-sm text-slate-600 font-medium">
            Plataforma Integrada de Reintegração Social e Cidadania do ES
          </p>
          <div class="flex items-center justify-center gap-2 mt-2">
            <span class="bg-sky-100 text-sky-800 text-[11px] font-bold px-2.5 py-0.5 rounded-full border border-sky-200">
              Escritório Social Digital
            </span>
            <span class="bg-emerald-100 text-emerald-800 text-[11px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-200">
              78 Municípios
            </span>
          </div>
        </div>

        <!-- Primary Option: Gov.br / Acesso Cidadão SSO Button -->
        <div class="mb-6">
          <div class="relative mb-3">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
              <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
              <span class="bg-white px-2 text-slate-500 font-bold tracking-wider">Acesso Unificado do Cidadão</span>
            </div>
          </div>

          <button
            id="govBrSsoBtn"
            type="button"
            class="w-full bg-[#1351b4] hover:bg-[#0c326f] active:bg-[#092452] text-white font-extrabold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-900/20 hover:shadow-xl transition-all duration-200 flex items-center justify-center gap-3 cursor-pointer min-h-[48px] focus:ring-4 focus:ring-blue-300 outline-none group disabled:opacity-60"
            :disabled="isLoadingGovBr"
            @click="handleGovBrLogin"
          >
            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center flex-shrink-0 shadow-xs">
              <span class="text-[#1351b4] font-black text-xs">gov</span>
            </div>
            <span class="text-sm sm:text-base">
              {{ isLoadingGovBr ? 'Autenticando com Gov.br...' : 'Entrar com Gov.br / Acesso Cidadão' }}
            </span>
            <span class="ml-auto text-xs bg-white/20 text-white px-2 py-0.5 rounded-full font-bold group-hover:bg-white/30 hidden sm:inline">
              Ouro / Prata / Bronze
            </span>
          </button>
          
          <p class="text-[11px] text-slate-500 text-center mt-1.5">
            Acesso único para cidadãos, egressos, familiares e servidores estaduais
          </p>
        </div>

        <!-- Separator: OR divider -->
        <div class="relative my-5">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-slate-200"></div>
          </div>
          <div class="relative flex justify-center text-xs uppercase">
            <span class="bg-white px-3 text-slate-400 font-semibold tracking-wider">ou acesse com suas credenciais</span>
          </div>
        </div>

        <!-- Secondary Option: Standard Credentials Form -->
        <form @submit.prevent="handleCredentialsLogin" class="space-y-4" novalidate>
          <!-- Email or CPF Input -->
          <div>
            <label for="loginIdentifier" class="block text-xs font-bold text-slate-700 mb-1">
              CPF ou E-mail Institucional <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true">👤</span>
              <input
                id="loginIdentifier"
                v-model="form.login"
                type="text"
                autocomplete="username"
                placeholder="000.000.000-00 ou usuario@sejus.es.gov.br"
                class="w-full pl-9 pr-4 py-2.5 text-sm bg-slate-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition"
                :class="errors.login ? 'border-rose-400 bg-rose-50/50' : 'border-slate-300'"
                required
              />
            </div>
            <p v-if="errors.login" class="text-rose-600 text-xs font-medium mt-1 flex items-center gap-1">
              <span>⚠️</span> {{ errors.login }}
            </p>
          </div>

          <!-- Password Input -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label for="loginPassword" class="block text-xs font-bold text-slate-700">
                Senha de Acesso <span class="text-rose-500">*</span>
              </label>
              <a href="#" @click.prevent="handleForgotPassword" class="text-xs text-sky-700 hover:text-sky-900 font-semibold hover:underline">
                Esqueceu a senha?
              </a>
            </div>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm" aria-hidden="true">🔒</span>
              <input
                id="loginPassword"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Digite sua senha"
                class="w-full pl-9 pr-10 py-2.5 text-sm bg-slate-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition"
                :class="errors.password ? 'border-rose-400 bg-rose-50/50' : 'border-slate-300'"
                required
              />
              <button
                type="button"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 p-1 text-sm rounded cursor-pointer"
                :aria-label="showPassword ? 'Ocultar senha' : 'Exibir senha'"
                @click="showPassword = !showPassword"
              >
                {{ showPassword ? '🙈' : '👁️' }}
              </button>
            </div>
            <p v-if="errors.password" class="text-rose-600 text-xs font-medium mt-1 flex items-center gap-1">
              <span>⚠️</span> {{ errors.password }}
            </p>
          </div>

          <!-- Remember Me Checkbox -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input
                id="rememberMe"
                v-model="form.remember"
                type="checkbox"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 border-slate-300 cursor-pointer"
              />
              <span class="text-xs text-slate-600 font-medium">Lembrar-me neste dispositivo</span>
            </label>
          </div>

          <!-- Submit Button -->
          <button
            id="loginSubmitBtn"
            type="submit"
            class="w-full bg-[#003366] hover:bg-[#002244] active:bg-[#001730] text-white font-extrabold py-3 px-4 rounded-xl shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer min-h-[44px] focus:ring-4 focus:ring-sky-300 outline-none disabled:opacity-60"
            :disabled="isLoadingCredentials"
          >
            <span v-if="isLoadingCredentials" class="animate-spin text-base">⏳</span>
            <span class="text-sm font-bold">
              {{ isLoadingCredentials ? 'Entrando no sistema...' : 'Entrar no Conecta Egresso' }}
            </span>
          </button>
        </form>

        <!-- Quick-Fill Demo Credentials Bar -->
        <div class="mt-6 pt-5 border-t border-slate-200">
          <div class="flex items-center justify-between mb-2.5">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
              <span>⚡</span> Preenchimento Rápido para Demonstração:
            </span>
            <span class="text-[10px] text-slate-400">Clique para preencher</span>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <!-- Suporte Agile -->
            <button
              id="demoSuporteBtn"
              type="button"
              class="p-2 rounded-lg border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-900 text-left transition cursor-pointer group"
              @click="quickFill('suporte')"
            >
              <div class="flex items-center gap-1 mb-0.5">
                <span class="text-xs">🛡️</span>
                <strong class="text-[11px] font-bold block truncate">Suporte Agile</strong>
              </div>
              <span class="text-[9px] text-purple-700 block truncate">Admin Geral</span>
            </button>

            <!-- Gestor Estadual -->
            <button
              id="demoGestorBtn"
              type="button"
              class="p-2 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-900 text-left transition cursor-pointer group"
              @click="quickFill('gestor')"
            >
              <div class="flex items-center gap-1 mb-0.5">
                <span class="text-xs">👔</span>
                <strong class="text-[11px] font-bold block truncate">Gestor Estadual</strong>
              </div>
              <span class="text-[9px] text-blue-700 block truncate">SEJUS Central</span>
            </button>

            <!-- Técnico Social -->
            <button
              id="demoTecnicoBtn"
              type="button"
              class="p-2 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 text-left transition cursor-pointer group"
              @click="quickFill('tecnico')"
            >
              <div class="flex items-center gap-1 mb-0.5">
                <span class="text-xs">📋</span>
                <strong class="text-[11px] font-bold block truncate">Técnico Social</strong>
              </div>
              <span class="text-[9px] text-emerald-700 block truncate">Escritório Social</span>
            </button>

            <!-- Egresso Cidadão -->
            <button
              id="demoEgressoBtn"
              type="button"
              class="p-2 rounded-lg border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-900 text-left transition cursor-pointer group"
              @click="quickFill('egresso')"
            >
              <div class="flex items-center gap-1 mb-0.5">
                <span class="text-xs">🤝</span>
                <strong class="text-[11px] font-bold block truncate">Egresso Cidadão</strong>
              </div>
              <span class="text-[9px] text-amber-700 block truncate">Autoatendimento</span>
            </button>
          </div>
        </div>

        <!-- Security & LGPD Seal Badge -->
        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
          <div class="flex items-center gap-1.5">
            <span class="text-sm">🔒</span>
            <span>Criptografia <strong>AES-256</strong> & LGPD Blind Index</span>
          </div>
          <span class="text-emerald-700 font-bold flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Conexão Segura
          </span>
        </div>

      </div>
    </main>

    <!-- Footer -->
    <footer class="w-full px-4 sm:px-8 py-3 text-center text-slate-400 text-xs z-10 border-t border-white/10 bg-slate-900/50 backdrop-blur-md">
      <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>© 2026 Secretaria de Estado da Justiça (SEJUS) — Governo do Estado do Espírito Santo.</span>
        <div class="flex items-center gap-4 text-[11px]">
          <a href="#" @click.prevent="toast.info('Termos de Uso', 'Em conformidade com as diretrizes do CNJ e DEPEN.')" class="hover:text-white">Termos de Uso</a>
          <span>•</span>
          <a href="#" @click.prevent="toast.info('Política de Privacidade', 'Dados protegidos pela LGPD (Lei 13.709/2018).')" class="hover:text-white">Privacidade & LGPD</a>
          <span>•</span>
          <a href="#" @click.prevent="toast.info('Suporte Técnico SEJUS', 'Central de Atendimento: (27) 3636-5700.')" class="hover:text-white">Suporte Técnico</a>
        </div>
      </div>
    </footer>

    <!-- Global Reactive Toast Container -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AccessibilityToolbar from '../Components/AccessibilityToolbar.vue';
import ToastContainer from '../Components/ToastContainer.vue';
import { useToast } from '../Composables/useToast';

const page = usePage();
const toast = useToast();

const form = reactive({
  login: '',
  password: '',
  remember: false,
});

const errors = reactive({
  login: '',
  password: '',
});

const showPassword = ref(false);
const isLoadingCredentials = ref(false);
const isLoadingGovBr = ref(false);

const demoAccounts = {
  suporte: {
    login: 'suporte.agile@sejus.es.gov.br',
    password: 'secret123',
    roleName: 'Suporte Técnico Agile',
    cpf: '999.888.777-00',
  },
  gestor: {
    login: 'gestor@sejus.es.gov.br',
    password: 'secret123',
    roleName: 'Gestor Estadual SEJUS',
    cpf: '111.222.333-44',
  },
  tecnico: {
    login: 'marcia.oliveira@sejus.es.gov.br',
    password: 'secret123',
    roleName: 'Assistente Social Escritório Social',
    cpf: '555.666.777-88',
  },
  egresso: {
    login: 'lucas.santos@cidadao.es.gov.br',
    password: 'secret123',
    roleName: 'Lucas Santos (Egresso Cidadão)',
    cpf: '192.830.456-78',
  },
};

const quickFill = (roleKey) => {
  const account = demoAccounts[roleKey];
  if (!account) return;

  form.login = account.login;
  form.password = account.password;
  errors.login = '';
  errors.password = '';

  toast.info('Credenciais Preenchidas', `Perfil ${account.roleName} carregado. Clique em Entrar.`);
};

const handleCredentialsLogin = () => {
  errors.login = '';
  errors.password = '';

  if (!form.login || form.login.trim() === '') {
    errors.login = 'Por favor, informe seu CPF ou e-mail.';
    toast.warning('Campo Obrigatório', 'Informe seu CPF ou e-mail institucional.');
    return;
  }

  if (!form.password || form.password.trim() === '') {
    errors.password = 'Por favor, digite sua senha de acesso.';
    toast.warning('Campo Obrigatório', 'Digite sua senha de acesso.');
    return;
  }

  isLoadingCredentials.value = true;

  router.post('/login', {
    login: form.login.trim(),
    email: form.login.trim(),
    cpf: form.login.trim(),
    password: form.password,
    remember: form.remember,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Autenticação Realizada', 'Bem-vindo ao Conecta Egresso!');
    },
    onError: (errs) => {
      isLoadingCredentials.value = false;
      if (errs.login) errors.login = errs.login;
      if (errs.password) errors.password = errs.password;
      toast.error('Falha no Login', errs.login || errs.password || 'Credenciais inválidas. Verifique os dados digitados.');
    },
    onFinish: () => {
      isLoadingCredentials.value = false;
    },
  });
};

const handleGovBrLogin = () => {
  isLoadingGovBr.value = true;
  toast.info('Gov.br / Acesso Cidadão', 'Iniciando autenticação unificada do Governo Federal / Estadual...');

  // Simulate Gov.br OIDC Callback with Gestor/Citizen claims
  const simulatedClaims = {
    sub: 'govbr-gestor-001',
    cpf: '111.222.333-44',
    name: 'Carlos Eduardo Silva',
    email: 'gestor@sejus.es.gov.br',
    nivel_confianca: 'Ouro',
    orgao: 'SEJUS',
    cargo: 'Gestor de Políticas Penais e Reintegração',
    scope: 'openid email profile govbr_servidor',
  };

  router.post('/auth/govbr/login', simulatedClaims, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Autenticado via Gov.br', 'Sessão OIDC validada com Nível Ouro.');
    },
    onError: (errs) => {
      isLoadingGovBr.value = false;
      toast.error('Erro na Autenticação Gov.br', errs.govbr || 'Não foi possível autenticar via Gov.br.');
    },
    onFinish: () => {
      isLoadingGovBr.value = false;
    },
  });
};

const handleForgotPassword = () => {
  toast.info('Recuperação de Senha', 'Para redefinir sua senha, entre em contato com o Suporte SEJUS pelo e-mail suporte.agile@sejus.es.gov.br ou telefone (27) 3636-5700.');
};
</script>

<style scoped>
.login-page {
  background-size: 200% 200%;
}
</style>
