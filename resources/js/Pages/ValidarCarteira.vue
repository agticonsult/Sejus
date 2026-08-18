<template>
  <div class="min-h-screen bg-slate-100 flex flex-col justify-center items-center p-4 md:p-6 font-sans text-slate-800">
    <Head title="Validador Público de Carteira Digital" />

    <!-- Top Navigation & Accessibility Bar -->
    <div class="w-full max-w-xl flex justify-between items-center mb-4 text-xs">
      <Link href="/dashboard" class="hover:underline flex items-center gap-1 font-bold text-[#003366] transition">
        <span>← Voltar ao Conecta Egresso</span>
      </Link>
      <AccessibilityToolbar :show-labels="false" />
    </div>

    <!-- Official Validation Card -->
    <div class="validation-card bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full overflow-hidden">
      <!-- Header with Espírito Santo State Branding -->
      <div class="card-header bg-[#003366] text-white p-6 text-center relative border-b-4 border-amber-400">
        <div class="flex items-center justify-center gap-2 mb-2">
          <div class="es-flag-badge w-5 h-6 rounded flex flex-col overflow-hidden shadow-xs" aria-hidden="true">
            <span class="h-1/3 bg-[#e63946]"></span>
            <span class="h-1/3 bg-[#ffffff]"></span>
            <span class="h-1/3 bg-[#003366]"></span>
          </div>
          <h1 class="text-xs font-extrabold uppercase tracking-widest text-slate-200 font-heading">
            Governo do Estado do Espírito Santo
          </h1>
        </div>
        <h2 class="text-xs font-bold text-sky-300 uppercase tracking-wide">
          Secretaria de Estado da Justiça — SEJUS / Escritório Social Digital
        </h2>
        <p class="text-[11px] text-slate-300 mt-1">
          Validador Público de Autenticidade de Carteira Digital do Egresso
        </p>
      </div>

      <!-- Body Content -->
      <div class="card-body p-6 md:p-8">
        <!-- CASE 1: Valid & Authentic Credential -->
        <div v-if="validationResult && validationResult.valid" class="space-y-6">
          <!-- Status Banner -->
          <div
            class="status-badge status-valid flex items-center gap-3.5 p-4 bg-emerald-50 border border-emerald-300 text-emerald-900 rounded-2xl font-bold text-xs"
            role="status"
          >
            <span class="text-2xl text-emerald-600 flex-shrink-0">✅</span>
            <div>
              <span class="block font-extrabold text-emerald-950 text-sm">DOCUMENTO OFICIAL AUTÊNTICO</span>
              <span class="text-[11px] text-emerald-800 font-medium">
                {{ validationResult.message || 'Credencial homologada pela SEJUS/ES em conformidade com a Lei Complementar Estadual nº 182/2021.' }}
              </span>
            </div>
          </div>

          <!-- Document Data Grid -->
          <div class="info-grid grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs" aria-label="Dados da Credencial">
            <div class="info-item bg-slate-50 p-3.5 rounded-xl border-l-4 border-sky-600 sm:col-span-2">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Nome Completo do Titular</span>
              <span class="info-value font-extrabold text-sm text-slate-900 block mt-0.5">{{ docPayload.nome || '---' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-xl border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">CPF Mascarado (LGPD)</span>
              <span class="info-value font-bold text-xs text-slate-800 font-mono block mt-0.5">{{ docPayload.cpf_masked || '---' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-xl border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Registro Geral SEJUS</span>
              <span class="info-value font-bold text-xs text-slate-800 font-mono block mt-0.5">{{ docPayload.registro_sejus || '---' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-xl border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Município de Referência</span>
              <span class="info-value font-bold text-xs text-slate-800 block mt-0.5">{{ docPayload.municipio || 'Espírito Santo' }}</span>
            </div>

            <div class="info-item bg-slate-50 p-3.5 rounded-xl border-l-4 border-sky-600">
              <span class="info-label text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Validade da Credencial</span>
              <span class="info-value font-bold text-xs text-slate-800 block mt-0.5">
                {{ formatExpiration(docPayload.expires_at || docPayload.exp) }}
              </span>
            </div>
          </div>

          <!-- Cryptographic Seal Verification Footer -->
          <div class="crypto-seal-box bg-slate-50 p-3.5 rounded-xl border border-slate-200 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 gap-2">
            <div class="flex items-center gap-2">
              <span class="text-base text-sky-600">🛡️</span>
              <span>Selo Criptográfico: <strong class="font-mono text-slate-900">{{ authenticitySeal }}</strong></span>
            </div>
            <span v-if="validationResult.verification_count" class="text-[11px] bg-slate-200 px-2.5 py-0.5 rounded-full text-slate-700 font-semibold">
              Consultas: {{ validationResult.verification_count }}
            </span>
          </div>
        </div>

        <!-- CASE 2: Expired Document -->
        <div v-else-if="validationResult && validationResult.status === 'EXPIRED_DOCUMENT'" class="space-y-4" role="alert">
          <div class="status-badge status-expired flex items-center gap-3.5 p-4 bg-amber-50 border border-amber-300 text-amber-950 rounded-2xl font-bold text-xs">
            <span class="text-2xl text-amber-600 flex-shrink-0">⚠️</span>
            <div>
              <span class="block font-extrabold text-sm">DOCUMENTO EXPIRADO</span>
              <span class="text-[11px] text-amber-800 font-medium">
                {{ validationResult.message || 'O prazo oficial de validade de 12 meses foi ultrapassado.' }}
              </span>
            </div>
          </div>
          <p class="text-xs text-slate-600 leading-relaxed">
            Solicite a revalidação da credencial junto ao Escritório Social do município de referência ou através do portal Conecta Egresso.
          </p>
        </div>

        <!-- CASE 3: Revoked Document -->
        <div v-else-if="validationResult && (validationResult.status === 'REVOGADO' || validationResult.status === 'REVOKED_DOCUMENT')" class="space-y-4" role="alert">
          <div class="status-badge status-invalid flex items-center gap-3.5 p-4 bg-red-50 border border-red-300 text-red-950 rounded-2xl font-bold text-xs">
            <span class="text-2xl text-red-600 flex-shrink-0">⛔</span>
            <div>
              <span class="block font-extrabold text-sm">DOCUMENTO REVOGADO</span>
              <span class="text-[11px] text-red-800 font-medium">
                {{ validationResult.message || 'Documento revogado administrativamente ou judicialmente pela SEJUS/ES.' }}
              </span>
            </div>
          </div>
        </div>

        <!-- CASE 4: Tampered / Invalid Document -->
        <div v-else-if="validationResult && !validationResult.valid" class="space-y-4" role="alert">
          <div class="status-badge status-invalid flex items-center gap-3.5 p-4 bg-red-50 border border-red-300 text-red-950 rounded-2xl font-bold text-xs">
            <span class="text-2xl text-red-600 flex-shrink-0">❌</span>
            <div>
              <span class="block font-extrabold text-sm">DOCUMENTO INVÁLIDO OU ADULTERADO</span>
              <span class="text-[11px] text-red-800 font-medium">
                {{ validationResult.message || 'A assinatura criptográfica não confere com a chave oficial do Governo do Estado do Espírito Santo.' }}
              </span>
            </div>
          </div>
          <p class="text-xs text-slate-600 leading-relaxed">
            Atenção: A falsificação ou uso de documentos públicos adulterados constitui crime previsto no Código Penal Brasileiro.
          </p>
        </div>

        <!-- CASE 5: Manual Token Search Form (Empty / Search State) -->
        <div v-else class="space-y-4">
          <p class="text-xs text-slate-600 text-center mb-3">
            Insira o token criptográfico ou código contido na Carteira Digital para consultar a autenticidade junto à SEJUS/ES.
          </p>
          <form @submit.prevent="handleManualSearch" class="flex gap-2">
            <input
              v-model="inputToken"
              type="text"
              placeholder="Cole o token de validação aqui..."
              class="flex-1 px-4 py-2.5 text-xs border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono"
              required
            />
            <button
              type="submit"
              class="px-5 py-2.5 bg-[#003366] text-white rounded-xl text-xs font-bold hover:bg-[#002244] transition cursor-pointer"
            >
              Validar
            </button>
          </form>
        </div>

        <!-- Another Lookup Button if result is shown -->
        <div v-if="validationResult" class="mt-6 pt-4 border-t border-slate-100 flex justify-center">
          <button
            type="button"
            class="text-xs font-bold text-[#003366] hover:underline"
            @click="resetSearch"
          >
            🔍 Validar Outra Carteira
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AccessibilityToolbar from '../Components/AccessibilityToolbar.vue';

const props = defineProps({
  result: {
    type: Object,
    default: null,
  },
  token: {
    type: String,
    default: '',
  },
});

const inputToken = ref(props.token || '');
const validationResult = ref(props.result);

const docPayload = computed(() => {
  if (!validationResult.value) return {};
  return validationResult.value.payload || validationResult.value || {};
});

const authenticitySeal = computed(() => {
  if (validationResult.value?.selo_autenticidade) {
    return validationResult.value.selo_autenticidade;
  }
  const t = props.token || inputToken.value || '';
  const parts = t.split('.');
  const sig = parts.length === 2 ? parts[1] : t;
  return `SEJUS-VALID-${sig.slice(0, 16).toUpperCase()}`;
});

const formatExpiration = (val) => {
  if (!val) return '17/08/2027';
  if (typeof val === 'number') {
    return new Date(val * 1000).toLocaleDateString('pt-BR');
  }
  return String(val);
};

const handleManualSearch = () => {
  if (!inputToken.value.trim()) return;
  router.visit(`/validar-carteira/${encodeURIComponent(inputToken.value.trim())}`);
};

const resetSearch = () => {
  validationResult.value = null;
  inputToken.value = '';
  router.visit('/validar-carteira');
};
</script>
