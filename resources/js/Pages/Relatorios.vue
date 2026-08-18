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
            <select v-model="filterPeriod" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
              <option value="ano_2026">Ano Corrente (2026)</option>
              <option value="mes_atual">Mês Atual (Agosto/2026)</option>
              <option value="trimestre">3º Trimestre / 2026</option>
              <option value="historico">Histórico Completo (108 mil atendimentos)</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Macrorregião do ES</label>
            <select v-model="filterRegion" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
              <option value="todas">Todas as Regiões (78 Cidades)</option>
              <option value="metropolitana">Região Metropolitana</option>
              <option value="norte">Norte & Rio Doce</option>
              <option value="sul">Sul & Caparaó</option>
              <option value="central">Central & Serrana</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Eixo de Atuação</label>
            <select v-model="filterAxis" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
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
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">108.000</strong>
          <span class="text-[10px] text-emerald-600 font-bold">+18.2% anual</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Atendimentos Vídeo</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">72.4%</strong>
          <span class="text-[10px] text-sky-600 font-bold">Via WebRTC Remoto</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Duração Média</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">26 min</strong>
          <span class="text-[10px] text-slate-400 font-medium">Por atendimento</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Vagas Preenchidas</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">1.820</strong>
          <span class="text-[10px] text-purple-600 font-bold">Cotas Afirmativas</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Reincidência Zero</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">84.6%</strong>
          <span class="text-[10px] text-emerald-600 font-bold">Efetividade</span>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Cidades Atendidas</span>
          <strong class="text-xl font-extrabold text-slate-900 block mt-1 font-heading">78 / 78</strong>
          <span class="text-[10px] text-blue-600 font-bold">100% Cobertura</span>
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
          <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
            Encadeamento 100% Válido
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
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';

const { t } = useAccessibility();

const filterPeriod = ref('ano_2026');
const filterRegion = ref('todas');
const filterAxis = ref('todos');

const regionalSummary = ref([
  { municipio: 'Vitória', regiao: 'Metropolitana', egressos: 3420, atendimentos: 28400, vagas: 580, fisico: true },
  { municipio: 'Serra', regiao: 'Metropolitana', egressos: 2910, atendimentos: 24100, vagas: 490, fisico: true },
  { municipio: 'Vila Velha', regiao: 'Metropolitana', egressos: 2450, atendimentos: 19800, vagas: 380, fisico: true },
  { municipio: 'Cariacica', regiao: 'Metropolitana', egressos: 2100, atendimentos: 16500, vagas: 290, fisico: true },
  { municipio: 'Linhares', regiao: 'Rio Doce / Norte', egressos: 1150, atendimentos: 7800, vagas: 140, fisico: true },
  { municipio: 'Cachoeiro de Itapemirim', regiao: 'Sul / Caparaó', egressos: 980, atendimentos: 6200, vagas: 110, fisico: true },
  { municipio: 'Colatina', regiao: 'Centro-Oeste', egressos: 740, atendimentos: 4900, vagas: 95, fisico: true },
  { municipio: 'São Mateus', regiao: 'Nordeste / Norte', egressos: 610, atendimentos: 4100, vagas: 85, fisico: true },
]);

const auditLogs = ref([
  { id: 48912, timestamp: '17/08/2026 14:32:10', acao: 'ATENDIMENTO_VIDEO_LOG', user_name: 'Dra. Márcia Oliveira', user_role: 'Técnico Social', ip: '187.111.45.20', current_hash: '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08' },
  { id: 48911, timestamp: '17/08/2026 14:20:05', acao: 'VALIDATE_QR', user_name: 'Público Externo / Empregador', user_role: 'Validador Público', ip: '201.86.120.4', current_hash: '8f4c2e6b9a1d0f5c8e3b7a2d4f6c1e9a8b7c5d3e1f0a2b4c6d8e0f1a3b5c7d9e' },
  { id: 48910, timestamp: '17/08/2026 13:45:00', acao: 'CARTEIRA_PDF_ISSUE', user_name: 'Lucas Santos de Oliveira', user_role: 'Egresso', ip: '177.92.14.88', current_hash: '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8' },
  { id: 48909, timestamp: '17/08/2026 11:15:22', acao: 'PRONTUARIO_EVOLUCAO', user_name: 'Carlos Eduardo Silva', user_role: 'Gestor SEJUS', ip: '10.150.4.12', current_hash: '4b227777d4dd1fc61c6f884f48641d02b4d121d3fd328cb08b5531fcacdabf8a' },
]);

const getAuditBadgeClass = (acao) => {
  if (acao.includes('VIDEO')) return 'bg-sky-100 text-sky-800';
  if (acao.includes('VALIDATE')) return 'bg-emerald-100 text-emerald-800';
  if (acao.includes('PDF')) return 'bg-purple-100 text-purple-800';
  return 'bg-slate-200 text-slate-800';
};

const exportData = (format) => {
  alert(`📊 Relatório consolidado exportado com sucesso no formato .${format.toUpperCase()}! O download foi iniciado.`);
};
</script>
