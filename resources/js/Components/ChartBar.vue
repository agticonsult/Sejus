<template>
  <div class="chart-bar-container relative w-full" :style="{ height: `${height}px` }">
    <canvas
      ref="canvasRef"
      :id="id"
      class="w-full h-full block"
      role="img"
      :aria-label="ariaLabel || 'Gráfico de barras de distribuição por município'"
    ></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
  id: {
    type: String,
    default: 'chartMunicipios',
  },
  height: {
    type: Number,
    default: 230,
  },
  data: {
    type: Array,
    default: () => [
      { label: 'Vitória', val: 3420, color: '#003366' },
      { label: 'Serra', val: 2910, color: '#0284c7' },
      { label: 'Vila Velha', val: 2450, color: '#38bdf8' },
      { label: 'Cariacica', val: 2100, color: '#10b981' },
      { label: 'Linhares*', val: 1150, color: '#8b5cf6' },
      { label: 'Cachoeiro*', val: 980, color: '#f59e0b' },
      { label: 'Colatina*', val: 740, color: '#ec4899' },
      { label: 'São Mateus*', val: 610, color: '#14b8a6' },
    ],
  },
  maxVal: {
    type: Number,
    default: 4000,
  },
  ariaLabel: {
    type: String,
    default: 'Distribuição dos atendimentos por município polo do Espírito Santo',
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

const render = () => {
  const canvas = canvasRef.value;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const rect = canvas.parentElement ? canvas.parentElement.getBoundingClientRect() : { width: 600, height: props.height };
  const width = Math.max(300, rect.width);
  const height = props.height;

  // Sharp rendering on retina/high-DPI screens
  const dpr = window.devicePixelRatio || 1;
  canvas.width = width * dpr;
  canvas.height = height * dpr;
  ctx.scale(dpr, dpr);

  const dark = checkIsDark();
  const data = props.data;
  const maxVal = props.maxVal || Math.max(...data.map(d => d.val), 100);
  const paddingLeft = 45;
  const paddingRight = 15;
  const paddingTop = 25;
  const paddingBottom = 35;

  const chartW = width - paddingLeft - paddingRight;
  const chartH = height - paddingTop - paddingBottom;
  const barWidth = chartW / data.length;

  ctx.clearRect(0, 0, width, height);

  // Draw horizontal gridlines and axis values
  ctx.strokeStyle = dark ? '#334155' : '#e2e8f0';
  ctx.lineWidth = 1;
  const steps = 4;
  for (let i = 0; i <= steps; i++) {
    const y = paddingTop + chartH - (i * chartH / steps);
    ctx.beginPath();
    ctx.moveTo(paddingLeft, y);
    ctx.lineTo(width - paddingRight, y);
    ctx.stroke();

    ctx.fillStyle = '#94a3b8';
    ctx.font = '10px Inter, sans-serif';
    ctx.textAlign = 'right';
    const valText = Math.round((maxVal / steps) * i).toLocaleString('pt-BR');
    ctx.fillText(valText, paddingLeft - 8, y + 3);
  }

  // Draw vertical bars
  data.forEach((item, index) => {
    const x = paddingLeft + index * barWidth;
    const barH = (item.val / maxVal) * chartH;
    const y = paddingTop + chartH - barH;
    const bW = Math.max(8, barWidth - 14);
    const bX = x + (barWidth - bW) / 2;

    // In dark mode, replace low-contrast deep navy (#003366 -> 1.42:1) with vibrant accessible sky-400 (#38bdf8 -> 9.36:1)
    let barColor = item.color || '#0284c7';
    if (dark) {
      if (item.darkColor) {
        barColor = item.darkColor;
      } else if (barColor.toLowerCase() === '#003366') {
        barColor = '#38bdf8';
      }
    }

    ctx.fillStyle = barColor;
    ctx.beginPath();
    if (ctx.roundRect) {
      ctx.roundRect(bX, y, bW, barH, [4, 4, 0, 0]);
    } else {
      ctx.rect(bX, y, bW, barH);
    }
    ctx.fill();

    // Value on top of bar
    ctx.fillStyle = dark ? '#f1f5f9' : '#1e293b';
    ctx.font = 'bold 10px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(item.val.toLocaleString('pt-BR'), bX + bW / 2, Math.max(12, y - 6));

    // Label at bottom
    ctx.fillStyle = dark ? '#cbd5e1' : '#64748b';
    ctx.font = '10px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(item.label, bX + bW / 2, height - 12);
  });
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
