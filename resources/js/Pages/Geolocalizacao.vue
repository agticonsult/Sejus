<template>
  <AppLayout :breadcrumbs="[{ name: 'Mapeamento Territorial dos 78 Municípios' }]">
    <Head title="Mapeamento Territorial dos 78 Municípios (ES)" />

    <div class="geolocalizacao-view space-y-6" id="view-geolocalizacao">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] via-slate-900 to-[#0284c7] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 text-sky-200 rounded-full text-xs font-semibold mb-2 border border-white/20">
            <span>📍 Cobertura Estadual Completa • {{ municipalities.length }} Cidades</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('geolocalizacao_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Rede de apoio socioassistencial (CRAS, CREAS, SINE, CAPS, Defensoria) e Escritórios Sociais distribuídos nos 78 municípios capixabas.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="isGpsModalOpen = true"
          >
            <span>📡 Localização Aproximada (GPS / LGPD)</span>
          </button>

          <button
            v-if="isStaff"
            type="button"
            class="px-4 py-2.5 bg-sky-500 hover:bg-sky-600 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="openCreateUnitModal"
          >
            <span>➕ Cadastrar Parceiro da Rede</span>
          </button>

          <div class="bg-white/10 backdrop-blur-md px-4 py-2 rounded-2xl border border-white/20 text-center">
            <span class="text-[10px] uppercase font-bold text-sky-200 block">Municípios</span>
            <strong class="text-xl font-extrabold text-white font-heading">{{ municipalities.length }}</strong>
          </div>
        </div>
      </div>

      <!-- GPS Nearest Result Banner -->
      <div v-if="gpsResult" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <strong class="text-sm font-extrabold text-emerald-950 block">
            {{ gpsResult.fora_do_estado
              ? `🌐 Atendimento Estadual Centralizado: ${gpsResult.municipio.nome}/ES`
              : `✅ Check-in Territorial Confirmado: ${gpsResult.municipio.nome}/ES (~${gpsResult.municipio.distancia_km} km)` }}
          </strong>
          <p class="text-xs text-emerald-800">
            {{ gpsResult.message }}
          </p>
        </div>
        <button type="button" @click="gpsResult = null" class="text-xs font-bold text-emerald-700 hover:underline self-start md:self-center">
          Dispensar
        </button>
      </div>

      <!-- Search & Micro-Region Filters -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="md:col-span-2 relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por nome do município, código IBGE ou região..."
              class="w-full pl-8 pr-4 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 outline-none"
            />
          </div>

          <div class="flex items-center">
            <label class="flex items-center gap-2 p-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-xs font-bold w-full cursor-pointer">
              <input
                v-model="filterOnlyPhysicalOffice"
                type="checkbox"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500"
              />
              <span>Apenas Polos com Escritório Físico</span>
            </label>
          </div>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto pt-2 border-t border-slate-100 text-xs">
          <span class="text-slate-400 font-bold text-[11px] flex-shrink-0">Macrorregião:</span>
          <button
            v-for="region in macroRegions"
            :key="region.id"
            type="button"
            class="px-3 py-1 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="selectedRegion === region.id ? 'bg-[#003366] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="selectedRegion = region.id"
          >
            {{ region.name }}
          </button>
        </div>
      </div>

      <!-- Main Grid: Municipalities List & Detail Inspector Card -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Municipalities Grid (2 cols) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
              Municípios do Espírito Santo
            </h2>
            <span class="text-xs font-semibold text-slate-500">{{ filteredMunicipalities.length }} de {{ municipalities.length }} municípios</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[600px] overflow-y-auto pr-1">
            <button
              v-for="muni in filteredMunicipalities"
              :key="muni.codigo_ibge"
              type="button"
              class="map-muni-btn p-3.5 rounded-xl border text-left transition flex flex-col justify-between gap-2 cursor-pointer"
              :class="selectedMuni && selectedMuni.codigo_ibge === muni.codigo_ibge ? 'bg-sky-50/90 border-sky-500 ring-2 ring-sky-500/20 shadow-xs active' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/80'"
              @click="selectMunicipality(muni)"
            >
              <div class="flex items-start justify-between gap-1">
                <strong class="text-xs font-extrabold text-slate-900 leading-snug">{{ muni.nome }}</strong>
                <span
                  class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase flex-shrink-0"
                  :class="muni.tem_escritorio_fisico ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'"
                >
                  {{ muni.tem_escritorio_fisico ? 'Físico' : 'Virtual' }}
                </span>
              </div>

              <div class="flex items-center justify-between text-[11px] text-slate-500">
                <span>{{ muni.microrregiao }}</span>
                <span class="font-bold text-slate-700">{{ muni.total_egressos }} atendidos</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Detail Inspector Panel (1 col) -->
        <div v-if="selectedMuni" class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Município Selecionado</span>
              <span class="text-xs font-mono font-bold text-slate-500">IBGE: {{ selectedMuni.codigo_ibge }}</span>
            </div>
            <h3 id="selectedMuniName" class="text-xl font-extrabold text-[#003366] font-heading leading-tight">
              {{ selectedMuni.nome }}
            </h3>
            <span id="selectedMuniType" class="text-xs font-semibold text-slate-600 block mt-1">
              {{ selectedMuni.tem_escritorio_fisico ? 'Polo Regional com Escritório Social Físico' : 'Atendimento Remoto Integrado (Polo Virtual)' }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2.5">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Egressos Acompanhados</span>
              <strong id="selectedMuniDemand" class="text-lg font-extrabold text-slate-900 block mt-0.5">
                {{ selectedMuni.total_egressos }}
              </strong>
            </div>
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">População Estimada</span>
              <strong class="text-lg font-extrabold text-slate-900 block mt-0.5">
                {{ (selectedMuni.populacao || 0).toLocaleString('pt-BR') }}
              </strong>
            </div>
          </div>

          <!-- Local Support Network -->
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">
                Rede de Apoio Socioassistencial
              </h4>
              <span class="text-[11px] font-semibold text-slate-500">{{ selectedMuniServices.length }} unidades</span>
            </div>

            <div v-if="selectedMuniServices.length === 0" class="p-4 rounded-xl bg-slate-50 border border-dashed border-slate-300 text-xs text-slate-500 text-center">
              Nenhuma unidade física cadastrada especificamente neste município. Atendimento via Escritório Social Virtual e polos regionais da macrorregião {{ selectedMuni.macrorregiao }}.
            </div>

            <div v-else class="space-y-2.5 text-xs max-h-[300px] overflow-y-auto pr-1">
              <div
                v-for="service in selectedMuniServices"
                :key="service.id || service.nome"
                class="p-3 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100/60 transition space-y-1.5"
              >
                <div class="flex items-center justify-between gap-2">
                  <strong class="text-slate-900 font-bold">{{ service.tipo }} — {{ service.nome }}</strong>
                  <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 flex-shrink-0">Ativo</span>
                </div>
                <p v-if="service.endereco" class="text-[11px] text-slate-600">{{ service.endereco }}</p>
                <div class="flex items-center justify-between gap-2 text-[10px] text-slate-400 font-medium pt-1">
                  <span>📞 {{ service.telefone || 'Não informado' }} • ⏱️ {{ service.horario || '08h às 17h' }}</span>
                  <button
                    v-if="isStaff && egressosList.length > 0"
                    type="button"
                    class="text-sky-600 hover:underline font-bold cursor-pointer"
                    @click="openForwardModal(service)"
                  >
                    Encaminhar →
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-2">
            <Link
              href="/atendimento"
              class="w-full py-2.5 px-4 bg-[#003366] hover:bg-[#002244] text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2"
            >
              <span>📹 Iniciar Atendimento no Polo {{ selectedMuni.nome }}</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- GPS LGPD Consent Modal -->
      <div v-if="isGpsModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
          <h3 class="text-base font-extrabold text-slate-900">Consentimento LGPD — Geolocalização Aproximada</h3>
          <p class="text-xs text-slate-600 leading-relaxed">
            Em conformidade com a LGPD (Lei 13.709/2018), sua localização aproximada é usada exclusivamente para identificar o polo e os serviços socioassistenciais (CRAS, CREAS, SINE) mais próximos no Espírito Santo, sem monitoramento contínuo.
          </p>

          <label class="flex items-start gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 cursor-pointer">
            <input v-model="gpsConsentChecked" type="checkbox" class="mt-0.5 w-4 h-4 rounded text-emerald-600" />
            <span>Concordo com a coleta pontual das minhas coordenadas aproximadas para atendimento territorial.</span>
          </label>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isGpsModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
            <button
              type="button"
              :disabled="!gpsConsentChecked || isSendingGps"
              @click="executeGpsCheckin"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition disabled:opacity-50"
            >
              {{ isSendingGps ? 'Localizando...' : 'Confirmar e Localizar Polo' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Create Partner Modal (Staff) -->
      <div v-if="isCreateUnitModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
          <h3 class="text-base font-extrabold text-slate-900 mb-3">Cadastrar Unidade / Parceiro da Rede</h3>
          <form @submit.prevent="handleCreateUnit" class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Nome da Unidade / Instituição *</label>
              <input v-model="unitForm.nome" type="text" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tipo *</label>
                <select v-model="unitForm.tipo" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                  <option value="CRAS">CRAS</option>
                  <option value="CREAS">CREAS</option>
                  <option value="SINE">SINE</option>
                  <option value="CAPS">CAPS</option>
                  <option value="CASA_CIDADAO">Casa do Cidadão</option>
                  <option value="DEFENSORIA">Defensoria Pública</option>
                  <option value="ESCRITORIO_SOCIAL">Escritório Social</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Município *</label>
                <select v-model="unitForm.municipio_id" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                  <option v-for="m in municipalities" :key="m.id" :value="m.id">{{ m.nome }}</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Endereço</label>
              <input v-model="unitForm.endereco" type="text" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Telefone</label>
                <input v-model="unitForm.telefone" type="text" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Horário</label>
                <input v-model="unitForm.horario_funcionamento" type="text" placeholder="08h às 17h" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg" />
              </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="isCreateUnitModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
              <button type="submit" :disabled="isSubmittingUnit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-lg disabled:opacity-50">
                {{ isSubmittingUnit ? 'Salvando...' : 'Salvar Unidade' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Forward Egresso Modal (Staff) -->
      <div v-if="isForwardModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
          <h3 class="text-base font-extrabold text-slate-900 mb-1">Encaminhar Egresso para Unidade</h3>
          <p class="text-xs text-slate-500 mb-4">{{ forwardUnit?.tipo }} — {{ forwardUnit?.nome }}</p>

          <form @submit.prevent="handleForwardEgresso" class="space-y-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Selecionar Egresso *</label>
              <select v-model="forwardForm.prontuario_id" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg">
                <option value="">-- Selecione um egresso --</option>
                <option v-for="eg in egressosList" :key="eg.prontuario_id" :value="eg.prontuario_id">
                  {{ eg.nome }} ({{ eg.numero_prontuario }})
                </option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Motivo / Observações do Encaminhamento *</label>
              <textarea v-model="forwardForm.descricao" rows="3" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="isForwardModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
              <button type="submit" :disabled="isForwarding" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg disabled:opacity-50">
                {{ isForwarding ? 'Registrando...' : 'Confirmar Encaminhamento' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  municipios: {
    type: Array,
    default: () => [],
  },
  redeApoio: {
    type: Array,
    default: () => [],
  },
  egressosList: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const { t } = useAccessibility();
const toast = useToast();

const isStaff = computed(() => ['gestor', 'tecnico', 'suporte'].includes(page.props.auth?.user?.role || ''));
const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const searchQuery = ref('');
const selectedRegion = ref('all');
const filterOnlyPhysicalOffice = ref(false);

const normalizeMacro = (val) => (val || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

const macroRegions = computed(() => {
  const counts = { metropolitana: 0, norte: 0, noroeste: 0, sul: 0, central: 0 };
  for (const m of props.municipios) {
    const key = normalizeMacro(m.macrorregiao);
    if (key.includes('metropolitana')) counts.metropolitana += 1;
    else if (key.includes('noroeste')) counts.noroeste += 1;
    else if (key.includes('norte')) counts.norte += 1;
    else if (key.includes('sul')) counts.sul += 1;
    else counts.central += 1;
  }
  return [
    { id: 'all', name: `Todos (${props.municipios.length})` },
    { id: 'metropolitana', name: `Metropolitana (${counts.metropolitana})` },
    { id: 'norte', name: `Norte (${counts.norte})` },
    { id: 'noroeste', name: `Noroeste (${counts.noroeste})` },
    { id: 'sul', name: `Sul (${counts.sul})` },
    { id: 'central', name: `Central (${counts.central})` },
  ];
});

const municipalities = ref([...props.municipios]);
const units = ref([...props.redeApoio]);

const selectedMuni = ref(
  municipalities.value.find(m => m.nome === 'Vitória') || municipalities.value[0] || null
);

const selectedMuniServices = computed(() => {
  if (!selectedMuni.value) return [];
  const local = units.value.filter(u => Number(u.municipio_id) === Number(selectedMuni.value.id));
  if (local.length > 0) return local;
  if (
    gpsResult.value
    && Number(gpsResult.value.municipio?.id) === Number(selectedMuni.value.id)
    && Array.isArray(gpsResult.value.unidades_proximas)
  ) {
    return gpsResult.value.unidades_proximas;
  }
  return [];
});

const filteredMunicipalities = computed(() => {
  return municipalities.value.filter((m) => {
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const match = (m.nome || '').toLowerCase().includes(q)
        || String(m.codigo_ibge || '').includes(q)
        || (m.microrregiao || '').toLowerCase().includes(q);
      if (!match) return false;
    }
    if (selectedRegion.value !== 'all') {
      const norm = normalizeMacro(m.macrorregiao);
      if (!norm.includes(selectedRegion.value)) return false;
    }
    if (filterOnlyPhysicalOffice.value && !m.tem_escritorio_fisico) {
      return false;
    }
    return true;
  });
});

const selectMunicipality = (muni) => {
  selectedMuni.value = muni;
};

// GPS Check-in Modal State
const isGpsModalOpen = ref(false);
const gpsConsentChecked = ref(false);
const isSendingGps = ref(false);
const gpsResult = ref(null);

const executeGpsCheckin = () => {
  if (!navigator.geolocation) {
    toast.error('Geolocalização Indisponível', 'Seu navegador não suporta captura de GPS.');
    return;
  }
  isSendingGps.value = true;
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      try {
        const res = await fetch('/api/territorios/checkin-gps', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
          },
          body: JSON.stringify({
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude,
            consentimento_lgpd: true,
          }),
        });
        const data = await res.json();
        if (res.ok) {
          gpsResult.value = data;
          isGpsModalOpen.value = false;
          const foundMuni = municipalities.value.find(m => Number(m.id) === Number(data.municipio?.id));
          if (foundMuni) selectedMuni.value = foundMuni;
          toast.success('Localização Territorial Registrada', data.message || 'Polo mais próximo identificado com sucesso.');
        } else {
          toast.error('Falha no Check-in GPS', data.error || data.message || 'Erro ao registrar localização.');
        }
      } catch (err) {
        toast.error('Erro de Rede', 'Falha de comunicação com a API territorial.');
      } finally {
        isSendingGps.value = false;
      }
    },
    (geoErr) => {
      isSendingGps.value = false;
      toast.error('Permissão GPS Negada', geoErr.message || 'Ative a permissão de localização no navegador.');
    },
    { enableHighAccuracy: false, timeout: 10000 }
  );
};

// Partner Creation State
const isCreateUnitModalOpen = ref(false);
const isSubmittingUnit = ref(false);
const unitForm = ref({
  nome: '',
  tipo: 'CRAS',
  municipio_id: null,
  endereco: '',
  telefone: '',
  horario_funcionamento: '08h às 17h',
});

const openCreateUnitModal = () => {
  unitForm.value = {
    nome: '',
    tipo: 'CRAS',
    municipio_id: selectedMuni.value?.id || municipalities.value[0]?.id || null,
    endereco: '',
    telefone: '',
    horario_funcionamento: '08h às 17h',
  };
  isCreateUnitModalOpen.value = true;
};

const handleCreateUnit = async () => {
  isSubmittingUnit.value = true;
  try {
    const res = await fetch('/api/rede-apoio', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify(unitForm.value),
    });
    const data = await res.json();
    if (res.ok && data.data) {
      units.value.push({
        id: data.data.id,
        municipio_id: data.data.municipio_id,
        tipo: data.data.tipo,
        nome: data.data.nome,
        endereco: data.data.endereco,
        telefone: data.data.telefone,
        horario: data.data.horario_funcionamento || '08h às 17h',
      });
      isCreateUnitModalOpen.value = false;
      toast.success('Parceiro Cadastrado', 'Unidade socioassistencial adicionada à rede estadual.');
    } else {
      toast.error('Erro ao Cadastrar', data.error || data.message || 'Verifique os campos.');
    }
  } catch (err) {
    toast.error('Erro de Rede', 'Falha ao cadastrar parceiro.');
  } finally {
    isSubmittingUnit.value = false;
  }
};

// Forward Egresso Modal
const isForwardModalOpen = ref(false);
const isForwarding = ref(false);
const forwardUnit = ref(null);
const forwardForm = ref({ prontuario_id: '', descricao: '' });

const openForwardModal = (unit) => {
  forwardUnit.value = unit;
  forwardForm.value = {
    prontuario_id: '',
    descricao: `Encaminhamento para ${unit.tipo} (${unit.nome}) no município de ${selectedMuni.value?.nome}/ES.`,
  };
  isForwardModalOpen.value = true;
};

const handleForwardEgresso = async () => {
  if (!forwardForm.value.prontuario_id) return;
  isForwarding.value = true;
  try {
    const res = await fetch(`/api/prontuarios/${forwardForm.value.prontuario_id}/timeline`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        tipo_evento: 'encaminhamento_rede',
        titulo: `Encaminhamento para ${forwardUnit.value.tipo} — ${forwardUnit.value.nome}`,
        descricao: forwardForm.value.descricao,
        metadata: {
          unidade_id: forwardUnit.value.id,
          unidade_tipo: forwardUnit.value.tipo,
          municipio: selectedMuni.value?.nome,
        },
      }),
    });
    if (res.ok) {
      isForwardModalOpen.value = false;
      toast.success('Encaminhamento Registrado', 'O encaminhamento foi gravado no Prontuário Único do Egresso.');
    } else {
      const err = await res.json().catch(() => ({}));
      toast.error('Erro ao Encaminhar', err.error || err.message || 'Não foi possível gravar o encaminhamento.');
    }
  } catch (err) {
    toast.error('Erro de Rede', 'Falha de comunicação com o servidor.');
  } finally {
    isForwarding.value = false;
  }
};
</script>
