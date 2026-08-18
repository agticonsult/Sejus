# Technical Specification: Telemetry Processing Engine, MOS Scoring & Webhook Dispatcher
## CONECTA EGRESSO (SEJUS/ES) — Milestone M4 (Python FastAPI WebRTC Microservice)

**Document:** Telemetry Processing, ITU-T G.107 MOS Engine, Aggregation & HMAC Webhook Dispatcher Specification  
**Component:** `webrtc_service/` (Submodules: `telemetry.py`, `mos.py`, `webhooks.py`, `aggregator.py`, `schemas.py`)  
**Author:** Explorer 3 (`explorer_m4_3`)  
**Date:** 2026-08-17  
**Status:** COMPLETE & FINAL SPECIFICATION  

---

## 1. Executive Summary & Architectural Scope

The **CONECTA EGRESSO** platform, developed for the Secretariat of Justice of the State of Espírito Santo (SEJUS/ES), provides remote social and legal assistance across all 78 municipalities in Espírito Santo. Because 74 of these 78 municipalities rely entirely on remote assistance via the platform, citizens (individuals formerly incarcerated and their families) frequently connect from mobile cellular networks (3G, 4G, 5G) in rural and peripheral areas.

To guarantee service quality, monitor connectivity, adapt media streams dynamically, and guarantee 100% reliable recording of social assistance attendances in the immutable *Prontuário Único*, the `webrtc_service` microservice incorporates three tightly integrated telemetry and dispatch subsystems:

1. **Real-Time Telemetry Ingestion Engine**: Collects and normalizes client-side WebRTC `RTCPeerConnection.getStats()` metrics every 2 to 5 seconds over WebSocket signaling or REST channels.
2. **ITU-T G.107 / E-Model MOS Scoring Engine**: Implements the standardized mathematical E-Model to convert raw network parameters (One-Way Delay/RTT, Jitter, Packet Loss, Codec Impairment) into an objective Mean Opinion Score (MOS) from 1.0 (unusable) to 5.0 (crystal clear).
3. **Session Aggregator & Quality Alert Manager**: Accumulates temporal time-series metrics per participant and session, classifies overall call health, emits real-time degradation alerts (e.g. suggesting audio-only fallback on congested cellular links), and compiles a comprehensive session quality summary on call termination.
4. **Reliable HMAC-SHA256 Webhook Dispatcher**: Delivers signed lifecycle events (`session.started`, `session.ended`, `session.quality_alert`, `attendee.admitted`) to the Laravel backend (`/api/webhooks/webrtc`), featuring non-blocking asynchronous dispatch (`httpx.AsyncClient`), strict HMAC-SHA256 signature generation, exponential backoff retries with full jitter, and persistent Redis Dead-Letter Queue (DLQ) for fault tolerance.

```
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                                 BROWSER CLIENT                                        │
│  (Egresso / Técnico)                                                                  │
│   - RTCPeerConnection.getStats() (Every 2-5s)                                         │
│   - Extracts: RTT, Jitter, Packets Lost, Bytes, Frames, Resolution, Freezes             │
└──────────────────────────────────────────┬────────────────────────────────────────────┘
                                           │ WebSocket Message: {"type": "telemetry_report", ...}
                                           ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                       FASTAPI WEBRTC MICROSERVICE (webrtc_service/)                   │
│                                                                                       │
│  ┌─────────────────────────┐     ┌─────────────────────────────────────────────────┐  │
│  │ Telemetry Ingestion     │────►│ ITU-T G.107 / E-Model MOS Engine (mos.py)       │  │
│  │ (schemas.py / stats)    │     │  - Delay Impairment Id(d)                       │  │
│  └────────────┬────────────┘     │  - Equipment Impairment Ie,eff(Ploss, Opus FEC) │  │
│               │                  │  - R-Factor & MOS (1.0 - 5.0) Calculation       │  │
│               ▼                  └────────────────────────┬────────────────────────┘  │
│  ┌─────────────────────────┐                              │                           │
│  │ Session Aggregator      │◄─────────────────────────────┘                           │
│  │ (aggregator.py)         │                                                          │
│  │  - Sliding Window Stats │────► [Realtime Quality Alert Check]                      │
│  │  - Min/Max/Avg/P95 MOS  │          │ (MOS < 3.2 or Loss > 10%)                     │
│  │  - Quality Distribution │          ▼                                               │
│  │  - Redis State Sync     │     WebSocket Alert: {"type": "network_quality_alert"}   │
│  └────────────┬────────────┘                                                          │
│               │ On Call Teardown                                                      │
│               ▼                                                                       │
│  ┌─────────────────────────────────────────────────────────────────────────────────┐  │
│  │ Reliable HMAC-SHA256 Webhook Dispatcher (webhooks.py)                           │  │
│  │  - Event Formatter (session.ended with summary_telemetry)                       │  │
│  │  - HMAC-SHA256 Signer (Header: X-Signature: sha256=...)                         │  │
│  │  - Async HTTP Client (httpx.AsyncClient + Connection Pool)                      │  │
│  │  - Exponential Backoff Retry (1s, 2s, 4s, 8s, 16s + Jitter)                     │  │
│  │  - Redis Dead-Letter Queue (DLQ: webrtc:webhook_dlq)                            │  │
│  └────────────────────────────────────────┬────────────────────────────────────────┘  │
└───────────────────────────────────────────┼───────────────────────────────────────────┘
                                            │ Signed POST /api/webhooks/webrtc
                                            ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                             LARAVEL 11 BACKEND API                                    │
│  - Verifies HMAC Signature (X-Signature Header)                                       │
│  - Persists Video Session Record in PostgreSQL                                        │
│  - Automatically Appends Attendance to Prontuário Único (Timeline & Audit Log)        │
└───────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Telemetry Ingestion Engine & Data Contracts

### 2.1 W3C WebRTC `getStats()` Extraction Standard

Client-side browsers collect WebRTC statistics using standard W3C `RTCPeerConnection.getStats()`. Because browser APIs return cumulative counters (e.g. `bytesReceived`, `packetsLost`, `framesDecoded`), the client or the ingestion engine calculates instantaneous rates by computing deltas over the sampling interval $\Delta t = t_k - t_{k-1}$.

The client aggregates inbound and outbound stats into a normalized payload transmitted every 3 seconds (configurable between 2s and 5s).

### 2.2 Pydantic v2 Telemetry Schemas

```python
# webrtc_service/app/schemas/telemetry.py
from pydantic import BaseModel, Field, field_validator
from typing import Optional, Dict, Any, List
from datetime import datetime
from enum import Enum

class NetworkQualityTier(str, Enum):
    EXCELLENT = "EXCELLENT"  # MOS >= 4.3 (HD Video, high bitrate audio)
    GOOD = "GOOD"            # 4.0 <= MOS < 4.3 (Standard Video, clear audio)
    FAIR = "FAIR"            # 3.6 <= MOS < 4.0 (Minor artifacts, acceptable)
    POOR = "POOR"            # 3.1 <= MOS < 3.6 (Noticeable stutter/distortion)
    BAD = "BAD"              # MOS < 3.1 (Severe degradation, recommend audio-only)

class AudioTrackStats(BaseModel):
    codec: str = Field(default="opus", description="Audio codec used")
    bitrate_kbps: float = Field(ge=0.0, default=0.0, description="Instantaneous audio bitrate in kbps")
    packets_lost: int = Field(ge=0, default=0, description="Cumulative audio packets lost")
    packets_received: int = Field(ge=0, default=0, description="Cumulative audio packets received")
    packet_loss_pct: float = Field(ge=0.0, le=100.0, default=0.0, description="Delta packet loss percentage")
    jitter_ms: float = Field(ge=0.0, default=0.0, description="Audio jitter in milliseconds")
    audio_level: Optional[float] = Field(default=None, ge=0.0, le=1.0, description="Current audio energy level (0.0 to 1.0)")

class VideoTrackStats(BaseModel):
    codec: str = Field(default="VP8", description="Video codec (VP8, VP9, H264)")
    bitrate_kbps: float = Field(ge=0.0, default=0.0, description="Instantaneous video bitrate in kbps")
    frame_width: Optional[int] = Field(default=None, ge=0, description="Current frame width in pixels")
    frame_height: Optional[int] = Field(default=None, ge=0, description="Current frame height in pixels")
    fps: float = Field(ge=0.0, default=0.0, description="Current frames per second")
    packets_lost: int = Field(ge=0, default=0, description="Cumulative video packets lost")
    packets_received: int = Field(ge=0, default=0, description="Cumulative video packets received")
    packet_loss_pct: float = Field(ge=0.0, le=100.0, default=0.0, description="Delta packet loss percentage")
    freeze_count: int = Field(ge=0, default=0, description="Cumulative video freeze events")
    total_freeze_duration_s: float = Field(ge=0.0, default=0.0, description="Total video freeze duration in seconds")
    quality_limitation_reason: Optional[str] = Field(default="none", description="Limitation: 'none', 'cpu', 'bandwidth', 'other'")

class ConnectionStats(BaseModel):
    rtt_ms: float = Field(ge=0.0, default=0.0, description="Round-trip time in milliseconds")
    candidate_type: str = Field(default="host", description="Candidate type: 'host', 'srflx', 'prflx', 'relay'")
    protocol: str = Field(default="udp", description="Transport protocol: 'udp', 'tcp'")
    available_outgoing_bitrate_kbps: Optional[float] = Field(default=None, ge=0.0)
    bytes_sent: int = Field(ge=0, default=0)
    bytes_received: int = Field(ge=0, default=0)

class ClientTelemetryReport(BaseModel):
    room_id: str = Field(..., description="UUID or room code")
    user_id: int = Field(..., description="Authenticated user ID")
    peer_id: str = Field(..., description="Unique WebRTC peer connection ID")
    role: str = Field(..., description="Role: 'tecnico', 'egresso', 'gestor', 'observador'")
    timestamp: datetime = Field(default_factory=datetime.utcnow, description="UTC timestamp of report")
    interval_seconds: float = Field(ge=0.1, le=60.0, default=3.0, description="Sampling delta interval")
    connection: ConnectionStats = Field(default_factory=ConnectionStats)
    audio: AudioTrackStats = Field(default_factory=AudioTrackStats)
    video: Optional[VideoTrackStats] = Field(default=None)
    
    # Inferred/Calculated Fields (populated by backend telemetry engine)
    calculated_mos: Optional[float] = Field(default=None, ge=1.0, le=5.0)
    quality_tier: Optional[NetworkQualityTier] = Field(default=None)

class TelemetryReportAck(BaseModel):
    status: str = "ok"
    room_id: str
    peer_id: str
    mos: float
    quality_tier: NetworkQualityTier
    recommended_action: Optional[str] = None
```

### 2.3 Delta Calculation & Ingestion Pipeline

When client reports cumulative values $C_k$ at time $t_k$, instantaneous metric $M_k$ is derived as:
$$\Delta t = t_k - t_{k-1}$$
$$\Delta \text{PacketsReceived} = \text{PacketsReceived}_k - \text{PacketsReceived}_{k-1}$$
$$\Delta \text{PacketsLost} = \text{PacketsLost}_k - \text{PacketsLost}_{k-1}$$
$$P_{\text{loss\_delta}} = \frac{\Delta \text{PacketsLost}}{\Delta \text{PacketsLost} + \Delta \text{PacketsReceived}} \times 100\%$$
$$\text{Bitrate}_{\text{kbps}} = \frac{(\Delta \text{BytesReceived} \times 8)}{1000 \times \Delta t}$$

---

## 3. ITU-T G.107 / E-Model derived MOS (Mean Opinion Score) Algorithm

### 3.1 Mathematical Foundation of the E-Model

The ITU-T Recommendation G.107 defines the computational model for predicting speech transmission quality. The standard models subjective human perception via a scalar transmission rating factor $R$:

$$R = R_0 - I_s - I_d - I_{e,\text{eff}} + A$$

Where:
- **$R_0$ (Basic Signal-to-Noise Ratio)**: Represents basic transmission quality in the absence of impairments. For standard PSTN narrow-band codecs, $R_0 = 93.2$. For wideband/fullband WebRTC codecs (specifically **Opus** at 48 kHz), default $R_0 = 94.0$ (or up to $95.0$).
- **$I_s$ (Simultaneous Impairment Factor)**: Quantization noise, non-linear distortion, sidetone, and background room noise. In modern digital end-to-end WebRTC channels, $I_s \approx 0.0$ to $1.4$. In our engine, we use $I_s = 1.4$.
- **$I_d$ (Delay Impairment Factor)**: Captures user degradation due to latency, round-trip delay, and echo.
- **$I_{e,\text{eff}}$ (Effective Equipment Impairment Factor)**: Captures degradation caused by low-bitrate codec compression and packet loss, adjusted for packet loss concealment (PLC) and in-band Forward Error Correction (FEC).
- **$A$ (Advantage / Expectation Factor)**: Psychological expectation allowance. For cellular/wireless connections where users tolerate slightly higher latency, $A = 0.0$ to $5.0$. For strict objective scoring, we default to $A = 0.0$ (configurable up to $5.0$ for rural 3G/4G).

---

### 3.2 Mathematical Derivation of Individual Impairments

#### 1. One-Way Delay $d$ & Delay Impairment $I_d$

In WebRTC, total one-way mouth-to-ear delay $d$ (in milliseconds) consists of half the network Round-Trip Time ($\text{RTT}$), the receiver jitter buffer delay $D_{\text{jitter}}$, and audio processing/encoding latency $D_{\text{codec}}$:

$$d = \frac{\text{RTT}}{2} + D_{\text{jitter}} + D_{\text{codec}}$$

*Standard defaults for WebRTC Opus*: $D_{\text{jitter}} \approx 20\text{ms} + 2 \times \text{Jitter}$, $D_{\text{codec}} \approx 20\text{ms}$.

The delay impairment $I_d$ is modeled piecewise as:
$$I_d(d) = 0.024 \cdot d + 0.11 \cdot (d - 177.3) \cdot H(d - 177.3)$$

Where $H(x)$ is the Heaviside step function:
$$H(x) = \begin{cases} 1, & \text{if } x > 0 \\ 0, & \text{if } x \le 0 \end{cases}$$

- For $d \le 177.3\text{ms}$: $I_d = 0.024 \cdot d$ (negligible impact on interactive conversation).
- For $d > 177.3\text{ms}$: $I_d = 0.024 \cdot d + 0.11 \cdot (d - 177.3)$ (rapidly increasing conversational turn-taking impairment).

#### 2. Effective Equipment Impairment $I_{e,\text{eff}}$ (Packet Loss & Codec)

Under packet loss percentage $P_{\text{loss}} \in [0, 100]\%$, the effective equipment impairment is formulated as:

$$I_{e,\text{eff}} = I_e + (95 - I_e) \cdot \frac{P_{\text{loss}}}{P_{\text{loss}} + B_{pl}}$$

Where:
- $I_e$: Intrinsic equipment impairment of the codec at the operating bitrate with zero packet loss. For Opus audio at $\ge 32\text{ kbps}$, $I_e = 5.0$.
- $B_{pl}$: Packet-loss robustness factor of the codec. Opus features advanced Packet Loss Concealment (PLC) and in-band Forward Error Correction (FEC), giving it high resilience ($B_{pl} \approx 15.0$ to $20.0$, compared to $B_{pl}=4.3$ for legacy G.711). In our engine, $B_{pl} = 15.0$.

#### 3. Total R-Factor Clamping

$$R = R_0 - I_s - I_d(d) - I_{e,\text{eff}}(P_{\text{loss}}) + A$$
$$R_{\text{effective}} = \max(0.0, \min(100.0, R))$$

---

### 3.3 Conversion of R-Factor to Mean Opinion Score (MOS)

The non-linear mapping from the transmission rating factor $R \in [0, 100]$ to the 5-point subjective MOS scale is given by the ITU-T G.107 standard curve:

$$\text{MOS} = \begin{cases} 
1.0, & \text{if } R < 0 \\
1.0 + 0.035 \cdot R + 7 \times 10^{-6} \cdot R \cdot (R - 60) \cdot (100 - R), & \text{if } 0 \le R \le 100 \\
4.5, & \text{if } R > 100 
\end{cases}$$

For wideband/fullband WebRTC extension (ITU-T P.800.1 MOS-LQSW / MOS-CQSW), MOS values are bounded between $1.0$ (worst) and $5.0$ (best).

```
   MOS Scale (1.0 - 5.0)
     5.0 ┌─────────────────────────────────────────────────────────────┐ (Wideband Max)
     4.5 │                                                    .────────┘ 
     4.0 │                                        .───────────'          (Good / Excellent)
     3.5 │                            .───────────'                      (Fair)
     3.0 │                .───────────'                                  (Poor - Alert Threshold)
     2.0 │    .───────────'                                              (Bad)
     1.0 └────┴───────────┴───────────┴───────────┴───────────┴──────────┘
         0   20          40          60          80          100     R-Factor
```

---

### 3.4 Python Implementation: `mos.py`

```python
# webrtc_service/app/telemetry/mos.py
import math
from typing import NamedTuple

class MOSCalculationResult(NamedTuple):
    mos: float
    r_factor: float
    one_way_delay_ms: float
    delay_impairment: float
    equipment_impairment: float
    quality_tier: str

class EModelMOSCalculator:
    """
    ITU-T G.107 E-Model MOS (Mean Opinion Score) Calculator tailored for WebRTC (Opus Codec).
    """
    def __init__(
        self,
        r0: float = 94.0,           # Basic signal-to-noise ratio (Opus Wideband)
        is_impairment: float = 1.4, # Simultaneous impairment
        ie_codec: float = 5.0,      # Equipment impairment for Opus at >=32kbps
        b_pl: float = 15.0,         # Packet loss robustness factor (Opus FEC/PLC)
        advantage_factor: float = 0.0 # Advantage factor (0.0 standard, up to 5.0 for cellular)
    ):
        self.r0 = r0
        self.is_impairment = is_impairment
        self.ie_codec = ie_codec
        self.b_pl = b_pl
        self.advantage_factor = advantage_factor

    def compute_one_way_delay(self, rtt_ms: float, jitter_ms: float) -> float:
        """
        Calculates one-way mouth-to-ear delay: d = (RTT / 2) + JitterBuffer + CodecDelay
        """
        jitter_buffer_delay = 20.0 + (2.0 * jitter_ms)
        codec_processing_delay = 20.0  # 20ms audio frame packetization/encoding
        return (rtt_ms / 2.0) + jitter_buffer_delay + codec_processing_delay

    def compute_delay_impairment(self, d: float) -> float:
        """
        ITU-T G.107 Delay Impairment Id(d) formula.
        """
        if d <= 0.0:
            return 0.0
        
        id_val = 0.024 * d
        if d > 177.3:
            id_val += 0.11 * (d - 177.3)
        return id_val

    def compute_equipment_impairment(self, packet_loss_pct: float) -> float:
        """
        ITU-T G.107 Equipment Impairment Ie,eff as a function of packet loss and codec robustness.
        """
        p_loss = max(0.0, min(100.0, packet_loss_pct))
        if p_loss == 0.0:
            return self.ie_codec
        
        ie_eff = self.ie_codec + (95.0 - self.ie_codec) * (p_loss / (p_loss + self.b_pl))
        return ie_eff

    def calculate_r_factor(self, rtt_ms: float, jitter_ms: float, packet_loss_pct: float) -> tuple[float, float, float, float]:
        d = self.compute_one_way_delay(rtt_ms, jitter_ms)
        id_imp = self.compute_delay_impairment(d)
        ie_eff = self.compute_equipment_impairment(packet_loss_pct)
        
        r = self.r0 - self.is_impairment - id_imp - ie_eff + self.advantage_factor
        r_clamped = max(0.0, min(100.0, r))
        return r_clamped, d, id_imp, ie_eff

    def r_to_mos(self, r: float) -> float:
        """
        Non-linear conversion from R-Factor (0-100) to MOS (1.0-5.0).
        """
        if r <= 0.0:
            return 1.0
        if r >= 100.0:
            return 4.5
        
        # ITU-T G.107 Standard Polynomial
        mos = 1.0 + (0.035 * r) + (7.0e-6 * r * (r - 60.0) * (100.0 - r))
        
        # Clamp to valid MOS boundaries
        return round(max(1.0, min(5.0, mos)), 2)

    def classify_tier(self, mos: float) -> str:
        if mos >= 4.3:
            return "EXCELLENT"
        elif mos >= 4.0:
            return "GOOD"
        elif mos >= 3.6:
            return "FAIR"
        elif mos >= 3.1:
            return "POOR"
        else:
            return "BAD"

    def evaluate(self, rtt_ms: float, jitter_ms: float, packet_loss_pct: float) -> MOSCalculationResult:
        r, d, id_imp, ie_eff = self.calculate_r_factor(rtt_ms, jitter_ms, packet_loss_pct)
        mos = self.r_to_mos(r)
        tier = self.classify_tier(mos)
        return MOSCalculationResult(
            mos=mos,
            r_factor=round(r, 2),
            one_way_delay_ms=round(d, 2),
            delay_impairment=round(id_imp, 2),
            equipment_impairment=round(ie_eff, 2),
            quality_tier=tier
        )
```

---

### 3.5 Reference MOS Evaluation Matrix (Calibration Table)

| Test Vector Profile | RTT ($ms$) | Jitter ($ms$) | Packet Loss ($\%$) | $d$ ($ms$) | $I_d$ | $I_{e,\text{eff}}$ | $R$-Factor | Expected MOS | Quality Tier |
|---|---|---|---|---|---|---|---|---|---|
| **Pristine Fiber / Ethernet** | 10.0 | 1.0 | 0.0% | 47.0 | 1.13 | 5.00 | 86.47 | **4.32** | EXCELLENT |
| **Typical 4G/5G Cellular** | 50.0 | 8.0 | 0.5% | 81.0 | 1.94 | 7.90 | 82.76 | **4.20** | GOOD |
| **Moderate Cellular Jitter** | 120.0 | 25.0 | 2.0% | 150.0 | 3.60 | 15.59 | 73.41 | **3.76** | FAIR |
| **Rural 3G / High Delay** | 280.0 | 40.0 | 5.0% | 260.0 | 15.34 | 27.50 | 49.76 | **2.56** | BAD |
| **Severe Congestion / Loss** | 450.0 | 80.0 | 15.0% | 425.0 | 37.45 | 50.00 | 5.15 | **1.18** | BAD (Alert Triggered) |

---

## 4. Telemetry Aggregator & Session Summary Metrics

### 4.1 Redis State Management & Time Series

To handle distributed multi-instance execution and memory efficiency, telemetry samples are tracked using Redis hashes and sliding window lists with automatic TTL:

- `webrtc:room:{room_id}:telemetry:{peer_id}` (Redis List of serialized telemetry snapshots, max length 600 samples = 30 minutes at 3s intervals, TTL = 2 hours).
- `webrtc:room:{room_id}:summary` (Redis Hash of accumulated stats: `total_samples`, `sum_mos`, `min_mos`, `max_mos`, `total_loss_events`, `bytes_sent`, `bytes_received`).

### 4.2 Aggregation Engine Implementation: `aggregator.py`

```python
# webrtc_service/app/telemetry/aggregator.py
import math
from typing import List, Dict, Any, Optional
from datetime import datetime
from pydantic import BaseModel, Field
from .mos import EModelMOSCalculator, MOSCalculationResult

class QualityDistribution(BaseModel):
    excellent_pct: float = Field(0.0, description="% time MOS >= 4.3")
    good_pct: float = Field(0.0, description="% time 4.0 <= MOS < 4.3")
    fair_pct: float = Field(0.0, description="% time 3.6 <= MOS < 4.0")
    poor_pct: float = Field(0.0, description="% time 3.1 <= MOS < 3.6")
    bad_pct: float = Field(0.0, description="% time MOS < 3.1")

class SessionTelemetrySummary(BaseModel):
    room_id: str
    peer_id: str
    user_id: int
    role: str
    sample_count: int
    duration_seconds: float
    
    # MOS Metrics
    avg_mos: float
    min_mos: float
    max_mos: float
    p95_mos: float
    overall_quality_tier: str
    quality_distribution: QualityDistribution
    
    # Network Metrics
    avg_rtt_ms: float
    max_rtt_ms: float
    avg_jitter_ms: float
    max_jitter_ms: float
    overall_packet_loss_pct: float
    
    # Media & Throughput
    avg_video_bitrate_kbps: float
    avg_audio_bitrate_kbps: float
    total_bytes_transferred: int
    
    # Video Health
    avg_fps: float
    total_freezes: int
    total_freeze_duration_s: float
    resolution_changes_count: int
    final_resolution: str
    
    # Alerts Triggered
    poor_network_alerts_count: int

class SessionAggregator:
    def __init__(self, room_id: str, mos_calculator: Optional[EModelMOSCalculator] = None):
        self.room_id = room_id
        self.mos_calculator = mos_calculator or EModelMOSCalculator()
        self.peer_samples: Dict[str, List[Dict[str, Any]]] = {}
        self.alerts_triggered: Dict[str, int] = {}
        self.start_times: Dict[str, datetime] = {}
        self.last_resolutions: Dict[str, str] = {}
        self.resolution_change_counts: Dict[str, int] = {}

    def record_sample(self, peer_id: str, user_id: int, role: str, raw_sample: Dict[str, Any]) -> MOSCalculationResult:
        if peer_id not in self.peer_samples:
            self.peer_samples[peer_id] = []
            self.alerts_triggered[peer_id] = 0
            self.start_times[peer_id] = datetime.utcnow()
            self.resolution_change_counts[peer_id] = 0
            self.last_resolutions[peer_id] = "unknown"

        conn = raw_sample.get("connection", {})
        audio = raw_sample.get("audio", {})
        video = raw_sample.get("video", {})

        rtt = float(conn.get("rtt_ms", 0.0))
        jitter = float(audio.get("jitter_ms", 0.0))
        loss = float(audio.get("packet_loss_pct", 0.0))

        # Evaluate MOS
        eval_res = self.mos_calculator.evaluate(rtt_ms=rtt, jitter_ms=jitter, packet_loss_pct=loss)

        # Check resolution change
        if video:
            res_str = f"{video.get('frame_width', 0)}x{video.get('frame_height', 0)}"
            if self.last_resolutions[peer_id] != "unknown" and self.last_resolutions[peer_id] != res_str:
                self.resolution_change_counts[peer_id] += 1
            self.last_resolutions[peer_id] = res_str

        # Check alert threshold (MOS < 3.2 or Loss > 10% or RTT > 350ms)
        if eval_res.mos < 3.2 or loss >= 10.0 or rtt >= 350.0:
            self.alerts_triggered[peer_id] += 1

        sample_record = {
            "timestamp": datetime.utcnow().isoformat(),
            "user_id": user_id,
            "role": role,
            "rtt_ms": rtt,
            "jitter_ms": jitter,
            "packet_loss_pct": loss,
            "mos": eval_res.mos,
            "quality_tier": eval_res.quality_tier,
            "audio_bitrate_kbps": float(audio.get("bitrate_kbps", 0.0)),
            "video_bitrate_kbps": float(video.get("bitrate_kbps", 0.0)) if video else 0.0,
            "fps": float(video.get("fps", 0.0)) if video else 0.0,
            "bytes_sent": int(conn.get("bytes_sent", 0)),
            "bytes_received": int(conn.get("bytes_received", 0)),
            "freezes": int(video.get("freeze_count", 0)) if video else 0,
            "freeze_duration_s": float(video.get("total_freeze_duration_s", 0.0)) if video else 0.0,
            "resolution": self.last_resolutions[peer_id]
        }
        self.peer_samples[peer_id].append(sample_record)
        return eval_res

    def generate_summary(self, peer_id: str) -> Optional[SessionTelemetrySummary]:
        samples = self.peer_samples.get(peer_id, [])
        if not samples:
            return None

        sample_count = len(samples)
        first_sample = samples[0]
        last_sample = samples[-1]

        mos_list = [s["mos"] for s in samples]
        rtt_list = [s["rtt_ms"] for s in samples]
        jitter_list = [s["jitter_ms"] for s in samples]
        loss_list = [s["packet_loss_pct"] for s in samples]
        v_bitrate_list = [s["video_bitrate_kbps"] for s in samples]
        a_bitrate_list = [s["audio_bitrate_kbps"] for s in samples]
        fps_list = [s["fps"] for s in samples]

        # Quality distribution breakdown
        tiers = {"EXCELLENT": 0, "GOOD": 0, "FAIR": 0, "POOR": 0, "BAD": 0}
        for s in samples:
            tiers[s["quality_tier"]] = tiers.get(s["quality_tier"], 0) + 1

        dist = QualityDistribution(
            excellent_pct=round((tiers["EXCELLENT"] / sample_count) * 100.0, 1),
            good_pct=round((tiers["GOOD"] / sample_count) * 100.0, 1),
            fair_pct=round((tiers["FAIR"] / sample_count) * 100.0, 1),
            poor_pct=round((tiers["POOR"] / sample_count) * 100.0, 1),
            bad_pct=round((tiers["BAD"] / sample_count) * 100.0, 1)
        )

        sorted_mos = sorted(mos_list)
        p95_idx = int(math.ceil(0.95 * sample_count)) - 1
        p95_mos = sorted_mos[max(0, min(sample_count - 1, p95_idx))]

        avg_mos = round(sum(mos_list) / sample_count, 2)
        total_bytes = last_sample["bytes_sent"] + last_sample["bytes_received"]
        duration = (datetime.utcnow() - self.start_times[peer_id]).total_seconds()

        return SessionTelemetrySummary(
            room_id=self.room_id,
            peer_id=peer_id,
            user_id=first_sample["user_id"],
            role=first_sample["role"],
            sample_count=sample_count,
            duration_seconds=round(duration, 1),
            avg_mos=avg_mos,
            min_mos=min(mos_list),
            max_mos=max(mos_list),
            p95_mos=p95_mos,
            overall_quality_tier=self.mos_calculator.classify_tier(avg_mos),
            quality_distribution=dist,
            avg_rtt_ms=round(sum(rtt_list) / sample_count, 1),
            max_rtt_ms=max(rtt_list),
            avg_jitter_ms=round(sum(jitter_list) / sample_count, 1),
            max_jitter_ms=max(jitter_list),
            overall_packet_loss_pct=round(sum(loss_list) / sample_count, 2),
            avg_video_bitrate_kbps=round(sum(v_bitrate_list) / sample_count, 1),
            avg_audio_bitrate_kbps=round(sum(a_bitrate_list) / sample_count, 1),
            total_bytes_transferred=total_bytes,
            avg_fps=round(sum(fps_list) / sample_count, 1),
            total_freezes=last_sample["freezes"],
            total_freeze_duration_s=last_sample["freeze_duration_s"],
            resolution_changes_count=self.resolution_change_counts[peer_id],
            final_resolution=self.last_resolutions[peer_id],
            poor_network_alerts_count=self.alerts_triggered[peer_id]
        )
```

---

## 5. Reliable HMAC-SHA256 Webhook Dispatcher

### 5.1 Security & Signature Specification

All webhook communications from `webrtc_service/` to Laravel (`POST /api/webhooks/webrtc`) must be cryptographically signed using a pre-shared symmetric key (`WEBHOOK_SECRET` / `WEBRTC_SHARED_SECRET`).

#### Signature Generation Algorithm
1. Serialize the payload into canonical JSON bytes UTF-8 (no extra spaces, key order preserved or deterministic).
2. Calculate HMAC using SHA-256:
   $$\text{Signature} = \text{HMAC-SHA256}(\text{key} = \text{WEBHOOK\_SECRET}, \text{msg} = \text{raw\_body\_bytes}).\text{hexdigest}()$$
3. Attach header:
   - Primary: `X-Signature: sha256=<hex_digest>`
   - Compatibility alias: `X-Signature-SHA256: <hex_digest>`
   - Content type: `Content-Type: application/json`
   - Timestamp: `X-Webhook-Timestamp: <unix_epoch_seconds>` (to protect against replay attacks on Laravel).

---

### 5.2 Retry Architecture with Exponential Backoff & Jitter

When sending webhooks across microservices, transient network partitions, container restarts, or Laravel queue worker busy states can cause temporary 5xx errors or timeouts.

#### Backoff Algorithm
For retry attempt $k \in \{1, 2, 3, 4, 5\}$:
$$\text{BaseDelay} = T_{\text{base}} \times 2^{k-1}$$
$$\text{Jitter} = \text{random\_uniform}(-0.2 \times \text{BaseDelay}, +0.2 \times \text{BaseDelay})$$
$$T_{\text{wait}} = \min(T_{\text{max}}, \text{BaseDelay} + \text{Jitter})$$

*Default Parameters*: $T_{\text{base}} = 1.0\text{s}$, $T_{\text{max}} = 30.0\text{s}$, $\text{MaxAttempts} = 5$.
- Attempt 1: immediate
- Attempt 2: $\approx 1.0\text{s} \pm 200\text{ms}$
- Attempt 3: $\approx 2.0\text{s} \pm 400\text{ms}$
- Attempt 4: $\approx 4.0\text{s} \pm 800\text{ms}$
- Attempt 5: $\approx 8.0\text{s} \pm 1.6\text{s}$

*Non-retryable conditions*: HTTP status codes 400, 401, 403, 404, 422 (indicates configuration or schema mismatch). HTTP 429 (Rate Limit) and 5xx (Internal Error) are strictly retried.

#### Dead-Letter Queue (DLQ)
If all 5 attempts fail, the webhook payload, timestamp, target URL, and complete error history are serialized and pushed to Redis key:
`webrtc:webhook_dlq` (Redis List). This ensures zero event loss and allows administrative inspection and replay.

---

### 5.3 Webhook Dispatcher Implementation: `webhooks.py`

```python
# webrtc_service/app/webhooks/dispatcher.py
import hmac
import hashlib
import json
import asyncio
import random
import logging
from typing import Dict, Any, Optional
from datetime import datetime
import httpx
from pydantic import BaseModel

logger = logging.getLogger("webrtc_service.webhooks")

class WebhookDeliveryResult(BaseModel):
    success: bool
    event: str
    room_id: str
    status_code: Optional[int] = None
    attempts: int
    duration_ms: float
    error_message: Optional[str] = None

class WebhookDispatcher:
    """
    Reliable, non-blocking HMAC-SHA256 signed webhook dispatcher with exponential backoff.
    """
    def __init__(
        self,
        endpoint_url: str,
        secret_key: str,
        max_retries: int = 5,
        base_delay_s: float = 1.0,
        max_delay_s: float = 30.0,
        timeout_s: float = 8.0,
        redis_client: Optional[Any] = None
    ):
        self.endpoint_url = endpoint_url
        self.secret_key = secret_key.encode("utf-8")
        self.max_retries = max_retries
        self.base_delay_s = base_delay_s
        self.max_delay_s = max_delay_s
        self.timeout_s = timeout_s
        self.redis_client = redis_client
        self._http_client: Optional[httpx.AsyncClient] = None

    async def get_client(self) -> httpx.AsyncClient:
        if self._http_client is None or self._http_client.is_closed:
            self._http_client = httpx.AsyncClient(
                timeout=httpx.Timeout(self.timeout_s, connect=3.0),
                limits=httpx.Limits(max_keepalive_connections=20, max_connections=100)
            )
        return self._http_client

    async def close(self):
        if self._http_client and not self._http_client.is_closed:
            await self._http_client.aclose()

    def generate_signature(self, payload_bytes: bytes) -> str:
        """Computes HMAC-SHA256 hex digest for request body."""
        return hmac.new(self.secret_key, payload_bytes, hashlib.sha256).hexdigest()

    async def dispatch(self, event_type: str, room_id: str, payload_data: Dict[str, Any]) -> WebhookDeliveryResult:
        """
        Dispatches a signed webhook asynchronously with automatic retry and DLQ fallback.
        """
        start_time = asyncio.get_event_loop().time()
        
        envelope = {
            "event": event_type,
            "timestamp": datetime.utcnow().isoformat() + "Z",
            "room_id": room_id,
            "data": payload_data
        }
        
        body_bytes = json.dumps(envelope, ensure_ascii=False, separators=(',', ':')).encode("utf-8")
        signature_hex = self.generate_signature(body_bytes)
        
        headers = {
            "Content-Type": "application/json",
            "X-Signature": f"sha256={signature_hex}",
            "X-Signature-SHA256": signature_hex,
            "X-Webhook-Timestamp": str(int(datetime.utcnow().timestamp())),
            "User-Agent": "ConectaEgresso-WebRTC-Dispatcher/1.0"
        }

        client = await self.get_client()
        last_error = None
        last_status = None

        for attempt in range(1, self.max_retries + 1):
            try:
                logger.info(f"Dispatching webhook '{event_type}' for room '{room_id}' (Attempt {attempt}/{self.max_retries})")
                response = await client.post(self.endpoint_url, content=body_bytes, headers=headers)
                last_status = response.status_code

                if response.is_success:
                    elapsed = (asyncio.get_event_loop().time() - start_time) * 1000.0
                    logger.info(f"Webhook '{event_type}' successfully delivered (HTTP {response.status_code}, {elapsed:.1f}ms)")
                    return WebhookDeliveryResult(
                        success=True,
                        event=event_type,
                        room_id=room_id,
                        status_code=response.status_code,
                        attempts=attempt,
                        duration_ms=round(elapsed, 2)
                    )
                
                # Check if error is client 4xx (non-retryable, except 429)
                if 400 <= response.status_code < 500 and response.status_code != 429:
                    err_msg = f"Non-retryable HTTP {response.status_code}: {response.text[:200]}"
                    logger.error(err_msg)
                    break

                last_error = f"HTTP {response.status_code}: {response.text[:200]}"

            except (httpx.ConnectError, httpx.TimeoutException, httpx.NetworkError) as exc:
                last_error = f"{type(exc).__name__}: {str(exc)}"
                logger.warning(f"Network error on attempt {attempt}: {last_error}")

            # If attempts remaining, calculate backoff with jitter
            if attempt < self.max_retries:
                base = min(self.max_delay_s, self.base_delay_s * (2 ** (attempt - 1)))
                jitter = random.uniform(-0.2 * base, 0.2 * base)
                sleep_delay = max(0.1, base + jitter)
                logger.info(f"Waiting {sleep_delay:.2f}s before retry attempt {attempt + 1}")
                await asyncio.sleep(sleep_delay)

        elapsed = (asyncio.get_event_loop().time() - start_time) * 1000.0
        logger.error(f"All {self.max_retries} attempts failed for webhook '{event_type}'. Escalating to DLQ.")

        # Push to Redis Dead-Letter Queue (DLQ) if Redis is available
        if self.redis_client:
            dlq_entry = {
                "event": event_type,
                "room_id": room_id,
                "url": self.endpoint_url,
                "envelope": envelope,
                "headers": headers,
                "failed_at": datetime.utcnow().isoformat(),
                "attempts": self.max_retries,
                "last_status": last_status,
                "error": last_error
            }
            try:
                await self.redis_client.rpush("webrtc:webhook_dlq", json.dumps(dlq_entry))
                logger.info(f"Stored failed webhook in Redis DLQ (key: webrtc:webhook_dlq)")
            except Exception as redis_exc:
                logger.critical(f"Failed to persist webhook to Redis DLQ: {redis_exc}")

        return WebhookDeliveryResult(
            success=False,
            event=event_type,
            room_id=room_id,
            status_code=last_status,
            attempts=self.max_retries,
            duration_ms=round(elapsed, 2),
            error_message=last_error
        )
```

---

### 5.4 Complete Webhook Event Catalog

#### 1. `session.started`
Triggered when both Technician and Egresso have connected and WebRTC media streams are established.

```json
{
  "event": "session.started",
  "timestamp": "2026-08-17T14:30:00Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "data": {
    "room_code": "ATD-VIX-2026-0042",
    "prontuario_id": "550e8400-e29b-41d4-a716-446655440000",
    "tecnico_id": 14,
    "egresso_id": 892,
    "municipio_ibge": "3205309",
    "started_at": "2026-08-17T14:30:00Z",
    "media_mode": "video_audio"
  }
}
```

#### 2. `session.ended` (Mandatory for Prontuário Único Automation)
Triggered upon room teardown or hangup. Transmits full aggregated telemetry for automatic audit logging and attendance records in Laravel.

```json
{
  "event": "session.ended",
  "timestamp": "2026-08-17T14:45:30Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "data": {
    "room_code": "ATD-VIX-2026-0042",
    "prontuario_id": "550e8400-e29b-41d4-a716-446655440000",
    "tecnico_id": 14,
    "egresso_id": 892,
    "started_at": "2026-08-17T14:30:00Z",
    "ended_at": "2026-08-17T14:45:30Z",
    "duration_seconds": 930,
    "hangup_reason": "normal_closure",
    "summary_telemetry": {
      "avg_mos": 4.28,
      "min_mos": 3.42,
      "max_mos": 4.45,
      "p95_mos": 4.40,
      "overall_quality_tier": "GOOD",
      "overall_packet_loss_pct": 0.35,
      "avg_rtt_ms": 42.5,
      "max_rtt_ms": 115.0,
      "avg_jitter_ms": 7.2,
      "max_jitter_ms": 22.0,
      "total_bytes_transferred": 68420100,
      "avg_video_bitrate_kbps": 580.4,
      "avg_audio_bitrate_kbps": 32.0,
      "avg_fps": 28.5,
      "total_freezes": 1,
      "total_freeze_duration_s": 1.2,
      "resolution_changes_count": 0,
      "final_resolution": "1280x720",
      "quality_distribution": {
        "excellent_pct": 74.2,
        "good_pct": 21.8,
        "fair_pct": 4.0,
        "poor_pct": 0.0,
        "bad_pct": 0.0
      }
    }
  }
}
```

#### 3. `session.quality_alert`
Triggered during an active call if poor network conditions persist for $\ge 10$ seconds.

```json
{
  "event": "session.quality_alert",
  "timestamp": "2026-08-17T14:38:12Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "data": {
    "user_id": 892,
    "role": "egresso",
    "current_mos": 2.45,
    "packet_loss_pct": 14.8,
    "rtt_ms": 480.0,
    "quality_tier": "BAD",
    "recommended_action": "switch_to_audio_only",
    "message": "Conexão móvel 3G instável. Recomenda-se desativar o vídeo para preservar o áudio."
  }
}
```

#### 4. `attendee.joined_queue` & `attendee.admitted`
Triggered when an egresso enters or is called from the virtual waiting room.

```json
{
  "event": "attendee.admitted",
  "timestamp": "2026-08-17T14:29:45Z",
  "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
  "data": {
    "ticket_id": "TCK-2026-0891",
    "unit_id": "unidade-vitoria",
    "egresso_id": 892,
    "tecnico_id": 14,
    "wait_duration_seconds": 184
  }
}
```

---

## 6. Comprehensive Pytest Testing Strategy

### 6.1 Test Suite Organization

The testing framework for `webrtc_service/` leverages `pytest`, `pytest-asyncio`, `httpx`, and `respx` to test MOS calculations, telemetry aggregations, cryptographic signatures, retry policies, and WebSocket streaming without requiring external services.

```
webrtc_service/
└── tests/
    ├── conftest.py                     # Fixtures: mock Redis, respx mock HTTP, telemetry sample generators
    ├── test_mos_calculator.py          # Unit tests: ITU-T G.107 math, boundary conditions, calibration vectors
    ├── test_telemetry_schemas.py       # Pydantic v2 validation & field constraints
    ├── test_telemetry_aggregator.py    # Time-series sliding window, summary stats, quality distribution
    ├── test_webhook_crypto.py          # HMAC-SHA256 signature verification & tamper rejection
    ├── test_webhook_dispatcher.py      # Retries, exponential backoff, status code handling, DLQ fallback
    ├── test_quality_alert_engine.py    # Alert threshold triggers & client advisory messages
    └── test_end_to_end_telemetry.py    # Full flow: WebSocket ingest -> MOS eval -> Aggregation -> Webhook
```

---

### 6.2 Pytest Fixtures (`conftest.py`)

```python
# webrtc_service/tests/conftest.py
import pytest
import pytest_asyncio
import respx
import json
from unittest.mock import AsyncMock
from app.telemetry.mos import EModelMOSCalculator
from app.webhooks.dispatcher import WebhookDispatcher
from app.telemetry.aggregator import SessionAggregator

@pytest.fixture
def mos_calculator():
    return EModelMOSCalculator(r0=94.0, is_impairment=1.4, ie_codec=5.0, b_pl=15.0)

@pytest.fixture
def mock_redis():
    redis = AsyncMock()
    redis.rpush = AsyncMock(return_value=1)
    redis.set = AsyncMock(return_value=True)
    redis.get = AsyncMock(return_value=None)
    return redis

@pytest.fixture
def webhook_secret():
    return "sejus_secret_test_key_conecta_egresso_2026"

@pytest.fixture
def webhook_url():
    return "http://laravel.test/api/webhooks/webrtc"

@pytest.fixture
def webhook_dispatcher(webhook_url, webhook_secret, mock_redis):
    return WebhookDispatcher(
        endpoint_url=webhook_url,
        secret_key=webhook_secret,
        max_retries=3,
        base_delay_s=0.01,  # Fast retries for testing
        max_delay_s=0.05,
        timeout_s=1.0,
        redis_client=mock_redis
    )

@pytest.fixture
def sample_perfect_report():
    return {
        "connection": {"rtt_ms": 12.0, "bytes_sent": 100000, "bytes_received": 200000},
        "audio": {"jitter_ms": 1.5, "packet_loss_pct": 0.0, "bitrate_kbps": 32.0},
        "video": {"fps": 30.0, "bitrate_kbps": 800.0, "frame_width": 1280, "frame_height": 720, "freeze_count": 0, "total_freeze_duration_s": 0.0}
    }

@pytest.fixture
def sample_degraded_report():
    return {
        "connection": {"rtt_ms": 380.0, "bytes_sent": 150000, "bytes_received": 250000},
        "audio": {"jitter_ms": 65.0, "packet_loss_pct": 12.5, "bitrate_kbps": 24.0},
        "video": {"fps": 12.0, "bitrate_kbps": 180.0, "frame_width": 640, "frame_height": 360, "freeze_count": 3, "total_freeze_duration_s": 4.5}
    }
```

---

### 6.3 Test Implementations

#### 1. MOS Calculator Test Suite (`test_mos_calculator.py`)
Validates exact ITU-T G.107 formulas against standardized test profiles.

```python
# webrtc_service/tests/test_mos_calculator.py
import pytest
from app.telemetry.mos import EModelMOSCalculator

def test_mos_perfect_connection(mos_calculator):
    # RTT = 10ms, Jitter = 1ms, Loss = 0% -> Expected MOS ~4.32 (EXCELLENT)
    res = mos_calculator.evaluate(rtt_ms=10.0, jitter_ms=1.0, packet_loss_pct=0.0)
    assert res.mos >= 4.30
    assert res.quality_tier == "EXCELLENT"
    assert res.r_factor > 85.0

def test_mos_typical_4g_connection(mos_calculator):
    # RTT = 50ms, Jitter = 8ms, Loss = 0.5% -> Expected MOS ~4.20 (GOOD)
    res = mos_calculator.evaluate(rtt_ms=50.0, jitter_ms=8.0, packet_loss_pct=0.5)
    assert 4.0 <= res.mos <= 4.3
    assert res.quality_tier == "GOOD"

def test_mos_degraded_3g_connection(mos_calculator):
    # RTT = 250ms, Jitter = 40ms, Loss = 6.0% -> Expected MOS ~2.5 - 3.0 (BAD/POOR)
    res = mos_calculator.evaluate(rtt_ms=250.0, jitter_ms=40.0, packet_loss_pct=6.0)
    assert res.mos < 3.2
    assert res.quality_tier in ["POOR", "BAD"]

def test_mos_severe_loss_clamping(mos_calculator):
    # 50% packet loss -> MOS must clamp to 1.0
    res = mos_calculator.evaluate(rtt_ms=800.0, jitter_ms=150.0, packet_loss_pct=50.0)
    assert res.mos == 1.0
    assert res.quality_tier == "BAD"

def test_mos_zero_delay_boundary(mos_calculator):
    res = mos_calculator.evaluate(rtt_ms=0.0, jitter_ms=0.0, packet_loss_pct=0.0)
    assert 4.3 <= res.mos <= 4.5
```

#### 2. Webhook Dispatcher & HMAC Test Suite (`test_webhook_dispatcher.py`)
Validates signature generation, successful 200 delivery, 500 retry escalation, and DLQ persistence.

```python
# webrtc_service/tests/test_webhook_dispatcher.py
import pytest
import respx
import httpx
import hmac
import hashlib
import json
from app.webhooks.dispatcher import WebhookDispatcher

@pytest.mark.asyncio
@respx.mock
async def test_webhook_successful_delivery(webhook_dispatcher, webhook_url, webhook_secret):
    route = respx.post(webhook_url).mock(return_value=httpx.Response(200, json={"status": "received"}))
    
    payload = {"session_id": "test-123", "duration": 300}
    res = await webhook_dispatcher.dispatch("session.ended", "room-abc", payload)
    
    assert res.success is True
    assert res.status_code == 200
    assert res.attempts == 1
    assert route.called
    
    # Verify cryptographic signature in request header
    request = route.calls.last.request
    expected_sig = hmac.new(webhook_secret.encode(), request.content, hashlib.sha256).hexdigest()
    assert request.headers["X-Signature"] == f"sha256={expected_sig}"
    assert request.headers["X-Signature-SHA256"] == expected_sig

@pytest.mark.asyncio
@respx.mock
async def test_webhook_retry_then_succeed(webhook_dispatcher, webhook_url):
    # First 2 requests return 503 Service Unavailable, 3rd succeeds with 200
    route = respx.post(webhook_url)
    route.side_effect = [
        httpx.Response(503, text="Service Unavailable"),
        httpx.Response(502, text="Bad Gateway"),
        httpx.Response(200, json={"status": "ok"})
    ]
    
    res = await webhook_dispatcher.dispatch("session.started", "room-123", {"user": 1})
    assert res.success is True
    assert res.attempts == 3
    assert route.call_count == 3

@pytest.mark.asyncio
@respx.mock
async def test_webhook_exhaustion_escalates_to_dlq(webhook_dispatcher, webhook_url, mock_redis):
    # All 3 attempts return 500
    respx.post(webhook_url).mock(return_value=httpx.Response(500, text="Internal Server Error"))
    
    res = await webhook_dispatcher.dispatch("session.ended", "room-fail", {"user": 2})
    assert res.success is False
    assert res.attempts == 3
    assert mock_redis.rpush.called
    
    # Inspect DLQ payload
    call_args = mock_redis.rpush.call_args[0]
    assert call_args[0] == "webrtc:webhook_dlq"
    dlq_data = json.loads(call_args[1])
    assert dlq_data["event"] == "session.ended"
    assert dlq_data["room_id"] == "room-fail"
```

#### 3. Session Aggregator Test Suite (`test_telemetry_aggregator.py`)
Validates time-series stats compilation, P95 calculation, quality distribution breakdown, and resolution tracking.

```python
# webrtc_service/tests/test_telemetry_aggregator.py
import pytest
from app.telemetry.aggregator import SessionAggregator

def test_aggregator_summary_generation(sample_perfect_report, sample_degraded_report):
    agg = SessionAggregator(room_id="room-vitoria-101")
    peer = "peer-egresso-01"
    
    # Feed 8 perfect samples
    for _ in range(8):
        agg.record_sample(peer, user_id=100, role="egresso", raw_sample=sample_perfect_report)
    
    # Feed 2 degraded samples
    for _ in range(2):
        agg.record_sample(peer, user_id=100, role="egresso", raw_sample=sample_degraded_report)
        
    summary = agg.generate_summary(peer)
    assert summary is not None
    assert summary.sample_count == 10
    assert summary.room_id == "room-vitoria-101"
    assert summary.role == "egresso"
    assert summary.min_mos < summary.max_mos
    assert summary.quality_distribution.excellent_pct == 80.0
    assert summary.poor_network_alerts_count == 2
    assert summary.resolution_changes_count == 1  # Switched from 720p to 360p
    assert summary.final_resolution == "640x360"
```

---

## 7. Integration Contract with Laravel Backend (`M3/M4`)

To ensure seamless coordination between Laravel and Python:

1. **Shared Secret Key Synchronization**:
   - Docker / `.env` variable: `WEBRTC_WEBHOOK_SECRET` must be identical in Laravel `.env` and `webrtc_service/.env`.
2. **Laravel Webhook Route**:
   - `POST /api/webhooks/webrtc` (handled by `App\Http\Controllers\WebRTCWebhookController`).
   - Verifies header: `hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), config('webrtc.secret')), $request->header('X-Signature'))`.
3. **Database Automation**:
   - On `session.ended`, Laravel updates table `video_rooms` (status = `finished`, duration, metrics JSON).
   - Injects a `ProntuarioTimeline` event categorized as `atendimento_remoto_video` linked to `prontuario_id`, with technician CPF, date/time, and telemetry summary.
   - Automatically writes an immutable audit record to `prontuario_audit_logs`.

---

## 8. Summary of Deliverables & Verification Plan

| Component | Target File | Role & Functionality | Verification Target |
|---|---|---|---|
| **Schemas** | `webrtc_service/app/schemas/telemetry.py` | Pydantic v2 models for `getStats()` parsing & validation | 100% schema validation pass |
| **MOS Engine** | `webrtc_service/app/telemetry/mos.py` | ITU-T G.107 E-Model MOS calculator (Opus tuned) | Exact match with test vectors (±0.05 MOS) |
| **Aggregator** | `webrtc_service/app/telemetry/aggregator.py` | Sliding window stats, P95 MOS, quality distribution | Accurate metric accumulation & alerts |
| **Webhook Client** | `webrtc_service/app/webhooks/dispatcher.py` | Async HTTP client, HMAC signing, exponential backoff, DLQ | 100% retry & HMAC tamper test pass |
| **Test Suite** | `webrtc_service/tests/` | Comprehensive Pytest suite with mock Redis & mock HTTP | `pytest` passing with >90% coverage |
