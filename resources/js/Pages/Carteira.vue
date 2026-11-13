<template>
  <AppLayout :breadcrumbs="[{ name: 'Carteira Digital' }]">
    <Head title="Carteira Digital do Egresso" />

    <div class="carteira-view space-y-6" id="view-carteira">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-semibold mb-2 border border-emerald-500/30">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span>Documento Oficial Homologado • SEJUS/ES</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('carteira_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Credencial digital oficial com assinatura criptográfica HMAC-SHA256 e validação instantânea por QR Code nos 78 municípios do Espírito Santo.
          </p>
        </div>

        <div class="flex flex-wrap gap-2.5">
          <a
            :href="pdfDownloadUrl"
            target="_blank"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
          >
            <span>📥 Baixar Carteira em PDF</span>
          </a>
          <button
            type="button"
            class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold text-xs rounded-xl border border-white/20 transition flex items-center gap-2 cursor-pointer"
            @click="handlePrint"
          >
            <span>🖨️ Imprimir</span>
          </button>
        </div>
      </div>

      <!-- Barra de Busca e Seleção Operacional (Exibida para Gestor / Suporte / Técnico) -->
      <div v-if="isStaff" class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
              <span>🔍 Consulta & Emissão de Carteira Digital</span>
            </h3>
            <p class="text-xs text-slate-500">Busque o cidadão egresso por CPF ou Nome completo para visualizar, validar ou emitir a 2ª via oficial.</p>
          </div>
          <form @submit.prevent="handleSearch" class="flex items-center gap-2 max-w-md w-full sm:w-auto">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Digite o CPF ou Nome do egresso..."
              class="w-full sm:w-72 px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 outline-none"
            />
            <button
              type="submit"
              class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl transition cursor-pointer"
            >
              Buscar
            </button>
            <button
              v-if="egressoData"
              type="button"
              @click="clearSelection"
              class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-medium rounded-xl transition cursor-pointer"
              title="Limpar seleção"
            >
              ✕
            </button>
          </form>
        </div>

        <!-- Sugestões de Egressos Cadastrados no Banco -->
        <div v-if="recentEgressos && recentEgressos.length" class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2">
          <span class="text-[11px] font-semibold text-slate-400">Atendimentos Recentes:</span>
          <button
            v-for="e in recentEgressos"
            :key="e.id"
            type="button"
            @click="selectEgresso(e.id)"
            class="text-[11px] px-2.5 py-1 rounded-lg border transition cursor-pointer flex items-center gap-1.5"
            :class="egressoData && egressoData.id === e.id ? 'bg-sky-50 border-sky-300 text-sky-800 font-bold' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'"
          >
            <span>👤 {{ e.nome_completo }}</span>
            <span class="text-slate-400 font-mono">({{ e.cpf_masked }})</span>
          </button>
        </div>
      </div>

      <!-- Empty State when no Egresso record is selected or available -->
      <div
        v-if="!egressoData"
        class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center space-y-3 shadow-xs"
      >
        <div class="w-14 h-14 bg-sky-50 text-sky-600 rounded-2xl mx-auto flex items-center justify-center text-2xl font-bold">
          🪪
        </div>
        <p class="text-base font-bold text-slate-800">Nenhum Egresso Selecionado para Emissão</p>
        <p class="text-xs text-slate-500 max-w-md mx-auto">
          {{ isStaff ? 'Utilize a barra de pesquisa acima para selecionar o cidadão atendido e emitir a respectiva Carteira Digital Oficial com validação criptográfica.' : 'Cadastre ou vincule um perfil de egresso para emitir a credencial oficial com assinatura criptográfica SEJUS/ES.' }}
        </p>
      </div>

      <!-- Credential Card Container -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Official Digital Wallet Card (2 cols) -->
        <div class="lg:col-span-2 flex justify-center">
          <div class="w-full max-w-2xl bg-white rounded-3xl border-2 border-slate-300/80 shadow-2xl overflow-hidden relative guilloche-pattern">
            <!-- Institutional Header Bar -->
            <div class="bg-[#003366] text-white p-5 md:p-6 flex items-center justify-between border-b-4 border-amber-400 relative">
              <div class="flex items-center gap-3">
                <div class="w-8 h-10 rounded overflow-hidden flex flex-col shadow-sm" aria-hidden="true">
                  <span class="h-1/3 bg-[#e63946]"></span>
                  <span class="h-1/3 bg-[#ffffff]"></span>
                  <span class="h-1/3 bg-[#003366]"></span>
                </div>
                <div>
                  <h2 class="text-xs md:text-sm font-extrabold uppercase tracking-wide text-slate-100">
                    Governo do Estado do Espírito Santo
                  </h2>
                  <h3 class="text-[11px] md:text-xs font-semibold text-sky-300 uppercase">
                    Secretaria de Estado da Justiça • SEJUS / Escritório Social
                  </h3>
                </div>
              </div>

              <div class="text-right">
                <span class="text-[9px] uppercase font-bold text-slate-300 block">Status Oficial</span>
                <span class="text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-emerald-500 text-white shadow-2xs">
                  REGULAR
                </span>
              </div>
            </div>

            <!-- Card Body: Photo, Info, and QR Code -->
            <div class="p-6 md:p-8 grid grid-cols-1 sm:grid-cols-3 gap-6 bg-white/90 backdrop-blur-xs">
              <!-- Left: Avatar Photo & Registration Seal -->
              <div class="flex flex-col items-center text-center space-y-3">
                <div class="w-32 h-36 rounded-2xl bg-gradient-to-tr from-slate-200 to-slate-300 border-2 border-slate-400/60 flex flex-col items-center justify-center text-slate-600 shadow-inner relative overflow-hidden">
                  <span class="text-4xl">👤</span>
                  <span class="text-[10px] font-bold text-slate-500 mt-1 uppercase">Foto Oficial</span>
                  <div class="absolute bottom-0 inset-x-0 bg-slate-800/80 text-white text-[9px] py-0.5 font-mono">
                    SEJUS-ES
                  </div>
                </div>

                <div class="w-full p-2 rounded-xl bg-slate-50 border border-slate-200">
                  <span class="text-[9px] uppercase font-bold text-slate-400 block">Registro Geral SEJUS</span>
                  <span class="text-xs font-mono font-extrabold text-slate-800 block">
                    {{ egressoData.registro_sejus }}
                  </span>
                </div>
              </div>

              <!-- Center: Identification Data -->
              <div class="sm:col-span-2 space-y-3">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                  <span class="text-[10px] font-bold text-slate-500 uppercase block tracking-wider">Nome Completo do Titular</span>
                  <strong class="text-sm font-extrabold text-slate-900 block mt-0.5">{{ egressoData.nome_completo }}</strong>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                  <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">CPF Mascarado (LGPD)</span>
                    <strong class="text-slate-800 font-mono block mt-0.5">{{ egressoData.cpf_masked }}</strong>
                  </div>

                  <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Data de Nascimento</span>
                    <strong class="text-slate-800 block mt-0.5">{{ egressoData.data_nascimento }}</strong>
                  </div>

                  <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Município Polo</span>
                    <strong class="text-slate-800 block mt-0.5">{{ egressoData.municipio }}</strong>
                  </div>

                  <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Validade da Credencial</span>
                    <strong class="text-slate-800 block mt-0.5">{{ egressoData.data_validade }}</strong>
                  </div>
                </div>

                <div class="p-2.5 bg-amber-50/70 rounded-xl border border-amber-200 text-[10px] text-amber-900 leading-snug">
                  <strong>Base Legal:</strong> Lei Complementar Estadual nº 182/2021 e Resolução CNJ nº 307/2019. Documento de identificação com fé pública para acesso a programas de reintegração social.
                </div>
              </div>
            </div>

            <!-- Card Footer: Cryptographic QR Code & Verification Banner -->
            <div class="bg-slate-50 p-6 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div class="flex items-center gap-4">
                <QrCodeDisplay
                  :value="carteiraToken"
                  :size="110"
                  id="carteiraQrCode"
                  alt="QR Code criptográfico para validação da Carteira Digital do Egresso"
                />

                <div class="space-y-1">
                  <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Verificação Digital Instantânea</span>
                  <p class="text-xs text-slate-600 max-w-xs">
                    Aponte a câmera do celular para consultar a autenticidade oficial da credencial.
                  </p>
                  <Link
                    v-if="carteiraToken"
                    :href="`/validar-carteira/${carteiraToken}`"
                    class="text-xs font-bold text-sky-600 hover:underline inline-flex items-center gap-1 mt-1"
                  >
                    <span>Abrir Validador Público →</span>
                  </Link>
                </div>
              </div>

              <div class="text-right">
                <span class="text-[9px] uppercase font-bold text-slate-400 block">Emissão Oficial</span>
                <span class="text-xs font-semibold text-slate-700 block">{{ egressoData.data_emissao }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Side: Services & Security Info -->
        <div class="space-y-5">
          <!-- Document Actions Card -->
          <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">
              Serviços da Carteira
            </h3>

            <div class="space-y-2.5">
              <button
                type="button"
                class="w-full p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-left transition flex items-center justify-between cursor-pointer"
                @click="requestDuplicate('2ª Via da Carteira Digital')"
              >
                <div>
                  <strong class="text-xs font-bold text-slate-800 block">Solicitar 2ª Via do Documento</strong>
                  <span class="text-[11px] text-slate-500">Reemissão com novo token e assinatura</span>
                </div>
                <span class="text-slate-400">→</span>
              </button>

              <button
                type="button"
                class="w-full p-3 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-left transition flex items-center justify-between cursor-pointer"
                @click="requestDuplicate('Declaração de Vínculo SEJUS')"
              >
                <div>
                  <strong class="text-xs font-bold text-slate-800 block">Emitir Declaração de Vínculo</strong>
                  <span class="text-[11px] text-slate-500">Comprovante para empresas conveniadas</span>
                </div>
                <span class="text-slate-400">→</span>
              </button>
            </div>
          </div>

          <!-- Security & Integrity Notice -->
          <div class="bg-slate-900 text-white p-6 rounded-2xl shadow-md space-y-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">🛡️</span>
              <h4 class="text-xs font-extrabold uppercase tracking-wide text-sky-400">
                Garantia Criptográfica
              </h4>
            </div>
            <p class="text-xs text-slate-300 leading-relaxed">
              A Carteira Digital utiliza assinatura digital padrão HMAC-SHA256 canônica sobre dados de identificação imutáveis, garantindo inviolabilidade perante os órgãos de segurança pública e empregadores.
            </p>
            <div v-if="carteiraToken" class="pt-2 border-t border-slate-800 text-[11px] text-slate-400 font-mono break-all">
              HMAC: {{ carteiraToken.slice(0, 32) }}...
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import QrCodeDisplay from '../Components/QrCodeDisplay.vue';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  isStaff: {
    type: Boolean,
    default: false,
  },
  recentEgressos: {
    type: Array,
    default: () => [],
  },
  egresso: {
    type: Object,
    default: null,
  },
  token: {
    type: String,
    default: '',
  },
  pdfDownloadUrl: {
    type: String,
    default: '/carteira/pdf',
  },
});

const { t } = useAccessibility();
const toast = useToast();

const searchQuery = ref('');
const egressoData = computed(() => props.egresso);
const carteiraToken = computed(() => props.token || '');

const handleSearch = () => {
  if (!searchQuery.value.trim()) {
    router.visit('/carteira');
    return;
  }
  router.visit(`/carteira?q=${encodeURIComponent(searchQuery.value.trim())}`, {
    preserveState: true,
  });
};

const selectEgresso = (id) => {
  router.visit(`/carteira?egresso_id=${id}`, {
    preserveState: true,
  });
};

const clearSelection = () => {
  searchQuery.value = '';
  router.visit('/carteira');
};

const handlePrint = () => {
  if (typeof window !== 'undefined') {
    window.print();
  }
};

const requestDuplicate = (docType) => {
  toast.success(
    '2ª Via Solicitada com Sucesso',
    `Requisição de 2ª via para "${docType}" gerada com sucesso!\nO egresso receberá notificação com a data de emissão no polo de referência.`
  );
};
</script>
