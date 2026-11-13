<template>
  <transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="transform translate-y-full opacity-0"
    enter-to-class="transform translate-y-0 opacity-100"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="transform translate-y-0 opacity-100"
    leave-to-class="transform translate-y-full opacity-0"
  >
    <div
      v-if="isVisible"
      id="cookieConsentBanner"
      class="fixed bottom-0 inset-x-0 z-40 p-4 sm:p-6 pointer-events-none"
      role="region"
      aria-label="Aviso de Privacidade e Cookies"
    >
      <div class="max-w-5xl mx-auto bg-slate-900/95 text-white p-4 sm:p-6 rounded-2xl shadow-2xl border border-slate-700/80 backdrop-blur-md pointer-events-auto flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
          <div class="w-9 h-9 rounded-xl bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center flex-shrink-0 mt-0.5">
            <Cookie class="w-5 h-5 stroke-[2]" />
          </div>
          <div class="space-y-1 max-w-2xl">
            <h4 class="text-xs sm:text-sm font-bold text-white flex items-center gap-2">
              <span>🍪 Privacidade e Cookies Institucionais</span>
              <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-500/30">LGPD</span>
            </h4>
            <p class="text-xs text-slate-300 leading-relaxed">
              O portal <strong>CONECTA EGRESSO (SEJUS/ES)</strong> utiliza cookies estritamente necessários para autenticação segura, prevenção a fraudes (CSRF) e persistência de preferências de acessibilidade e alto contraste. Não utilizamos cookies para rastreamento comercial de terceiros.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2.5 w-full md:w-auto justify-end flex-shrink-0">
          <button
            type="button"
            class="px-3 py-2 text-xs font-semibold text-slate-300 hover:text-white hover:underline transition cursor-pointer"
            @click="openPrivacyModal"
          >
            Termos & LGPD
          </button>
          <button
            type="button"
            class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 rounded-xl text-xs font-bold transition cursor-pointer"
            @click="acceptEssentialOnly"
          >
            Apenas Essenciais
          </button>
          <button
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md transition cursor-pointer"
            @click="acceptAllCookies"
          >
            Aceitar Todos
          </button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Cookie } from 'lucide-vue-next';

const COOKIE_CONSENT_KEY = 'conecta_cookie_consent_status';
const isVisible = ref(false);

const emit = defineEmits(['open-privacy']);

onMounted(() => {
  const saved = localStorage.getItem(COOKIE_CONSENT_KEY);
  if (!saved) {
    isVisible.value = true;
  }
});

const acceptAllCookies = () => {
  localStorage.setItem(COOKIE_CONSENT_KEY, 'all');
  isVisible.value = false;
};

const acceptEssentialOnly = () => {
  localStorage.setItem(COOKIE_CONSENT_KEY, 'essential');
  isVisible.value = false;
};

const openPrivacyModal = () => {
  emit('open-privacy');
};
</script>
