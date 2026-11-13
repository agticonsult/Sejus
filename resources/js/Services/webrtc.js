/**
 * WebRTC Teleatendimento Engine - CONECTA EGRESSO (SEJUS/ES)
 * Implements W3C Perfect Negotiation, Trickle ICE, STUN/TURN traversal,
 * and ITU-T G.107 MOS Telemetry calculation.
 */

export class WebRTCClient {
  constructor(config = {}) {
    this.wsUrl = config.wsUrl || (typeof window !== 'undefined' ? `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.hostname}:8001` : 'ws://localhost:8001');
    this.roomId = config.roomId || 'SEJUS-ES-2026';
    this.token = config.token || '';
    this.userId = config.userId || 1;
    this.userName = config.userName || 'Participante';
    this.role = config.role || 'attendee';
    this.iceServers = config.iceServers || [
      { urls: 'stun:stun.l.google.com:19302' },
      { urls: 'turn:coturn.sejus.es.gov.br:3478', username: 'sejus_user', credential: 'turn_password_2026' },
    ];

    this.peerConnection = null;
    this.ws = null;
    this.localStream = null;
    this.remoteStream = null;
    this.screenStream = null;

    this.isPolite = ['attendee', 'egresso', 'familiar'].includes(this.role.toLowerCase());
    this.clientId = null;
    this.remotePeerId = null;
    this.makingOffer = false;
    this.ignoreOffer = false;
    this.isSettingRemoteAnswerPending = false;

    this.statsInterval = null;
    this.pingInterval = null;
    this._fallbackInterval = null;
    this.previousStats = null;
    this.isConnected = false;

    // Callbacks
    this.onJoined = config.onJoined || (() => {});
    this.onPeerJoined = config.onPeerJoined || (() => {});
    this.onPeerLeft = config.onPeerLeft || (() => {});
    this.onRemoteStream = config.onRemoteStream || (() => {});
    this.onTelemetryUpdate = config.onTelemetryUpdate || (() => {});
    this.onQualityAlert = config.onQualityAlert || (() => {});
    this.onChatMessage = config.onChatMessage || (() => {});
    this.onError = config.onError || (() => {});
    this.onCallEnded = config.onCallEnded || (() => {});
  }

  /**
   * Connect to FastAPI WebSocket signaling server.
   */
  async connect() {
    return new Promise((resolve, reject) => {
      try {
        const fullUrl = `${this.wsUrl}/ws/signaling/${this.roomId}?token=${encodeURIComponent(this.token)}`;
        this.ws = new WebSocket(fullUrl);

        this.ws.onopen = () => {
          this.isConnected = true;
          this._startHeartbeat();
          resolve(true);
        };

        this.ws.onmessage = async (event) => {
          try {
            const data = JSON.parse(event.data);
            await this._handleSignalingMessage(data);
          } catch (err) {
            console.error('Failed to parse signaling message:', err);
          }
        };

        this.ws.onerror = (err) => {
          console.warn('WebSocket signaling connection error:', err);
          this.onError(err);
        };

        this.ws.onclose = () => {
          this.isConnected = false;
          this._stopHeartbeat();
          this._stopStatsPolling();
        };
      } catch (err) {
        reject(err);
      }
    });
  }

  /**
   * Handle incoming WebSocket signaling payloads.
   */
  async _handleSignalingMessage(data) {
    switch (data.type) {
      case 'joined':
      case 'room_joined':
        if (data.client_id) {
          this.clientId = data.client_id;
        }
        if (typeof data.polite === 'boolean') {
          this.isPolite = data.polite;
        }
        if (data.ice_servers && Array.isArray(data.ice_servers)) {
          this.iceServers = data.ice_servers;
        }
        this.onJoined(data);
        await this.initPeerConnection();
        if (Array.isArray(data.peers) && data.peers.length > 0) {
          const firstPeer = data.peers[0];
          this.remotePeerId = firstPeer.client_id || null;
          this.onPeerJoined({ peer: firstPeer });
          await this._makeOffer();
        }
        break;

      case 'peer_joined':
        if (data.peer?.client_id) {
          this.remotePeerId = data.peer.client_id;
        }
        this.onPeerJoined(data);
        if (!this.isPolite && this.peerConnection) {
          await this._makeOffer();
        }
        break;

      case 'peer_left':
        this.onPeerLeft(data);
        break;

      case 'chat_message':
        this.onChatMessage(data);
        break;

      case 'offer':
        await this._handleOffer(data);
        break;

      case 'answer':
        await this._handleAnswer(data);
        break;

      case 'ice_candidate':
        await this._handleRemoteIceCandidate(data.candidate);
        break;

      case 'telemetry_ack':
        this.onTelemetryUpdate(data);
        break;

      case 'quality_alert':
        this.onQualityAlert(data);
        break;

      case 'room_terminated':
        this.endCall(data.reason || 'room_terminated');
        break;

      default:
        break;
    }
  }

  /**
   * Initialize RTCPeerConnection with W3C Perfect Negotiation.
   */
  async initPeerConnection() {
    if (typeof RTCPeerConnection === 'undefined') return;

    this.peerConnection = new RTCPeerConnection({ iceServers: this.iceServers });

    // ICE Candidate handler
    this.peerConnection.onicecandidate = ({ candidate }) => {
      if (candidate && this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.ws.send(JSON.stringify({
          type: 'ice_candidate',
          candidate: {
            candidate: candidate.candidate,
            sdpMid: candidate.sdpMid,
            sdpMLineIndex: candidate.sdpMLineIndex,
          },
        }));
      }
    };

    // Track handler
    this.peerConnection.ontrack = (event) => {
      if (!this.remoteStream) {
        this.remoteStream = new MediaStream();
      }
      this.remoteStream.addTrack(event.track);
      this.onRemoteStream(this.remoteStream);
    };

    // Negotiation needed handler
    this.peerConnection.onnegotiationneeded = async () => {
      try {
        this.makingOffer = true;
        await this.peerConnection.setLocalDescription();
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
          this.ws.send(JSON.stringify({
            type: 'offer',
            sdp: this.peerConnection.localDescription.sdp,
          }));
        }
      } catch (err) {
        this.onError(err);
      } finally {
        this.makingOffer = false;
      }
    };

    // Add local tracks if available
    if (this.localStream) {
      this.localStream.getTracks().forEach((track) => {
        this.peerConnection.addTrack(track, this.localStream);
      });
    }

    this.startStatsPolling(2000);
  }

  async _makeOffer() {
    if (!this.peerConnection) await this.initPeerConnection();
    if (!this.peerConnection || this.makingOffer) return;
    this.makingOffer = true;
    try {
      const offer = await this.peerConnection.createOffer();
      await this.peerConnection.setLocalDescription(offer);
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.ws.send(JSON.stringify({
          type: 'offer',
          sdp: this.peerConnection.localDescription.sdp,
        }));
      }
    } catch (err) {
      this.onError(err);
    } finally {
      this.makingOffer = false;
    }
  }

  async _handleOffer(data) {
    if (!this.peerConnection) await this.initPeerConnection();

    const offerCollision = this.makingOffer || this.peerConnection.signalingState !== 'stable';
    const politeForGlare = (this.remotePeerId && this.clientId)
      ? (String(this.clientId) < String(this.remotePeerId))
      : this.isPolite;
    this.ignoreOffer = !politeForGlare && offerCollision;
    if (this.ignoreOffer) return;

    await this.peerConnection.setRemoteDescription({ type: 'offer', sdp: data.sdp });
    await this.peerConnection.setLocalDescription();

    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({
        type: 'answer',
        sdp: this.peerConnection.localDescription.sdp,
      }));
    }
  }

  async _handleAnswer(data) {
    if (!this.peerConnection) return;
    await this.peerConnection.setRemoteDescription({ type: 'answer', sdp: data.sdp });
  }

  async _handleRemoteIceCandidate(candidateObj) {
    if (!this.peerConnection || !candidateObj) return;
    try {
      await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidateObj));
    } catch (err) {
      if (!this.ignoreOffer) {
        console.warn('Error adding received ICE candidate', err);
      }
    }
  }

  /**
   * Acquire local webcam & microphone media streams.
   */
  async startLocalMedia(constraints = { audio: true, video: true }) {
    try {
      if (typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
      } else {
        this.localStream = this._createFallbackMediaStream();
      }
    } catch (err) {
      console.warn('getUserMedia (audio+video) failed, trying audio-only + fallback video:', err.message);
      let audioStream = null;
      if (constraints.audio && typeof navigator !== 'undefined' && navigator.mediaDevices?.getUserMedia) {
        try {
          audioStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
        } catch (_) {
          audioStream = null;
        }
      }
      const fallbackVideoStream = this._createFallbackMediaStream();
      if (audioStream && fallbackVideoStream) {
        audioStream.getAudioTracks().forEach((track) => fallbackVideoStream.addTrack(track));
        this.localStream = fallbackVideoStream;
      } else {
        this.localStream = audioStream || fallbackVideoStream;
      }
    }
    return this.localStream;
  }

  /**
   * Safe fallback for headless test runners / desktops without physical cameras.
   */
  _createFallbackMediaStream() {
    if (typeof document === 'undefined') return null;
    if (this._fallbackInterval) {
      clearInterval(this._fallbackInterval);
      this._fallbackInterval = null;
    }
    const canvas = document.createElement('canvas');
    canvas.width = 640;
    canvas.height = 480;
    const ctx = canvas.getContext('2d');
    const drawFrame = () => {
      if (!ctx) return;
      ctx.fillStyle = '#0f172a';
      ctx.fillRect(0, 0, 640, 480);
      ctx.fillStyle = '#0284c7';
      ctx.fillRect(0, 0, 640, 8);
      ctx.fillStyle = '#f8fafc';
      ctx.font = 'bold 22px sans-serif';
      ctx.fillText('SEJUS/ES — Conecta Egresso', 48, 210);
      ctx.fillStyle = '#94a3b8';
      ctx.font = '16px sans-serif';
      ctx.fillText(`Participante: ${this.userName} (${new Date().toLocaleTimeString('pt-BR')})`, 48, 250);
      ctx.fillStyle = '#10b981';
      ctx.beginPath();
      ctx.arc(56, 290, 6, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = '#34d399';
      ctx.font = '14px sans-serif';
      ctx.fillText('Sinal WebRTC Ativo (Áudio / Vídeo Virtual)', 72, 295);
    };
    drawFrame();
    const stream = canvas.captureStream ? canvas.captureStream(15) : new MediaStream();
    this._fallbackInterval = setInterval(drawFrame, 250);
    return stream;
  }

  toggleAudio(muted) {
    if (this.localStream) {
      this.localStream.getAudioTracks().forEach((t) => {
        t.enabled = !muted;
      });
    }
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({ type: 'media_state', audio_muted: !!muted }));
    }
  }

  toggleVideo(muted) {
    if (this.localStream) {
      this.localStream.getVideoTracks().forEach((t) => {
        t.enabled = !muted;
      });
    }
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({ type: 'media_state', video_muted: !!muted }));
    }
  }

  async startScreenShare() {
    if (typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
      this.screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
      const screenTrack = this.screenStream.getVideoTracks()[0];
      if (this.peerConnection) {
        const sender = this.peerConnection.getSenders().find((s) => s.track && s.track.kind === 'video');
        if (sender) {
          sender.replaceTrack(screenTrack);
        }
      }
      screenTrack.onended = () => {
        this.stopScreenShare();
      };
      return this.screenStream;
    }
    return null;
  }

  stopScreenShare() {
    if (this.screenStream) {
      this.screenStream.getTracks().forEach((t) => t.stop());
      this.screenStream = null;
      if (this.peerConnection && this.localStream) {
        const videoTrack = this.localStream.getVideoTracks()[0];
        const sender = this.peerConnection.getSenders().find((s) => s.track && s.track.kind === 'video');
        if (sender && videoTrack) {
          sender.replaceTrack(videoTrack);
        }
      }
    }
  }

  /**
   * Real-time Telemetry & ITU-T G.107 MOS calculation.
   */
  startStatsPolling(intervalMs = 2000) {
    this._stopStatsPolling();
    this.statsInterval = setInterval(async () => {
      if (!this.peerConnection) return;
      try {
        const stats = await this.peerConnection.getStats();
        let rttMs = 45;
        let jitterMs = 10;
        let packetLossPct = 0.5;

        stats.forEach((report) => {
          if (report.type === 'candidate-pair' && report.state === 'succeeded') {
            if (report.currentRoundTripTime) rttMs = report.currentRoundTripTime * 1000;
          }
          if (report.type === 'inbound-rtp' && report.kind === 'audio') {
            if (report.jitter) jitterMs = report.jitter * 1000;
            if (report.packetsLost && report.packetsReceived) {
              const total = report.packetsLost + report.packetsReceived;
              if (total > 0) packetLossPct = (report.packetsLost / total) * 100;
            }
          }
        });

        const mos = this.calculateMOS(rttMs, jitterMs, packetLossPct);
        const qualityTier = mos >= 4.0 ? 'Excelente' : (mos >= 3.2 ? 'Bom' : (mos >= 2.5 ? 'Regular' : 'Instável'));

        const telemetryData = {
          mos,
          quality_tier: qualityTier,
          rtt_ms: Math.round(rttMs),
          jitter_ms: Math.round(jitterMs),
          packet_loss_pct: Math.round(packetLossPct * 10) / 10,
        };

        this.onTelemetryUpdate(telemetryData);

        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
          this.ws.send(JSON.stringify({
            type: 'telemetry',
            audio: { jitter_ms: jitterMs, packet_loss_pct: packetLossPct },
            connection: { rtt_ms: rttMs },
          }));
        }
      } catch (err) {
        // Stats sampling error safe handling
      }
    }, intervalMs);
  }

  /**
   * ITU-T G.107 E-model MOS Calculation.
   */
  calculateMOS(rttMs, jitterMs, packetLossPct) {
    // Effective delay: one-way delay + jitter buffer estimate
    const oneWayDelay = (rttMs / 2) + (jitterMs * 2);

    // Delay impairment Id
    let Id = 0;
    if (oneWayDelay > 100) {
      Id = 0.024 * oneWayDelay + 0.11 * (oneWayDelay - 177.3) * (oneWayDelay > 177.3 ? 1 : 0);
    }

    // Packet loss impairment Ie-eff
    const Ie = 0; // standard codec
    const Bpl = 4.3; // Packet loss robustness factor
    const IeEff = Ie + (95 - Ie) * (packetLossPct / (packetLossPct + Bpl));

    // Base transmission rating factor R
    const R = Math.max(0, Math.min(100, 93.2 - Id - IeEff));

    // Calculate MOS from R
    let mos = 1.0;
    if (R <= 0) {
      mos = 1.0;
    } else if (R >= 100) {
      mos = 4.5;
    } else {
      mos = 1 + 0.035 * R + R * (R - 60) * (100 - R) * 0.000007;
    }

    return Math.max(1.0, Math.min(4.5, Math.round(mos * 10) / 10));
  }

  _stopStatsPolling() {
    if (this.statsInterval) {
      clearInterval(this.statsInterval);
      this.statsInterval = null;
    }
  }

  _startHeartbeat() {
    this._stopHeartbeat();
    this.pingInterval = setInterval(() => {
      if (this.ws && this.ws.readyState === WebSocket.OPEN) {
        this.ws.send(JSON.stringify({ type: 'ping' }));
      }
    }, 20000);
  }

  _stopHeartbeat() {
    if (this.pingInterval) {
      clearInterval(this.pingInterval);
      this.pingInterval = null;
    }
  }

  sendChatMessage(text) {
    if (!text || !text.trim()) return;
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({
        type: 'chat_message',
        text: text.trim(),
      }));
    }
  }

  endCall(reason = 'voluntary') {
    this._stopStatsPolling();
    this._stopHeartbeat();
    if (this._fallbackInterval) {
      clearInterval(this._fallbackInterval);
      this._fallbackInterval = null;
    }
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify({ type: 'leave', reason }));
      this.ws.close();
    }
    if (this.localStream) {
      this.localStream.getTracks().forEach((t) => t.stop());
    }
    if (this.screenStream) {
      this.screenStream.getTracks().forEach((t) => t.stop());
    }
    if (this.peerConnection) {
      this.peerConnection.close();
      this.peerConnection = null;
    }
    this.onCallEnded(reason);
  }

  destroy() {
    this.endCall('destroyed');
  }
}
