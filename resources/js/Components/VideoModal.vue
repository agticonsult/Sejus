<template>
  <div
    v-if="isOpen"
    ref="modalRootRef"
    id="videoModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 backdrop-blur-md animate-fade-in"
    :class="isFullscreenMode ? 'p-0' : 'p-1.5 md:p-3'"
    role="dialog"
    aria-modal="true"
    aria-labelledby="videoModalTitle"
    tabindex="-1"
    @keydown.esc="handleEscKey"
  >
    <div
      class="video-modal-content bg-slate-900 text-white shadow-2xl overflow-hidden flex flex-col transition-all duration-200"
      :class="isFullscreenMode
        ? 'w-screen h-screen max-w-none max-h-none rounded-none border-0'
        : 'w-[96vw] max-w-[1720px] h-[92vh] max-h-[92vh] rounded-2xl border border-slate-700/90'"
    >
      <!-- Header -->
      <div class="modal-header px-4 md:px-6 py-3 bg-slate-800/95 border-b border-slate-700 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-3 h-3 rounded-full bg-emerald-500 flex-shrink-0"></div>
          <div class="min-w-0">
            <h2 id="videoModalTitle" class="text-sm md:text-base font-extrabold text-white leading-tight truncate">
              Atendimento Remoto Seguro — Sala #{{ roomId || 'SEJUS-ES-2026' }}
            </h2>
            <div class="flex flex-wrap items-center gap-2 mt-0.5">
              <span class="text-[11px] text-slate-300 font-medium">WebRTC E2E</span>
              <span v-if="isScreenSharing" class="text-[10px] font-bold px-2 py-0.5 rounded bg-indigo-500/30 text-indigo-200 border border-indigo-400/40">
                🖥️ Compartilhando Tela
              </span>
              <template v-if="isStaff">
                <span class="text-slate-600">•</span>
                <button
                  type="button"
                  @click="copyInviteLink"
                  class="text-[11px] bg-sky-600 hover:bg-sky-500 text-white font-bold px-2.5 py-0.5 rounded-md flex items-center gap-1 transition cursor-pointer"
                  title="Copiar link para enviar ao egresso ou familiar"
                >
                  <span>🔗 {{ inviteCopied ? 'Link Copiado!' : 'Copiar Link para Convidar' }}</span>
                </button>
              </template>
            </div>
          </div>
        </div>

        <!-- View Mode & Telemetry Controls -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Layout Switcher -->
          <div class="hidden sm:flex items-center bg-slate-900/90 border border-slate-700 rounded-xl p-0.5 text-[11px] font-bold">
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg transition cursor-pointer"
              :class="viewLayout === 'stage' ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-300 hover:text-white'"
              title="Vídeo principal ampliado com câmera secundária flutuante"
              @click="viewLayout = 'stage'"
            >
              🖼️ Destaque
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg transition cursor-pointer"
              :class="viewLayout === 'split' ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-300 hover:text-white'"
              title="Dividir tela igualmente 50/50"
              @click="viewLayout = 'split'"
            >
              ◫ Lado a Lado
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded-lg transition cursor-pointer"
              :class="viewLayout === 'focus' ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-300 hover:text-white'"
              title="Foco total sem câmera sobreposta"
              @click="viewLayout = 'focus'"
            >
              🎯 Foco Total
            </button>
          </div>

          <button
            type="button"
            class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold border transition cursor-pointer"
            :class="fitContain ? 'bg-indigo-600/30 border-indigo-400/50 text-indigo-200' : 'bg-slate-700/70 border-slate-600 text-slate-200 hover:bg-slate-700'"
            :title="fitContain ? 'Exibir vídeo inteiro sem cortes (recomendado para compartilhamento de tela)' : 'Preencher quadro completo'"
            @click="fitContain = !fitContain"
          >
            {{ fitContain ? '📐 Ajustar Tela Inteira' : '⛶ Preencher Quadro' }}
          </button>

          <button
            type="button"
            class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold bg-slate-700/70 hover:bg-slate-700 border border-slate-600 text-slate-100 transition cursor-pointer"
            title="Alternar quem aparece no vídeo principal"
            @click="isLocalMain = !isLocalMain"
          >
            ⇄ Inverter
          </button>

          <!-- Telemetry Signal Meter Badge -->
          <div
            class="telemetry-badge hidden xl:flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold border transition"
            :class="telemetryBadgeClass"
            :title="`MOS: ${safeTelemetry.mos.toFixed(1)} | RTT: ${safeTelemetry.rtt_ms}ms | Jitter: ${safeTelemetry.jitter_ms}ms | Perda: ${safeTelemetry.packet_loss_pct.toFixed(1)}%`"
          >
            <span class="signal-bars flex items-end gap-0.5 h-3">
              <span class="w-1 bg-current rounded-xs" :class="safeTelemetry.mos >= 2.5 ? 'h-1.5' : 'h-1'"></span>
              <span class="w-1 bg-current rounded-xs" :class="safeTelemetry.mos >= 3.2 ? 'h-2.5' : 'h-1 opacity-30'"></span>
              <span class="w-1 bg-current rounded-xs" :class="safeTelemetry.mos >= 4.0 ? 'h-3.5' : 'h-1 opacity-30'"></span>
            </span>
            <span>MOS {{ safeTelemetry.mos.toFixed(1) }} ({{ safeTelemetry.quality_tier }})</span>
          </div>

          <button
            type="button"
            class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer border"
            :class="isFullscreenMode ? 'bg-emerald-600/30 border-emerald-400/50 text-emerald-200' : 'bg-slate-700/80 border-slate-600 text-slate-200 hover:text-white'"
            :title="isFullscreenMode ? 'Sair da Tela Cheia' : 'Expandir para Tela Cheia'"
            @click="toggleFullscreen"
          >
            {{ isFullscreenMode ? '🗗 Reduzir' : '⛶ Tela Cheia' }}
          </button>

          <button
            type="button"
            class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-700 transition cursor-pointer"
            aria-label="Fechar janela"
            @click="handleClose"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Network Degradation Alert Banner -->
      <div
        v-if="safeTelemetry.mos < 3.2 && safeTelemetry.mos > 0"
        class="bg-amber-500/20 border-b border-amber-500/40 text-amber-200 px-6 py-2 text-xs flex items-center justify-between"
      >
        <span>⚠️ Conexão instável (MOS {{ safeTelemetry.mos.toFixed(1) }}). Sugerimos desativar o vídeo para priorizar o áudio com nitidez.</span>
        <button
          type="button"
          class="underline font-bold hover:text-white ml-3 cursor-pointer"
          @click="emit('toggle-video', true)"
        >
          Desativar Vídeo
        </button>
      </div>

      <!-- Main Body: Dynamic Stage Video Grid & Didactic Side Panel -->
      <div class="modal-body flex-1 flex flex-col lg:flex-row min-h-0 overflow-hidden">
        <!-- Video Grid -->
        <div class="video-grid flex-1 p-2.5 md:p-4 bg-slate-950 relative flex items-center justify-center min-h-0">
          <div
            class="w-full h-full min-h-0"
            :class="viewLayout === 'split'
              ? 'grid grid-cols-1 lg:grid-cols-2 gap-3 md:gap-4'
              : 'relative rounded-xl overflow-hidden bg-slate-900'"
          >
            <!-- Remote Participant Video -->
            <div
              class="remote-video-box bg-slate-900 rounded-xl overflow-hidden border border-slate-700/80 flex items-center justify-center transition-all duration-200"
              :class="remoteBoxClass"
              @click="handleBoxClick('remote')"
            >
              <video
                ref="remoteVideoRef"
                id="remoteVideo"
                autoplay
                playsinline
                class="w-full h-full bg-black"
                :class="fitContain ? 'object-contain' : 'object-cover'"
              ></video>

              <!-- Waiting overlay if remote participant not connected yet -->
              <div
                v-if="!hasRemoteStream"
                class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center bg-slate-900/92 z-10"
              >
                <div class="w-16 h-16 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-3xl mb-3 shadow-inner">
                  👤
                </div>
                <h3 class="text-sm md:text-base font-bold text-white mb-1">Aguardando o outro participante entrar...</h3>
                <p class="text-xs text-slate-400 max-w-md mb-4">
                  Assim que o cidadão ou técnico acessar a sala, o vídeo e o compartilhamento de tela aparecerão automaticamente em destaque aqui.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                  <button
                    type="button"
                    @click.stop="copyInviteLink"
                    class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                  >
                    <span>🔗 {{ inviteCopied ? 'Link Copiado!' : 'Copiar Link da Sala WebRTC' }}</span>
                  </button>
                  <button
                    v-if="isStaff"
                    type="button"
                    @click.stop="emit('open-meet')"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                  >
                    <span>🎥 Abrir Contingência Google Meet</span>
                  </button>
                </div>
              </div>

              <div class="absolute bottom-3 left-3 bg-slate-950/75 backdrop-blur-md px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-2 z-20 border border-white/10">
                <span class="w-2 h-2 rounded-full" :class="hasRemoteStream ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                <span>{{ remoteUserName || 'Atendido / Egresso' }}</span>
                <span v-if="isRemoteMuted" class="text-red-400">🔇</span>
              </div>

              <div
                v-if="viewLayout === 'stage' && isLocalMain"
                class="absolute top-2 right-2 bg-black/70 text-[10px] text-sky-300 font-bold px-2 py-0.5 rounded z-20"
              >
                Clique para ampliar
              </div>
            </div>

            <!-- Local Participant Video -->
            <div
              class="local-video-box bg-slate-900 rounded-xl overflow-hidden border border-slate-700/80 flex items-center justify-center transition-all duration-200"
              :class="localBoxClass"
              @click="handleBoxClick('local')"
            >
              <video
                ref="localVideoRef"
                id="localVideo"
                autoplay
                playsinline
                muted
                class="w-full h-full bg-black"
                :class="(fitContain || isScreenSharing) ? 'object-contain' : 'object-cover'"
              ></video>
              <div class="absolute bottom-3 left-3 bg-slate-950/75 backdrop-blur-md px-3 py-1.5 rounded-lg text-xs font-semibold text-white flex items-center gap-2 z-20 border border-white/10">
                <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                <span>Você ({{ localUserName || 'Técnico Social' }})</span>
                <span v-if="isScreenSharing" class="text-indigo-300 font-bold">• Tela</span>
                <span v-if="isLocalAudioMuted" class="text-red-400">🔇</span>
              </div>

              <div
                v-if="viewLayout === 'stage' && !isLocalMain"
                class="absolute top-2 right-2 bg-black/70 text-[10px] text-sky-300 font-bold px-2 py-0.5 rounded z-20"
              >
                Clique para ampliar
              </div>
            </div>
          </div>
        </div>

        <!-- In-Call Didactic Side Panel (Chat & Interactive Script) -->
        <div
          v-if="showChat"
          class="chat-panel w-full lg:w-96 bg-slate-800/95 border-t lg:border-t-0 lg:border-l border-slate-700 flex flex-col min-h-0 max-h-[42vh] lg:max-h-none"
        >
          <!-- Panel Tabs -->
          <div class="p-2 border-b border-slate-700 grid grid-cols-2 gap-1.5 bg-slate-900/60">
            <button
              type="button"
              class="px-3 py-2 rounded-lg text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1.5"
              :class="activeSideTab === 'chat' ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-300 hover:bg-slate-800'"
              @click="activeSideTab = 'chat'"
            >
              <span>💬 Conversa</span>
              <span class="text-[10px] bg-slate-950/40 px-1.5 py-0.5 rounded-full">{{ chatMessages.length }}</span>
            </button>
            <button
              type="button"
              class="px-3 py-2 rounded-lg text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1.5"
              :class="activeSideTab === 'guide' ? 'bg-sky-600 text-white shadow-xs' : 'text-slate-300 hover:bg-slate-800'"
              @click="activeSideTab = 'guide'"
            >
              <span>📋 Roteiro Didático</span>
              <span class="text-[10px] bg-slate-950/40 px-1.5 py-0.5 rounded-full">{{ completedGuideCount }}/{{ guideSteps.length }}</span>
            </button>
          </div>

          <!-- Chat Tab -->
          <template v-if="activeSideTab === 'chat'">
            <div class="flex-1 p-3.5 overflow-y-auto space-y-2.5 text-xs">
              <div
                v-for="(msg, idx) in chatMessages"
                :key="idx"
                class="p-3 rounded-xl"
                :class="msg.isSelf ? 'bg-sky-600/30 border border-sky-500/40 ml-5' : 'bg-slate-700/70 border border-slate-600/40 mr-5'"
              >
                <div class="flex justify-between text-[10px] font-bold text-slate-300 mb-1">
                  <span>{{ msg.sender }}</span>
                  <span>{{ msg.time }}</span>
                </div>
                <p class="text-slate-100 leading-relaxed">{{ msg.text }}</p>
              </div>
              <div v-if="chatMessages.length === 0" class="text-center text-slate-400 py-8 text-xs">
                Nenhuma mensagem trocada ainda.
              </div>
            </div>

            <!-- Didactic Quick Messages -->
            <div class="px-3 pt-2 pb-1 border-t border-slate-700/70 bg-slate-900/40">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1.5">Mensagens Rápidas (1 clique):</span>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="(preset, idx) in quickMessages"
                  :key="idx"
                  type="button"
                  class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-700/80 hover:bg-sky-600 text-slate-200 hover:text-white transition cursor-pointer text-left"
                  @click="sendQuickMessage(preset.text)"
                >
                  {{ preset.label }}
                </button>
              </div>
            </div>

            <form @submit.prevent="handleSendMessage" class="p-3 border-t border-slate-700 flex gap-2">
              <input
                v-model="chatInput"
                type="text"
                placeholder="Digite uma orientação ou mensagem..."
                class="flex-1 bg-slate-900 border border-slate-600 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500"
              />
              <button
                type="submit"
                class="px-4 py-2 bg-sky-600 text-white rounded-xl text-xs font-bold hover:bg-sky-500 transition cursor-pointer"
              >
                Enviar
              </button>
            </form>
          </template>

          <!-- Interactive Didactic Guide Tab -->
          <div v-else class="flex-1 p-4 overflow-y-auto space-y-3 text-xs">
            <div class="bg-sky-500/10 border border-sky-500/30 rounded-xl p-3 text-sky-100 leading-relaxed">
              <strong class="block text-sky-300 mb-1">Guia Dinâmico de Teleatendimento</strong>
              Utilize este roteiro para conduzir a conversa de forma clara e acolhedora com o cidadão egressante ou familiar.
            </div>

            <label
              v-for="step in guideSteps"
              :key="step.id"
              class="flex items-start gap-2.5 p-3 rounded-xl border transition cursor-pointer"
              :class="step.done ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-100' : 'bg-slate-900/60 border-slate-700 text-slate-200 hover:border-slate-500'"
            >
              <input
                v-model="step.done"
                type="checkbox"
                class="mt-0.5 w-4 h-4 rounded text-emerald-500 focus:ring-emerald-500"
              />
              <div>
                <strong class="block font-bold">{{ step.title }}</strong>
                <span class="text-[11px] text-slate-400 block mt-0.5">{{ step.desc }}</span>
              </div>
            </label>

            <div v-if="isStaff" class="pt-1">
              <button
                type="button"
                class="w-full py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition cursor-pointer"
                @click="startDidacticScreenShare"
              >
                🖥️ Compartilhar Tela para Explicar Passo a Passo
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Call Controls -->
      <div class="modal-footer px-4 md:px-6 py-3 bg-slate-800 border-t border-slate-700 flex items-center justify-between flex-wrap gap-3">
        <!-- Call Duration Timer & Status -->
        <div class="call-timer font-mono text-sm font-bold text-slate-200 flex items-center gap-3">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
            <span>Duração: {{ formattedDuration }}</span>
          </div>
          <span class="hidden md:inline text-xs font-sans font-medium text-slate-400">
            • {{ hasRemoteStream ? 'Participante conectado' : 'Aguardando participante' }}
          </span>
        </div>

        <!-- Controls Toolbar -->
        <div class="flex flex-wrap items-center gap-2 md:gap-2.5">
          <!-- Audio Toggle -->
          <button
            type="button"
            class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5"
            :class="isLocalAudioMuted ? 'bg-red-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            :aria-label="isLocalAudioMuted ? 'Ativar microfone' : 'Desativar microfone'"
            :title="isLocalAudioMuted ? 'Ativar microfone' : 'Desativar microfone'"
            @click="emit('toggle-audio')"
          >
            <span>{{ isLocalAudioMuted ? '🔇' : '🎙️' }}</span>
            <span class="hidden sm:inline">{{ isLocalAudioMuted ? 'Mudo' : 'Microfone' }}</span>
          </button>

          <!-- Video Toggle -->
          <button
            type="button"
            class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5"
            :class="isLocalVideoMuted ? 'bg-red-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            :aria-label="isLocalVideoMuted ? 'Ativar câmera' : 'Desativar câmera'"
            :title="isLocalVideoMuted ? 'Ativar câmera' : 'Desativar câmera'"
            @click="emit('toggle-video')"
          >
            <span>{{ isLocalVideoMuted ? '🚫' : '📹' }}</span>
            <span class="hidden sm:inline">{{ isLocalVideoMuted ? 'Câmera Off' : 'Câmera' }}</span>
          </button>

          <!-- Screen Share Toggle -->
          <button
            type="button"
            class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5"
            :class="isScreenSharing ? 'bg-indigo-600 text-white ring-2 ring-indigo-300/60' : 'bg-slate-700 text-white hover:bg-slate-600'"
            aria-label="Compartilhar tela"
            title="Compartilhar tela com atendido"
            @click="emit('toggle-screenshare')"
          >
            <span>🖥️</span>
            <span class="hidden sm:inline">{{ isScreenSharing ? 'Parar Tela' : 'Compartilhar Tela' }}</span>
          </button>

          <!-- Toggle Side Chat / Didactic Guide -->
          <button
            type="button"
            class="px-3.5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5 relative"
            :class="showChat ? 'bg-sky-600 text-white' : 'bg-slate-700 text-white hover:bg-slate-600'"
            aria-label="Alternar painel de mensagens"
            title="Abrir conversa e roteiro didático"
            @click="toggleSidePanel"
          >
            <span>💬</span>
            <span class="hidden sm:inline">Chat & Roteiro</span>
            <span
              v-if="!showChat && unreadChatCount > 0"
              class="ml-1 px-1.5 py-0.5 text-[10px] rounded-full bg-amber-500 text-slate-950 font-extrabold"
            >
              {{ unreadChatCount }}
            </span>
          </button>

          <!-- End Call Button -->
          <button
            id="btnEndCall"
            type="button"
            class="px-5 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-extrabold flex items-center gap-2 shadow-lg shadow-red-600/30 transition cursor-pointer"
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
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';

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
  isStaff: {
    type: Boolean,
    default: true,
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
  localStream: {
    type: Object,
    default: null,
  },
  remoteStream: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits([
  'close',
  'end-call',
  'toggle-audio',
  'toggle-video',
  'toggle-screenshare',
  'send-chat',
  'open-meet',
]);

const modalRootRef = ref(null);
const localVideoRef = ref(null);
const remoteVideoRef = ref(null);
const hasRemoteStream = ref(false);
const showChat = ref(true);
const activeSideTab = ref('chat');
const unreadChatCount = ref(0);
const chatInput = ref('');

// Ergonomic stage layout state
const viewLayout = ref('stage'); // 'stage' | 'split' | 'focus'
const isLocalMain = ref(false);
const fitContain = ref(false);
const isFullscreenMode = ref(false);

const quickMessages = [
  { label: '👋 Ouvindo bem?', text: 'Olá! Está conseguindo me ouvir e ver bem?' },
  { label: '🖥️ Compartilhando tela', text: 'Vou compartilhar minha tela agora para explicar os detalhes com mais clareza.' },
  { label: '🪪 Documento / Carteira', text: 'Por favor, aproxime seu documento ou Carteira Digital da câmera para conferência.' },
  { label: '✅ Registrar encaminhamento', text: 'Tudo confirmado! Vou registrar o encaminhamento e as orientações no sistema.' },
];

const guideSteps = ref([
  { id: 1, title: '1. Acolhimento & Áudio/Vídeo', desc: 'Confirmar que o cidadão ouve e visualiza a equipe com clareza.', done: false },
  { id: 2, title: '2. Identidade & Carteira Digital', desc: 'Conferir nome, município de referência e situação cadastral.', done: false },
  { id: 3, title: '3. Orientação / Compartilhamento em Tela', desc: 'Apresentar vagas, cursos ou unidades CRAS/CREAS disponíveis.', done: false },
  { id: 4, title: '4. Pactuação & Evolução no Prontuário', desc: 'Combinar próximos passos e gravar registro ao encerrar.', done: false },
]);

const completedGuideCount = computed(() => guideSteps.value.filter((s) => s.done).length);

const remoteBoxClass = computed(() => {
  if (viewLayout.value === 'split') {
    return 'relative h-full min-h-[240px]';
  }
  const isMain = !isLocalMain.value;
  if (isMain) {
    return 'absolute inset-0 w-full h-full z-10';
  }
  if (viewLayout.value === 'focus') {
    return 'absolute bottom-4 right-4 w-48 aspect-video opacity-0 pointer-events-none z-0';
  }
  return 'absolute bottom-4 right-4 w-48 sm:w-64 md:w-72 aspect-video z-30 shadow-2xl ring-2 ring-white/30 hover:ring-sky-400 cursor-pointer';
});

const localBoxClass = computed(() => {
  if (viewLayout.value === 'split') {
    return 'relative h-full min-h-[240px]';
  }
  const isMain = isLocalMain.value;
  if (isMain) {
    return 'absolute inset-0 w-full h-full z-10';
  }
  if (viewLayout.value === 'focus') {
    return 'absolute bottom-4 right-4 w-48 aspect-video opacity-0 pointer-events-none z-0';
  }
  return 'absolute bottom-4 right-4 w-48 sm:w-64 md:w-72 aspect-video z-30 shadow-2xl ring-2 ring-white/30 hover:ring-sky-400 cursor-pointer';
});

const handleBoxClick = (which) => {
  if (viewLayout.value !== 'stage') return;
  if (which === 'local' && !isLocalMain.value) {
    isLocalMain.value = true;
  } else if (which === 'remote' && isLocalMain.value) {
    isLocalMain.value = false;
  }
};

const formatNowTime = () => {
  const now = new Date();
  return `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
};

const chatMessages = ref([
  { sender: 'Sistema', time: formatNowTime(), text: 'Sessão criptografada iniciada com sucesso.', isSelf: false },
]);

const callDurationSeconds = ref(0);
const inviteCopied = ref(false);
let durationTimer = null;

const copyInviteLink = () => {
  const url = `${window.location.origin}/atendimento?room=${props.roomId}`;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => {
      inviteCopied.value = true;
      setTimeout(() => { inviteCopied.value = false; }, 3000);
    });
  } else {
    prompt('Copie o link abaixo para enviar ao cidadão:', url);
  }
};

const toggleFullscreen = async () => {
  const next = !isFullscreenMode.value;
  isFullscreenMode.value = next;
  try {
    if (next && modalRootRef.value?.requestFullscreen && !document.fullscreenElement) {
      await modalRootRef.value.requestFullscreen();
    } else if (!next && document.fullscreenElement && document.exitFullscreen) {
      await document.exitFullscreen();
    }
  } catch (_) {
    // Fallback CSS fullscreen already active via isFullscreenMode
  }
};

const onFullscreenChange = () => {
  if (!document.fullscreenElement && isFullscreenMode.value) {
    isFullscreenMode.value = false;
  }
};

const handleEscKey = () => {
  if (isFullscreenMode.value) {
    isFullscreenMode.value = false;
    return;
  }
  handleClose();
};

const formattedDuration = computed(() => {
  const m = Math.floor(callDurationSeconds.value / 60).toString().padStart(2, '0');
  const s = (callDurationSeconds.value % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
});

const safeTelemetry = computed(() => {
  const t = props.telemetry || {};
  const mosNum = Number(t.mos);
  const rttNum = Number(t.rtt_ms);
  const jitterNum = Number(t.jitter_ms);
  const lossNum = Number(t.packet_loss_pct);
  return {
    mos: Number.isFinite(mosNum) && mosNum > 0 ? mosNum : 4.3,
    quality_tier: t.quality_tier || 'Excelente (4G/Wi-Fi)',
    rtt_ms: Number.isFinite(rttNum) ? rttNum : 38,
    jitter_ms: Number.isFinite(jitterNum) ? jitterNum : 6,
    packet_loss_pct: Number.isFinite(lossNum) ? lossNum : 0.1,
  };
});

const telemetryBadgeClass = computed(() => {
  const mos = safeTelemetry.value.mos;
  if (mos >= 4.0) return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
  if (mos >= 3.2) return 'bg-sky-500/20 text-sky-300 border-sky-500/40';
  if (mos >= 2.5) return 'bg-amber-500/20 text-amber-300 border-amber-500/40';
  return 'bg-red-500/20 text-red-300 border-red-500/40';
});

const syncStreamToVideo = async (videoEl, stream) => {
  if (!videoEl) return;
  if (stream && videoEl.srcObject !== stream) {
    videoEl.srcObject = stream;
    try {
      await videoEl.play?.();
    } catch (_) {}
  } else if (!stream && videoEl.srcObject) {
    videoEl.srcObject = null;
  }
};

watch(() => props.isScreenSharing, (sharing) => {
  if (sharing) {
    fitContain.value = true;
    viewLayout.value = 'stage';
  }
});

watch(() => props.isOpen, async (newVal) => {
  if (newVal) {
    callDurationSeconds.value = 0;
    unreadChatCount.value = 0;
    if (durationTimer) clearInterval(durationTimer);
    durationTimer = setInterval(() => {
      callDurationSeconds.value += 1;
    }, 1000);
    await nextTick();
    if (props.localStream) await syncStreamToVideo(localVideoRef.value, props.localStream);
    if (props.remoteStream) {
      hasRemoteStream.value = true;
      await syncStreamToVideo(remoteVideoRef.value, props.remoteStream);
    }
  } else {
    hasRemoteStream.value = false;
    isFullscreenMode.value = false;
    if (document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen().catch(() => {});
    }
    if (durationTimer) clearInterval(durationTimer);
  }
});

watch(() => props.localStream, async (newStream) => {
  await nextTick();
  await syncStreamToVideo(localVideoRef.value, newStream);
});

watch(() => props.remoteStream, async (newStream) => {
  hasRemoteStream.value = Boolean(newStream);
  await nextTick();
  await syncStreamToVideo(remoteVideoRef.value, newStream);
});

onMounted(() => {
  document.addEventListener('fullscreenchange', onFullscreenChange);
});

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange);
  if (durationTimer) clearInterval(durationTimer);
});

const handleClose = () => {
  emit('close');
};

const handleEndCall = () => {
  emit('end-call', { durationSeconds: callDurationSeconds.value });
};

const toggleSidePanel = () => {
  showChat.value = !showChat.value;
  if (showChat.value) {
    unreadChatCount.value = 0;
  }
};

const sendQuickMessage = (text) => {
  chatMessages.value.push({
    sender: props.localUserName || 'Você',
    time: formatNowTime(),
    text,
    isSelf: true,
  });
  emit('send-chat', text);
};

const startDidacticScreenShare = () => {
  if (!props.isScreenSharing) {
    emit('toggle-screenshare');
  }
  sendQuickMessage('Estou compartilhando minha tela para apresentar os detalhes do atendimento.');
};

const handleSendMessage = () => {
  if (!chatInput.value.trim()) return;
  const text = chatInput.value.trim();
  chatMessages.value.push({
    sender: props.localUserName || 'Você',
    time: formatNowTime(),
    text,
    isSelf: true,
  });
  emit('send-chat', text);
  chatInput.value = '';
};

const appendChatMessage = (msg) => {
  chatMessages.value.push({
    sender: msg.sender || 'Participante',
    time: msg.time || formatNowTime(),
    text: msg.text || '',
    isSelf: Boolean(msg.isSelf),
  });
  if (!showChat.value && !msg.isSelf) {
    unreadChatCount.value += 1;
  }
};

const setLocalStream = async (stream) => {
  await nextTick();
  await syncStreamToVideo(localVideoRef.value, stream);
};

const setRemoteStream = async (stream) => {
  hasRemoteStream.value = Boolean(stream);
  await nextTick();
  await syncStreamToVideo(remoteVideoRef.value, stream);
};

defineExpose({
  localVideoRef,
  remoteVideoRef,
  hasRemoteStream,
  setLocalStream,
  setRemoteStream,
  appendChatMessage,
});
</script>
