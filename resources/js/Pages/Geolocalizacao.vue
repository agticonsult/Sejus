<template>
  <AppLayout :breadcrumbs="[{ name: 'Mapeamento Territorial dos 78 Municípios' }]">
    <Head title="Mapeamento Territorial dos 78 Municípios (ES)" />

    <div class="geolocalizacao-view space-y-6" id="view-geolocalizacao">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] via-slate-900 to-[#0284c7] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 text-sky-200 rounded-full text-xs font-semibold mb-2 border border-white/20">
            <span>📍 Cobertura Estadual Completa • 78 Cidades</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('geolocalizacao_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Rede de apoio socioassistencial (CRAS, CREAS, SINE, Defensoria) e polos integrados de atendimento ao egresso em todo o território capixaba.
          </p>
        </div>

        <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
          <span class="text-[10px] uppercase font-bold text-sky-200 block">Total de Municípios</span>
          <strong class="text-3xl font-extrabold text-white font-heading">78</strong>
        </div>
      </div>

      <!-- Search & Micro-Region Filters -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <!-- Search Bar -->
          <div class="md:col-span-2 relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por nome do município, código IBGE ou região..."
              class="w-full pl-8 pr-4 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 outline-none"
            />
          </div>

          <!-- Physical Office Toggle -->
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

        <!-- Region Filter Pills -->
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
            <span class="text-xs font-semibold text-slate-500">{{ filteredMunicipalities.length }} de 78 municípios</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[580px] overflow-y-auto pr-1">
            <button
              v-for="muni in filteredMunicipalities"
              :key="muni.codigo_ibge"
              type="button"
              class="map-muni-btn p-3.5 rounded-xl border text-left transition flex flex-col justify-between gap-2 cursor-pointer"
              :class="selectedMuni.codigo_ibge === muni.codigo_ibge ? 'bg-sky-50/90 border-sky-500 ring-2 ring-sky-500/20 shadow-xs active' : 'bg-slate-50 border-slate-200 hover:bg-slate-100/80'"
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
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-5">
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

          <!-- Stats Metric Boxes -->
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
                {{ selectedMuni.populacao.toLocaleString('pt-BR') }}
              </strong>
            </div>
          </div>

          <!-- Local Support Network (Rede de Apoio Local) -->
          <div class="space-y-3">
            <h4 class="text-xs font-extrabold uppercase tracking-wide text-slate-700">
              Rede de Apoio Socioassistencial
            </h4>

            <div class="space-y-2.5 text-xs">
              <div
                v-for="service in selectedMuniServices"
                :key="service.nome"
                class="p-3 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100/60 transition space-y-1"
              >
                <div class="flex items-center justify-between">
                  <strong class="text-slate-900 font-bold">{{ service.tipo }} — {{ service.nome }}</strong>
                  <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Ativo</span>
                </div>
                <p class="text-[11px] text-slate-600">{{ service.endereco }}</p>
                <div class="flex items-center gap-2 text-[10px] text-slate-400 font-medium pt-1">
                  <span>📞 {{ service.telefone }}</span>
                  <span>•</span>
                  <span>⏱️ {{ service.horario }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Action -->
          <div class="pt-2">
            <Link
              href="/atendimento"
              class="w-full py-2.5 px-4 bg-[#003366] hover:bg-[#002244] text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2"
            >
              <span>📹 Iniciar Atendimento com Polo {{ selectedMuni.nome }}</span>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';

const { t } = useAccessibility();

const searchQuery = ref('');
const selectedRegion = ref('all');
const filterOnlyPhysicalOffice = ref(false);

const macroRegions = [
  { id: 'all', name: 'Todos (78)' },
  { id: 'metropolitana', name: 'Metropolitana (7)' },
  { id: 'norte', name: 'Rio Doce & Norte (14)' },
  { id: 'noroeste', name: 'Noroeste (15)' },
  { id: 'sul', name: 'Sul & Caparaó (26)' },
  { id: 'central', name: 'Central & Serrana (16)' },
];

const municipalities = ref([
  { codigo_ibge: '3205309', nome: 'Vitória', microrregiao: 'Metropolitana', macrorregiao: 'metropolitana', tem_escritorio_fisico: true, total_egressos: 3420, populacao: 365855 },
  { codigo_ibge: '3205002', nome: 'Serra', microrregiao: 'Metropolitana', macrorregiao: 'metropolitana', tem_escritorio_fisico: true, total_egressos: 2910, populacao: 520653 },
  { codigo_ibge: '3205200', nome: 'Vila Velha', microrregiao: 'Metropolitana', macrorregiao: 'metropolitana', tem_escritorio_fisico: true, total_egressos: 2450, populacao: 501325 },
  { codigo_ibge: '3201308', nome: 'Cariacica', microrregiao: 'Metropolitana', macrorregiao: 'metropolitana', tem_escritorio_fisico: true, total_egressos: 2100, populacao: 383917 },
  { codigo_ibge: '3203205', nome: 'Linhares', microrregiao: 'Rio Doce', macrorregiao: 'norte', tem_escritorio_fisico: true, total_egressos: 1150, populacao: 176688 },
  { codigo_ibge: '3201209', nome: 'Cachoeiro de Itapemirim', microrregiao: 'Central Sul', macrorregiao: 'sul', tem_escritorio_fisico: true, total_egressos: 980, populacao: 210589 },
  { codigo_ibge: '3201506', nome: 'Colatina', microrregiao: 'Centro-Oeste', macrorregiao: 'norte', tem_escritorio_fisico: true, total_egressos: 740, populacao: 123400 },
  { codigo_ibge: '3204906', nome: 'São Mateus', microrregiao: 'Nordeste', macrorregiao: 'norte', tem_escritorio_fisico: true, total_egressos: 610, populacao: 132642 },
  { codigo_ibge: '3202405', nome: 'Guarapari', microrregiao: 'Metropolitana', macrorregiao: 'metropolitana', tem_escritorio_fisico: false, total_egressos: 490, populacao: 126783 },
  { codigo_ibge: '3200607', nome: 'Aracruz', microrregiao: 'Rio Doce', macrorregiao: 'norte', tem_escritorio_fisico: false, total_egressos: 410, populacao: 103101 },
  { codigo_ibge: '3205101', nome: 'Viana', microrregiao: 'Metropolitana', macrorregiao: 'metropolitana', tem_escritorio_fisico: true, total_egressos: 520, populacao: 79500 },
  { codigo_ibge: '3203908', nome: 'Nova Venécia', microrregiao: 'Noroeste', macrorregiao: 'noroeste', tem_escritorio_fisico: false, total_egressos: 260, populacao: 50400 },
  { codigo_ibge: '3200805', nome: 'Barra de São Francisco', microrregiao: 'Noroeste', macrorregiao: 'noroeste', tem_escritorio_fisico: false, total_egressos: 215, populacao: 45000 },
  { codigo_ibge: '3203320', nome: 'Marataízes', microrregiao: 'Litoral Sul', macrorregiao: 'sul', tem_escritorio_fisico: false, total_egressos: 190, populacao: 38800 },
  { codigo_ibge: '3201407', nome: 'Castelo', microrregiao: 'Central Sul', macrorregiao: 'sul', tem_escritorio_fisico: false, total_egressos: 160, populacao: 37700 },
  { codigo_ibge: '3201902', nome: 'Domingos Martins', microrregiao: 'Sudoeste Serrana', macrorregiao: 'central', tem_escritorio_fisico: false, total_egressos: 140, populacao: 34000 },
  { codigo_ibge: '3204559', nome: 'Santa Maria de Jetibá', microrregiao: 'Central Serrana', macrorregiao: 'central', tem_escritorio_fisico: false, total_egressos: 130, populacao: 41000 },
  { codigo_ibge: '3200102', nome: 'Afonso Cláudio', microrregiao: 'Central Serrana', macrorregiao: 'central', tem_escritorio_fisico: false, total_egressos: 110, populacao: 31000 },
]);

const selectedMuni = ref(municipalities.value[0]);

const selectedMuniServices = computed(() => {
  return [
    { tipo: 'CRAS', nome: `Centro de Referência ${selectedMuni.value.nome}`, endereco: `Av. Central, nº 250, Centro, ${selectedMuni.value.nome}/ES`, telefone: '(27) 3382-6000', horario: '08h às 17h' },
    { tipo: 'CREAS', nome: `Unidade Especializada ${selectedMuni.value.nome}`, endereco: `Rua das Flores, 88, Bairro Social, ${selectedMuni.value.nome}/ES`, telefone: '(27) 3382-6150', horario: '08h às 17h' },
    { tipo: 'SINE', nome: `Agência do Trabalhador ${selectedMuni.value.nome}`, endereco: `Praça Principal, 10, ${selectedMuni.value.nome}/ES`, telefone: '(27) 3382-6200', horario: '08h às 16h' },
    { tipo: 'DEFENSORIA', nome: 'Núcleo de Execução Penal', endereco: `Fórum Municipal, ${selectedMuni.value.nome}/ES`, telefone: '(27) 3382-6300', horario: '12h às 18h' },
  ];
});

const filteredMunicipalities = computed(() => {
  return municipalities.value.filter((m) => {
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase();
      const match = m.nome.toLowerCase().includes(q) || m.codigo_ibge.includes(q) || m.microrregiao.toLowerCase().includes(q);
      if (!match) return false;
    }
    if (selectedRegion.value !== 'all' && m.macrorregiao !== selectedRegion.value) {
      return false;
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
</script>
