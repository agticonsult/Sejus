<template>
  <nav
    class="accessibility-toolbar flex items-center gap-1 p-1 bg-slate-100/80 rounded-xl border border-slate-200/70 shadow-2xs backdrop-blur-xs"
    role="region"
    aria-label="Barra de Ferramentas de Acessibilidade"
  >
    <!-- Dark Mode Toggle Button -->
    <button
      id="darkModeBtn"
      type="button"
      class="a11y-btn flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
      :class="isDarkMode && !highContrast ? 'bg-slate-800 text-sky-300 ring-1 ring-sky-500/40 font-bold' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
      :aria-pressed="isDarkMode"
      aria-label="Alternar Modo Escuro"
      title="Ativar ou desativar Modo Escuro Institucional"
      @click="handleToggleDarkMode"
    >
      <Moon v-if="!isDarkMode" class="w-3.5 h-3.5 stroke-[1.75]" />
      <Sun v-else class="w-3.5 h-3.5 stroke-[1.75] text-amber-300" />
      <span v-if="showLabels" class="hidden sm:inline">{{ isDarkMode ? 'Modo Claro' : 'Modo Escuro' }}</span>
    </button>

    <!-- High Contrast Toggle Button -->
    <button
      id="contrastBtn"
      type="button"
      class="a11y-btn flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
      :class="highContrast ? 'bg-black text-yellow-300 ring-2 ring-yellow-400 font-bold' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
      :aria-pressed="highContrast"
      aria-label="Alternar Modo Alto Contraste"
      title="Ativar ou desativar modo de Alto Contraste (WCAG AAA)"
      @click="handleToggleContrast"
    >
      <Contrast class="w-3.5 h-3.5 stroke-[1.75]" />
      <span v-if="showLabels" class="hidden sm:inline">Alto Contraste</span>
    </button>

    <!-- Font Zoom Controls (A-, A+, Reset) -->
    <div class="flex items-center gap-0.5 border-l border-slate-200/80 pl-1" role="group" aria-label="Controle de tamanho do texto">
      <!-- Zoom Out (A-) -->
      <button
        id="fontZoomOutBtn"
        type="button"
        class="a11y-btn p-1.5 rounded-lg text-xs font-medium transition cursor-pointer text-slate-600 hover:bg-white hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed"
        :disabled="fontZoom <= MIN_ZOOM"
        aria-label="Diminuir tamanho da fonte"
        title="Diminuir tamanho da fonte (-18%)"
        @click="handleZoomOut"
      >
        <ZoomOut class="w-3.5 h-3.5 stroke-[1.75]" />
      </button>

      <!-- Zoom In (A+) -->
      <button
        id="fontSizeBtn"
        type="button"
        class="a11y-btn flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer"
        :class="fontZoom > 1.00 ? 'bg-sky-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
        aria-label="Aumentar Tamanho da Fonte (+18%)"
        :title="`Aumentar tamanho da fonte (+18%). Atual: ${Math.round(fontZoom * 100)}%`"
        @click="handleZoomIn"
      >
        <ZoomIn class="w-3.5 h-3.5 stroke-[1.75]" />
        <span v-if="fontZoom > 1.00" class="text-[10px] font-mono opacity-90">+{{ Math.round((fontZoom - 1) * 100) }}%</span>
      </button>

      <!-- Reset Zoom (100%) -->
      <button
        v-if="fontZoom > 1.00"
        id="fontResetBtn"
        type="button"
        class="a11y-btn p-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-900 hover:bg-white transition cursor-pointer"
        aria-label="Redefinir tamanho da fonte para o padrão (100%)"
        title="Redefinir tamanho da fonte para 100%"
        @click="handleResetZoom"
      >
        <RotateCcw class="w-3 h-3 stroke-[2]" />
      </button>
    </div>

    <!-- Simplified Language Toggle Button -->
    <div class="border-l border-slate-200/80 pl-1">
      <button
        id="simplifiedTextBtn"
        type="button"
        class="a11y-btn flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer"
        :class="simplifiedLanguage ? 'bg-emerald-600 text-white shadow-2xs font-bold' : 'text-slate-600 hover:bg-white hover:text-slate-900'"
        :aria-pressed="simplifiedLanguage"
        aria-label="Ativar Modo Linguagem Simplificada"
        title="Ativar modo de Linguagem Fácil (vocabulário acessível para baixo letramento)"
        @click="handleToggleSimplified"
      >
        <MessageSquare class="w-3.5 h-3.5 stroke-[1.75]" />
        <span v-if="showLabels" class="hidden sm:inline">Linguagem Fácil</span>
      </button>
    </div>
  </nav>
</template>

<script setup>
import { onMounted } from 'vue';
import { Contrast, ZoomIn, ZoomOut, RotateCcw, MessageSquare, Moon, Sun } from 'lucide-vue-next';
import { useAccessibility, MIN_ZOOM, MAX_ZOOM, ZOOM_STEP } from '../Composables/useAccessibility';
import { useTheme } from '../Composables/useTheme';

const props = defineProps({
  showLabels: {
    type: Boolean,
    default: true,
  },
  floating: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['contrast-change', 'zoom-change', 'simplified-change', 'theme-change']);

const { isDarkMode, toggleDarkMode, applyTheme, currentTheme } = useTheme();

const {
  highContrast,
  fontZoom,
  simplifiedLanguage,
  initAccessibility,
  toggleHighContrast,
  zoomIn,
  zoomOut,
  resetZoom,
  toggleSimplifiedLanguage,
} = useAccessibility();

onMounted(() => {
  initAccessibility();
});

const handleToggleContrast = () => {
  const active = toggleHighContrast();
  emit('contrast-change', active);
};

const handleToggleDarkMode = () => {
  const active = toggleDarkMode();
  emit('theme-change', active);
};

const handleZoomIn = () => {
  const current = zoomIn();
  emit('zoom-change', current);
};

const handleZoomOut = () => {
  const current = zoomOut();
  emit('zoom-change', current);
};

const handleResetZoom = () => {
  const current = resetZoom();
  emit('zoom-change', current);
};

const handleToggleSimplified = () => {
  const active = toggleSimplifiedLanguage();
  emit('simplified-change', active);
};
</script>
