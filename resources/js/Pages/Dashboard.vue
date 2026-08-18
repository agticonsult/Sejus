<template>
  <AppLayout :breadcrumbs="[{ name: 'Dashboard' }]">
    <Head title="Dashboard & Monitoramento" />

    <div class="dashboard-view space-y-6" id="view-dashboard">
      <!-- Hero Banner with Institutional Greeting -->
      <div class="hero-banner bg-gradient-to-r from-[#003366] to-[#0284c7] rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="relative z-10 max-w-3xl">
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 backdrop-blur-md rounded-full text-xs font-semibold mb-3 border border-white/20">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            <span>Sistema Estadual Conecta Egresso • Operação Ativa nos 78 Municípios</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight font-heading">
            {{ t('dashboard_title') }}
          </h1>
          <p class="mt-2 text-slate-100 text-xs md:text-sm leading-relaxed max-w-2xl">
            Acompanhamento em tempo real da reintegração social, atendimentos psicossociais remotos, emissão de credenciais digitais e inserção no mercado de trabalho em todo o Espírito Santo.
          </p>

          <div class="mt-5 flex flex-wrap gap-3">
            <Link
              href="/atendimento"
              class="px-4 py-2.5 bg-white text-[#003366] font-bold text-xs rounded-xl shadow-md hover:bg-slate-100 transition flex items-center gap-2 cursor-pointer"
            >
              <span>📹 Iniciar Atendimento Remoto</span>
            </Link>
            <Link
              href="/oportunidades"
              class="px-4 py-2.5 bg-white/20 hover:bg-white/30 text-white font-bold text-xs rounded-xl backdrop-blur-md border border-white/30 transition flex items-center gap-2 cursor-pointer"
            >
              <span>💼 Ver Vagas Afirmativas (42)</span>
            </Link>
            <Link
              href="/relatorios"
              class="px-4 py-2.5 bg-black/20 hover:bg-black/30 text-white font-bold text-xs rounded-xl backdrop-blur-md border border-white/20 transition flex items-center gap-2 cursor-pointer"
            >
              <span>📊 Relatórios Executivos</span>
            </Link>
          </div>
        </div>
      </div>

      <!-- 4 Core KPI Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
        <!-- KPI 1 -->
        <div class="kpi-card bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Egressos Acompanhados</span>
            <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-lg font-bold">
              👥
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-extrabold text-slate-900 font-heading">
            {{ kpis.total_egressos.toLocaleString('pt-BR') }}
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-xs text-emerald-600 font-bold">
            <span>↑ +12.4%</span>
            <span class="text-slate-400 font-normal">em relação ao mês anterior</span>
          </div>
        </div>

        <!-- KPI 2 -->
        <div class="kpi-card bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Atendimentos Realizados</span>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
              🩺
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-extrabold text-slate-900 font-heading">
            108 mil
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-xs text-sky-600 font-bold">
            <span>{{ kpis.atendimentos_hoje }} hoje</span>
            <span class="text-slate-400 font-normal">• 108.000 acumulados</span>
          </div>
        </div>

        <!-- KPI 3 -->
        <div class="kpi-card bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Vagas & Qualificação</span>
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg font-bold">
              💼
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-extrabold text-slate-900 font-heading">
            {{ kpis.vagas_preenchidas.toLocaleString('pt-BR') }}
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-xs text-purple-600 font-bold">
            <span>{{ kpis.vagas_abertas }} abertas</span>
            <span class="text-slate-400 font-normal">• {{ kpis.cursos_ativos }} cursos ativos</span>
          </div>
        </div>

        <!-- KPI 4 -->
        <div class="kpi-card bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-md transition">
          <div class="flex items-center justify-between mb-3">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Taxa de Reintegração</span>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg font-bold">
              🛡️
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-extrabold text-slate-900 font-heading">
            {{ kpis.taxa_reincidencia_zero }}%
          </div>
          <div class="mt-2 flex items-center gap-1.5 text-xs text-emerald-600 font-bold">
            <span>Reincidência Zero</span>
            <span class="text-slate-400 font-normal">nos programas ativos</span>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Municipalities Demand Bar Chart (2 cols) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs lg:col-span-2">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                Demanda por Município Polo (ES)
              </h2>
              <p class="text-xs text-slate-500">
                Atendimentos acumulados nos principais polos do Escritório Social (*Polos Virtuais no Interior)
              </p>
            </div>
            <span class="text-xs font-bold text-sky-600 bg-sky-50 px-2.5 py-1 rounded-full border border-sky-200">
              78 Cidades Integradas
            </span>
          </div>

          <ChartBar id="chartMunicipios" :height="240" />
        </div>

        <!-- Reintegration Donut Chart (1 col) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
          <div class="mb-4">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
              Efetividade da Reintegração
            </h2>
            <p class="text-xs text-slate-500">
              Distribuição percentual por eixo de atendimento SEJUS
            </p>
          </div>

          <ChartDonut id="chartReintegracao" :height="240" />
        </div>
      </div>

      <!-- Bottom Grid: Recent Activity Stream & 78 Municipalities Status Summary -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activities Feed -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
              Trilha de Atividades Recentes
            </h2>
            <span class="text-xs text-slate-400">Tempo Real</span>
          </div>

          <div class="space-y-3.5">
            <div
              v-for="activity in recentActivities"
              :key="activity.id"
              class="activity-item flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-100 hover:bg-slate-100/80 transition"
            >
              <span class="text-lg flex-shrink-0">{{ activity.icon }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-slate-800 leading-snug">
                  {{ activity.descricao }}
                </p>
                <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500">
                  <span class="font-semibold text-sky-700">{{ activity.municipio }}</span>
                  <span>•</span>
                  <span>{{ activity.autor }}</span>
                  <span>•</span>
                  <span>{{ activity.tempo }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 78 ES Municipalities Quick Status -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                Expansão Estadual (78 Municípios)
              </h2>
              <Link href="/geolocalizacao" class="text-xs font-bold text-sky-600 hover:underline">
                Ver Mapa Completo →
              </Link>
            </div>
            <p class="text-xs text-slate-600 mb-4">
              A política de atendimento ao egresso do Espírito Santo conta com cobertura 100% digital via videochamada e polos físicos estratégicos nos maiores centros urbanos.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-xs">
              <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Região Metropolitana</span>
                <strong class="text-slate-800 text-sm block mt-0.5">7 Cidades</strong>
                <span class="text-[11px] text-emerald-600 font-semibold">100% Coberta</span>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Região Norte & Noroeste</span>
                <strong class="text-slate-800 text-sm block mt-0.5">28 Cidades</strong>
                <span class="text-[11px] text-sky-600 font-semibold">Polos Virtuais</span>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Região Sul & Caparaó</span>
                <strong class="text-slate-800 text-sm block mt-0.5">27 Cidades</strong>
                <span class="text-[11px] text-sky-600 font-semibold">Polos Virtuais</span>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Região Central & Serrana</span>
                <strong class="text-slate-800 text-sm block mt-0.5">16 Cidades</strong>
                <span class="text-[11px] text-sky-600 font-semibold">Polos Virtuais</span>
              </div>
              <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/60 col-span-2 sm:col-span-2">
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Integração Gov.br / Acesso Cidadão</span>
                <strong class="text-slate-800 text-sm block mt-0.5">Autenticação Única Oficial</strong>
                <span class="text-[11px] text-purple-600 font-semibold">Conforme Lei 14.063/2020</span>
              </div>
            </div>
          </div>

          <div class="mt-5 p-3 rounded-xl bg-sky-50 border border-sky-200 text-xs text-sky-900 flex items-center justify-between">
            <span>Deseja exportar os relatórios consolidados em PDF/CSV?</span>
            <Link href="/relatorios" class="px-3 py-1.5 bg-sky-600 text-white rounded-lg font-bold hover:bg-sky-700 transition">
              Acessar BI
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import ChartBar from '../Components/ChartBar.vue';
import ChartDonut from '../Components/ChartDonut.vue';
import { useAccessibility } from '../Composables/useAccessibility';

const props = defineProps({
  kpis: {
    type: Object,
    default: () => ({
      total_egressos: 14850,
      atendimentos_hoje: 142,
      atendimentos_total: 108000,
      vagas_preenchidas: 1820,
      taxa_reincidencia_zero: 84.6,
      vagas_abertas: 42,
      cursos_ativos: 18,
    }),
  },
  recentActivities: {
    type: Array,
    default: () => [
      { id: 1, icon: '🩺', descricao: 'Atendimento psicossocial remoto concluído via WebRTC', municipio: 'São Mateus', autor: 'Dra. Márcia Oliveira', tempo: 'Há 5 min' },
      { id: 2, icon: '💼', descricao: 'Candidatura aprovada em vaga afirmativa de Almoxarife', municipio: 'Linhares', autor: 'SENAI / Empresa Amiga', tempo: 'Há 22 min' },
      { id: 3, icon: '💳', descricao: 'Carteira Digital emitida e homologada com selo HMAC-SHA256', municipio: 'Cariacica', autor: 'Sistema Central', tempo: 'Há 45 min' },
      { id: 4, icon: '📁', descricao: 'Nova evolução registrada no Prontuário Único', municipio: 'Vitória', autor: 'Téc. Roberto Costa', tempo: 'Há 1h' },
    ],
  },
});

const { t } = useAccessibility();
</script>
