/**
 * Empirical Challenger Test Harness for Milestone M5
 * Tests WebRTC Client, ITU-T G.107 MOS Telemetry, WebSocket Signaling Lifecycle,
 * ICE Candidate Exchange, and Media Mute/Unmute State Management.
 */

import { WebRTCClient } from '../resources/js/Services/webrtc.js';

// --- Mock Browser WebRTC & WebSocket APIs for Node.js Testing ---

class MockMediaStreamTrack {
  constructor(kind = 'video') {
    this.kind = kind;
    this.enabled = true;
    this.stopped = false;
    this.id = `track-${Math.random().toString(36).substring(2, 9)}`;
  }
  stop() {
    this.stopped = true;
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
  constructor(candidateInitDict) {
    this.candidate = candidateInitDict.candidate;
    this.sdpMid = candidateInitDict.sdpMid;
    this.sdpMLineIndex = candidateInitDict.sdpMLineIndex;
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
    this.addedIceCandidates.push(candidate);
    return Promise.resolve();
  }

  async getStats() {
    // Return mock stats reports
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
    }, 10);
  }

  send(data) {
    if (this.readyState !== MockWebSocket.OPEN) {
      throw new Error('WebSocket is not open');
    }
    this.sentMessages.push(JSON.parse(data));
  }

  close() {
    this.readyState = MockWebSocket.CLOSED;
    if (this.onclose) this.onclose();
  }

  // Helper to simulate server message reception
  receiveMessage(obj) {
    if (this.onmessage) {
      this.onmessage({ data: JSON.stringify(obj) });
    }
  }
}

// Inject globals into runtime environment
globalThis.WebSocket = MockWebSocket;
globalThis.RTCPeerConnection = MockRTCPeerConnection;
globalThis.RTCIceCandidate = MockRTCIceCandidate;
globalThis.MediaStream = MockMediaStream;

try {
  Object.defineProperty(globalThis, 'navigator', {
    value: {
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

// --- Test Suite Execution ---

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

async function main() {
  console.log('\x1b[36m================================================================================\x1b[0m');
  console.log('\x1b[1m\x1b[36m   CHALLENGER 2: EMPIRICAL WEBRTC & ITU-T G.107 STRESS HARNESS\x1b[0m');
  console.log('\x1b[36m================================================================================\x1b[0m\n');

  console.log('\x1b[1m[Group 1: ITU-T G.107 MOS Telemetry Calculation Boundaries]\x1b[0m');

  await runTest('test_01_excellent_connection_telemetry', () => {
    // 0% loss, 10ms jitter, 20ms RTT -> MOS ~ 4.4 (Excellent)
    const client = new WebRTCClient();
    const mos = client.calculateMOS(20, 10, 0.0);
    assert(mos >= 4.3 && mos <= 4.5, `Expected MOS ~ 4.4, got ${mos}`);
  });

  await runTest('test_02_typical_mobile_4g_telemetry', () => {
    // 0.5% loss, 12ms jitter, 50ms RTT -> MOS ~ 4.3
    const client = new WebRTCClient();
    const mos = client.calculateMOS(50, 12, 0.5);
    assert(mos >= 3.8 && mos <= 4.4, `Expected MOS >= 3.8, got ${mos}`);
  });

  await runTest('test_03_moderate_packet_loss_boundary', () => {
    // 5% loss, 50ms jitter, 150ms RTT
    const client = new WebRTCClient();
    const mos = client.calculateMOS(150, 50, 5.0);
    // Under G.711 standard uncompensated loss, MOS drops significantly
    assert(mos >= 1.0 && mos <= 3.8, `Expected MOS within valid bounds [1.0, 3.8], got ${mos}`);
  });

  await runTest('test_04_degraded_alert_trigger_telemetry', () => {
    // 15% loss, 120ms jitter, 400ms RTT -> MOS < 3.2 (Alert trigger)
    const client = new WebRTCClient();
    const mos = client.calculateMOS(400, 120, 15.0);
    assert(mos < 3.2, `Expected MOS < 3.2 for alert trigger, got ${mos}`);
    assert(mos >= 1.0, `Expected MOS >= 1.0, got ${mos}`);
  });

  await runTest('test_05_extreme_network_blackout_floor', () => {
    // 100% loss, 500ms jitter, 2000ms RTT -> MOS = 1.0 minimum floor
    const client = new WebRTCClient();
    const mos = client.calculateMOS(2000, 500, 100.0);
    assert(mos === 1.0, `Expected absolute floor MOS 1.0, got ${mos}`);
  });

  await runTest('test_06_zero_latency_zero_loss_ceiling', () => {
    // 0ms RTT, 0ms jitter, 0% loss -> MOS clamped to 4.4 - 4.5
    const client = new WebRTCClient();
    const mos = client.calculateMOS(0, 0, 0.0);
    assert(mos >= 4.4 && mos <= 4.5, `Expected MOS ceiling ~ 4.4 - 4.5, got ${mos}`);
  });

  console.log('\n\x1b[1m[Group 2: WebSocket Signaling Lifecycle & Perfect Negotiation]\x1b[0m');

  await runTest('test_07_ws_connect_and_heartbeat', async () => {
    const client = new WebRTCClient({
      wsUrl: 'ws://localhost:8001',
      roomId: 'sala-es-test-101',
      token: 'jwt_valid_token_123',
    });

    await client.connect();
    assert(client.isConnected === true, 'Client should be connected');
    assert(client.ws !== null, 'WebSocket instance must exist');
    assert(client.ws.url.includes('/ws/signaling/sala-es-test-101?token=jwt_valid_token_123'), 'URL must match room and token');

    client.destroy();
    assert(client.isConnected === false, 'Client should disconnect on destroy');
  });

  await runTest('test_08_joined_event_ice_servers_and_init', async () => {
    let joinedFired = false;
    const client = new WebRTCClient({
      roomId: 'sala-test',
      onJoined: (data) => {
        joinedFired = true;
      },
    });

    await client.connect();

    // Simulate incoming 'joined' payload with custom TURN servers
    const mockIceServers = [
      { urls: 'stun:stun.sejus.es.gov.br:3478' },
      { urls: 'turn:turn.sejus.es.gov.br:3478', username: 'u', credential: 'p' },
    ];
    client.ws.receiveMessage({
      type: 'joined',
      room_id: 'sala-test',
      ice_servers: mockIceServers,
    });

    // Wait microtask for async handler
    await new Promise(r => setTimeout(r, 20));

    assert(joinedFired === true, 'onJoined callback must be triggered');
    assert(client.iceServers.length === 2, 'iceServers must be updated');
    assert(client.peerConnection !== null, 'PeerConnection must be initialized');

    client.destroy();
  });

  await runTest('test_09_sdp_offer_and_answer_exchange', async () => {
    const client = new WebRTCClient({
      roomId: 'sala-sdp',
      role: 'egresso', // polite peer
    });

    await client.connect();
    await client.initPeerConnection();

    // Simulate incoming offer from Técnico
    const remoteOfferSdp = 'v=0\r\no=tecnico 100 2 IN IP4 127.0.0.1\r\ns=-\r\nt=0 0\r\nm=video 9 UDP/TLS/RTP/SAVPF 96\r\n';
    client.ws.receiveMessage({
      type: 'offer',
      sdp: remoteOfferSdp,
    });

    await new Promise(r => setTimeout(r, 30));

    // Verify answer was generated and sent over WebSocket
    const answerSent = client.ws.sentMessages.find(m => m.type === 'answer');
    assert(answerSent !== undefined, 'Answer message must be sent over WebSocket');
    assert(answerSent.sdp.startsWith('v=0'), 'Answer SDP must be valid');

    client.destroy();
  });

  await runTest('test_10_remote_ice_candidate_addition', async () => {
    const client = new WebRTCClient({ roomId: 'sala-ice' });
    await client.connect();
    await client.initPeerConnection();

    // Simulate remote ICE candidate
    const candidateObj = {
      candidate: 'candidate:1 1 UDP 2130706431 192.168.1.1 5000 typ host',
      sdpMid: '0',
      sdpMLineIndex: 0,
    };

    client.ws.receiveMessage({
      type: 'ice_candidate',
      candidate: candidateObj,
    });

    await new Promise(r => setTimeout(r, 20));

    assert(client.peerConnection.addedIceCandidates.length === 1, 'ICE candidate should be added to RTCPeerConnection');
    assert(client.peerConnection.addedIceCandidates[0].candidate === candidateObj.candidate, 'Candidate content must match');

    client.destroy();
  });

  await runTest('test_11_telemetry_ack_and_quality_alert', async () => {
    let telemetryAckReceived = false;
    let qualityAlertReceived = false;

    const client = new WebRTCClient({
      roomId: 'sala-alerts',
      onTelemetryUpdate: (data) => {
        telemetryAckReceived = true;
      },
      onQualityAlert: (data) => {
        qualityAlertReceived = true;
      },
    });

    await client.connect();

    client.ws.receiveMessage({ type: 'telemetry_ack', computed_mos: 4.2 });
    client.ws.receiveMessage({ type: 'quality_alert', reason: 'high_packet_loss', mos: 2.1 });

    await new Promise(r => setTimeout(r, 20));

    assert(telemetryAckReceived === true, 'onTelemetryUpdate should be called on telemetry_ack');
    assert(qualityAlertReceived === true, 'onQualityAlert should be called on quality_alert');

    client.destroy();
  });

  console.log('\n\x1b[1m[Group 3: Media Tracks, Mute/Unmute & Screen Sharing]\x1b[0m');

  await runTest('test_12_local_media_acquisition_and_mute_controls', async () => {
    const client = new WebRTCClient({ roomId: 'sala-media' });
    await client.connect();
    await client.startLocalMedia();

    assert(client.localStream !== null, 'Local stream should be acquired');
    assert(client.localStream.getAudioTracks().length > 0, 'Should have audio tracks');
    assert(client.localStream.getVideoTracks().length > 0, 'Should have video tracks');

    // Toggle Audio Mute (true = muted)
    client.toggleAudio(true);
    assert(client.localStream.getAudioTracks()[0].enabled === false, 'Audio track should be disabled');
    const audioMuteMsg = client.ws.sentMessages.find(m => m.type === 'media_state' && m.audio_muted === true);
    assert(audioMuteMsg !== undefined, 'Media state for audio muted should be dispatched');

    // Toggle Audio Unmute (false = unmuted)
    client.toggleAudio(false);
    assert(client.localStream.getAudioTracks()[0].enabled === true, 'Audio track should be re-enabled');

    // Toggle Video Mute
    client.toggleVideo(true);
    assert(client.localStream.getVideoTracks()[0].enabled === false, 'Video track should be disabled');
    const videoMuteMsg = client.ws.sentMessages.find(m => m.type === 'media_state' && m.video_muted === true);
    assert(videoMuteMsg !== undefined, 'Media state for video muted should be dispatched');

    client.destroy();
  });

  await runTest('test_13_screen_sharing_start_and_stop_track_replacement', async () => {
    const client = new WebRTCClient({ roomId: 'sala-screen' });
    await client.connect();
    await client.startLocalMedia();
    await client.initPeerConnection();

    // Start Screen Sharing
    const screenStream = await client.startScreenShare();
    assert(screenStream !== null, 'Screen stream should be created');
    assert(client.screenStream !== null, 'Client screenStream reference set');

    // Stop Screen Sharing
    client.stopScreenShare();
    assert(client.screenStream === null, 'Screen stream should be cleared');

    client.destroy();
  });

  await runTest('test_14_end_call_and_resource_teardown', async () => {
    let callEndedFired = false;
    const client = new WebRTCClient({
      roomId: 'sala-end',
      onCallEnded: (reason) => {
        callEndedFired = true;
      },
    });

    await client.connect();
    await client.startLocalMedia();
    await client.initPeerConnection();

    client.endCall('technician_completed');

    assert(callEndedFired === true, 'onCallEnded callback must be called');
    assert(client.peerConnection === null, 'RTCPeerConnection should be closed and nulled');
    assert(client.isConnected === false, 'WebSocket should be closed');

    const leaveMsg = client.ws.sentMessages.find(m => m.type === 'leave');
    assert(leaveMsg !== undefined, 'Leave message should be dispatched before closing');
    assert(leaveMsg.reason === 'technician_completed', 'Leave reason must be preserved');
  });

  console.log('\n\x1b[1m[Group 4: Adversarial Stress, Rapid Toggles & Edge Combinations]\x1b[0m');

  await runTest('test_15_rapid_media_mute_unmute_cycling_100x', async () => {
    const client = new WebRTCClient({ roomId: 'sala-stress-mute' });
    await client.connect();
    await client.startLocalMedia();

    // Rapidly toggle audio and video 100 times
    for (let i = 0; i < 100; i++) {
      const isMuted = i % 2 === 0;
      client.toggleAudio(isMuted);
      client.toggleVideo(isMuted);
      assert(client.localStream.getAudioTracks()[0].enabled === !isMuted, `Audio track enabled should be ${!isMuted} at iter ${i}`);
      assert(client.localStream.getVideoTracks()[0].enabled === !isMuted, `Video track enabled should be ${!isMuted} at iter ${i}`);
    }

    // Final state: unmuted
    client.toggleAudio(false);
    client.toggleVideo(false);
    assert(client.localStream.getAudioTracks()[0].enabled === true, 'Audio must end unmuted');
    assert(client.localStream.getVideoTracks()[0].enabled === true, 'Video must end unmuted');

    client.destroy();
  });

  await runTest('test_16_telemetry_rapid_oscillation_transitions', () => {
    const client = new WebRTCClient();
    const networkProfiles = [
      { rtt: 15, jitter: 3, loss: 0.0, expectedTier: 'Excelente' },
      { rtt: 80, jitter: 15, loss: 1.0, expectedTier: 'Bom' },
      { rtt: 250, jitter: 60, loss: 8.0, expectedTier: 'Instável' },
      { rtt: 800, jitter: 200, loss: 25.0, expectedTier: 'Instável' },
      { rtt: 20, jitter: 5, loss: 0.0, expectedTier: 'Excelente' },
    ];

    for (const prof of networkProfiles) {
      const mos = client.calculateMOS(prof.rtt, prof.jitter, prof.loss);
      const tier = mos >= 4.0 ? 'Excelente' : (mos >= 3.2 ? 'Bom' : (mos >= 2.5 ? 'Regular' : 'Instável'));
      assert(tier === prof.expectedTier, `Profile RTT ${prof.rtt}ms Loss ${prof.loss}% expected ${prof.expectedTier}, got ${tier} (MOS ${mos})`);
    }
  });

  await runTest('test_17_ice_candidate_before_peer_connection_safety', async () => {
    const client = new WebRTCClient({ roomId: 'sala-early-ice' });
    await client.connect();

    // Send ICE candidate before initPeerConnection
    client.ws.receiveMessage({
      type: 'ice_candidate',
      candidate: { candidate: 'candidate:dummy', sdpMid: '0', sdpMLineIndex: 0 },
    });

    await new Promise(r => setTimeout(r, 20));

    // Must not throw or crash
    assert(client.peerConnection === null, 'Peer connection still null, handled safely without crashing');

    client.destroy();
  });

  await runTest('test_18_sudden_websocket_drop_during_call', async () => {
    const client = new WebRTCClient({ roomId: 'sala-drop' });
    await client.connect();
    await client.startLocalMedia();
    await client.initPeerConnection();

    assert(client.isConnected === true, 'Client initially connected');

    // Simulate abrupt WS closure
    client.ws.close();

    await new Promise(r => setTimeout(r, 20));

    assert(client.isConnected === false, 'Client should mark isConnected false on close');
    assert(client.pingInterval === null, 'Heartbeat must be stopped');
    assert(client.statsInterval === null, 'Stats polling must be stopped');

    client.destroy();
  });

  await runTest('test_19_screenshare_abort_during_call_ends_cleanly', async () => {
    const client = new WebRTCClient({ roomId: 'sala-screen-abort' });
    await client.connect();
    await client.startLocalMedia();
    await client.initPeerConnection();

    const screenStream = await client.startScreenShare();
    assert(client.screenStream !== null, 'Screen stream active');

    // Abrupt endCall while screen sharing
    client.endCall('user_cancelled');

    assert(screenStream.getTracks()[0].stopped === true, 'Screen track must be stopped on endCall');
    assert(client.localStream.getTracks()[0].stopped === true, 'Local tracks must be stopped on endCall');
    assert(client.peerConnection === null, 'Peer connection cleaned up');
  });

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
