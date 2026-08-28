/**
 * Unit Test for useToast Composable & Notification Store
 * Conecta Egresso (SEJUS/ES) - Milestone 1
 */

import { describe, it, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import {
  toasts,
  useToast,
  addToast,
  removeToast,
  pauseToast,
  resumeToast,
  clearAll,
  success,
  error,
  warning,
  info,
  DEFAULT_TOAST_DURATION,
} from '../../resources/js/Composables/useToast.js';

describe('useToast Composable & Reactive Store', () => {
  beforeEach(() => {
    clearAll();
  });

  it('initializes with empty toasts array', () => {
    assert.equal(toasts.value.length, 0);
  });

  it('adds success toast with correct structure and type', () => {
    const id = success('Registro Salvo', 'Prontuário atualizado com sucesso.');
    assert.equal(typeof id, 'number');
    assert.equal(toasts.value.length, 1);
    const t = toasts.value[0];
    assert.equal(t.type, 'success');
    assert.equal(t.title, 'Registro Salvo');
    assert.equal(t.message, 'Prontuário atualizado com sucesso.');
    assert.equal(t.duration, DEFAULT_TOAST_DURATION);
  });

  it('adds error toast with longer default duration', () => {
    const id = error('Falha na Operação', 'Não foi possível conectar ao servidor.');
    assert.equal(toasts.value.length, 1);
    const t = toasts.value[0];
    assert.equal(t.type, 'error');
    assert.equal(t.duration, 6000);
  });

  it('adds warning toast with correct type', () => {
    const id = warning('Atenção', 'A sessão expirará em 5 minutos.');
    assert.equal(toasts.value.length, 1);
    const t = toasts.value[0];
    assert.equal(t.type, 'warning');
    assert.equal(t.duration, 5000);
  });

  it('adds info toast with options object parameter', () => {
    const id = info('Atualização', { message: 'Novo documento disponível.', duration: 3000 });
    assert.equal(toasts.value.length, 1);
    const t = toasts.value[0];
    assert.equal(t.type, 'info');
    assert.equal(t.title, 'Atualização');
    assert.equal(t.message, 'Novo documento disponível.');
    assert.equal(t.duration, 3000);
  });

  it('removes a toast by ID cleanly', () => {
    const id1 = success('Toast 1');
    const id2 = success('Toast 2');
    const id3 = success('Toast 3');
    assert.equal(toasts.value.length, 3);

    removeToast(id2);
    assert.equal(toasts.value.length, 2);
    assert.equal(toasts.value.some((t) => t.id === id2), false);
    assert.equal(toasts.value[0].id, id1);
    assert.equal(toasts.value[1].id, id3);
  });

  it('pauses and resumes toast timers correctly', () => {
    const id = success('Pausable Toast', 'Testing hover pause');
    const t = toasts.value[0];
    assert.ok(t._timer !== null);

    pauseToast(id);
    assert.equal(t._timer, null);

    resumeToast(id);
    assert.ok(t._timer !== null);
  });

  it('clearAll removes all toasts and cancels timers', () => {
    success('T1');
    error('T2');
    warning('T3');
    info('T4');
    assert.equal(toasts.value.length, 4);

    clearAll();
    assert.equal(toasts.value.length, 0);
  });

  it('caps visible toasts at maximum capacity to prevent screen clutter', () => {
    for (let i = 1; i <= 10; i++) {
      success(`Toast ${i}`);
    }
    assert.ok(toasts.value.length <= 6);
  });

  it('useToast hook returns all expected methods and reactive state', () => {
    const toast = useToast();
    assert.ok(toast.toasts);
    assert.equal(typeof toast.addToast, 'function');
    assert.equal(typeof toast.removeToast, 'function');
    assert.equal(typeof toast.pauseToast, 'function');
    assert.equal(typeof toast.resumeToast, 'function');
    assert.equal(typeof toast.clearAll, 'function');
    assert.equal(typeof toast.success, 'function');
    assert.equal(typeof toast.error, 'function');
    assert.equal(typeof toast.warning, 'function');
    assert.equal(typeof toast.info, 'function');
  });
});
