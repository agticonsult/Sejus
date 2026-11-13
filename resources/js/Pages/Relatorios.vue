<template>
  <AppLayout :breadcrumbs="[{ name: 'Relatórios & Análise SEJUS' }]">
    <Head title="Relatórios Executivos & Auditoria SEJUS" />

    <div class="relatorios-view space-y-6" id="view-relatorios">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 text-sky-200 rounded-full text-xs font-semibold mb-2 border border-white/20">
            <span>📊 Inteligência de Dados & Governança Pública</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('relatorios_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Indicadores de efetividade da reintegração social, dados agregados dos 78 municípios e trilha de auditoria criptográfica LGPD.
          </p>
        </div>

        <div class="flex flex-wrap gap-2.5">
          <button
            type="button"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer"
            @click="exportData('csv')"
          >
            <span>📥 Exportar CSV</span>
          </button>
          <button
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-1.5 cursor-pointer"
            @click="exportData('pdf')"
          >
            <span>📄 Exportar Relatório PDF</span>
          </button>
        </div>
      </div>

      <!-- Filter Controls Bar -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Período de Análise</label>
            <select v-model="filterPeriod" @change="applyFilters" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
              <option value="ano_2026">Ano Corrente ({{ new Date().getFullYear() }})</option>
              <option value="mes_atual">Mês Atual</option>
              <option value="trimestre">Últimos 90 dias</option>
              <option value="historico">Histórico Completo</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Macrorregião do ES</label>
            <select v-model="filterRegion" @change="applyFilters" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
              <option value="todas">Todas as Regiões (78 Cidades)</option>
              <option value="metropolitana">Região Metropolitana</option>
              <option value="norte">Norte & Rio Doce</option>
              <option value="sul">Sul & Caparaó</option>
              <option value="central">Central & Serrana</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Eixo de Atuação</label>
            <select v-model="filterAxis" @change="applyFilters" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
              <option value="todos">Todos os Eixos</option>
              <option value="trabalho">Emprego & Renda</option>
              <option value="cursos">Cursos & Capacitação</option>
              <option value="psicossocial">Atendimento Psicossocial</option>
              <option value="documentos">Carteira Digital & Documentos</option>
            </select>
          </div>
        </div>
      </div>

      <!-- KPI Metrics Grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Atendimentos</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">{{ (kpis.total_atendimentos || 0).toLocaleString('pt-BR') }}</strong>
          <span class="text-[10px] text-emerald-600 font-bold">Dados em Tempo Real</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Atendimentos Vídeo</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">{{ kpis.taxa_video_pct ?? 0 }}%</strong>
          <span class="text-[10px] text-sky-600 font-bold">Via WebRTC Remoto</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Duração Média</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">{{ kpis.duracao_media_min ?? 0 }} min</strong>
          <span class="text-[10px] text-slate-400 font-medium">Por atendimento</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Vagas Preenchidas</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">{{ (kpis.vagas_preenchidas || 0).toLocaleString('pt-BR') }}</strong>
          <span class="text-[10px] text-purple-600 font-bold">Cotas Afirmativas</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Prontuários Ativos</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">{{ kpis.taxa_reincidencia_zero ?? 0 }}%</strong>
          <span class="text-[10px] text-emerald-600 font-bold">Efetividade</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Cidades Atendidas</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">{{ kpis.cidades_atendidas ?? 0 }} / 78</strong>
          <span class="text-[10px] text-blue-600 font-bold">Cobertura Estadual</span>
        </div>
      </div>

      <!-- Regional Summary Table -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
            Consolidação Regional por Município Polo
          </h2>
          <span class="text-xs text-slate-500 font-semibold">Fonte: SEJUS/ES • Banco de Dados Auditado</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 uppercase text-[10px] tracking-wider font-extrabold">
                <th class="py-3 px-3">Município Polo</th>
                <th class="py-3 px-3">Macrorregião</th>
                <th class="py-3 px-3 text-right">Egressos Cadastrados</th>
                <th class="py-3 px-3 text-right">Atendimentos Remotos</th>
                <th class="py-3 px-3 text-right">Vagas Preenchidas</th>
                <th class="py-3 px-3 text-center">Tipo de Polo</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <tr v-for="row in regionalSummary" :key="row.municipio" class="hover:bg-slate-50 transition">
                <td class="py-3 px-3 font-bold text-slate-900">{{ row.municipio }}</td>
                <td class="py-3 px-3 text-slate-600">{{ row.regiao }}</td>
                <td class="py-3 px-3 text-right font-mono text-slate-800">{{ row.egressos.toLocaleString('pt-BR') }}</td>
                <td class="py-3 px-3 text-right font-mono text-sky-700 font-bold">{{ row.atendimentos.toLocaleString('pt-BR') }}</td>
                <td class="py-3 px-3 text-right font-mono text-purple-700 font-bold">{{ row.vagas.toLocaleString('pt-BR') }}</td>
                <td class="py-3 px-3 text-center">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="row.fisico ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'">
                    {{ row.fisico ? 'Físico + Virtual' : '100% Virtual' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Immutable Audit Trail Inspector Table -->
      <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
              Trilha de Auditoria Criptográfica (LGPD Art. 37)
            </h2>
            <p class="text-xs text-slate-500">
              Registros encadeados com hash SHA-256 e integridade inviolável
            </p>
          </div>
          <span
            class="text-xs font-bold px-2.5 py-1 rounded-full border"
            :class="chainIntegrity.valid !== false
              ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
              : 'text-rose-700 bg-rose-50 border-rose-200'"
          >
            {{ chainIntegrity.valid !== false ? 'Encadeamento SHA-256 Válido' : 'Divergência Detectada na Cadeia' }}
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse font-mono">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 uppercase text-[10px] tracking-wider font-extrabold">
                <th class="py-2.5 px-3">ID / Data</th>
                <th class="py-2.5 px-3">Ação</th>
                <th class="py-2.5 px-3">Usuário / Perfil</th>
                <th class="py-2.5 px-3">IP / Origem</th>
                <th class="py-2.5 px-3">Hash SHA-256 do Registro</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-[11px]">
              <tr v-if="auditLogs.length === 0">
                <td colspan="5" class="py-6 text-center text-xs font-sans text-slate-400">
                  Nenhum registro de auditoria encontrado para o período selecionado.
                </td>
              </tr>
              <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-slate-50 transition">
                <td class="py-2.5 px-3 font-sans">
                  <span class="font-bold text-slate-800">#{{ log.id }}</span>
                  <span class="text-slate-400 block text-[10px]">{{ log.timestamp }}</span>
                </td>
                <td class="py-2.5 px-3">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold font-sans" :class="getAuditBadgeClass(log.acao)">
                    {{ log.acao }}
                  </span>
                </td>
                <td class="py-2.5 px-3 font-sans text-slate-700">
                  <strong>{{ log.user_name }}</strong>
                  <span class="text-slate-400 block text-[10px]">{{ log.user_role }}</span>
                </td>
                <td class="py-2.5 px-3 text-slate-600">{{ log.ip }}</td>
                <td class="py-2.5 px-3 text-slate-500 font-mono text-[10px]">
                  <span class="text-slate-800 font-bold">{{ log.current_hash.slice(0, 16) }}...</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  auditLogs: {
    type: Array,
    default: () => [],
  },
  regionalSummary: {
    type: Array,
    default: () => [],
  },
  kpis: {
    type: Object,
    default: () => ({
      total_atendimentos: 0,
      taxa_video_pct: 0,
      duracao_media_min: 0,
      vagas_preenchidas: 0,
      taxa_reincidencia_zero: 0,
      cidades_atendidas: 0,
    }),
  },
  chainIntegrity: {
    type: Object,
    default: () => ({ valid: true }),
  },
  filters: {
    type: Object,
    default: () => ({
      period: 'ano_2026',
      region: 'todas',
      axis: 'todos',
    }),
  },
});

const { t } = useAccessibility();
const toast = useToast();

const filterPeriod = ref(props.filters?.period || 'ano_2026');
const filterRegion = ref(props.filters?.region || 'todas');
const filterAxis = ref(props.filters?.axis || 'todos');

const regionalSummary = computed(() => props.regionalSummary && props.regionalSummary.length ? props.regionalSummary : []);
const auditLogs = computed(() => props.auditLogs && props.auditLogs.length ? props.auditLogs : []);

const applyFilters = () => {
  router.get('/relatorios', {
    period: filterPeriod.value,
    region: filterRegion.value,
    axis: filterAxis.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const getAuditBadgeClass = (acao) => {
  if (acao.includes('VIDEO')) return 'bg-sky-100 text-sky-800';
  if (acao.includes('VALIDATE')) return 'bg-emerald-100 text-emerald-800';
  if (acao.includes('PDF')) return 'bg-purple-100 text-purple-800';
  return 'bg-slate-200 text-slate-800';
};

const escapeCsv = (val) => {
  const s = String(val ?? '').replace(/"/g, '""');
  return `"${s}"`;
};

const exportData = (format) => {
  if (format === 'pdf') {
    if (typeof window !== 'undefined') {
      toast.info('Preparando Impressão / PDF', 'Selecione "Salvar como PDF" na janela de impressão do navegador.');
      setTimeout(() => window.print(), 250);
    }
    return;
  }

  const lines = [];
  lines.push(['Relatório SEJUS/ES Conecta Egresso', `Período: ${filterPeriod.value}`, `Região: ${filterRegion.value}`, `Eixo: ${filterAxis.value}`].map(escapeCsv).join(';'));
  lines.push('');
  lines.push(['Município Polo', 'Macrorregião', 'Egressos Cadastrados', 'Atendimentos Registrados', 'Vagas Preenchidas', 'Tipo de Polo'].map(escapeCsv).join(';'));
  regionalSummary.value.forEach((row) => {
    lines.push([
      row.municipio,
      row.regiao,
      row.egressos,
      row.atendimentos,
      row.vagas,
      row.fisico ? 'Físico + Virtual' : '100% Virtual',
    ].map(escapeCsv).join(';'));
  });
  lines.push('');
  lines.push(['ID Auditoria', 'Data/Hora', 'Ação', 'Usuário', 'Perfil', 'IP', 'Hash SHA-256'].map(escapeCsv).join(';'));
  auditLogs.value.forEach((log) => {
    lines.push([
      log.id,
      log.timestamp,
      log.acao,
      log.user_name,
      log.user_role,
      log.ip,
      log.current_hash,
    ].map(escapeCsv).join(';'));
  });

  const csvContent = '\uFEFF' + lines.join('\r\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `relatorio-sejus-${filterPeriod.value}-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);

  toast.success('CSV Exportado', 'Planilha consolidada baixada com codificação UTF-8.');
};
</script>
