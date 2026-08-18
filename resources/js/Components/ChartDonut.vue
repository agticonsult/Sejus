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
});

const canvasRef = ref(null);
let resizeObserver = null;

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

  // Inner cutout hole
  ctx.fillStyle = '#ffffff';
  ctx.beginPath();
  ctx.arc(centerX, centerY, innerRadius, 0, 2 * Math.PI);
  ctx.fill();

  // Center text
  ctx.fillStyle = '#0f172a';
  ctx.font = 'bold 15px Outfit, sans-serif';
  ctx.textAlign = 'center';
  ctx.fillText(props.centerText, centerX, centerY - 2);

  ctx.font = '10px Inter, sans-serif';
  ctx.fillStyle = '#64748b';
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
      ctx.fillStyle = '#1e293b';
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
});

onBeforeUnmount(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
  }
});

watch(() => props.data, () => render(), { deep: true });
</script>
