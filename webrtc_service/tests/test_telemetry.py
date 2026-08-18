"""
Unit Tests for ITU-T G.107 E-Model MOS Scoring & Session Aggregator (app/telemetry.py)
"""

import pytest
from app.telemetry import EModelMOSCalculator, SessionAggregator, calculate_mos
from app.schemas import NetworkQualityTier


def test_mos_perfect_connection(mos_calculator):
    # RTT = 10ms, Jitter = 1ms, Loss = 0% -> Expected MOS ~4.32 (EXCELLENT)
    res = mos_calculator.evaluate(rtt_ms=10.0, jitter_ms=1.0, packet_loss_pct=0.0)
    assert res.mos >= 4.30
    assert res.quality_tier == NetworkQualityTier.EXCELLENT
    assert res.r_factor > 85.0


def test_mos_typical_4g_connection(mos_calculator):
    # RTT = 50ms, Jitter = 8ms, Loss = 0.5% -> Expected MOS ~4.20 - 4.35 (GOOD/EXCELLENT)
    res = mos_calculator.evaluate(rtt_ms=50.0, jitter_ms=8.0, packet_loss_pct=0.5)
    assert 4.0 <= res.mos <= 4.4
    assert res.quality_tier in [NetworkQualityTier.GOOD, NetworkQualityTier.EXCELLENT]


def test_mos_moderate_cellular_jitter(mos_calculator):
    # RTT = 120ms, Jitter = 25ms, Loss = 2.0% -> Expected MOS ~3.6 - 4.1 (FAIR/GOOD)
    res = mos_calculator.evaluate(rtt_ms=120.0, jitter_ms=25.0, packet_loss_pct=2.0)
    assert 3.6 <= res.mos <= 4.1
    assert res.quality_tier in [NetworkQualityTier.FAIR, NetworkQualityTier.GOOD]



def test_mos_degraded_3g_connection(mos_calculator):
    # RTT = 250ms, Jitter = 40ms, Loss = 6.0% -> Expected MOS < 3.2 (POOR/BAD)
    res = mos_calculator.evaluate(rtt_ms=250.0, jitter_ms=40.0, packet_loss_pct=6.0)
    assert res.mos < 3.2
    assert res.quality_tier in [NetworkQualityTier.POOR, NetworkQualityTier.BAD]


def test_mos_severe_loss_clamping(mos_calculator):
    # 50% packet loss -> MOS must clamp to 1.0 floor
    res = mos_calculator.evaluate(rtt_ms=800.0, jitter_ms=150.0, packet_loss_pct=50.0)
    assert res.mos == 1.0
    assert res.quality_tier == NetworkQualityTier.BAD


def test_mos_zero_delay_boundary(mos_calculator):
    res = mos_calculator.evaluate(rtt_ms=0.0, jitter_ms=0.0, packet_loss_pct=0.0)
    assert 4.3 <= res.mos <= 4.5


def test_calculate_mos_convenience_function():
    mos = calculate_mos(rtt_ms=10.0, jitter_ms=1.0, packet_loss_pct=0.0)
    assert isinstance(mos, float)
    assert mos >= 4.3


def test_session_aggregator_summary_generation(sample_perfect_report, sample_degraded_report):
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
    assert summary.total_bytes_transferred > 0
