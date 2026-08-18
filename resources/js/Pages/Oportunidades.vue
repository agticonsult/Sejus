<template>
  <AppLayout :breadcrumbs="[{ name: 'Oportunidades & Trabalho' }]">
    <Head title="Oportunidades & Trabalho" />

    <div class="oportunidades-view space-y-6" id="view-oportunidades">
      <!-- Header Banner -->
      <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-[#003366] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 text-purple-200 rounded-full text-xs font-semibold mb-2 border border-white/20">
            <span>✨ Programa Estadual de Inserção Produtiva e Cidadania</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('oportunidades_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-200 mt-1 max-w-xl">
            Vagas de emprego com cotas afirmativas SEJUS, cursos gratuitos do SENAI/IFES e bolsas de capacitação nos 78 municípios capixabas.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <div class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-xl border border-white/20 text-center">
            <span class="text-[10px] uppercase font-bold tracking-wider text-purple-200 block">Vagas Abertas</span>
            <strong class="text-2xl font-extrabold text-white font-heading">42</strong>
          </div>
          <div class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-xl border border-white/20 text-center">
            <span class="text-[10px] uppercase font-bold tracking-wider text-purple-200 block">Cursos Gratuitos</span>
            <strong class="text-2xl font-extrabold text-white font-heading">18</strong>
          </div>
        </div>
      </div>

      <!-- Filter Controls Bar -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <!-- Search Input -->
          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Buscar por Cargo ou Empresa</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
              <input
                v-model="filters.search"
                type="text"
                placeholder="Ex: Almoxarife, Soldador, Logística..."
                class="w-full pl-8 pr-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
              />
            </div>
          </div>

          <!-- Municipality Filter -->
          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Município do ES (78 Cidades)</label>
            <select
              v-model="filters.municipio"
              class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
            >
              <option value="">Todos os 78 Municípios</option>
              <option v-for="muni in municipiosList" :key="muni" :value="muni">{{ muni }}</option>
            </select>
          </div>

          <!-- Modality Filter -->
          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Modalidade</label>
            <select
              v-model="filters.modalidade"
              class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
            >
              <option value="">Todas as Modalidades</option>
              <option value="Presencial">Presencial</option>
              <option value="Híbrido">Híbrido</option>
              <option value="EAD">100% EAD / Remoto</option>
            </select>
          </div>

          <!-- Affirmative Filter Toggle -->
          <div class="flex items-end">
            <label class="flex items-center gap-2 p-2 rounded-lg bg-purple-50 border border-purple-200 text-purple-900 text-xs font-bold w-full cursor-pointer">
              <input
                v-model="filters.somenteAfirmativas"
                type="checkbox"
                class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500"
              />
              <span>Apenas Vagas Afirmativas SEJUS</span>
            </label>
          </div>
        </div>

        <!-- Category Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pt-2 border-t border-slate-100 text-xs">
          <span class="text-slate-400 font-bold text-[11px] flex-shrink-0">Eixo:</span>
          <button
            type="button"
            class="px-3 py-1 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'todos' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = 'todos'"
          >
            Todos ({{ filteredVagas.length + filteredCursos.length }})
          </button>
          <button
            type="button"
            class="px-3 py-1 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'vagas' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = 'vagas'"
          >
            💼 Vagas de Emprego ({{ filteredVagas.length }})
          </button>
          <button
            type="button"
            class="px-3 py-1 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'cursos' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = 'cursos'"
          >
            🎓 Cursos de Capacitação SENAI/IFES ({{ filteredCursos.length }})
          </button>
        </div>
      </div>

      <!-- Vagas de Emprego Grid -->
      <div v-if="activeTab === 'todos' || activeTab === 'vagas'" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>💼 Vagas de Trabalho Disponíveis</span>
            <span class="text-xs font-normal text-slate-500">({{ filteredVagas.length }} encontradas)</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="vaga in filteredVagas"
            :key="vaga.id"
            class="vaga-card bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition flex flex-col justify-between"
          >
            <div>
              <div class="flex items-start justify-between gap-2 mb-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ vaga.empresa }}</span>
                <span
                  v-if="vaga.afirmativa"
                  class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200"
                >
                  Cota SEJUS
                </span>
              </div>

              <h3 class="text-sm font-extrabold text-slate-900 leading-snug">
                {{ vaga.titulo }}
              </h3>

              <div class="flex items-center gap-2 my-2 text-xs font-semibold text-slate-600">
                <span>📍 {{ vaga.municipio }}</span>
                <span>•</span>
                <span class="text-emerald-700 font-bold">R$ {{ vaga.salario.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}</span>
                <span>•</span>
                <span>{{ vaga.regime }}</span>
              </div>

              <p class="text-xs text-slate-600 line-clamp-2 mb-3">
                {{ vaga.descricao }}
              </p>

              <div class="flex flex-wrap gap-1 mb-4">
                <span v-for="b in vaga.beneficios" :key="b" class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-medium">
                  {{ b }}
                </span>
              </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[11px] text-slate-400 font-medium">
                {{ vaga.vagas_restantes }} vagas restantes
              </span>
              <button
                type="button"
                class="px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer"
                @click="openApplicationModal(vaga)"
              >
                <span>Candidatar-se</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Cursos de Capacitação Grid -->
      <div v-if="activeTab === 'todos' || activeTab === 'cursos'" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>🎓 Cursos Gratuitos de Qualificação (SENAI / IFES / SEJUS)</span>
            <span class="text-xs font-normal text-slate-500">({{ filteredCursos.length }} encontrados)</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="curso in filteredCursos"
            :key="curso.id"
            class="curso-card bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition flex flex-col justify-between"
          >
            <div>
              <div class="flex items-start justify-between gap-2 mb-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ curso.instituicao }}</span>
                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                  100% Gratuito
                </span>
              </div>

              <h3 class="text-sm font-extrabold text-slate-900 leading-snug">
                {{ curso.titulo }}
              </h3>

              <div class="flex items-center gap-2 my-2 text-xs font-semibold text-slate-600">
                <span>📍 {{ curso.municipio || 'Estadual / EAD' }}</span>
                <span>•</span>
                <span>⏱️ {{ curso.carga_horaria }}h</span>
                <span>•</span>
                <span class="text-purple-700 font-bold">{{ curso.modalidade }}</span>
              </div>

              <p class="text-xs text-slate-600 line-clamp-2 mb-3">
                {{ curso.descricao }}
              </p>

              <div v-if="curso.bolsa_auxilio" class="p-2 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-bold mb-4 flex items-center gap-1.5">
                <span>💰 Bolsa-Auxílio: R$ {{ curso.bolsa_auxilio.toFixed(2) }}/mês</span>
              </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[11px] text-slate-400 font-medium">
                {{ curso.vagas_disponiveis }} vagas abertas
              </span>
              <button
                type="button"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer"
                @click="openCourseModal(curso)"
              >
                <span>Inscrever-se</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Application Confirmation Modal -->
      <div v-if="selectedOpportunity" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-2xl">✉️</span>
            <h3 class="text-base font-extrabold text-slate-900">Confirmar Inscrição</h3>
          </div>

          <p class="text-xs text-slate-600 mb-4">
            Você está se candidatando para a oportunidade: <br />
            <strong class="text-slate-900 text-sm block mt-1">{{ selectedOpportunity.titulo }}</strong>
            <span class="text-slate-500 text-xs block">Instituição / Empresa: {{ selectedOpportunity.empresa || selectedOpportunity.instituicao }} ({{ selectedOpportunity.municipio }})</span>
          </p>

          <div class="p-3 bg-purple-50 rounded-xl border border-purple-200 text-xs text-purple-900 mb-4 space-y-1">
            <p class="font-bold">Protocolo de Encaminhamento SEJUS:</p>
            <p>Seus dados cadastrais básicos e o comprovante da Carteira Digital serão compartilhados de forma segura conforme a LGPD.</p>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" @click="selectedOpportunity = null" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
            <button type="button" @click="confirmApplication" class="px-4 py-2 bg-purple-700 text-white text-xs font-bold rounded-lg hover:bg-purple-800 transition">Confirmar Encaminhamento</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';

const { t } = useAccessibility();

const activeTab = ref('todos');
const selectedOpportunity = ref(null);

const filters = ref({
  search: '',
  municipio: '',
  modalidade: '',
  somenteAfirmativas: false,
});

const municipiosList = [
  'Vitória', 'Serra', 'Vila Velha', 'Cariacica', 'Linhares', 'Cachoeiro de Itapemirim',
  'Colatina', 'São Mateus', 'Guarapari', 'Aracruz', 'Viana', 'Marataízes', 'Nova Venécia',
  'Barra de São Francisco', 'Santa Maria de Jetibá', 'Castelo', 'Domingos Martins', 'Afonso Cláudio'
];

const vagas = ref([
  {
    id: 1,
    empresa: 'Logística Capixaba S/A (Empresa Amiga)',
    titulo: 'Assistente de Almoxarifado e Estoque',
    municipio: 'Linhares',
    salario: 2150.00,
    regime: 'CLT Efetivo',
    afirmativa: true,
    descricao: 'Conferência de mercadorias, organização de estoque e emissão de notas com suporte e treinamento no local.',
    beneficios: ['Vale Refeição R$ 32/dia', 'Plano de Saúde', 'Vale Transporte'],
    vagas_restantes: 4,
    modalidade: 'Presencial',
  },
  {
    id: 2,
    empresa: 'Construtora Vitória Forte',
    titulo: 'Oficial de Alvenaria e Acabamento',
    municipio: 'Vitória',
    salario: 2480.00,
    regime: 'CLT',
    afirmativa: true,
    descricao: 'Atuação em obras civis governamentais com cota legal para reintegração social.',
    beneficios: ['Seguro de Vida', 'Cesta Básica R$ 400', 'Café da Manhã'],
    vagas_restantes: 8,
    modalidade: 'Presencial',
  },
  {
    id: 3,
    empresa: 'EcoService Ambiental',
    titulo: 'Operador de Máquinas e Triagem',
    municipio: 'Serra',
    salario: 1950.00,
    regime: 'CLT',
    afirmativa: true,
    descricao: 'Operação de esteiras e separação de materiais recicláveis.',
    beneficios: ['Insalubridade 20%', 'Vale Transporte', 'Refeitório'],
    vagas_restantes: 6,
    modalidade: 'Presencial',
  },
  {
    id: 4,
    empresa: 'Centro Automotivo Norte',
    titulo: 'Auxiliar de Mecânica e Manutenção',
    municipio: 'São Mateus',
    salario: 1890.00,
    regime: 'CLT',
    afirmativa: true,
    descricao: 'Auxílio na troca de óleo, pneus e manutenção preventiva de frotas.',
    beneficios: ['Vale Alimentação', 'Uniforme e EPIs'],
    vagas_restantes: 3,
    modalidade: 'Presencial',
  },
  {
    id: 5,
    empresa: 'CallCenter Digital ES',
    titulo: 'Operador de Atendimento ao Cidadão',
    municipio: 'Vila Velha',
    salario: 1650.00,
    regime: 'CLT 6h/dia',
    afirmativa: false,
    descricao: 'Atendimento telefônico e via chat para esclarecimento de dúvidas de serviços públicos.',
    beneficios: ['Home Office Parcial', 'Vale Refeição', 'Auxílio Creche'],
    vagas_restantes: 12,
    modalidade: 'Híbrido',
  },
]);

const cursos = ref([
  {
    id: 101,
    instituicao: 'SENAI Espírito Santo',
    titulo: 'Curso de Qualificação em Soldagem Industrial e Serralheria',
    municipio: 'Linhares',
    carga_horaria: 160,
    modalidade: 'Presencial',
    bolsa_auxilio: 450.00,
    descricao: 'Formação técnica prática com certificação oficial reconhecida nacionalmente e fornecimento de material didático e EPIs.',
    vagas_disponiveis: 15,
  },
  {
    id: 102,
    instituicao: 'IFES - Instituto Federal do ES',
    titulo: 'Informática Básica, Produtividade e Letramento Digital',
    municipio: 'São Mateus',
    carga_horaria: 80,
    modalidade: 'Híbrido',
    bolsa_auxilio: 300.00,
    descricao: 'Curso focado em rotinas administrativas, elaboração de currículos e uso seguro de ferramentas digitais do Governo Federal.',
    vagas_disponiveis: 25,
  },
  {
    id: 103,
    instituicao: 'Qualificar ES / SECTI',
    titulo: 'Eletricista Instalador Residencial e Predial',
    municipio: 'Cariacica',
    carga_horaria: 120,
    modalidade: 'Presencial',
    bolsa_auxilio: 400.00,
    descricao: 'Aulas práticas no polo móvel do Qualificar ES com encaminhamento direto para o banco de talentos do SINE.',
    vagas_disponiveis: 20,
  },
]);

const filteredVagas = computed(() => {
  return vagas.value.filter((v) => {
    if (filters.value.search) {
      const q = filters.value.search.toLowerCase();
      const match = v.titulo.toLowerCase().includes(q) || v.empresa.toLowerCase().includes(q) || v.descricao.toLowerCase().includes(q);
      if (!match) return false;
    }
    if (filters.value.municipio && v.municipio !== filters.value.municipio) return false;
    if (filters.value.modalidade && v.modalidade !== filters.value.modalidade) return false;
    if (filters.value.somenteAfirmativas && !v.afirmativa) return false;
    return true;
  });
});

const filteredCursos = computed(() => {
  return cursos.value.filter((c) => {
    if (filters.value.search) {
      const q = filters.value.search.toLowerCase();
      const match = c.titulo.toLowerCase().includes(q) || c.instituicao.toLowerCase().includes(q) || c.descricao.toLowerCase().includes(q);
      if (!match) return false;
    }
    if (filters.value.municipio && c.municipio !== filters.value.municipio) return false;
    if (filters.value.modalidade && c.modalidade !== filters.value.modalidade) return false;
    return true;
  });
});

const openApplicationModal = (vaga) => {
  selectedOpportunity.value = vaga;
};

const openCourseModal = (curso) => {
  selectedOpportunity.value = curso;
};

const confirmApplication = () => {
  const title = selectedOpportunity.value?.titulo;
  selectedOpportunity.value = null;
  alert(`✉️ Egresso encaminhado com sucesso para a oportunidade: "${title}"!\nSua inscrição foi enviada para o parceiro conveniado SEJUS.`);
};
</script>
