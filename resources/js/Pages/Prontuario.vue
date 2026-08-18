<template>
  <AppLayout :breadcrumbs="[{ name: 'Prontuário Único' }]">
    <Head title="Prontuário Único do Egresso" />

    <div class="prontuario-view space-y-6" id="view-prontuario">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-semibold mb-2 border border-emerald-500/30">
            <span>📁 Trilha de Auditoria Imutável LGPD Ativa</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('prontuario_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Histórico unificado de atendimentos psicossociais, videochamadas, encaminhamentos profissionais e evoluções técnicas do cidadão egresso.
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="isNewEntryModalOpen = true"
          >
            <span>➕ Nova Evolução Técnica</span>
          </button>
        </div>
      </div>

      <!-- Egresso Profile Header Dossier -->
      <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-900 text-white font-extrabold text-lg flex items-center justify-center border-2 border-white shadow-md flex-shrink-0">
              LS
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base font-extrabold text-slate-900">{{ egresso.nome_completo }}</h2>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                  {{ prontuario.situacao }}
                </span>
              </div>
              <p class="text-xs text-slate-500 mt-0.5">
                Prontuário: <span class="font-mono font-bold text-slate-700">{{ prontuario.numero_prontuario }}</span> • Polo: <span class="font-semibold text-slate-700">{{ egresso.municipio.nome }}/ES</span>
              </p>
            </div>
          </div>

          <div class="text-right">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Técnico Responsável</span>
            <strong class="text-xs text-slate-800 font-bold block">{{ prontuario.tecnico_responsavel.name }}</strong>
            <span class="text-[11px] text-slate-500">Abertura: {{ prontuario.data_abertura }}</span>
          </div>
        </div>

        <!-- Masked PII Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 text-xs">
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">CPF Mascarado (LGPD)</span>
            <strong class="text-slate-800 font-mono block mt-0.5">{{ egresso.cpf_masked }}</strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Data de Nascimento</span>
            <strong class="text-slate-800 block mt-0.5">{{ egresso.data_nascimento }}</strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Escolaridade</span>
            <strong class="text-slate-800 block mt-0.5">{{ egresso.escolaridade }}</strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Processo de Execução</span>
            <strong class="text-slate-800 font-mono block mt-0.5">{{ egresso.numero_processo_execucao }}</strong>
          </div>
        </div>

        <!-- Vulnerability Tags & Individual Plan Goal -->
        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1.5">Vulnerabilidades Mapeadas</span>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="tag in egresso.vulnerabilidades" :key="tag" class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-[11px] font-semibold">
                ⚠️ {{ tag }}
              </span>
            </div>
          </div>

          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1.5">Meta do Plano Individual de Reintegração</span>
            <p class="text-slate-700 bg-sky-50/70 p-2.5 rounded-xl border border-sky-200/70 text-[11px] leading-relaxed">
              {{ prontuario.meta_plano_individual }}
            </p>
          </div>
        </div>
      </div>

      <!-- Chronological Timeline Stream -->
      <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>⏱️ Linha do Tempo e Histórico de Evolução</span>
            <span class="text-xs font-normal text-slate-500">({{ timeline.length }} registros auditados)</span>
          </h2>
          <span class="text-[11px] font-mono text-slate-400">Hash SHA-256 Validado</span>
        </div>

        <!-- Timeline Items -->
        <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
          <div
            v-for="evt in timeline"
            :key="evt.id"
            class="timeline-item relative group"
          >
            <!-- Timeline Dot -->
            <div class="absolute -left-6 top-1.5 w-5 h-5 rounded-full bg-white border-2 border-sky-600 flex items-center justify-center shadow-xs">
              <div class="w-2 h-2 rounded-full bg-sky-600"></div>
            </div>

            <!-- Content Card -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 group-hover:border-sky-300 transition space-y-2">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <div class="flex items-center gap-2">
                  <span class="text-base">{{ getEventIcon(evt.tipo_evento) }}</span>
                  <strong class="text-xs font-extrabold text-slate-900">{{ evt.titulo }}</strong>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase bg-slate-200 text-slate-700">
                    {{ evt.tipo_evento }}
                  </span>
                </div>
                <span class="text-[11px] text-slate-400 font-medium">{{ evt.data_evento }}</span>
              </div>

              <p class="text-xs text-slate-700 leading-relaxed">
                {{ evt.descricao }}
              </p>

              <!-- Metadata Box if present -->
              <div v-if="evt.metadata && Object.keys(evt.metadata).length" class="p-2.5 bg-white rounded-xl border border-slate-200 text-[11px] font-mono text-slate-600 space-y-0.5">
                <div v-for="(val, key) in evt.metadata" :key="key" class="flex items-center gap-2">
                  <span class="font-bold text-slate-500">{{ key }}:</span>
                  <span class="text-slate-800">{{ val }}</span>
                </div>
              </div>

              <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400">
                <span>Responsável: <strong class="text-slate-600">{{ evt.responsavel.name }}</strong></span>
                <span class="text-emerald-700 font-bold">● Registro Imutável</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- New Entry Modal -->
      <div v-if="isNewEntryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xl">➕</span>
            <h3 class="text-base font-extrabold text-slate-900">Registrar Nova Evolução no Prontuário</h3>
          </div>
          <p class="text-xs text-slate-500 mb-4">O registro será assinado digitalmente e integrado à trilha imutável de auditoria.</p>

          <form @submit.prevent="handleCreateEntry" class="space-y-3.5">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Tipo de Evento</label>
              <select v-model="newEntryForm.tipo_evento" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option value="EVOLUCAO_TECNICA">Evolução Técnica / Psicossocial</option>
                <option value="ATENDIMENTO_VIDEO">Atendimento Remoto por Vídeo</option>
                <option value="ENCAMINHAMENTO_EMPREGO">Encaminhamento para Vaga de Emprego</option>
                <option value="CURSO_CAPACITACAO">Matrícula em Curso de Capacitação</option>
                <option value="EMISSAO_CARTEIRA">Emissão de Credencial / Documentos</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Título do Evento</label>
              <input v-model="newEntryForm.titulo" type="text" required placeholder="Ex: Acompanhamento mensal de reintegração..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Descrição Detalhada</label>
              <textarea v-model="newEntryForm.descricao" rows="4" required placeholder="Relate as intervenções, estado socioeconômico e encaminhamentos acordados..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="isNewEntryModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
              <button type="submit" class="px-4 py-2 bg-sky-600 text-white text-xs font-bold rounded-lg hover:bg-sky-700 transition">Gravar Evolução</button>
            </div>
          </form>
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

const props = defineProps({
  egresso: {
    type: Object,
    default: () => ({
      nome_completo: 'Lucas Santos de Oliveira',
      cpf_masked: '***.192.830-**',
      data_nascimento: '15/06/1994',
      escolaridade: 'Ensino Fundamental Completo',
      municipio: { nome: 'São Mateus' },
      numero_processo_execucao: '0012480-45.2024.8.08.0047',
      vulnerabilidades: [
        'Baixo Letramento Digital',
        'Busca de Qualificação Profissional',
        'Necessidade de Regularização de Documentos',
      ],
    }),
  },
  prontuario: {
    type: Object,
    default: () => ({
      numero_prontuario: 'PRON-2026-3204906-10842',
      situacao: 'ACOMPANHAMENTO ATIVO',
      data_abertura: '17/08/2026',
      tecnico_responsavel: { name: 'Dra. Márcia Oliveira (CRESS 4891/ES)' },
      resumo_diagnostico: 'Egresso em cumprimento de livramento condicional, motivado para inserção no mercado de trabalho e capacitação técnica.',
      meta_plano_individual: 'Concluir curso de qualificação profissional SENAI e obter colocação formal em vaga de cota afirmativa no polo Linhares/São Mateus.',
    }),
  },
  timeline: {
    type: Array,
    default: () => [
      {
        id: 1,
        tipo_evento: 'ACOLHIMENTO_INICIAL',
        titulo: 'Cadastro Inicial e Acolhimento no Conecta Egresso',
        descricao: 'Egresso realizou cadastro via Acesso Cidadão/Gov.br. Perfil integrado à política pública SEJUS e verificação de biometria.',
        responsavel: { name: 'Sistema Central SEJUS' },
        data_evento: '17/08/2026 às 09:05',
        metadata: { 'Polo': 'Escritório Social Virtual - Norte', 'Origem': 'Acesso Cidadão Gov.br' },
      },
      {
        id: 2,
        tipo_evento: 'EMISSAO_CARTEIRA',
        titulo: 'Emissão da Carteira de Identificação Digital',
        descricao: 'Credencial oficial gerada com assinatura HMAC-SHA256 e QR Code para validação pública conforme Lei Estadual 182/2021.',
        responsavel: { name: 'Sistema Central SEJUS' },
        data_evento: '17/08/2026 às 09:12',
        metadata: { 'Registro': 'ES-2026-3204906-10842', 'Status': 'REGULAR' },
      },
      {
        id: 3,
        tipo_evento: 'ATENDIMENTO_VIDEO',
        titulo: 'Atendimento Psicossocial Remoto Realizado',
        descricao: 'Sessão por videochamada via WebRTC para mapeamento de vocações e orientação de benefícios socioassistenciais.',
        responsavel: { name: 'Dra. Márcia Oliveira' },
        data_evento: '17/08/2026 às 14:30',
        metadata: { 'Duração': '24 min', 'Qualidade (MOS)': '4.3 (Excelente)', 'Encaminhamento': 'SENAI Linhares' },
      },
    ],
  },
});

const { t } = useAccessibility();

const timeline = ref([...props.timeline]);
const isNewEntryModalOpen = ref(false);

const newEntryForm = ref({
  tipo_evento: 'EVOLUCAO_TECNICA',
  titulo: '',
  descricao: '',
});

const getEventIcon = (tipo) => {
  switch (tipo) {
    case 'ACOLHIMENTO_INICIAL': return '👋';
    case 'EMISSAO_CARTEIRA': return '💳';
    case 'ATENDIMENTO_VIDEO': return '📹';
    case 'ENCAMINHAMENTO_EMPREGO': return '💼';
    case 'CURSO_CAPACITACAO': return '🎓';
    default: return '📝';
  }
};

const handleCreateEntry = () => {
  timeline.value.unshift({
    id: Date.now(),
    tipo_evento: newEntryForm.value.tipo_evento,
    titulo: newEntryForm.value.titulo,
    descricao: newEntryForm.value.descricao,
    responsavel: { name: 'Dra. Márcia Oliveira (CRESS 4891/ES)' },
    data_evento: 'Hoje às ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
    metadata: { 'Origem': 'Painel Web Escritório Social', 'Integridade': 'Assinado Digitalmente' },
  });

  isNewEntryModalOpen.value = false;
  newEntryForm.value = { tipo_evento: 'EVOLUCAO_TECNICA', titulo: '', descricao: '' };
};
</script>
