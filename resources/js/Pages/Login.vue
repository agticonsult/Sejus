<template>
  <div class="login-page min-h-screen bg-slate-950 text-slate-100 flex flex-col justify-between relative overflow-hidden font-sans antialiased">
    <!-- Subtle Ambient Glows -->
    <div class="absolute top-0 right-0 -mr-40 -mt-40 w-96 h-96 rounded-full bg-sky-600/10 blur-3xl pointer-events-none" aria-hidden="true"></div>
    <div class="absolute bottom-0 left-0 -ml-40 -mb-40 w-96 h-96 rounded-full bg-indigo-600/10 blur-3xl pointer-events-none" aria-hidden="true"></div>

    <!-- Top Header / Accessibility Bar -->
    <header class="top-nav w-full px-4 sm:px-8 py-3.5 flex items-center justify-between z-10 border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md">
      <!-- Institutional Branding -->
      <div class="flex items-center gap-3">
        <!-- ES Flag Ribbon -->
        <div class="es-flag w-6 h-7 rounded overflow-hidden flex flex-col shadow-xs flex-shrink-0" aria-hidden="true">
          <span class="h-1/3 bg-[#e63946]"></span>
          <span class="h-1/3 bg-[#ffffff]"></span>
          <span class="h-1/3 bg-[#003366]"></span>
        </div>
        <div>
          <span class="text-xs sm:text-sm font-bold tracking-tight text-white uppercase block leading-tight">
            Governo do Estado do Espírito Santo
          </span>
          <span class="text-[10px] sm:text-[11px] font-medium text-slate-400 uppercase tracking-wider block">
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
      <div class="w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-slate-200 text-slate-800 p-6 sm:p-8 md:p-10 transition-all duration-300">
        <!-- Header: Portal Title & Seal -->
        <div class="text-center mb-6">
          <div class="inline-flex items-center justify-center gap-2.5 mb-1.5">
            <Building2 class="w-6 h-6 text-[#003366] stroke-[2]" />
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#003366] tracking-tight">
              CONECTA <span class="text-sky-600">EGRESSO</span>
            </h1>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 font-medium">
            Plataforma Integrada de Reintegração Social e Cidadania do ES
          </p>
          <div class="flex items-center justify-center gap-2 mt-2.5">
            <span class="bg-sky-50 text-sky-800 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border border-sky-100">
              Escritório Social Digital
            </span>
            <span class="bg-emerald-50 text-emerald-800 text-[11px] font-semibold px-2.5 py-0.5 rounded-full border border-emerald-100">
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
              <span class="bg-white px-2.5 text-slate-400 font-semibold tracking-wider text-[11px]">Acesso Unificado do Cidadão</span>
            </div>
          </div>

          <button
            id="govBrSsoBtn"
            type="button"
            class="w-full bg-[#1351b4] hover:bg-[#0c326f] active:bg-[#092452] text-white font-bold py-3.5 px-4 rounded-xl shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-3 cursor-pointer min-h-[48px] focus:ring-4 focus:ring-blue-100 outline-none group disabled:opacity-60"
            :disabled="isLoadingGovBr"
            @click="handleGovBrLogin"
          >
            <div class="w-6 h-6 rounded-full bg-white flex items-center justify-center flex-shrink-0 shadow-2xs">
              <span class="text-[#1351b4] font-black text-xs">gov</span>
            </div>
            <span class="text-sm sm:text-base">
              {{ isLoadingGovBr ? 'Redirecionando ao Gov.br...' : 'Entrar com Gov.br / Acesso Cidadão' }}
            </span>
            <span class="ml-auto text-xs bg-white/20 text-white px-2 py-0.5 rounded-full font-semibold group-hover:bg-white/30 hidden sm:inline">
              Ouro / Prata / Bronze
            </span>
          </button>

          <p class="text-[11px] text-slate-400 text-center mt-2">
            Autenticação oficial habilitada para perfis autorizados: gestor, tecnico, egresso, empregador e suporte.
          </p>
        </div>

        <!-- Separator: OR divider -->
        <div class="relative my-5">
          <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-slate-200"></div>
          </div>
          <div class="relative flex justify-center text-xs uppercase">
            <span class="bg-white px-3 text-slate-400 font-medium tracking-wider text-[11px]">ou acesse com suas credenciais</span>
          </div>
        </div>

        <!-- Secondary Option: Standard Credentials Form -->
        <form @submit.prevent="handleCredentialsLogin" class="space-y-4" novalidate>
          <!-- Email or CPF Input -->
          <div>
            <label for="loginIdentifier" class="block text-xs font-semibold text-slate-700 mb-1">
              CPF ou E-mail Institucional <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <User class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 stroke-[2]" aria-hidden="true" />
              <input
                id="loginIdentifier"
                v-model="form.login"
                type="text"
                autocomplete="username"
                placeholder="000.000.000-00 ou usuario@instituicao.gov.br"
                class="w-full pl-9 pr-4 py-2.5 text-sm bg-slate-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-sky-100 focus:border-sky-500 outline-none transition"
                :class="errors.login ? 'border-rose-400 bg-rose-50/50' : 'border-slate-300'"
                required
              />
            </div>
            <p v-if="errors.login" class="text-rose-600 text-xs font-medium mt-1 flex items-center gap-1">
              <AlertCircle class="w-3.5 h-3.5" /> {{ errors.login }}
            </p>
          </div>

          <!-- Password Input -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label for="loginPassword" class="block text-xs font-semibold text-slate-700">
                Senha de Acesso <span class="text-rose-500">*</span>
              </label>
              <a href="#" @click.prevent="handleForgotPassword" class="text-xs text-sky-700 hover:text-sky-900 font-semibold hover:underline">
                Esqueceu a senha?
              </a>
            </div>
            <div class="relative">
              <Lock class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 stroke-[2]" aria-hidden="true" />
              <input
                id="loginPassword"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Digite sua senha"
                class="w-full pl-9 pr-10 py-2.5 text-sm bg-slate-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-sky-100 focus:border-sky-500 outline-none transition"
                :class="errors.password ? 'border-rose-400 bg-rose-50/50' : 'border-slate-300'"
                required
              />
              <button
                type="button"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 p-1 rounded cursor-pointer"
                :aria-label="showPassword ? 'Ocultar senha' : 'Exibir senha'"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" class="w-4 h-4 stroke-[1.75]" />
                <Eye v-else class="w-4 h-4 stroke-[1.75]" />
              </button>
            </div>
            <p v-if="errors.password" class="text-rose-600 text-xs font-medium mt-1 flex items-center gap-1">
              <AlertCircle class="w-3.5 h-3.5" /> {{ errors.password }}
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
            class="w-full bg-[#003366] hover:bg-[#002244] active:bg-[#001730] text-white font-bold py-3 px-4 rounded-xl shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer min-h-[44px] focus:ring-4 focus:ring-sky-200 outline-none disabled:opacity-60"
            :disabled="isLoadingCredentials"
          >
            <span v-if="isLoadingCredentials" class="animate-spin text-sm">↻</span>
            <span class="text-sm font-semibold">
              {{ isLoadingCredentials ? 'Entrando no sistema...' : 'Entrar no Conecta Egresso' }}
            </span>
          </button>
        </form>

        <!-- Security & LGPD Seal Badge -->
        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
          <div class="flex items-center gap-1.5">
            <Lock class="w-3.5 h-3.5 stroke-[2] text-slate-400" />
            <span>Criptografia <strong>AES-256</strong> & LGPD Blind Index</span>
          </div>
          <span class="text-emerald-700 font-semibold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            Conexão Segura
          </span>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="w-full px-4 sm:px-8 py-3 text-center text-slate-400 text-xs z-10 border-t border-slate-800/80 bg-slate-900/60 backdrop-blur-md">
      <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
        <span>© 2026 Secretaria de Estado da Justiça (SEJUS) — Governo do Estado do Espírito Santo.</span>
        <div class="flex items-center gap-4 text-[11px]">
          <a href="#" @click.prevent="isPrivacyModalOpen = true" class="hover:text-white transition underline cursor-pointer">Termos de Uso</a>
          <span>•</span>
          <a href="#" @click.prevent="isPrivacyModalOpen = true" class="hover:text-white transition underline cursor-pointer">Privacidade & LGPD</a>
          <span>•</span>
          <a href="#" @click.prevent="toast.info('Suporte Técnico SEJUS', 'Central de Atendimento: (27) 3636-5700.')" class="hover:text-white">Suporte Técnico</a>
        </div>
      </div>
    </footer>

    <!-- Global Reactive Toast Container -->
    <ToastContainer />

    <!-- LGPD Cookie Consent Banner -->
    <CookieConsentBanner @open-privacy="isPrivacyModalOpen = true" />

    <!-- Legal Terms of Use & Privacy Policy Modal -->
    <PrivacyTermsModal :is-open="isPrivacyModalOpen" @close="isPrivacyModalOpen = false" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
  User,
  Lock,
  Eye,
  EyeOff,
  Building2,
  AlertCircle,
} from 'lucide-vue-next';
import AccessibilityToolbar from '../Components/AccessibilityToolbar.vue';
import ToastContainer from '../Components/ToastContainer.vue';
import CookieConsentBanner from '../Components/CookieConsentBanner.vue';
import PrivacyTermsModal from '../Components/PrivacyTermsModal.vue';
import { useToast } from '../Composables/useToast';

const isPrivacyModalOpen = ref(false);

const props = defineProps({
  govbrAuthUrl: {
    type: String,
    default: null,
  },
});

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

const handleCredentialsLogin = () => {
  errors.login = '';
  errors.password = '';

  if (!form.login.trim()) {
    errors.login = 'Informe seu CPF ou E-mail institucional.';
    toast.error('Campo Obrigatório', errors.login);
    return;
  }

  if (!form.password) {
    errors.password = 'Digite sua senha de acesso.';
    toast.error('Campo Obrigatório', errors.password);
    return;
  }

  isLoadingCredentials.value = true;

  router.post('/login', {
    login: form.login,
    password: form.password,
    remember: form.remember,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      isLoadingCredentials.value = false;
      toast.success('Autenticação Realizada', 'Redirecionando para o painel operacional...');
    },
    onError: (errs) => {
      isLoadingCredentials.value = false;
      if (errs.login) errors.login = errs.login;
      if (errs.password) errors.password = errs.password;
      toast.error('Falha de Autenticação', errs.login || errs.password || 'Credenciais inválidas.');
    },
    onFinish: () => {
      isLoadingCredentials.value = false;
    },
  });
};

const handleGovBrLogin = () => {
  const ssoUrl = props.govbrAuthUrl || page.props.govbrAuthUrl;
  if (ssoUrl && typeof window !== 'undefined') {
    isLoadingGovBr.value = true;
    window.location.href = ssoUrl;
    return;
  }

  toast.info(
    'Provedor Gov.br / Acesso Cidadão',
    'Utilize seu CPF ou E-mail e senha institucional para autenticar nesta instância.'
  );
};

const handleForgotPassword = () => {
  toast.info('Recuperação de Senha', 'Entre em contato com o administrador SEJUS ou utilize o portal Acesso Cidadão ES.');
};
</script>
