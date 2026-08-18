<template>
  <nav
    class="accessibility-toolbar flex items-center gap-1.5 p-1 bg-slate-100/90 rounded-lg border border-slate-200/80 shadow-sm"
    role="region"
    aria-label="Barra de Ferramentas de Acessibilidade"
  >
    <!-- High Contrast Toggle Button -->
    <button
      id="contrastBtn"
      type="button"
      class="a11y-btn flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-semibold transition cursor-pointer"
      :class="highContrast ? 'bg-black text-yellow-300 ring-2 ring-yellow-400' : 'text-slate-700 hover:bg-white hover:text-slate-900'"
      :aria-pressed="highContrast"
      aria-label="Alternar Modo Alto Contraste"
      title="Ativar ou desativar modo de Alto Contraste (WCAG AAA)"
      @click="handleToggleContrast"
    >
      <span aria-hidden="true" class="text-sm">👁️</span>
      <span v-if="showLabels" class="hidden sm:inline">Alto Contraste</span>
    </button>

    <!-- Font Zoom Controls (A-, A+, Reset) -->
    <div class="flex items-center gap-0.5" role="group" aria-label="Controle de tamanho do texto">
      <!-- Zoom Out (A-) -->
      <button
        id="fontZoomOutBtn"
        type="button"
        class="a11y-btn px-2 py-1.5 rounded-md text-xs font-semibold transition cursor-pointer text-slate-700 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="fontZoom <= MIN_ZOOM"
        aria-label="Diminuir tamanho da fonte"
        title="Diminuir tamanho da fonte (-18%)"
        @click="handleZoomOut"
      >
        <span aria-hidden="true">A-</span>
      </button>

      <!-- Zoom In (A+) -->
      <button
        id="fontSizeBtn"
        type="button"
        class="a11y-btn flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-bold transition cursor-pointer"
        :class="fontZoom > 1.00 ? 'bg-sky-600 text-white shadow-sm' : 'text-slate-700 hover:bg-white hover:text-slate-900'"
        aria-label="Aumentar Tamanho da Fonte (+18%)"
        :title="`Aumentar tamanho da fonte (+18%). Atual: ${Math.round(fontZoom * 100)}%`"
        @click="handleZoomIn"
      >
        <span aria-hidden="true">A+</span>
        <span v-if="fontZoom > 1.00" class="text-[10px] opacity-90">+{{ Math.round((fontZoom - 1) * 100) }}%</span>
      </button>

      <!-- Reset Zoom (100%) -->
      <button
        v-if="fontZoom > 1.00"
        id="fontResetBtn"
        type="button"
        class="a11y-btn px-1.5 py-1.5 rounded-md text-[11px] font-bold text-slate-500 hover:text-slate-800 hover:bg-white transition cursor-pointer"
        aria-label="Redefinir tamanho da fonte para o padrão (100%)"
        title="Redefinir tamanho da fonte para 100%"
        @click="handleResetZoom"
      >
        100%
      </button>
    </div>

    <!-- Simplified Language Toggle Button -->
    <button
      id="simplifiedTextBtn"
      type="button"
      class="a11y-btn flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-semibold transition cursor-pointer"
      :class="simplifiedLanguage ? 'bg-emerald-600 text-white shadow-sm ring-2 ring-emerald-400/50' : 'text-slate-700 hover:bg-white hover:text-slate-900'"
      :aria-pressed="simplifiedLanguage"
      aria-label="Ativar Modo Linguagem Simplificada"
      title="Ativar modo de Linguagem Fácil (vocabulário acessível para baixo letramento)"
      @click="handleToggleSimplified"
    >
      <span aria-hidden="true" class="text-sm">💬</span>
      <span v-if="showLabels" class="hidden sm:inline">Linguagem Fácil</span>
    </button>
  </nav>
</template>

<script setup>
import { onMounted } from 'vue';
import { useAccessibility, MIN_ZOOM, MAX_ZOOM, ZOOM_STEP } from '../Composables/useAccessibility';

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

const emit = defineEmits(['contrast-change', 'zoom-change', 'simplified-change']);

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
