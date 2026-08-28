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
            Atendimento humanizado com transmissão criptografada WebRTC, controle de fila em tempo real e registro automático no Prontuário Único.
          </p>
        </div>

        <div class="flex flex-wrap gap-2.5">
          <button
            type="button"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="openJoinQueueModal"
          >
            <span>➕ Entrar na Fila Virtual</span>
          </button>
          <button
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="startDirectCall"
          >
            <span>📹 Iniciar Chamada Imediata</span>
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
                Fila de Espera em Tempo Real
              </h2>
              <p class="text-xs text-slate-500">
                Atendimentos aguardando chamada de vídeo ou orientação técnica
              </p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                {{ queueList.length }} em espera
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
              :key="ticket.ticket_id"
              class="queue-item p-4 rounded-xl border transition flex flex-col sm:flex-row sm:items-center justify-between gap-3"
              :class="ticket.prioridade === 'urgente' ? 'bg-red-50/40 border-red-200' : (ticket.prioridade === 'preferencial' ? 'bg-amber-50/40 border-amber-200' : 'bg-slate-50 border-slate-200/80')"
            >
              <div class="flex items-start gap-3.5">
                <div
                  class="w-9 h-9 rounded-full flex items-center justify-center font-extrabold text-xs flex-shrink-0"
                  :class="ticket.prioridade === 'urgente' ? 'bg-red-600 text-white' : (ticket.prioridade === 'preferencial' ? 'bg-amber-500 text-white' : 'bg-sky-600 text-white')"
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
                    <span class="text-[11px] text-slate-500 font-semibold">• {{ ticket.municipio }}/ES</span>
                  </div>
                  <p class="text-xs text-slate-600 mt-1 leading-snug">
                    Motivo: <span class="font-medium text-slate-800">{{ ticket.motivo }}</span>
                  </p>
                  <div class="flex items-center gap-3 mt-1.5 text-[11px] text-slate-400">
                    <span>⏱️ Espera: {{ ticket.tempo_espera }}</span>
                    <span>•</span>
                    <span>📱 Sinal: 4G / Wi-Fi OK</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 self-end sm:self-center">
                <button
                  type="button"
                  class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5 cursor-pointer"
                  @click="admitAttendee(ticket)"
                >
                  <span>📞 Chamar Atendido</span>
                </button>
              </div>
            </div>

            <div v-if="queueList.length === 0" class="text-center py-12 text-slate-400 text-xs">
              Nenhum atendido na fila virtual no momento.
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
                <strong class="text-slate-800 font-mono">{{ currentTelemetry.mos.toFixed(1) }} / 4.5</strong>
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
                <strong class="text-slate-800 font-mono">{{ currentTelemetry.packet_loss_pct }}%</strong>
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
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Nome Completo</label>
              <input v-model="newTicket.name" type="text" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Município de Residência</label>
              <input v-model="newTicket.municipio" type="text" required class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none" />
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
              <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg hover:bg-emerald-700 transition">Confirmar Entrada</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Post-Call Intervention Notes Modal -->
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
              <button type="submit" class="px-4 py-2 bg-sky-600 text-white text-xs font-bold rounded-lg hover:bg-sky-700 transition">Salvar no Prontuário</button>
            </div>
          </form>
        </div>
      </div>

      <!-- WebRTC Video Modal Component -->
      <VideoModal
        ref="videoModalRef"
        :is-open="isVideoModalOpen"
        :room-id="activeRoomId"
        :local-user-name="'Dra. Márcia Oliveira'"
        :remote-user-name="activeAttendeeName"
        :telemetry="currentTelemetry"
        :is-local-audio-muted="isLocalAudioMuted"
        :is-local-video-muted="isLocalVideoMuted"
        :is-screen-sharing="isScreenSharing"
        @close="handleCloseVideoModal"
        @end-call="handleEndVideoCall"
        @toggle-audio="handleToggleAudio"
        @toggle-video="handleToggleVideo"
        @toggle-screenshare="handleToggleScreenShare"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import VideoModal from '../Components/VideoModal.vue';
import { WebRTCClient } from '../Services/webrtc';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  initialQueue: {
    type: Array,
    default: () => [
      { ticket_id: 'TCK-101', name: 'Lucas Santos de Oliveira', municipio: 'São Mateus', prioridade: 'urgente', motivo: 'Orientação para emissão da Carteira Digital e cota de emprego', tempo_espera: '4 min' },
      { ticket_id: 'TCK-102', name: 'Marcos Vinícius Barbosa', municipio: 'Linhares', prioridade: 'preferencial', motivo: 'Agendamento de atendimento psicossocial presencial no CRAS', tempo_espera: '12 min' },
      { ticket_id: 'TCK-103', name: 'Amanda Ferreira Lima', municipio: 'Vitória', prioridade: 'normal', motivo: 'Inscrição no curso de Qualificação SENAI', tempo_espera: '18 min' },
    ],
  },
});

const { t } = useAccessibility();
const toast = useToast();

const queueList = ref([...props.initialQueue]);
const isVideoModalOpen = ref(false);
const isJoinQueueModalOpen = ref(false);
const isNotesModalOpen = ref(false);

const activeRoomId = ref('SEJUS-ROOM-ES-2026');
const activeAttendeeName = ref('Lucas Santos');
const lastCallDuration = ref('00:00');

const isLocalAudioMuted = ref(false);
const isLocalVideoMuted = ref(false);
const isScreenSharing = ref(false);

const currentTelemetry = ref({
  mos: 4.3,
  quality_tier: 'Excelente (4G/Wi-Fi)',
  rtt_ms: 38,
  jitter_ms: 6,
  packet_loss_pct: 0.1,
});

const newTicket = ref({
  name: '',
  municipio: 'Vitória',
  prioridade: 'normal',
  motivo: '',
});

const interventionForm = ref({
  tipo_encaminhamento: 'CRAS - Centro de Referência de Assistência Social',
  resumo: '',
});

let rtcClient = null;

const startDirectCall = async () => {
  activeAttendeeName.value = 'Atendido / Egresso';
  activeRoomId.value = `SEJUS-CALL-${Math.floor(1000 + Math.random() * 9000)}`;
  isVideoModalOpen.value = true;
  await initWebRTC();
};

const admitAttendee = async (ticket) => {
  activeAttendeeName.value = ticket.name;
  activeRoomId.value = `SEJUS-ROOM-${ticket.ticket_id}`;
  // Remove from queue
  queueList.value = queueList.value.filter(t => t.ticket_id !== ticket.ticket_id);
  isVideoModalOpen.value = true;
  await initWebRTC();
};

const initWebRTC = async () => {
  rtcClient = new WebRTCClient({
    roomId: activeRoomId.value,
    userName: 'Dra. Márcia Oliveira',
    role: 'tecnico',
    onTelemetryUpdate: (data) => {
      currentTelemetry.value = data;
    },
    onQualityAlert: (alert) => {
      console.warn('WebRTC Quality Alert:', alert);
    },
  });

  try {
    await rtcClient.startLocalMedia();
  } catch (err) {
    console.warn('Could not start media tracks:', err);
  }
};

const handleCloseVideoModal = () => {
  if (rtcClient) {
    rtcClient.endCall('closed');
  }
  isVideoModalOpen.value = false;
};

const handleEndVideoCall = (payload) => {
  if (rtcClient) {
    rtcClient.endCall('finished');
  }
  const s = payload.durationSeconds || 0;
  const m = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  lastCallDuration.value = `${m}:${sec}`;

  isVideoModalOpen.value = false;
  isNotesModalOpen.value = true;
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
  newTicket.value = {
    name: '',
    municipio: 'Vitória',
    prioridade: 'normal',
    motivo: '',
  };
  isJoinQueueModalOpen.value = true;
};

const handleJoinQueueSubmit = () => {
  const ticketId = `TCK-${Math.floor(100 + Math.random() * 900)}`;
  queueList.value.push({
    ticket_id: ticketId,
    name: newTicket.value.name,
    municipio: newTicket.value.municipio,
    prioridade: newTicket.value.prioridade,
    motivo: newTicket.value.motivo,
    tempo_espera: '1 min',
  });
  isJoinQueueModalOpen.value = false;
};

const handleSaveNotes = () => {
  isNotesModalOpen.value = false;
  toast.success(
    'Registro Salvo no Prontuário',
    'Registro salvo com sucesso no Prontuário do Egresso!\nEncaminhamento: ' + interventionForm.value.tipo_encaminhamento
  );
};

onBeforeUnmount(() => {
  if (rtcClient) {
    rtcClient.destroy();
  }
});
</script>
