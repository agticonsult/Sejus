<template>
  <AppLayout :breadcrumbs="[{ name: 'Dashboard' }]">
    <Head title="Visão Executiva & Monitoramento" />

    <div class="dashboard-view space-y-6" id="view-dashboard">
      <!-- Executive Page Header & Quick Actions -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6 transition-colors duration-200">
        <div class="space-y-1">
          <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-sky-50 dark:bg-sky-950/70 border border-sky-100 dark:border-sky-900/60 text-sky-700 dark:text-sky-300 text-xs font-semibold">
            <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
            <span>Sistema Estadual Conecta Egresso • 78 Municípios Ativos</span>
          </div>
          <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 font-heading">
            {{ t('dashboard_title') }}
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 max-w-2xl leading-relaxed">
            Acompanhamento em tempo real da reintegração social, atendimentos telepresenciais WebRTC, emissão de credenciais digitais e inserção no mercado de trabalho em todo o Espírito Santo.
          </p>
        </div>

        <!-- Action CTAs -->
        <div class="flex flex-wrap items-center gap-2.5 flex-shrink-0">
          <Link
            href="/atendimento"
            class="px-4 py-2 bg-sky-600 hover:bg-sky-700 dark:bg-sky-700 dark:hover:bg-sky-800 text-white font-semibold text-xs rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer"
          >
            <Video class="w-4 h-4 stroke-[1.75]" />
            <span>Iniciar Atendimento Remoto</span>
          </Link>
          <Link
            href="/oportunidades"
            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200/70 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 font-semibold text-xs rounded-xl border border-slate-200/80 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer"
          >
            <Briefcase class="w-4 h-4 text-slate-500 dark:text-slate-400 stroke-[1.75]" />
            <span>Vagas Afirmativas (42)</span>
          </Link>
          <Link
            href="/relatorios"
            class="px-3.5 py-2 bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 font-semibold text-xs rounded-xl border border-slate-200/80 dark:border-slate-700 transition flex items-center gap-1.5 cursor-pointer"
          >
            <BarChart3 class="w-4 h-4 text-slate-500 dark:text-slate-400 stroke-[1.75]" />
            <span>Relatórios SEGER</span>
          </Link>
        </div>
      </div>

      <!-- 4 Core Uniform KPI Cards (No Rainbow, Enterprise Cohesion) -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-5">
        <!-- KPI 1: Egressos Acompanhados -->
        <div class="kpi-card bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Egressos Acompanhados</span>
            <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-transparent dark:border-slate-700 flex items-center justify-center">
              <Users class="w-4 h-4 stroke-[1.75]" />
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-bold text-slate-900 dark:text-slate-100 font-mono tabular-nums mt-3">
            {{ kpis.total_egressos.toLocaleString('pt-BR') }}
          </div>
          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
              <ArrowUpRight class="w-3.5 h-3.5" /> +12.4%
            </span>
            <span class="text-slate-400 dark:text-slate-400 text-[11px]">vs. mês anterior</span>
          </div>
        </div>

        <!-- KPI 2: Atendimentos Realizados -->
        <div class="kpi-card bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Atendimentos Realizados</span>
            <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-transparent dark:border-slate-700 flex items-center justify-center">
              <Video class="w-4 h-4 stroke-[1.75]" />
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-bold text-slate-900 dark:text-slate-100 font-mono tabular-nums mt-3">
            108 mil
          </div>
          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-sky-600 dark:text-sky-400 font-semibold">{{ kpis.atendimentos_hoje }} hoje</span>
            <span class="text-slate-400 dark:text-slate-400 text-[11px]">108.000 acumulados</span>
          </div>
        </div>

        <!-- KPI 3: Vagas & Qualificação -->
        <div class="kpi-card bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Vagas & Qualificação</span>
            <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-transparent dark:border-slate-700 flex items-center justify-center">
              <Briefcase class="w-4 h-4 stroke-[1.75]" />
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-bold text-slate-900 dark:text-slate-100 font-mono tabular-nums mt-3">
            {{ kpis.vagas_preenchidas.toLocaleString('pt-BR') }}
          </div>
          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-slate-700 dark:text-slate-300 font-semibold">{{ kpis.vagas_abertas }} abertas</span>
            <span class="text-slate-400 dark:text-slate-400 text-[11px]">{{ kpis.cursos_ativos }} cursos ativos</span>
          </div>
        </div>

        <!-- KPI 4: Taxa de Reintegração -->
        <div class="kpi-card bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Taxa de Reintegração</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/70 border border-transparent dark:border-emerald-800/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <ShieldCheck class="w-4 h-4 stroke-[1.75]" />
            </div>
          </div>
          <div class="kpi-value text-2xl md:text-3xl font-bold text-emerald-600 dark:text-emerald-400 font-mono tabular-nums mt-3">
            {{ kpis.taxa_reincidencia_zero }}%
          </div>
          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-emerald-700 dark:text-emerald-300 font-semibold bg-emerald-50 dark:bg-emerald-950/70 border border-transparent dark:border-emerald-800/50 px-2 py-0.5 rounded-full text-[10px]">Reincidência Zero</span>
            <span class="text-slate-400 dark:text-slate-400 text-[11px]">programas ativos</span>
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Municipalities Demand Bar Chart (2 cols) -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs lg:col-span-2 transition-colors duration-200">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wide">
                Demanda por Município Polo (ES)
              </h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Atendimentos acumulados nos principais polos do Escritório Social (*Polos Virtuais no Interior)
              </p>
            </div>
            <span class="text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/70 px-2.5 py-1 rounded-full border border-sky-200 dark:border-sky-800/60">
              78 Cidades Integradas
            </span>
          </div>

          <ChartBar id="chartMunicipios" :height="240" />
        </div>

        <!-- Reintegration Donut Chart (1 col) -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors duration-200">
          <div class="mb-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wide">
              Efetividade da Reintegração
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Distribuição percentual por eixo de atendimento SEJUS
            </p>
          </div>

          <ChartDonut id="chartReintegracao" :height="240" />
        </div>
      </div>

      <!-- Bottom Grid: Recent Activity Stream & 78 Municipalities Status Summary -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activities Feed -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs transition-colors duration-200">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <Activity class="w-4 h-4 text-sky-600 dark:text-sky-400 stroke-[2]" />
              <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wide">
                Trilha de Atividades Recentes
              </h2>
            </div>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Tempo Real</span>
          </div>

          <div class="space-y-3">
            <div
              v-for="activity in recentActivities"
              :key="activity.id"
              class="activity-item flex items-start gap-3 p-3 rounded-xl bg-slate-50/70 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/60 hover:bg-slate-100/70 dark:hover:bg-slate-800/90 transition"
            >
              <div class="w-7 h-7 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0 mt-0.5">
                <Video v-if="activity.iconType === 'video' || activity.icon === '🩺'" class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 stroke-[2]" />
                <Briefcase v-else-if="activity.iconType === 'vaga' || activity.icon === '💼'" class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 stroke-[2]" />
                <CreditCard v-else-if="activity.iconType === 'carteira' || activity.icon === '💳'" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 stroke-[2]" />
                <FileText v-else class="w-3.5 h-3.5 text-slate-600 dark:text-slate-300 stroke-[2]" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 leading-snug">
                  {{ activity.descricao }}
                </p>
                <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                  <span class="font-medium text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/70 border border-sky-100 dark:border-sky-900/60 px-1.5 py-0.5 rounded">
                    {{ activity.municipio }}
                  </span>
                  <span>•</span>
                  <span>{{ activity.autor }}</span>
                  <span>•</span>
                  <span class="font-mono text-slate-500 dark:text-slate-400">{{ activity.tempo }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 78 ES Municipalities Quick Status -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-col justify-between transition-colors duration-200">
          <div>
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-2">
                <MapPin class="w-4 h-4 text-sky-600 dark:text-sky-400 stroke-[2]" />
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wide">
                  Expansão Estadual (78 Municípios)
                </h2>
              </div>
              <Link href="/geolocalizacao" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-800 dark:hover:text-sky-300 flex items-center gap-1 transition">
                <span>Ver Mapa</span>
                <span aria-hidden="true">→</span>
              </Link>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
              A política de atendimento ao egresso do Espírito Santo conta com cobertura 100% digital via telepresença WebRTC e polos físicos estratégicos nos maiores centros urbanos.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 text-xs">
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-400 block">Metropolitana</span>
                <strong class="text-slate-800 dark:text-slate-100 text-sm block mt-0.5">7 Cidades</strong>
                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold">100% Coberta</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-400 block">Norte & Noroeste</span>
                <strong class="text-slate-800 dark:text-slate-100 text-sm block mt-0.5">28 Cidades</strong>
                <span class="text-[11px] text-sky-600 dark:text-sky-400 font-semibold">Polos Virtuais</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-400 block">Sul & Caparaó</span>
                <strong class="text-slate-800 dark:text-slate-100 text-sm block mt-0.5">27 Cidades</strong>
                <span class="text-[11px] text-sky-600 dark:text-sky-400 font-semibold">Polos Virtuais</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-400 block">Central & Serrana</span>
                <strong class="text-slate-800 dark:text-slate-100 text-sm block mt-0.5">16 Cidades</strong>
                <span class="text-[11px] text-sky-600 dark:text-sky-400 font-semibold">Polos Virtuais</span>
              </div>
              <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 col-span-2 sm:col-span-2">
                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-400 block">Acesso Cidadão / Gov.br</span>
                <strong class="text-slate-800 dark:text-slate-100 text-sm block mt-0.5">Autenticação Única Oficial</strong>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Conforme Lei 14.063/2020</span>
              </div>
            </div>
          </div>

          <div class="mt-5 p-3.5 rounded-xl bg-sky-50/70 dark:bg-sky-950/60 border border-sky-100 dark:border-sky-900/60 text-xs text-sky-900 dark:text-sky-200 flex items-center justify-between">
            <span class="font-medium">Deseja exportar relatórios executivos em PDF/CSV?</span>
            <Link href="/relatorios" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 dark:bg-sky-700 dark:hover:bg-sky-800 text-white rounded-lg font-semibold text-xs transition shadow-2xs">
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
import {
  Users,
  Video,
  Briefcase,
  ShieldCheck,
  ArrowUpRight,
  BarChart3,
  Activity,
  FileText,
  CreditCard,
  MapPin,
} from 'lucide-vue-next';
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
      { id: 1, iconType: 'video', descricao: 'Atendimento psicossocial remoto concluído via WebRTC', municipio: 'São Mateus', autor: 'Dra. Márcia Oliveira', tempo: 'Há 5 min' },
      { id: 2, iconType: 'vaga', descricao: 'Candidatura aprovada em vaga afirmativa de Almoxarife', municipio: 'Linhares', autor: 'SENAI / Empresa Amiga', tempo: 'Há 22 min' },
      { id: 3, iconType: 'carteira', descricao: 'Carteira Digital emitida e homologada com selo HMAC-SHA256', municipio: 'Cariacica', autor: 'Sistema Central', tempo: 'Há 45 min' },
      { id: 4, iconType: 'prontuario', descricao: 'Nova evolução registrada no Prontuário Único', municipio: 'Vitória', autor: 'Téc. Roberto Costa', tempo: 'Há 1h' },
    ],
  },
});

const { t } = useAccessibility();
</script>
