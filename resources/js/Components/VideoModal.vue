<template>
  <div
    v-if="isOpen"
    id="videoModal"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 md:p-6 bg-black/80 backdrop-blur-sm animate-fade-in"
    role="dialog"
    aria-modal="true"
    aria-labelledby="videoModalTitle"
    tabindex="-1"
    @keydown.esc="handleClose"
  >
    <div class="video-modal-content w-full max-w-5xl bg-slate-900 text-white rounded-2xl border border-slate-700 shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
      <!-- Header -->
      <div class="modal-header px-6 py-4 bg-slate-800/90 border-b border-slate-700 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
          <div>
            <h2 id="videoModalTitle" class="text-base font-extrabold text-white leading-tight">
              Atendimento Remoto Seguro — Sala #{{ roomId || 'SEJUS-ES-2026' }}
            </h2>
            <p class="text-xs text-slate-400">
              Criptografia de Ponta a Ponta WebRTC • Protocolo de Gravação de Auditoria SEJUS
            </p>
          </div>
        </div>

        <!-- Telemetry Signal Meter Badge -->
        <div class="flex items-center gap-3">
          <div
            class="telemetry-badge flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold border transition"
            :class="telemetryBadgeClass"
            :title="`MOS: ${telemetry.mos.toFixed(1)} | RTT: ${telemetry.rtt_ms}ms | Jitter: ${telemetry.jitter_ms}ms | Perda: ${telemetry.packet_loss_pct.toFixed(1)}%`"
          >
            <span class="signal-bars flex items-end gap-0.5 h-3">
              <span class="w-1 bg-current rounded-xs" :class="telemetry.mos >= 2.5 ? 'h-1.5' : 'h-1'"></span>
              <span class="w-1 bg-current rounded-xs" :class="telemetry.mos >= 3.2 ? 'h-2.5' : 'h-1 opacity-30'"></span>
              <span class="w-1 bg-current rounded-xs" :class="telemetry.mos >= 4.0 ? 'h-3.5' : 'h-1 opacity-30'"></span>
            </span>
            <span>MOS {{ telemetry.mos.toFixed(1) }} ({{ telemetry.quality_tier }})</span>
          </div>

          <button
            type="button"
            class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-700 transition"
            aria-label="Fechar janela"
            @click="handleClose"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Network Degradation Alert Banner -->
      <div
        v-if="telemetry.mos < 3.2 && telemetry.mos > 0"
        class="bg-amber-500/20 border-b border-amber-500/40 text-amber-200 px-6 py-2 text-xs flex items-center justify-between"
      >
        <span>⚠️ Conexão instável (MOS {{ telemetry.mos.toFixed(1) }}). Sugerimos desativar o vídeo para priorizar o áudio com nitidez.</span>
        <button
          type="button"
          class="underline font-bold hover:text-white ml-3"
          @click="emit('toggle-video', true)"
        >
          Desativar Vídeo
        </button>
      </div>

      <!-- Main Body: Video Grid & Side Chat -->
      <div class="modal-body flex-1 flex flex-col lg:flex-row min-h-[380px] overflow-hidden">
        <!-- Video Grid -->
        <div class="video-grid flex-1 p-4 bg-black relative flex items-center justify-center">
          <div class="w-full h-full grid grid-cols-1 md:grid-cols-2 gap-4 relative">
            <!-- Remote Participant Video -->
            <div class="remote-video-box relative bg-slate-800 rounded-xl overflow-hidden border border-slate-700 flex items-center justify-center aspect-video md:aspect-auto">
              <video
                ref="remoteVideoRef"
                id="remoteVideo"
                autoplay
                playsinline
                class="w-full h-full object-cover"
              ></video>
              <div class="absolute bottom-3 left-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded text-xs font-semibold text-white flex items-center gap-2">
                <span>{{ remoteUserName || 'Atendido / Egresso' }}</span>
                <span v-if="isRemoteMuted" class="text-red-400">🔇</span>
              </div>
            </div>

            <!-- Local Participant Video -->
            <div class="local-video-box relative bg-slate-800 rounded-xl overflow-hidden border border-slate-700 flex items-center justify-center aspect-video md:aspect-auto">
              <video
                ref="localVideoRef"
                id="localVideo"
                autoplay
                playsinline
                muted
                class="w-full h-full object-cover"
              ></video>
              <div class="absolute bottom-3 left-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded text-xs font-semibold text-white flex items-center gap-2">
                <span>Você ({{ localUserName || 'Técnico Social' }})</span>
                <span v-if="isLocalAudioMuted" class="text-red-400">🔇</span>
              </div>
            </div>
          </div>
        </div>

        <!-- In-Call Side Chat Panel -->
        <div
          v-if="showChat"
          class="chat-panel w-full lg:w-80 bg-slate-800/95 border-t lg:border-t-0 lg:border-l border-slate-700 flex flex-col"
        >
          <div class="p-3 border-b border-slate-700 font-bold text-xs flex justify-between items-center text-slate-300">
            <span>Mensagens na Chamada</span>
            <span class="text-[10px] bg-slate-700 px-2 py-0.5 rounded text-slate-300">{{ chatMessages.length }}</span>
          </div>

          <div class="flex-1 p-3 overflow-y-auto space-y-2.5 text-xs max-h-60 lg:max-h-none">
            <div
              v-for="(msg, idx) in chatMessages"
              :key="idx"
              class="p-2.5 rounded-lg"
              :class="msg.isSelf ? 'bg-sky-600/30 border border-sky-500/30 ml-4' : 'bg-slate-700/60 border border-slate-600/30 mr-4'"
            >
              <div class="flex justify-between text-[10px] font-bold text-slate-400 mb-1">
                <span>{{ msg.sender }}</span>
                <span>{{ msg.time }}</span>
              </div>
              <p class="text-slate-200 leading-snug">{{ msg.text }}</p>
            </div>
            <div v-if="chatMessages.length === 0" class="text-center text-slate-500 py-8 text-xs">
              Nenhuma mensagem trocada ainda.
            </div>
          </div>

          <form @submit.prevent="handleSendMessage" class="p-3 border-t border-slate-700 flex gap-2">
            <input
              v-model="chatInput"
              type="text"
              placeholder="Digite uma mensagem..."
              class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"
            />
            <button
              type="submit"
              class="px-3 py-1.5 bg-sky-600 text-white rounded-lg text-xs font-bold hover:bg-sky-500 transition cursor-pointer"
            >
              Enviar
            </button>
          </form>
        </div>
      </div>

      <!-- Footer Call Controls -->
      <div class="modal-footer px-6 py-4 bg-slate-800 border-t border-slate-700 flex items-center justify-between flex-wrap gap-3">
        <!-- Call Duration Timer -->
        <div class="call-timer font-mono text-sm font-bold text-slate-300 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
          <span>Duração: {{ formattedDuration }}</span>
        </div>

        <!-- Controls Toolbar -->
        <div class="flex items-center gap-3">
          <!-- Audio Toggle -->
          <button
            type="button"
            class="p-3 rounded-full font-bold transition cursor-pointer"
            :class="isLocalAudioMuted ? 'bg-red-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            :aria-label="isLocalAudioMuted ? 'Ativar microfone' : 'Desativar microfone'"
            :title="isLocalAudioMuted ? 'Ativar microfone' : 'Desativar microfone'"
            @click="emit('toggle-audio')"
          >
            {{ isLocalAudioMuted ? '🔇' : '🎙️' }}
          </button>

          <!-- Video Toggle -->
          <button
            type="button"
            class="p-3 rounded-full font-bold transition cursor-pointer"
            :class="isLocalVideoMuted ? 'bg-red-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            :aria-label="isLocalVideoMuted ? 'Ativar câmera' : 'Desativar câmera'"
            :title="isLocalVideoMuted ? 'Ativar câmera' : 'Desativar câmera'"
            @click="emit('toggle-video')"
          >
            {{ isLocalVideoMuted ? '🚫' : '📹' }}
          </button>

          <!-- Screen Share Toggle -->
          <button
            type="button"
            class="p-3 rounded-full font-bold transition cursor-pointer"
            :class="isScreenSharing ? 'bg-indigo-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            aria-label="Compartilhar tela"
            title="Compartilhar tela com atendido"
            @click="emit('toggle-screenshare')"
          >
            🖥️
          </button>

          <!-- Toggle Chat -->
          <button
            type="button"
            class="p-3 rounded-full font-bold transition cursor-pointer"
            :class="showChat ? 'bg-sky-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            aria-label="Alternar painel de mensagens"
            title="Abrir ou fechar chat"
            @click="showChat = !showChat"
          >
            💬
          </button>

          <!-- End Call Button -->
          <button
            id="btnEndCall"
            type="button"
            class="px-5 py-2.5 rounded-full bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold flex items-center gap-2 shadow-lg shadow-red-600/30 transition cursor-pointer"
            aria-label="Encerrar Atendimento"
            @click="handleEndCall"
          >
            <span>📞 Encerrar Atendimento</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  roomId: {
    type: String,
    default: '',
  },
  localUserName: {
    type: String,
    default: 'Técnico Social',
  },
  remoteUserName: {
    type: String,
    default: 'Atendido / Egresso',
  },
  isLocalAudioMuted: {
    type: Boolean,
    default: false,
  },
  isLocalVideoMuted: {
    type: Boolean,
    default: false,
  },
  isRemoteMuted: {
    type: Boolean,
    default: false,
  },
  isScreenSharing: {
    type: Boolean,
    default: false,
  },
  telemetry: {
    type: Object,
    default: () => ({
      mos: 4.2,
      quality_tier: 'Excelente (4G/Wi-Fi)',
      rtt_ms: 45,
      jitter_ms: 8,
      packet_loss_pct: 0.2,
    }),
  },
});

const emit = defineEmits([
  'close',
  'end-call',
  'toggle-audio',
  'toggle-video',
  'toggle-screenshare',
  'send-chat',
]);

const localVideoRef = ref(null);
const remoteVideoRef = ref(null);
const showChat = ref(false);
const chatInput = ref('');
const chatMessages = ref([
  { sender: 'Sistema', time: '14:20', text: 'Sessão criptografada iniciada com sucesso.', isSelf: false },
]);

const callDurationSeconds = ref(0);
let durationTimer = null;

const formattedDuration = computed(() => {
  const m = Math.floor(callDurationSeconds.value / 60).toString().padStart(2, '0');
  const s = (callDurationSeconds.value % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
});

const telemetryBadgeClass = computed(() => {
  const mos = props.telemetry.mos || 4.0;
  if (mos >= 4.0) return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
  if (mos >= 3.2) return 'bg-sky-500/20 text-sky-300 border-sky-500/40';
  if (mos >= 2.5) return 'bg-amber-500/20 text-amber-300 border-amber-500/40';
  return 'bg-red-500/20 text-red-300 border-red-500/40';
});

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    callDurationSeconds.value = 0;
    if (durationTimer) clearInterval(durationTimer);
    durationTimer = setInterval(() => {
      callDurationSeconds.value += 1;
    }, 1000);
  } else {
    if (durationTimer) clearInterval(durationTimer);
  }
});

onBeforeUnmount(() => {
  if (durationTimer) clearInterval(durationTimer);
});

const handleClose = () => {
  emit('close');
};

const handleEndCall = () => {
  emit('end-call', { durationSeconds: callDurationSeconds.value });
};

const handleSendMessage = () => {
  if (!chatInput.value.trim()) return;
  const now = new Date();
  const timeStr = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
  chatMessages.value.push({
    sender: props.localUserName || 'Você',
    time: timeStr,
    text: chatInput.value.trim(),
    isSelf: true,
  });
  emit('send-chat', chatInput.value.trim());
  chatInput.value = '';
};

defineExpose({
  localVideoRef,
  remoteVideoRef,
});
</script>
