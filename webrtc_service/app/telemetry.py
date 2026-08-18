"""
ITU-T G.107 E-Model MOS Scoring Engine & Session Telemetry Aggregator
Tuned for WebRTC Opus/VP8 real-time streams over 3G/4G/5G mobile networks.
"""

import math
from typing import Dict, Any, Optional, List, NamedTuple
from datetime import datetime
from .schemas import (
    NetworkQualityTier,
    ClientTelemetryReport,
    TelemetryReportAck,
    QualityDistribution,
    SessionTelemetrySummary,
    QualityAlertMessage
)


class MOSCalculationResult(NamedTuple):
    mos: float
    r_factor: float
    one_way_delay_ms: float
    delay_impairment: float
    equipment_impairment: float
    quality_tier: NetworkQualityTier


class EModelMOSCalculator:
    """
    Standardized ITU-T G.107 E-Model computation engine tuned for Opus wideband codec.
    """
    def __init__(
        self,
        r0: float = 94.2,             # Basic signal-to-noise ratio
        is_impairment: float = 0.0,   # Simultaneous impairment
        ie_codec: float = 0.0,        # Base equipment impairment
        b_pl: float = 15.0,           # Packet loss robustness factor
        advantage_factor: float = 0.0 # Advantage factor
    ):
        self.r0 = r0
        self.is_impairment = is_impairment
        self.ie_codec = ie_codec
        self.b_pl = b_pl
        self.advantage_factor = advantage_factor

    def compute_one_way_delay(self, rtt_ms: float, jitter_ms: float) -> float:
        """
        Calculates effective one-way latency: d = RTT + 2 * Jitter
        """
        return max(0.0, rtt_ms + (2.0 * jitter_ms))

    def compute_delay_impairment(self, d: float) -> float:
        """
        Calculates delay impairment Id(d) from effective latency d (ms).
        """
        if d < 160.0:
            return d / 40.0
        return (d - 120.0) / 10.0

    def compute_equipment_impairment(self, packet_loss_pct: float) -> float:
        """
        Calculates effective equipment impairment Ie,eff from packet loss percentage (0-100%).
        """
        p_loss = max(0.0, min(100.0, packet_loss_pct)) / 100.0
        return 30.0 * math.log(1.0 + (15.0 * p_loss))

    def calculate_r_factor(self, rtt_ms: float, jitter_ms: float, packet_loss_pct: float) -> tuple[float, float, float, float]:
        d = self.compute_one_way_delay(rtt_ms, jitter_ms)
        id_imp = self.compute_delay_impairment(d)
        ie_eff = self.compute_equipment_impairment(packet_loss_pct)

        r = self.r0 - self.is_impairment - id_imp - ie_eff + self.advantage_factor
        r_clamped = max(0.0, min(100.0, r))
        return r_clamped, d, id_imp, ie_eff


    def r_to_mos(self, r: float) -> float:
        """
        Non-linear polynomial mapping from R-Factor (0-100) to MOS (1.0-5.0).
        """
        if r <= 0.0:
            return 1.0
        if r >= 100.0:
            return 4.5

        # ITU-T G.107 standard mapping polynomial
        mos = 1.0 + (0.035 * r) + (7.0e-6 * r * (r - 60.0) * (100.0 - r))
        return round(max(1.0, min(5.0, mos)), 2)

    def classify_tier(self, mos: float) -> NetworkQualityTier:
        if mos >= 4.3:
            return NetworkQualityTier.EXCELLENT
        elif mos >= 4.0:
            return NetworkQualityTier.GOOD
        elif mos >= 3.6:
            return NetworkQualityTier.FAIR
        elif mos >= 3.1:
            return NetworkQualityTier.POOR
        else:
            return NetworkQualityTier.BAD

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


def calculate_mos(rtt_ms: float, jitter_ms: float, packet_loss_pct: float) -> float:
    """Convenience functional helper for instant MOS evaluation."""
    calculator = EModelMOSCalculator()
    return calculator.evaluate(rtt_ms, jitter_ms, packet_loss_pct).mos


class SessionAggregator:
    """
    Collects time-series telemetry reports, aggregates quality metrics,
    and produces consolidated session summaries for Prontuário Único recording.
    """
    def __init__(self, room_id: str, mos_calculator: Optional[EModelMOSCalculator] = None):
        self.room_id = room_id
        self.mos_calculator = mos_calculator or EModelMOSCalculator()
        self.peer_samples: Dict[str, List[Dict[str, Any]]] = {}
        self.alerts_triggered: Dict[str, int] = {}
        self.start_times: Dict[str, datetime] = {}
        self.last_resolutions: Dict[str, str] = {}
        self.resolution_change_counts: Dict[str, int] = {}

    def record_sample(
        self,
        peer_id: str,
        user_id: int,
        role: str,
        raw_sample: Dict[str, Any]
    ) -> MOSCalculationResult:
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

        # Track resolution changes
        if video:
            w = video.get("frame_width", 0)
            h = video.get("frame_height", 0)
            if w and h:
                res_str = f"{w}x{h}"
                if self.last_resolutions[peer_id] != "unknown" and self.last_resolutions[peer_id] != res_str:
                    self.resolution_change_counts[peer_id] += 1
                self.last_resolutions[peer_id] = res_str

        # Check alert conditions (MOS < 3.2 or Loss >= 10% or RTT >= 350ms)
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
            "quality_tier": eval_res.quality_tier.value,
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
            t = s["quality_tier"]
            tiers[t] = tiers.get(t, 0) + 1

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
            overall_quality_tier=self.mos_calculator.classify_tier(avg_mos).value,
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
