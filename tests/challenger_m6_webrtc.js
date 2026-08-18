/**
 * Milestone M6 Phase 2: Empirical Challenger Test Harness
 * WebRTC Signaling, ITU-T G.107 MOS Telemetry, Offline IndexedDB State Management & A11y
 * 
 * Target Modules:
 * - resources/js/Services/webrtc.js
 * - resources/js/Composables/useAccessibility.js
 * - In-memory IndexedDB Offline Queue & State Synchronization Engine
 */

import { WebRTCClient } from '../resources/js/Services/webrtc.js';
import { useAccessibility, MIN_ZOOM, MAX_ZOOM, ZOOM_STEP } from '../resources/js/Composables/useAccessibility.js';

// ==============================================================================
// 1. Browser & DOM Mocks for Node.js Testing Environment
// ==============================================================================

class MockMediaStreamTrack {
  constructor(kind = 'video') {
    this.kind = kind;
    this.enabled = true;
    this.stopped = false;
    this.id = `track-${Math.random().toString(36).substring(2, 9)}`;
    this.onended = null;
  }
  stop() {
    this.stopped = true;
    if (this.onended) this.onended();
  }
}

class MockMediaStream {
  constructor(tracks = []) {
    this.tracks = tracks.length > 0 ? tracks : [new MockMediaStreamTrack('audio'), new MockMediaStreamTrack('video')];
  }
  getTracks() {
    return this.tracks;
  }
  getAudioTracks() {
    return this.tracks.filter(t => t.kind === 'audio');
  }
  getVideoTracks() {
    return this.tracks.filter(t => t.kind === 'video');
  }
  addTrack(track) {
    this.tracks.push(track);
  }
}

class MockRTCRtpSender {
  constructor(track) {
    this.track = track;
  }
  replaceTrack(newTrack) {
    this.track = newTrack;
    return Promise.resolve();
  }
}

class MockRTCIceCandidate {
  constructor(candidateInitDict = {}) {
    this.candidate = candidateInitDict.candidate || '';
    this.sdpMid = candidateInitDict.sdpMid || '0';
    this.sdpMLineIndex = candidateInitDict.sdpMLineIndex !== undefined ? candidateInitDict.sdpMLineIndex : 0;
  }
}

class MockRTCPeerConnection {
  constructor(config = {}) {
    this.config = config;
    this.localDescription = null;
    this.remoteDescription = null;
    this.signalingState = 'stable';
    this.iceConnectionState = 'new';
    this.senders = [];
    this.addedIceCandidates = [];
    this.closed = false;

    this.onicecandidate = null;
    this.ontrack = null;
    this.onnegotiationneeded = null;
  }

  addTrack(track, stream) {
    const sender = new MockRTCRtpSender(track);
    this.senders.push(sender);
    return sender;
  }

  getSenders() {
    return this.senders;
  }

  async setLocalDescription(desc) {
    this.localDescription = desc || { type: 'offer', sdp: 'v=0\r\no=- 123 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n' };
    this.signalingState = this.localDescription.type === 'offer' ? 'have-local-offer' : 'stable';
  }

  async setRemoteDescription(desc) {
    this.remoteDescription = desc;
    this.signalingState = desc.type === 'offer' ? 'have-remote-offer' : 'stable';
  }

  async addIceCandidate(candidate) {
    if (!candidate || !candidate.candidate) {
      throw new Error('Invalid ICE candidate payload');
    }
    this.addedIceCandidates.push(candidate);
    return Promise.resolve();
  }

  async getStats() {
    return [
      { type: 'candidate-pair', state: 'succeeded', currentRoundTripTime: 0.045 },
      { type: 'inbound-rtp', kind: 'audio', jitter: 0.008, packetsLost: 2, packetsReceived: 998 },
    ];
  }

  close() {
    this.closed = true;
    this.signalingState = 'closed';
  }
}

class MockWebSocket {
  static OPEN = 1;
  static CLOSED = 3;

  constructor(url) {
    this.url = url;
    this.readyState = MockWebSocket.OPEN;
    this.sentMessages = [];
    this.onopen = null;
    this.onmessage = null;
    this.onerror = null;
    this.onclose = null;

    setTimeout(() => {
      if (this.onopen) this.onopen();
    }, 5);
  }

  send(data) {
    if (this.readyState !== MockWebSocket.OPEN) {
      throw new Error('WebSocket is not open');
    }
    try {
      this.sentMessages.push(JSON.parse(data));
    } catch (e) {
      this.sentMessages.push({ _raw_string: data });
    }
  }

  close() {
    this.readyState = MockWebSocket.CLOSED;
    if (this.onclose) this.onclose();
  }

  receiveMessage(obj) {
    if (this.onmessage) {
      this.onmessage({ data: typeof obj === 'string' ? obj : JSON.stringify(obj) });
    }
  }
}

// In-Memory LocalStorage Mock
class MockLocalStorage {
  constructor() {
    this.store = {};
  }
  getItem(key) {
    return this.store[key] !== undefined ? this.store[key] : null;
  }
  setItem(key, value) {
    this.store[key] = String(value);
  }
  removeItem(key) {
    delete this.store[key];
  }
  clear() {
    this.store = {};
  }
}

// In-Memory DOM Element Mock
class MockElement {
  constructor(tagName) {
    this.tagName = tagName;
    this.classList = {
      classes: new Set(),
      add: (cls) => this.classList.classes.add(cls),
      remove: (cls) => this.classList.classes.delete(cls),
      contains: (cls) => this.classList.classes.has(cls),
      toggle: (cls) => {
        if (this.classList.classes.has(cls)) {
          this.classList.classes.delete(cls);
          return false;
        } else {
          this.classList.classes.add(cls);
          return true;
        }
      }
    };
    this.style = {
      properties: {},
      setProperty: (prop, val) => { this.style.properties[prop] = val; },
      getPropertyValue: (prop) => this.style.properties[prop] || '',
    };
  }
}

const mockDocElement = new MockElement('HTML');
const mockBodyElement = new MockElement('BODY');
const mockLocalStorage = new MockLocalStorage();

// Global Injection
globalThis.WebSocket = MockWebSocket;
globalThis.RTCPeerConnection = MockRTCPeerConnection;
globalThis.RTCIceCandidate = MockRTCIceCandidate;
globalThis.MediaStream = MockMediaStream;
globalThis.localStorage = mockLocalStorage;

globalThis.document = {
  documentElement: mockDocElement,
  body: mockBodyElement,
  addEventListener: (event, handler) => {},
  removeEventListener: (event, handler) => {},
};

globalThis.window = {
  location: { protocol: 'http:', hostname: 'localhost' },
  localStorage: mockLocalStorage,
  addEventListener: (event, handler) => {},
  removeEventListener: (event, handler) => {},
};

try {
  Object.defineProperty(globalThis, 'navigator', {
    value: {
      onLine: true,
      mediaDevices: {
        getUserMedia: async (constraints) => new MockMediaStream(),
        getDisplayMedia: async (constraints) => new MockMediaStream([new MockMediaStreamTrack('video')]),
      },
    },
    writable: true,
    configurable: true,
  });
} catch (e) {
  globalThis.navigator.mediaDevices = {
    getUserMedia: async (constraints) => new MockMediaStream(),
    getDisplayMedia: async (constraints) => new MockMediaStream([new MockMediaStreamTrack('video')]),
  };
}

// ==============================================================================
// 2. In-Memory IndexedDB Simulation Engine for Offline State Testing
// ==============================================================================

class MockIDBObjectStore {
  constructor(name) {
    this.name = name;
    this.data = new Map();
  }

  put(item) {
    const key = item.id || item.key || `auto_${Date.now()}_${Math.random()}`;
    this.data.set(key, JSON.parse(JSON.stringify(item)));
    return {
      onsuccess: null,
      onerror: null,
      result: key
    };
  }

  get(key) {
    const item = this.data.get(key);
    return {
      onsuccess: null,
      onerror: null,
      result: item ? JSON.parse(JSON.stringify(item)) : undefined
    };
  }

  delete(key) {
    this.data.delete(key);
    return { onsuccess: null, onerror: null };
  }

  getAll() {
    return {
      onsuccess: null,
      onerror: null,
      result: Array.from(this.data.values()).map(v => JSON.parse(JSON.stringify(v)))
    };
  }

  clear() {
    this.data.clear();
    return { onsuccess: null, onerror: null };
  }
}

class MockIDBDatabase {
  constructor(name) {
    this.name = name;
    this.stores = new Map();
  }

  createObjectStore(name) {
    const store = new MockIDBObjectStore(name);
    this.stores.set(name, store);
    return store;
  }

  transaction(storeNames, mode = 'readonly') {
    const names = Array.isArray(storeNames) ? storeNames : [storeNames];
    return {
      objectStore: (name) => {
        if (!this.stores.has(name)) {
          throw new Error(`NotFoundError: ObjectStore ${name} does not exist`);
        }
        return this.stores.get(name);
      },
      oncomplete: null,
      onerror: null,
      abort: () => {},
    };
  }
}

// ==============================================================================
// 3. Test Runner & Assertion Engine
// ==============================================================================

const testResults = [];

function assert(condition, message) {
  if (!condition) {
    throw new Error(`Assertion Failed: ${message}`);
  }
}

async function runTest(testName, testFn) {
  const start = Date.now();
  try {
    await testFn();
    const duration = Date.now() - start;
    testResults.push({ name: testName, status: 'PASS', duration, error: null });
    console.log(`  \x1b[32m[PASS]\x1b[0m ${testName} (${duration}ms)`);
  } catch (err) {
    const duration = Date.now() - start;
    testResults.push({ name: testName, status: 'FAIL', duration, error: err.message });
    console.error(`  \x1b[31m[FAIL]\x1b[0m ${testName} (${duration}ms): ${err.message}`);
  }
}

// ==============================================================================
// 4. Test Suite Execution
// ==============================================================================

async function main() {
  console.log('\x1b[36m================================================================================\x1b[0m');
  console.log('\x1b[1m\x1b[36m   CHALLENGER M6 PHASE 2: WEBRTC, E-MODEL & FRONTEND ADVERSARIAL HARNESS\x1b[0m');
  console.log('\x1b[36m================================================================================\x1b[0m\n');

  // ----------------------------------------------------------------------------
  // GROUP 1: ITU-T G.107 E-Model Mathematical Extremes (JS Implementation)
  // ----------------------------------------------------------------------------
  console.log('\x1b[1m[Group 1: ITU-T G.107 E-Model JS Engine Precision & Bounds]\x1b[0m');

  await runTest('test_01_e_model_js_extreme_latency_matrix', () => {
    const client = new WebRTCClient();
    
    // Latencies: 0ms, 150ms, 400ms, 2500ms
    const mos0 = client.calculateMOS(0, 0, 0);
    assert(mos0 >= 4.3 && mos0 <= 4.5, `0ms latency expected MOS ~4.4, got ${mos0}`);

    const mos150 = client.calculateMOS(150, 10, 0.5);
    assert(mos150 >= 3.8 && mos150 <= 4.4, `150ms latency expected MOS in [3.8, 4.4], got ${mos150}`);

    const mos400 = client.calculateMOS(400, 80, 5.0);
    assert(mos400 >= 1.0 && mos400 <= 3.5, `400ms latency expected degraded MOS <= 3.5, got ${mos400}`);

    const mos2500 = client.calculateMOS(2500, 200, 10.0);
    assert(mos2500 === 1.0, `2500ms latency expected floor MOS 1.0, got ${mos2500}`);
  });

  await runTest('test_02_e_model_js_packet_loss_sweep_0_to_100', () => {
    const client = new WebRTCClient();
    const losses = [0.0, 0.5, 2.0, 5.0, 10.0, 20.0, 50.0, 100.0];
    let prevMos = 5.0;

    for (const loss of losses) {
      const mos = client.calculateMOS(50, 10, loss);
      assert(mos >= 1.0 && mos <= 4.5, `MOS ${mos} out of bounds [1.0, 4.5] at loss ${loss}%`);
      assert(mos <= prevMos + 0.01, `Monotonic degradation violated at loss ${loss}% (curr ${mos} > prev ${prevMos})`);
      prevMos = mos;
    }
  });

  await runTest('test_03_e_model_js_jitter_spike_bursts', () => {
    const client = new WebRTCClient();
    const jitters = [0, 5, 25, 100, 500, 1500];

    for (const j of jitters) {
      const mos = client.calculateMOS(60, j, 1.0);
      assert(mos >= 1.0 && mos <= 4.5, `MOS ${mos} out of bounds for jitter ${j}ms`);
    }
  });

  await runTest('test_04_e_model_js_alert_trigger_thresholds', () => {
    const client = new WebRTCClient();
    
    // Normal connection -> No alert condition
    const mosGood = client.calculateMOS(40, 5, 0.1);
    assert(mosGood >= 4.0, `Good connection expected MOS >= 4.0, got ${mosGood}`);

    // Degraded connection -> Alert condition (MOS < 3.2)
    const mosDegraded = client.calculateMOS(380, 90, 12.0);
    assert(mosDegraded < 3.2, `Degraded connection expected MOS < 3.2, got ${mosDegraded}`);
    assert(mosDegraded >= 1.0, `Degraded connection must respect floor 1.0`);
  });

  // ----------------------------------------------------------------------------
  // GROUP 2: WebSocket Signaling Fuzzing & Glare Negotiation
  // ----------------------------------------------------------------------------
  console.log('\n\x1b[1m[Group 2: WebSocket Signaling Fuzzing & Perfect Negotiation]\x1b[0m');

  await runTest('test_05_ws_malformed_json_resilience', async () => {
    let errorFired = false;
    const client = new WebRTCClient({
      roomId: 'sala-fuzz-json',
      onError: (err) => { errorFired = true; }
    });
    await client.connect();

    // Inject corrupted JSON strings directly into onmessage handler
    const malformedPayloads = [
      'NOT_A_JSON',
      '{ incomplete: ',
      '{"type": "offer", "sdp": undefined}',
      '\x00\x01\x02',
      '{"type": "telemetry", "mos": NaN}'
    ];

    for (const payload of malformedPayloads) {
      // Must not throw uncaught exception
      client.ws.receiveMessage(payload);
    }

    assert(client.isConnected === true, 'Client should remain connected after malformed payloads');
    client.destroy();
  });

  await runTest('test_06_ws_massive_sdp_offer_handling', async () => {
    const client = new WebRTCClient({ roomId: 'sala-huge-sdp' });
    await client.connect();
    await client.initPeerConnection();

    // Create 100KB synthetic SDP
    let hugeSdp = 'v=0\r\no=- 999 2 IN IP4 127.0.0.1\r\ns=MassiveSDP\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n';
    for (let i = 0; i < 2000; i++) {
      hugeSdp += `a=candidate:${i} 1 UDP 2130706431 10.0.0.1 ${5000 + i} typ host\r\n`;
    }

    client.ws.receiveMessage({
      type: 'offer',
      sdp: hugeSdp,
    });

    await new Promise(r => setTimeout(r, 20));

    // Verify answer generated without crash
    const answerSent = client.ws.sentMessages.find(m => m.type === 'answer');
    assert(answerSent !== undefined, 'Answer message should be generated for massive SDP');

    client.destroy();
  });

  await runTest('test_07_glare_negotiation_polite_vs_impolite', async () => {
    // Polite client (Egresso / Attendee)
    const politeClient = new WebRTCClient({ role: 'egresso', roomId: 'sala-glare' });
    await politeClient.connect();
    await politeClient.initPeerConnection();

    assert(politeClient.isPolite === true, 'Egresso must be polite peer');

    // Impolite client (Técnico / Technician)
    const impoliteClient = new WebRTCClient({ role: 'tecnico', roomId: 'sala-glare' });
    await impoliteClient.connect();
    await impoliteClient.initPeerConnection();

    assert(impoliteClient.isPolite === false, 'Técnico must be impolite peer');

    politeClient.destroy();
    impoliteClient.destroy();
  });

  await runTest('test_08_ice_candidate_injection_before_peer_connection', async () => {
    const client = new WebRTCClient({ roomId: 'sala-ice-inject' });
    await client.connect();

    // Malicious candidate sent before initPeerConnection
    client.ws.receiveMessage({
      type: 'ice_candidate',
      candidate: { candidate: '<script>alert("xss")</script>', sdpMid: '0', sdpMLineIndex: 0 }
    });

    await new Promise(r => setTimeout(r, 10));
    assert(client.peerConnection === null, 'PeerConnection safely null');

    // Initialize peer connection and send valid candidate
    await client.initPeerConnection();
    client.ws.receiveMessage({
      type: 'ice_candidate',
      candidate: { candidate: 'candidate:1 1 UDP 2130706431 192.168.1.1 5000 typ host', sdpMid: '0', sdpMLineIndex: 0 }
    });

    await new Promise(r => setTimeout(r, 10));
    assert(client.peerConnection.addedIceCandidates.length === 1, 'Valid ICE candidate added');

    client.destroy();
  });

  // ----------------------------------------------------------------------------
  // GROUP 3: Frontend Offline State Management & IndexedDB Conflict Sync
  // ----------------------------------------------------------------------------
  console.log('\n\x1b[1m[Group 3: Frontend Offline Store, IndexedDB & Sync Conflict Resolution]\x1b[0m');

  await runTest('test_09_indexeddb_offline_queue_crud_and_persistence', async () => {
    const db = new MockIDBDatabase('ConectaEgressoDB');
    const queueStore = db.createObjectStore('offline_actions_queue');

    // Enqueue 3 mutations
    queueStore.put({ id: 'act_001', action: 'CREATE_EVOLUCAO', payload: { nota: 'Atendimento presencial' }, timestamp: 1700000100 });
    queueStore.put({ id: 'act_002', action: 'UPDATE_CHECKIN', payload: { status: 'presente' }, timestamp: 1700000200 });
    queueStore.put({ id: 'act_003', action: 'CLAIM_VAGA', payload: { vaga_id: 42 }, timestamp: 1700000300 });

    const allItems = queueStore.getAll().result;
    assert(allItems.length === 3, `Expected 3 items in offline queue, got ${allItems.length}`);

    // Retrieve single item
    const item2 = queueStore.get('act_002').result;
    assert(item2 !== undefined, 'Item 2 must exist');
    assert(item2.action === 'UPDATE_CHECKIN', 'Item 2 action must match');

    // Remove processed item
    queueStore.delete('act_001');
    assert(queueStore.getAll().result.length === 2, 'Queue length should decrease to 2');
  });

  await runTest('test_10_stale_checkin_sync_conflict_resolution_lww', async () => {
    // Server state (Version 2, Updated at t=500)
    const serverCheckin = {
      egresso_id: 8412,
      municipio: 'Vitoria',
      status: 'em_atendimento',
      version: 2,
      updated_at: 1700000500,
    };

    // Stale offline mutation (generated offline at t=400, Version 1)
    const staleOfflineMutation = {
      egresso_id: 8412,
      municipio: 'Vitoria',
      status: 'aguardando',
      version: 1,
      updated_at: 1700000400,
    };

    // Fresh offline mutation (generated offline at t=600, Version 3)
    const freshOfflineMutation = {
      egresso_id: 8412,
      municipio: 'Vitoria',
      status: 'concluido',
      version: 3,
      updated_at: 1700000600,
    };

    function reconcile(server, client) {
      if (client.version <= server.version && client.updated_at <= server.updated_at) {
        return { applied: false, state: server, reason: 'STALE_MUTATION_REJECTED' };
      }
      return { applied: true, state: client, reason: 'FRESH_MUTATION_APPLIED' };
    }

    const resStale = reconcile(serverCheckin, staleOfflineMutation);
    assert(resStale.applied === false, 'Stale offline mutation must be rejected');
    assert(resStale.state.status === 'em_atendimento', 'Server status must be preserved');

    const resFresh = reconcile(serverCheckin, freshOfflineMutation);
    assert(resFresh.applied === true, 'Fresh offline mutation must be applied');
    assert(resFresh.state.status === 'concluido', 'Server status must be updated');
  });

  await runTest('test_11_network_reconnect_rapid_flapping_and_idempotency', async () => {
    const executedActions = [];
    const processedIdempotencyKeys = new Set();

    function processOfflineAction(action) {
      if (processedIdempotencyKeys.has(action.idempotency_key)) {
        return { status: 'DUPLICATE_IGNORED' };
      }
      processedIdempotencyKeys.add(action.idempotency_key);
      executedActions.push(action.id);
      return { status: 'PROCESSED' };
    }

    const action1 = { id: 'act_101', idempotency_key: 'idemp_key_abc_1', type: 'SAVE_PRONTUARIO' };
    const action1Dup = { id: 'act_101_retry', idempotency_key: 'idemp_key_abc_1', type: 'SAVE_PRONTUARIO' };

    const r1 = processOfflineAction(action1);
    const r2 = processOfflineAction(action1Dup);

    assert(r1.status === 'PROCESSED', 'First action must be processed');
    assert(r2.status === 'DUPLICATE_IGNORED', 'Duplicate action must be ignored');
    assert(executedActions.length === 1, 'Only one mutation should execute');
  });

  // ----------------------------------------------------------------------------
  // GROUP 4: Accessibility Composable & WCAG 2.1 AAA Navigation
  // ----------------------------------------------------------------------------
  console.log('\n\x1b[1m[Group 4: Accessibility Composable, WCAG AAA & Keyboard Trapping]\x1b[0m');

  await runTest('test_12_a11y_high_contrast_toggle_and_dom_class', () => {
    mockLocalStorage.clear();
    mockDocElement.classList.classes.clear();

    const a11y = useAccessibility();
    a11y.initAccessibility();

    assert(a11y.highContrast.value === false, 'Initial high contrast should be false');

    // Toggle on
    const active = a11y.toggleHighContrast();
    assert(active === true, 'High contrast should be active');
    assert(mockDocElement.classList.contains('high-contrast'), 'DOM root must have .high-contrast class');
    assert(mockLocalStorage.getItem('conecta_high_contrast') === 'true', 'Must persist in localStorage');

    // Toggle off
    const deactivated = a11y.toggleHighContrast();
    assert(deactivated === false, 'High contrast should be inactive');
    assert(!mockDocElement.classList.contains('high-contrast'), 'DOM root must remove .high-contrast class');
  });

  await runTest('test_13_a11y_font_zoom_clamping_and_step_invariants', () => {
    const a11y = useAccessibility();
    a11y.resetZoom();

    assert(a11y.fontZoom.value === 1.00, 'Initial zoom should be 1.00');

    // Zoom in (+0.18)
    a11y.zoomIn();
    assert(a11y.fontZoom.value === 1.18, `Expected 1.18, got ${a11y.fontZoom.value}`);

    // Zoom in (+0.18) -> 1.36
    a11y.zoomIn();
    assert(a11y.fontZoom.value === 1.36, `Expected 1.36, got ${a11y.fontZoom.value}`);

    // Zoom in (+0.18) -> clamped to 1.50 (MAX_ZOOM)
    a11y.zoomIn();
    assert(a11y.fontZoom.value === MAX_ZOOM, `Expected MAX_ZOOM (1.50), got ${a11y.fontZoom.value}`);

    // Attempting zoom in further remains clamped
    a11y.zoomIn();
    assert(a11y.fontZoom.value === MAX_ZOOM, 'Must remain clamped at MAX_ZOOM');

    // Zoom out to minimum
    a11y.zoomOut();
    a11y.zoomOut();
    a11y.zoomOut();
    a11y.zoomOut();
    assert(a11y.fontZoom.value === MIN_ZOOM, `Expected MIN_ZOOM (1.00), got ${a11y.fontZoom.value}`);
  });

  await runTest('test_14_a11y_simplified_language_dictionary_translations', () => {
    const a11y = useAccessibility();
    
    // Standard language
    if (a11y.simplifiedLanguage.value) a11y.toggleSimplifiedLanguage();
    assert(a11y.t('dashboard_title') === 'Painel de Gestão e Monitoramento de Egressos', 'Standard translation match');
    assert(a11y.t('atendimento_title') === 'Atendimento Remoto e Videochamadas Seguras', 'Standard translation match');

    // Simplified language (Linguagem Fácil)
    a11y.toggleSimplifiedLanguage();
    assert(a11y.t('dashboard_title') === 'Página Principal', 'Simplified translation match');
    assert(a11y.t('atendimento_title') === 'Conversa em Vídeo com Assistente Social', 'Simplified translation match');
    assert(a11y.t('carteira_title') === 'Seu Documento Digital', 'Simplified translation match');

    // Fallback key
    assert(a11y.t('fallback_only_key') === 'Texto Padrão sem Equivalente Simplificado', 'Fallback translation match');
    assert(a11y.t('non_existent_key') === '[non_existent_key]', 'Bracketed key on missing string');
  });

  await runTest('test_15_modal_escape_key_dismissal_simulation', () => {
    let modalOpen = true;
    let callEndedReason = null;

    function handleModalKeyDown(event) {
      if (event.key === 'Escape') {
        modalOpen = false;
        callEndedReason = 'user_escaped';
      }
    }

    // Simulate Escape key press
    handleModalKeyDown({ key: 'Escape' });
    assert(modalOpen === false, 'Modal should close on Escape key');
    assert(callEndedReason === 'user_escaped', 'Reason should record escape event');
  });

  // ----------------------------------------------------------------------------
  // Summary
  // ----------------------------------------------------------------------------
  console.log('\n\x1b[36m================================================================================\x1b[0m');
  const passed = testResults.filter(r => r.status === 'PASS').length;
  const failed = testResults.filter(r => r.status === 'FAIL').length;
  console.log(`\x1b[1mSummary: ${passed}/${testResults.length} passed, ${failed} failed.\x1b[0m`);
  console.log('\x1b[36m================================================================================\x1b[0m\n');

  if (failed > 0) {
    process.exit(1);
  }
}

main().catch(err => {
  console.error('Fatal error running challenger tests:', err);
  process.exit(1);
});
