/**
 * Reactive Toast Notification Composable for CONECTA EGRESSO (SEJUS/ES)
 * Conforms to WCAG 2.1 AA / AAA and institutional styling standards.
 */
import { ref } from 'vue';

// Shared singleton reactive state across all component instances
export const toasts = ref([]);
let nextId = 1;

/**
 * Default toast duration in milliseconds
 */
export const DEFAULT_TOAST_DURATION = 4500;

/**
 * Remove a toast by id
 * @param {number} id
 */
export function removeToast(id) {
  const index = toasts.value.findIndex((t) => t.id === id);
  if (index !== -1) {
    const toast = toasts.value[index];
    if (toast._timer) {
      clearTimeout(toast._timer);
    }
    toasts.value.splice(index, 1);
  }
}

/**
 * Pause auto-dismiss timer on mouse hover
 * @param {number} id
 */
export function pauseToast(id) {
  const toast = toasts.value.find((t) => t.id === id);
  if (toast && toast._timer) {
    clearTimeout(toast._timer);
    toast._timer = null;
    if (toast.remaining && toast._startedAt) {
      const elapsed = Date.now() - toast._startedAt;
      toast.remaining = Math.max(500, toast.remaining - elapsed);
    }
  }
}

/**
 * Resume auto-dismiss timer on mouse leave
 * @param {number} id
 */
export function resumeToast(id) {
  const toast = toasts.value.find((t) => t.id === id);
  if (toast && !toast._timer && toast.remaining > 0) {
    toast._startedAt = Date.now();
    toast._timer = setTimeout(() => {
      removeToast(id);
    }, toast.remaining);
  }
}

/**
 * Add a new toast notification
 * @param {Object} toastParams
 * @returns {number} toast id
 */
export function addToast({
  type = 'info',
  title = '',
  message = '',
  duration = DEFAULT_TOAST_DURATION,
}) {
  const id = nextId++;
  const toastDuration = typeof duration === 'number' ? duration : DEFAULT_TOAST_DURATION;

  const validTypes = ['success', 'error', 'warning', 'info'];
  const safeType = validTypes.includes(type) ? type : 'info';

  const newToast = {
    id,
    type: safeType,
    title: String(title || ''),
    message: String(message || ''),
    duration: toastDuration,
    remaining: toastDuration,
    _startedAt: Date.now(),
    _timer: null,
    createdAt: new Date(),
  };

  // Schedule auto-dismiss if duration > 0
  if (toastDuration > 0) {
    newToast._timer = setTimeout(() => {
      removeToast(id);
    }, toastDuration);
  }

  toasts.value.push(newToast);

  // Maximum concurrent visible toasts to prevent viewport clutter
  if (toasts.value.length > 5) {
    const oldest = toasts.value[0];
    removeToast(oldest.id);
  }

  return id;
}

/**
 * Clear all active toasts
 */
export function clearAll() {
  toasts.value.forEach((t) => {
    if (t._timer) clearTimeout(t._timer);
  });
  toasts.value = [];
}

/**
 * Normalizes title, message and duration parameters across multiple call signatures
 */
function normalizeArgs(title, messageOrOptions, durationOrOptions, defaultDuration = DEFAULT_TOAST_DURATION) {
  let message = '';
  let duration = defaultDuration;

  if (typeof messageOrOptions === 'object' && messageOrOptions !== null) {
    message = messageOrOptions.message || '';
    duration = messageOrOptions.duration !== undefined ? messageOrOptions.duration : defaultDuration;
  } else {
    message = messageOrOptions || '';
    if (typeof durationOrOptions === 'object' && durationOrOptions !== null) {
      duration = durationOrOptions.duration !== undefined ? durationOrOptions.duration : defaultDuration;
    } else if (typeof durationOrOptions === 'number') {
      duration = durationOrOptions;
    }
  }

  return { title, message, duration };
}

/**
 * Convenience helper methods
 */
export function success(title, message, duration) {
  const params = normalizeArgs(title, message, duration, DEFAULT_TOAST_DURATION);
  return addToast({ type: 'success', ...params });
}

export function error(title, message, duration) {
  const params = normalizeArgs(title, message, duration, 6000); // errors displayed slightly longer
  return addToast({ type: 'error', ...params });
}

export function warning(title, message, duration) {
  const params = normalizeArgs(title, message, duration, 5000);
  return addToast({ type: 'warning', ...params });
}

export function info(title, message, duration) {
  const params = normalizeArgs(title, message, duration, DEFAULT_TOAST_DURATION);
  return addToast({ type: 'info', ...params });
}

/**
 * Vue Composable Hook
 */
export function useToast() {
  return {
    toasts,
    addToast,
    removeToast,
    pauseToast,
    resumeToast,
    clearAll,
    success,
    error,
    warning,
    info,
  };
}

export default useToast;
