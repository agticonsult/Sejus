<template>
  <aside
    id="toast-container"
    class="fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-sm w-full pointer-events-none px-4 sm:px-0"
    aria-live="polite"
    aria-label="Notificações do Sistema"
  >
    <TransitionGroup
      name="toast-slide"
      tag="div"
      class="flex flex-col gap-2.5 w-full"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast-card pointer-events-auto relative w-full rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-lg shadow-slate-900/10 p-3.5 flex items-start gap-3 transition-all duration-300 overflow-hidden"
        :class="[getBorderAccentClass(toast.type), getCardBgClass(toast.type)]"
        :role="toast.type === 'error' ? 'alert' : 'status'"
        :aria-live="toast.type === 'error' ? 'assertive' : 'polite'"
        @mouseenter="pauseToast(toast.id)"
        @mouseleave="resumeToast(toast.id)"
      >
        <!-- Icon Badge -->
        <div
          class="toast-icon-wrapper flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center shadow-2xs"
          :class="getIconWrapperClass(toast.type)"
          aria-hidden="true"
        >
          <CheckCircle v-if="toast.type === 'success'" class="w-5 h-5" />
          <AlertCircle v-else-if="toast.type === 'error'" class="w-5 h-5" />
          <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5" />
          <Info v-else class="w-5 h-5" />
        </div>

        <!-- Content Area -->
        <div class="toast-content flex-1 min-w-0 pr-1">
          <div class="flex items-center justify-between gap-1 mb-0.5">
            <h4 class="text-xs font-extrabold tracking-tight truncate" :class="getTitleClass(toast.type)">
              {{ toast.title || getDefaultTitle(toast.type) }}
            </h4>
          </div>
          <p
            v-if="toast.message"
            class="text-[11px] text-slate-600 dark:text-slate-300 leading-relaxed break-words whitespace-pre-line"
          >
            {{ toast.message }}
          </p>
        </div>

        <!-- Manual Dismiss Button -->
        <button
          type="button"
          class="toast-close-btn flex-shrink-0 p-1 -mr-1 -mt-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer min-w-[28px] min-h-[28px] flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-sky-500"
          aria-label="Fechar notificação"
          title="Fechar"
          @click.stop="removeToast(toast.id)"
        >
          <X class="w-4 h-4" />
        </button>

        <!-- Progress Indicator -->
        <div
          v-if="toast.duration > 0"
          class="absolute bottom-0 left-0 right-0 h-0.5 opacity-60 overflow-hidden"
        >
          <div
            class="h-full w-full origin-left toast-progress-bar"
            :class="getProgressClass(toast.type)"
            :style="{ animationDuration: `${toast.duration}ms` }"
          ></div>
        </div>
      </div>
    </TransitionGroup>
  </aside>
</template>

<script setup>
import { CheckCircle, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next';
import { useToast } from '../Composables/useToast';

const { toasts, removeToast, pauseToast, resumeToast } = useToast();

const getDefaultTitle = (type) => {
  switch (type) {
    case 'success': return 'Operação Realizada';
    case 'error': return 'Erro no Sistema';
    case 'warning': return 'Atenção';
    default: return 'Informação';
  }
};

const getBorderAccentClass = (type) => {
  switch (type) {
    case 'success': return 'border-l-4 border-l-emerald-600 dark:border-l-emerald-500';
    case 'error': return 'border-l-4 border-l-rose-600 dark:border-l-rose-500';
    case 'warning': return 'border-l-4 border-l-amber-500 dark:border-l-amber-400';
    default: return 'border-l-4 border-l-[#003366] dark:border-l-sky-500';
  }
};

const getCardBgClass = (type) => {
  switch (type) {
    case 'success': return 'hover:border-emerald-300 dark:hover:border-emerald-700';
    case 'error': return 'hover:border-rose-300 dark:hover:border-rose-700';
    case 'warning': return 'hover:border-amber-300 dark:hover:border-amber-700';
    default: return 'hover:border-sky-300 dark:hover:border-sky-700';
  }
};

const getIconWrapperClass = (type) => {
  switch (type) {
    case 'success':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300';
    case 'error':
      return 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300';
    case 'warning':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300';
    default:
      return 'bg-sky-100 text-[#003366] dark:bg-sky-950/80 dark:text-sky-300';
  }
};

const getTitleClass = (type) => {
  switch (type) {
    case 'success': return 'text-emerald-900 dark:text-emerald-300';
    case 'error': return 'text-rose-900 dark:text-rose-300';
    case 'warning': return 'text-amber-900 dark:text-amber-300';
    default: return 'text-slate-900 dark:text-sky-200';
  }
};

const getProgressClass = (type) => {
  switch (type) {
    case 'success': return 'bg-emerald-600 dark:bg-emerald-400';
    case 'error': return 'bg-rose-600 dark:bg-rose-400';
    case 'warning': return 'bg-amber-500 dark:bg-amber-400';
    default: return 'bg-[#003366] dark:bg-sky-400';
  }
};
</script>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-slide-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}

.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.92);
}

.toast-slide-move {
  transition: transform 0.25s ease;
}

@keyframes toastProgress {
  from {
    transform: scaleX(1);
  }
  to {
    transform: scaleX(0);
  }
}

.toast-progress-bar {
  animation: toastProgress linear forwards;
}

/* Pause progress bar animation on hover */
.toast-card:hover .toast-progress-bar {
  animation-play-state: paused;
}
</style>
