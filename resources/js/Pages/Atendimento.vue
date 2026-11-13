<template>
  <AppLayout :breadcrumbs="[{ name: 'Atendimento Remoto' }]">
    <Head title="Atendimento Remoto & Videochamadas" />

    <div class="atendimento-view space-y-6" id="view-atendimento">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-semibold mb-2 border border-emerald-500/30">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Mesa Virtual de Teleatendimento Ativa • SEJUS/ES</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('atendimento_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            <template v-if="isStaff">
              Atendimento humanizado com transmissão criptografada WebRTC, fila virtual em tempo real e registro automático no Prontuário Único.
            </template>
            <template v-else>
              Solicite seu teleatendimento psicossocial ou jurídico e aguarde a chamada do técnico da SEJUS/ES em sala segura e criptografada.
            </template>
          </p>
        </div>

        <div class="flex flex-wrap gap-2.5">
          <button
            type="button"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="openJoinQueueModal"
          >
            <span>{{ isStaff ? '➕ Inserir na Fila Virtual' : '➕ Solicitar Atendimento (Entrar na Fila)' }}</span>
          </button>
          <button
            v-if="isStaff"
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="startDirectCall"
          >
            <span>📹 Iniciar Chamada WebRTC</span>
          </button>
          <button
            v-if="isStaff"
            type="button"
            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            :disabled="isCreatingMeet"
            @click="createGoogleMeetRoom"
          >
            <span>{{ isCreatingMeet ? '⏳ Criando Sala...' : '📅 Criar Google Meet' }}</span>
          </button>
        </div>
      </div>

      <!-- Egresso/Familiar Call Ready Banner -->
      <div
        v-if="!isStaff && activeCall && activeCall.status === 'em_andamento'"
        class="bg-emerald-50 border-2 border-emerald-400 rounded-2xl p-5 shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4"
      >
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xl">
            📞
          </div>
          <div>
            <span class="text-sm font-extrabold text-emerald-950 block">Sua vez chegou! O técnico está chamando você na sala.</span>
            <span class="text-xs text-emerald-800 block mt-0.5">
              Sala: <strong class="font-mono">{{ activeCall.room_code }}</strong> • Técnico: {{ activeCall.tecnico_nome || 'Equipe SEJUS/ES' }}
            </span>
          </div>
        </div>
        <button
          type="button"
          class="px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold rounded-xl shadow-md transition cursor-pointer flex items-center gap-2"
          @click="joinActiveRoom(activeCall)"
        >
          <span>🎥 Entrar na Chamada Agora</span>
        </button>
      </div>

      <!-- Google Meet / Contingency Room Active Banner if created -->
      <div v-if="activeMeetRoom" class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg">
            🎥
          </div>
          <div>
            <span class="text-xs font-bold text-indigo-900 block">
              {{ activeMeetRoom.contingency_mode ? 'Sala de Contingência WebRTC Criada (Link Direto)' : 'Sala Google Meet Criada com Sucesso!' }}
            </span>
            <span class="text-[11px] text-indigo-700 font-mono select-all">{{ activeMeetRoom.meeting_uri }}</span>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="copyMeetLink"
            class="px-3.5 py-2 bg-white hover:bg-indigo-50 border border-indigo-300 text-indigo-800 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5"
          >
            <span>📋 {{ meetCopied ? 'Link Copiado!' : 'Copiar Link para o Cidadão' }}</span>
          </button>
          <a
            :href="activeMeetRoom.meeting_uri"
            :target="activeMeetRoom.contingency_mode ? '_self' : '_blank'"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer"
          >
            <span>Entrar na Sala ↗</span>
          </a>
          <button
            type="button"
            @click="activeMeetRoom = null"
            class="p-2 text-indigo-400 hover:text-indigo-700 transition"
            title="Fechar aviso"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Main Layout: Attendance Queue & Active Session Info -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Attendance Queue List (2 cols) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 lg:col-span-2">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
                {{ isStaff ? 'Fila de Espera em Tempo Real' : 'Minha Solicitação de Teleatendimento' }}
              </h2>
              <p class="text-xs text-slate-500">
                {{ isStaff ? 'Atendimentos aguardando chamada de vídeo ou orientação técnica' : 'Acompanhe o status do seu atendimento em tempo real (atualizado a cada 5s)' }}
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                {{ queueList.length }} {{ queueList.length === 1 ? 'registro' : 'registros' }}
              </span>
            </div>
          </div>

          <!-- ARIA Live Queue List -->
          <div
            id="attendanceQueue"
            class="space-y-3"
            role="status"
            aria-live="polite"
            aria-label="Fila de Atendimento em Tempo Real"
          >
            <div
              v-for="(ticket, idx) in queueList"
              :key="ticket.id || ticket.ticket_id"
              class="queue-item p-4 rounded-xl border transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
              :class="ticket.status === 'em_andamento' ? 'bg-emerald-50/70 border-emerald-300' : (ticket.prioridade === 'urgente' ? 'bg-red-50/40 border-red-200' : (ticket.prioridade === 'preferencial' ? 'bg-amber-50/40 border-amber-200' : 'bg-slate-50 border-slate-200/80'))"
            >
              <div class="flex items-start gap-3.5">
                <div
                  class="w-9 h-9 rounded-full flex items-center justify-center font-extrabold text-xs flex-shrink-0"
                  :class="ticket.status === 'em_andamento' ? 'bg-emerald-600 text-white' : (ticket.prioridade === 'urgente' ? 'bg-red-600 text-white' : (ticket.prioridade === 'preferencial' ? 'bg-amber-500 text-white' : 'bg-sky-600 text-white'))"
                >
                  #{{ idx + 1 }}
                </div>
                <div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <strong class="text-slate-900 text-xs font-extrabold">{{ ticket.name }}</strong>
                    <span
                      class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                      :class="ticket.prioridade === 'urgente' ? 'bg-red-100 text-red-800' : (ticket.prioridade === 'preferencial' ? 'bg-amber-100 text-amber-800' : 'bg-sky-100 text-sky-800')"
                    >
                      {{ ticket.prioridade }}
                    </span>
                    <span
                      v-if="ticket.status === 'em_andamento'"
                      class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800"
                    >
                      Em Chamada
                    </span>
                    <span class="text-[11px] text-slate-500 font-semibold">• {{ ticket.municipio }}/ES</span>
                  </div>
                  <p class="text-xs text-slate-600 mt-1 leading-snug">
                    Motivo: <span class="font-medium text-slate-800">{{ ticket.motivo }}</span>
                  </p>
                  <div class="flex items-center gap-3 mt-1.5 text-[11px] text-slate-400">
                    <span>⏱️ Espera: {{ ticket.tempo_espera }}</span>
                    <span>•</span>
                    <span class="font-mono">Sala: {{ ticket.room_code }}</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 self-end sm:self-center">
                <button
                  v-if="isStaff"
                  type="button"
                  class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                  @click="admitAttendee(ticket)"
                >
                  <span>📞 {{ ticket.status === 'em_andamento' ? 'Reconectar na Sala' : 'Chamar Atendido' }}</span>
                </button>
                <button
                  v-else-if="ticket.status === 'em_andamento'"
                  type="button"
                  class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                  @click="joinActiveRoom(ticket)"
                >
                  <span>🎥 Entrar na Chamada</span>
                </button>
                <span
                  v-else
                  class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 text-[11px] font-bold"
                >
                  ⏳ Aguardando Técnico
                </span>
              </div>
            </div>

            <div v-if="queueList.length === 0" class="text-center py-12 text-slate-400 text-xs">
              {{ isStaff ? 'Nenhum atendido na fila virtual no momento.' : 'Você não possui solicitações abertas na fila. Clique em "Solicitar Atendimento" acima para aguardar um técnico.' }}
            </div>
          </div>
        </div>

        <!-- Right Side: Quality Telemetry & Instructions -->
        <div class="space-y-5">
          <!-- Telemetry Status Card -->
          <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">
                Qualidade da Conexão (Telemetria)
              </h2>
              <span class="text-emerald-600 font-bold text-xs">● Operacional</span>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/60 space-y-3">
              <div class="flex justify-between items-center text-xs">
                <span class="text-slate-500">Pontuação MOS ITU-T:</span>
                <strong class="text-slate-800 font-mono">{{ Number(currentTelemetry.mos || 4.3).toFixed(1) }} / 4.5</strong>
              </div>
              <div class="flex justify-between items-center text-xs">
                <span class="text-slate-500">Latência RTT:</span>
                <strong class="text-slate-800 font-mono">{{ currentTelemetry.rtt_ms }} ms</strong>
              </div>
              <div class="flex justify-between items-center text-xs">
                <span class="text-slate-500">Variação de Jitter:</span>
                <strong class="text-slate-800 font-mono">{{ currentTelemetry.jitter_ms }} ms</strong>
              </div>
              <div class="flex justify-between items-center text-xs">
                <span class="text-slate-500">Perda de Pacotes:</span>
                <strong class="text-slate-800 font-mono">{{ Number(currentTelemetry.packet_loss_pct || 0).toFixed(1) }}%</strong>
              </div>
              <div class="pt-2 border-t border-slate-200 text-[11px] text-slate-500">
                Protocolo Coturn STUN/TURN ativo para travessia de CGNAT em redes móveis (3G/4G/5G).
              </div>
            </div>
          </div>

          <!-- Guidelines Box -->
          <div class="bg-sky-50/80 border border-sky-200 p-5 rounded-2xl text-xs text-sky-900 space-y-2">
            <strong class="block font-bold text-sky-950 text-sm">Orientações para o Atendimento</strong>
            <ul class="list-disc list-inside space-y-1 text-sky-800">
              <li>Verifique a identidade do egresso via dados do Gov.br antes de iniciar.</li>
              <li>Toda chamada gera registro imutável com duração no Prontuário Único.</li>
              <li>Em caso de conexão instável, utilize a sugestão de desligar o vídeo.</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Join Queue Modal -->
      <div v-if="isJoinQueueModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
          <h3 class="text-base font-extrabold text-slate-900 mb-2">Entrar na Fila de Atendimento Virtual</h3>
          <p class="text-xs text-slate-500 mb-4">Preencha os dados para aguardar sua vez com a equipe psicossocial.</p>

          <form @submit.prevent="handleJoinQueueSubmit" class="space-y-3.5">
            <div v-if="isStaff && egressosList.length > 0">
              <label class="block text-xs font-bold text-slate-700 mb-1">Vincular Egresso Cadastrado (opcional)</label>
              <select v-model="newTicket.egresso_id" @change="onSelectEgressoForQueue" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option value="">-- Informar solicitante manualmente --</option>
                <option v-for="eg in egressosList" :key="eg.id" :value="eg.id">{{ eg.nome }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Nome Completo</label>
              <input v-model="newTicket.name" type="text" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Município de Residência</label>
              <select v-if="municipios.length > 0" v-model="newTicket.municipio_id" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option v-for="m in municipios" :key="m.id" :value="m.id">{{ m.nome }}</option>
              </select>
              <input v-else v-model="newTicket.municipio" type="text" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Prioridade</label>
              <select v-model="newTicket.prioridade" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option value="normal">Normal (Ordem de chegada)</option>
                <option value="preferencial">Preferencial (Idoso, Gestante, PcD)</option>
                <option value="urgente">Urgente (Prazo judicial / Audiência)</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Motivo do Atendimento</label>
              <textarea v-model="newTicket.motivo" rows="3" required placeholder="Ex: Encaminhamento para vaga de emprego, dúvidas sobre cumprimento de pena..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="isJoinQueueModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
              <button type="submit" :disabled="isSubmittingQueue" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition disabled:opacity-50">
                {{ isSubmittingQueue ? 'Enviando...' : 'Confirmar Entrada' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Post-Call Intervention Notes Modal (Staff Only) -->
      <div v-if="isNotesModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xl">📝</span>
            <h3 class="text-base font-extrabold text-slate-900">Registro de Evolução e Encaminhamento</h3>
          </div>
          <p class="text-xs text-slate-500 mb-4">
            A chamada com duração de <strong>{{ lastCallDuration }}</strong> foi finalizada. Registre a evolução no Prontuário Único.
          </p>

          <form @submit.prevent="handleSaveNotes" class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Tipo de Encaminhamento Realizado</label>
              <select id="callEncaminhamentoType" v-model="interventionForm.tipo_encaminhamento" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option value="CRAS - Centro de Referência de Assistência Social">CRAS - Apoio Assistencial e Benefícios</option>
                <option value="CREAS - Centro de Referência Especializado">CREAS - Acompanhamento Especializado</option>
                <option value="SINE - Agência do Trabalhador">SINE - Intermediação de Mão de Obra</option>
                <option value="CAPS - Atenção Psicossocial">CAPS - Saúde Mental e Dependência</option>
                <option value="Defensoria Pública Estadual">Defensoria Pública - Orientação Jurídica</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Resumo Diagnóstico / Evolução Técnica</label>
              <textarea v-model="interventionForm.resumo" rows="4" required placeholder="Descreva os pontos tratados, orientações fornecidas e próximos passos combinados..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="isNotesModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Pular Registro</button>
              <button type="submit" :disabled="isSavingNotes" class="px-4 py-2 bg-sky-600 text-white text-xs font-bold rounded-lg hover:bg-sky-700 transition disabled:opacity-50">
                {{ isSavingNotes ? 'Salvando...' : 'Salvar no Prontuário' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- WebRTC Video Modal Component -->
      <VideoModal
        ref="videoModalRef"
        :is-open="isVideoModalOpen"
        :room-id="activeRoomId"
        :local-user-name="localUserName"
        :remote-user-name="activeAttendeeName"
        :telemetry="currentTelemetry"
        :is-local-audio-muted="isLocalAudioMuted"
        :is-local-video-muted="isLocalVideoMuted"
        :is-screen-sharing="isScreenSharing"
        :is-staff="isStaff"
        @close="handleCloseVideoModal"
        @end-call="handleEndVideoCall"
        @toggle-audio="handleToggleAudio"
        @toggle-video="handleToggleVideo"
        @toggle-screenshare="handleToggleScreenShare"
        @send-chat="handleSendChat"
        @open-meet="createGoogleMeetRoom"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import VideoModal from '../Components/VideoModal.vue';
import { WebRTCClient } from '../Services/webrtc';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  initialQueue: {
    type: Array,
    default: () => [],
  },
  activeCallForUser: {
    type: Object,
    default: null,
  },
  municipios: {
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

const localUserName = computed(() => page.props.auth?.user?.name || 'Equipe Técnica SEJUS');
const localUserRole = computed(() => page.props.auth?.user?.role || 'tecnico');
const isStaff = computed(() => ['gestor', 'tecnico', 'suporte'].includes(localUserRole.value));

const queueList = ref([...props.initialQueue]);
const activeCall = ref(props.activeCallForUser);
const isVideoModalOpen = ref(false);
const isJoinQueueModalOpen = ref(false);
const isNotesModalOpen = ref(false);
const isSubmittingQueue = ref(false);
const isSavingNotes = ref(false);

const activeRoomId = ref('SEJUS-ROOM-ES-2026');
const activeVideoRoomDbId = ref(null);
const activeAttendeeName = ref('Atendido / Egresso');
const lastCallDuration = ref('00:00');

const isLocalAudioMuted = ref(false);
const isLocalVideoMuted = ref(false);
const isScreenSharing = ref(false);

const isCreatingMeet = ref(false);
const activeMeetRoom = ref(null);
const meetCopied = ref(false);

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const refreshQueue = async () => {
  try {
    const res = await fetch('/api/atendimento/fila', { headers: { Accept: 'application/json' } });
    if (res.ok) {
      const data = await res.json();
      if (Array.isArray(data.queue)) {
        queueList.value = data.queue;
      }
      activeCall.value = data.active_call || null;
    }
  } catch (err) {
    // Silent retry on next poll interval
  }
};

let queuePollTimer = null;

onMounted(() => {
  queuePollTimer = setInterval(refreshQueue, 5000);

  if (typeof window !== 'undefined') {
    const params = new URLSearchParams(window.location.search);
    const roomParam = params.get('room');
    if (roomParam && roomParam.trim()) {
      activeRoomId.value = roomParam.trim();
      activeAttendeeName.value = isStaff.value ? 'Atendido na Sala' : 'Equipe Técnica SEJUS';
      isVideoModalOpen.value = true;
      initWebRTC();
    }
  }
});

const createGoogleMeetRoom = async () => {
  isCreatingMeet.value = true;
  try {
    const res = await fetch('/api/meet/rooms', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        displayName: `Atendimento SEJUS - ${localUserName.value}`,
      }),
    });
    const data = await res.json();
    if (res.ok && data.meeting_uri) {
      activeMeetRoom.value = data;
      toast.success(
        data.contingency_mode ? 'Sala de Contingência Criada' : 'Sala do Google Meet Criada',
        data.message || 'O link da reunião foi gerado e está pronto para envio.'
      );
    } else {
      toast.error('Google Meet', data.message || 'Não foi possível gerar a sala no Google Meet.');
    }
  } catch (err) {
    toast.error('Erro na Conexão', 'Falha ao conectar com o serviço de videoconferência.');
  } finally {
    isCreatingMeet.value = false;
  }
};

const copyMeetLink = () => {
  if (!activeMeetRoom.value?.meeting_uri) return;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(activeMeetRoom.value.meeting_uri).then(() => {
      meetCopied.value = true;
      setTimeout(() => { meetCopied.value = false; }, 3000);
    });
  } else {
    prompt('Copie o link abaixo para enviar ao cidadão:', activeMeetRoom.value.meeting_uri);
  }
};

const currentTelemetry = ref({
  mos: 4.3,
  quality_tier: 'Excelente (4G/Wi-Fi)',
  rtt_ms: 38,
  jitter_ms: 6,
  packet_loss_pct: 0.1,
});

const newTicket = ref({
  egresso_id: '',
  name: '',
  municipio_id: null,
  municipio: 'Vitória',
  prioridade: 'normal',
  motivo: '',
});

const interventionForm = ref({
  tipo_encaminhamento: 'CRAS - Centro de Referência de Assistência Social',
  resumo: '',
});

let rtcClient = null;
const videoModalRef = ref(null);

const startDirectCall = async () => {
  activeVideoRoomDbId.value = null;
  activeAttendeeName.value = 'Atendido / Egresso';
  activeRoomId.value = `SEJUS-CALL-${Math.floor(1000 + Math.random() * 9000)}`;
  isVideoModalOpen.value = true;
  await initWebRTC();
};

const admitAttendee = async (ticket) => {
  try {
    const res = await fetch(`/api/atendimento/fila/${ticket.id || ticket.ticket_id}/chamar`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
    });
    const data = await res.json();
    if (!res.ok) {
      toast.error('Fila Virtual', data.error || 'Não foi possível chamar o atendido.');
      return;
    }

    const roomData = data.room || ticket;
    activeVideoRoomDbId.value = roomData.id || ticket.id;
    activeAttendeeName.value = roomData.name || ticket.name;
    activeRoomId.value = roomData.room_code || ticket.room_code;
    await refreshQueue();
    isVideoModalOpen.value = true;
    await initWebRTC();
  } catch (err) {
    toast.error('Erro de Rede', 'Falha ao iniciar chamada pela fila virtual.');
  }
};

const joinActiveRoom = async (roomItem) => {
  activeVideoRoomDbId.value = roomItem.id || null;
  activeAttendeeName.value = roomItem.tecnico_nome || 'Equipe Técnica SEJUS';
  activeRoomId.value = roomItem.room_code;
  isVideoModalOpen.value = true;
  await initWebRTC();
};

const initWebRTC = async () => {
  try {
    if (rtcClient) {
      rtcClient.destroy();
      rtcClient = null;
    }

    let token = '';
    let iceServers = null;

    try {
      const tokenRes = await fetch('/api/webrtc/token', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({
          room_id: activeRoomId.value,
          room_code: activeRoomId.value,
          role: localUserRole.value,
        }),
      });
      if (tokenRes.ok) {
        const tokenData = await tokenRes.json();
        token = tokenData.token || '';
        iceServers = tokenData.ice_servers || null;
        if (!activeVideoRoomDbId.value && tokenData.video_room_id) {
          activeVideoRoomDbId.value = tokenData.video_room_id;
        }
      }
    } catch (tokenErr) {
      console.warn('Falha ao obter token WebRTC:', tokenErr);
    }

    const wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsHost = window.location.host;
    // WebRTCClient.connect() já concatena `/ws/signaling/${roomId}`
    const wsUrl = `${wsProto}//${wsHost}`;

    rtcClient = new WebRTCClient({
      wsUrl,
      token,
      roomId: activeRoomId.value,
      userName: localUserName.value,
      role: localUserRole.value,
      ...(iceServers ? { iceServers } : {}),
      onPeerJoined: (data) => {
        if (data?.peer?.name) {
          activeAttendeeName.value = data.peer.name;
        }
        videoModalRef.value?.appendChatMessage({
          sender: 'Sistema',
          text: `${data?.peer?.name || 'Participante'} entrou na sala.`,
          isSelf: false,
        });
      },
      onPeerLeft: () => {
        if (videoModalRef.value) {
          videoModalRef.value.hasRemoteStream = false;
        }
        videoModalRef.value?.appendChatMessage({
          sender: 'Sistema',
          text: 'O outro participante saiu da sala.',
          isSelf: false,
        });
      },
      onRemoteStream: (stream) => {
        if (videoModalRef.value?.setRemoteStream) {
          videoModalRef.value.setRemoteStream(stream);
        } else if (videoModalRef.value) {
          videoModalRef.value.hasRemoteStream = true;
          if (videoModalRef.value.remoteVideoRef) {
            videoModalRef.value.remoteVideoRef.srcObject = stream;
          }
        }
      },
      onTelemetryUpdate: (data) => {
        currentTelemetry.value = data;
      },
      onChatMessage: (data) => {
        videoModalRef.value?.appendChatMessage({
          sender: data.sender_name || 'Participante',
          time: data.timestamp,
          text: data.text,
          isSelf: false,
        });
      },
      onQualityAlert: (alert) => {
        console.warn('WebRTC Quality Alert:', alert);
      },
    });

    const localStream = await rtcClient.startLocalMedia({ audio: true, video: true });
    if (localStream) {
      if (videoModalRef.value?.setLocalStream) {
        await videoModalRef.value.setLocalStream(localStream);
      } else if (videoModalRef.value?.localVideoRef) {
        videoModalRef.value.localVideoRef.srcObject = localStream;
      }
    }

    if (token) {
      await rtcClient.connect();
    }
  } catch (err) {
    console.warn('Erro ao inicializar WebRTC:', err);
  }
};

const handleSendChat = (text) => {
  if (rtcClient) {
    rtcClient.sendChatMessage(text);
  }
};

const handleCloseVideoModal = () => {
  if (rtcClient) {
    rtcClient.endCall('closed');
    rtcClient = null;
  }
  isVideoModalOpen.value = false;
};

const handleEndVideoCall = async (payload) => {
  if (rtcClient) {
    rtcClient.endCall('finished');
    rtcClient = null;
  }
  const s = payload.durationSeconds || 0;
  const m = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  lastCallDuration.value = `${m}:${sec}`;

  isVideoModalOpen.value = false;
  if (isStaff.value) {
    interventionForm.value.resumo = '';
    isNotesModalOpen.value = true;
  } else if (activeVideoRoomDbId.value) {
    try {
      await fetch(`/api/atendimento/fila/${activeVideoRoomDbId.value}/encerrar`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ duracao: lastCallDuration.value }),
      });
      await refreshQueue();
    } catch (err) {}
    toast.success('Chamada Finalizada', 'Seu teleatendimento foi encerrado com segurança.');
  }
};

const handleToggleAudio = () => {
  isLocalAudioMuted.value = !isLocalAudioMuted.value;
  if (rtcClient) rtcClient.toggleAudio(isLocalAudioMuted.value);
};

const handleToggleVideo = () => {
  isLocalVideoMuted.value = !isLocalVideoMuted.value;
  if (rtcClient) rtcClient.toggleVideo(isLocalVideoMuted.value);
};

const handleToggleScreenShare = async () => {
  if (!isScreenSharing.value) {
    if (rtcClient) {
      await rtcClient.startScreenShare();
      isScreenSharing.value = true;
    }
  } else {
    if (rtcClient) {
      rtcClient.stopScreenShare();
      isScreenSharing.value = false;
    }
  }
};

const openJoinQueueModal = () => {
  const vitoria = props.municipios.find(m => m.nome === 'Vitória') || props.municipios[0];
  newTicket.value = {
    egresso_id: '',
    name: isStaff.value ? '' : localUserName.value,
    municipio_id: vitoria?.id || null,
    municipio: vitoria?.nome || 'Vitória',
    prioridade: 'normal',
    motivo: '',
  };
  isJoinQueueModalOpen.value = true;
};

const onSelectEgressoForQueue = () => {
  const found = props.egressosList.find(e => Number(e.id) === Number(newTicket.value.egresso_id));
  if (found) {
    newTicket.value.name = found.nome;
    if (found.municipio_id) {
      newTicket.value.municipio_id = found.municipio_id;
    }
  }
};

const handleJoinQueueSubmit = async () => {
  isSubmittingQueue.value = true;
  try {
    const res = await fetch('/api/atendimento/fila/entrar', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        egresso_id: newTicket.value.egresso_id || null,
        name: newTicket.value.name,
        municipio_id: newTicket.value.municipio_id || null,
        municipio: newTicket.value.municipio,
        prioridade: newTicket.value.prioridade,
        motivo: newTicket.value.motivo,
      }),
    });
    const data = await res.json();
    if (res.ok) {
      isJoinQueueModalOpen.value = false;
      await refreshQueue();
      toast.success('Fila Virtual', data.message || 'Solicitação registrada na fila de atendimento em tempo real.');
    } else {
      toast.error('Erro ao Entrar na Fila', data.error || data.message || 'Verifique os dados informados.');
    }
  } catch (err) {
    toast.error('Erro de Rede', 'Falha ao enviar solicitação para a fila.');
  } finally {
    isSubmittingQueue.value = false;
  }
};

const handleSaveNotes = async () => {
  if (!activeVideoRoomDbId.value) {
    isNotesModalOpen.value = false;
    toast.info('Chamada Finalizada', 'Atendimento encerrado. Caso deseje registrar em um prontuário específico, acesse a tela de Prontuário.');
    return;
  }

  isSavingNotes.value = true;
  try {
    const res = await fetch(`/api/atendimento/fila/${activeVideoRoomDbId.value}/encerrar`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        tipo_encaminhamento: interventionForm.value.tipo_encaminhamento,
        resumo: interventionForm.value.resumo,
        duracao: lastCallDuration.value,
      }),
    });
    const data = await res.json();
    if (res.ok) {
      isNotesModalOpen.value = false;
      await refreshQueue();
      toast.success(
        'Registro Salvo no Prontuário',
        'Atendimento encerrado e evolução gravada com hash auditável no Prontuário Único.'
      );
    } else {
      toast.error('Falha ao Salvar Evolução', data.error || data.message || 'Não foi possível gravar a evolução.');
    }
  } catch (err) {
    toast.error('Erro de Conexão', 'Não foi possível comunicar com o servidor.');
  } finally {
    isSavingNotes.value = false;
  }
};

onBeforeUnmount(() => {
  if (queuePollTimer) {
    clearInterval(queuePollTimer);
  }
  if (rtcClient) {
    rtcClient.destroy();
  }
});
</script>
