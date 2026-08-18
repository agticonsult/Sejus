<template>
  <div class="qr-code-display flex flex-col items-center justify-center p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
    <div class="qr-canvas-wrapper relative p-2 bg-white rounded-lg border border-slate-100 flex items-center justify-center">
      <canvas
        ref="canvasRef"
        :id="id"
        class="block rounded"
        role="img"
        :aria-label="alt"
      ></canvas>

      <!-- Center Logo Watermark Badge -->
      <div
        v-if="showWatermark"
        class="absolute inset-0 m-auto w-8 h-8 rounded-full bg-white/95 border border-sky-600 flex items-center justify-center shadow-md pointer-events-none"
      >
        <div class="w-4 h-5 rounded overflow-hidden flex flex-col shadow-xs" aria-hidden="true">
          <span class="h-1/3 bg-[#e63946]"></span>
          <span class="h-1/3 bg-[#ffffff]"></span>
          <span class="h-1/3 bg-[#003366]"></span>
        </div>
      </div>
    </div>

    <!-- Token Fingerprint & Security Tag -->
    <div v-if="showFingerprint && value" class="mt-2.5 text-center max-w-[220px]">
      <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Selo HMAC-SHA256</span>
      <span class="text-[11px] font-mono text-slate-700 break-all font-semibold block bg-slate-50 px-2 py-0.5 rounded border border-slate-200">
        {{ formattedFingerprint }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
  value: {
    type: String,
    required: true,
  },
  id: {
    type: String,
    default: 'carteiraQrCode',
  },
  alt: {
    type: String,
    default: 'QR Code criptográfico para validação da Carteira Digital do Egresso',
  },
  size: {
    type: Number,
    default: 180,
  },
  showWatermark: {
    type: Boolean,
    default: true,
  },
  showFingerprint: {
    type: Boolean,
    default: true,
  },
});

const canvasRef = ref(null);

const formattedFingerprint = computed(() => {
  if (!props.value) return '---';
  const parts = props.value.split('.');
  const sig = parts.length === 2 ? parts[1] : props.value;
  return sig.slice(0, 16).toUpperCase() + '...';
});

const renderQrCode = async () => {
  if (!canvasRef.value || !props.value) return;
  try {
    await QRCode.toCanvas(canvasRef.value, props.value, {
      width: props.size,
      margin: 1,
      color: {
        dark: '#003366',
        light: '#ffffff',
      },
      errorCorrectionLevel: 'H',
    });
  } catch (err) {
    console.error('Failed to render QR Code:', err);
  }
};

onMounted(() => {
  renderQrCode();
});

watch([() => props.value, () => props.size], () => {
  renderQrCode();
});
</script>
