<template>
  <div class="chart-donut-container relative w-full" :style="{ height: `${height}px` }">
    <canvas
      ref="canvasRef"
      :id="id"
      class="w-full h-full block"
      role="img"
      :aria-label="ariaLabel || 'Gráfico donut de distribuição da reintegração social'"
    ></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
  id: {
    type: String,
    default: 'chartReintegracao',
  },
  height: {
    type: Number,
    default: 230,
  },
  centerText: {
    type: String,
    default: '100%',
  },
  centerSubtext: {
    type: String,
    default: 'Efetividade',
  },
  data: {
    type: Array,
    default: () => [
      { label: 'Emprego & Renda (42%)', val: 0.42, color: '#10b981' },
      { label: 'Cursos & Capacitação (28%)', val: 0.28, color: '#8b5cf6' },
      { label: 'Apoio Psicossocial (18%)', val: 0.18, color: '#0284c7' },
      { label: 'Documentação Emitida (12%)', val: 0.12, color: '#f59e0b' },
    ],
  },
  ariaLabel: {
    type: String,
    default: 'Distribuição dos eixos de reintegração social',
  },
  isDark: {
    type: Boolean,
    default: null,
  },
});

const canvasRef = ref(null);
let resizeObserver = null;
let themeObserver = null;

const checkIsDark = () => {
  if (props.isDark !== null) return props.isDark;
  if (typeof document === 'undefined') return false;
  return document.documentElement.classList.contains('dark') ||
         document.documentElement.classList.contains('high-contrast');
};

const getEffectiveBg = (canvas, isDark) => {
  if (typeof window === 'undefined') return isDark ? '#0f172a' : '#ffffff';

  // Check CSS custom property --bg-card first if defined by active theme
  const rootStyle = window.getComputedStyle(document.documentElement);
  const bgCardVar = rootStyle.getPropertyValue('--bg-card').trim();
  if (bgCardVar && bgCardVar !== 'inherit' && bgCardVar !== 'initial') {
    return bgCardVar;
  }

  // Walk up DOM to find containing card background
  let el = canvas.parentElement;
  while (el && el !== document.body) {
    const bg = window.getComputedStyle(el).backgroundColor;
    if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') {
      return bg;
    }
    el = el.parentElement;
  }

  return isDark ? '#0f172a' : '#ffffff';
};

const render = () => {
  const canvas = canvasRef.value;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const rect = canvas.parentElement ? canvas.parentElement.getBoundingClientRect() : { width: 600, height: props.height };
  const width = Math.max(300, rect.width);
  const height = props.height;

  const dpr = window.devicePixelRatio || 1;
  canvas.width = width * dpr;
  canvas.height = height * dpr;
  ctx.scale(dpr, dpr);

  ctx.clearRect(0, 0, width, height);

  const dark = checkIsDark();
  const centerX = width < 480 ? width / 2 : width / 3.2;
  const centerY = width < 480 ? height / 2.8 : height / 2;
  const radius = Math.min(centerX - 20, centerY - 15, 75);
  const innerRadius = radius * 0.6;

  let startAngle = -Math.PI / 2;
  const slices = props.data;

  // Draw donut slices
  slices.forEach((slice) => {
    const sliceAngle = slice.val * 2 * Math.PI;

    ctx.fillStyle = slice.color;
    ctx.beginPath();
    ctx.moveTo(centerX, centerY);
    ctx.arc(centerX, centerY, radius, startAngle, startAngle + sliceAngle);
    ctx.closePath();
    ctx.fill();

    startAngle += sliceAngle;
  });

  // Inner cutout hole dynamically matched to card background
  ctx.fillStyle = getEffectiveBg(canvas, dark);
  ctx.beginPath();
  ctx.arc(centerX, centerY, innerRadius, 0, 2 * Math.PI);
  ctx.fill();

  // Center text
  ctx.fillStyle = dark ? '#f8fafc' : '#0f172a';
  ctx.font = 'bold 15px Outfit, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(props.centerText, centerX, centerY - 2);

  ctx.font = '10px Inter, sans-serif';
  ctx.fillStyle = dark ? '#94a3b8' : '#64748b';
  ctx.fillText(props.centerSubtext, centerX, centerY + 13);

  // Draw Legend (Right side on desktop, bottom on mobile)
  if (width >= 480) {
    ctx.textAlign = 'left';
    const legendX = centerX + radius + 35;
    const startY = Math.max(30, centerY - (slices.length * 32) / 2);

    slices.forEach((slice, index) => {
      const ly = startY + index * 32;

      // Color swatch circle
      ctx.fillStyle = slice.color;
      ctx.beginPath();
      ctx.arc(legendX, ly, 5.5, 0, 2 * Math.PI);
      ctx.fill();

      // Label text
      ctx.fillStyle = dark ? '#e2e8f0' : '#1e293b';
      ctx.font = 'bold 11px Inter, sans-serif';
      ctx.fillText(slice.label, legendX + 14, ly + 4);
    });
  }
};

onMounted(() => {
  render();
  if (window.ResizeObserver && canvasRef.value && canvasRef.value.parentElement) {
    resizeObserver = new ResizeObserver(() => render());
    resizeObserver.observe(canvasRef.value.parentElement);
  }

  // Observe theme / dark mode class changes on <html>
  if (typeof window !== 'undefined' && window.MutationObserver) {
    themeObserver = new MutationObserver(() => {
      render();
    });
    themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class', 'data-theme'],
    });
  }
});

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
  }
  if (themeObserver) {
    themeObserver.disconnect();
  }
});

watch(() => props.data, () => render(), { deep: true });
watch(() => props.isDark, () => render());
</script>
