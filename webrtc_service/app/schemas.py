"""
Pydantic v2 schemas for WebRTC Signaling, Queue Management, Telemetry & Webhooks
"""

from enum import Enum
from typing import Optional, List, Dict, Any
from datetime import datetime
from pydantic import BaseModel, Field, field_validator


# ==========================================
# 1. Enums & Core Types
# ==========================================

class ClientRole(str, Enum):
    TECHNICIAN = "technician"
    TECNICO = "tecnico"
    ATTENDEE = "attendee"
    EGRESSO = "egresso"
    OBSERVER = "observer"
    GESTOR = "gestor"
    DEFENSORIA = "defensoria"

    @classmethod
    def normalize(cls, role_str: str) -> "ClientRole":
        role_map = {
            "technician": cls.TECHNICIAN,
            "tecnico": cls.TECHNICIAN,
            "attendee": cls.ATTENDEE,
            "egresso": cls.ATTENDEE,
            "observer": cls.OBSERVER,
            "gestor": cls.GESTOR,
            "defensoria": cls.DEFENSORIA,
        }
        val = role_map.get(role_str.lower().strip())
        if not val:
            raise ValueError(f"Invalid client role: {role_str}")
        return val


class RoomState(str, Enum):
    CREATED = "created"
    WAITING = "waiting"
    IN_PROGRESS = "in_progress"
    RECONNECTING = "reconnecting"
    ENDED = "ended"
    EXPIRED = "expired"
    ABORTED = "aborted"


class QueuePriority(str, Enum):
    URGENTE = "urgente"
    PREFERENCIAL = "preferencial"
    NORMAL = "normal"


class NetworkQualityTier(str, Enum):
    EXCELLENT = "EXCELLENT"  # MOS >= 4.3
    GOOD = "GOOD"            # 4.0 <= MOS < 4.3
    FAIR = "FAIR"            # 3.6 <= MOS < 4.0
    POOR = "POOR"            # 3.1 <= MOS < 3.6
    BAD = "BAD"              # MOS < 3.1


# ==========================================
# 2. Authentication & JWT Claims
# ==========================================

class JWTClaims(BaseModel):
    iss: Optional[str] = "conecta-egresso-laravel"
    aud: Optional[str] = "conecta-egresso-webrtc"
    sub: str = Field(..., description="User ID as string")
    name: str = Field(default="Usuário", description="Full display name")
    cpf_masked: Optional[str] = None
    role: str = Field(..., description="Role string (technician, attendee, etc.)")
    room_id: Optional[str] = None
    unit_id: Optional[str] = None
    prontuario_id: Optional[str] = None
    municipio: Optional[str] = None
    iat: Optional[int] = None
    exp: Optional[int] = None

    @property
    def user_id(self) -> int:
        try:
            return int(self.sub)
        except ValueError:
            return hash(self.sub) % 1000000

    @property
    def normalized_role(self) -> ClientRole:
        return ClientRole.normalize(self.role)


# ==========================================
# 3. Media & Participant State
# ==========================================

class MediaState(BaseModel):
    audio_muted: bool = False
    video_muted: bool = False
    screen_sharing: bool = False
    network_quality: str = "good"  # "excellent", "good", "poor", "critical"


class ParticipantInfo(BaseModel):
    client_id: str
    user_id: int
    name: str
    role: str
    media_state: MediaState = Field(default_factory=MediaState)
    joined_at: Optional[datetime] = Field(default_factory=datetime.utcnow)


# ==========================================
# 4. WebRTC Signaling WebSocket Schemas
# ==========================================

class BaseSignalingMessage(BaseModel):
    type: str


class JoinMessage(BaseSignalingMessage):
    type: str = "join"
    token: str
    media_state: Optional[MediaState] = Field(default_factory=MediaState)


class JoinedAckMessage(BaseSignalingMessage):
    type: str = "joined"
    room_id: str
    client_id: str
    user_id: int
    role: str
    polite: bool
    peers: List[ParticipantInfo] = Field(default_factory=list)
    ice_servers: List[Dict[str, Any]] = Field(default_factory=list)


class PeerJoinedMessage(BaseSignalingMessage):
    type: str = "peer_joined"
    peer: ParticipantInfo


class SdpMessage(BaseSignalingMessage):
    type: str  # "offer" or "answer"
    target_client_id: Optional[str] = None
    sender_client_id: Optional[str] = None
    sdp: str
    ice_restart: Optional[bool] = False


class IceCandidatePayload(BaseModel):
    candidate: str
    sdpMid: Optional[str] = None
    sdpMLineIndex: Optional[int] = None
    usernameFragment: Optional[str] = None


class IceCandidateMessage(BaseSignalingMessage):
    type: str = "ice_candidate"
    target_client_id: Optional[str] = None
    sender_client_id: Optional[str] = None
    candidate: IceCandidatePayload


class MediaStateChangeMessage(BaseSignalingMessage):
    type: str = "media_state_change"
    audio_muted: Optional[bool] = None
    video_muted: Optional[bool] = None
    screen_sharing: Optional[bool] = None


class PeerMediaUpdatedMessage(BaseSignalingMessage):
    type: str = "peer_media_updated"
    client_id: str
    user_id: int
    media_state: MediaState


class LeaveMessage(BaseSignalingMessage):
    type: str = "leave"
    reason: Optional[str] = "voluntary"


class PeerLeftMessage(BaseSignalingMessage):
    type: str = "peer_left"
    client_id: str
    user_id: int
    reason: str = "disconnected"


class TerminateRoomMessage(BaseSignalingMessage):
    type: str = "terminate_room"
    reason: Optional[str] = "attendance_completed"
    notes: Optional[str] = None


class RoomTerminatedMessage(BaseSignalingMessage):
    type: str = "room_terminated"
    reason: str = "technician_ended"
    duration_seconds: int = 0


class PingMessage(BaseSignalingMessage):
    type: str = "ping"
    timestamp: Optional[int] = None


class PongMessage(BaseSignalingMessage):
    type: str = "pong"
    timestamp: Optional[int] = None
    server_time: int = Field(default_factory=lambda: int(datetime.utcnow().timestamp() * 1000))


class ErrorMessage(BaseSignalingMessage):
    type: str = "error"
    code: str
    message: str
    details: Optional[Dict[str, Any]] = None


# ==========================================
# 5. Telemetry Schemas (ITU-T G.107 / E-Model)
# ==========================================

class AudioTrackStats(BaseModel):
    codec: str = Field(default="opus")
    bitrate_kbps: float = Field(ge=0.0, default=0.0)
    packets_lost: int = Field(ge=0, default=0)
    packets_received: int = Field(ge=0, default=0)
    packet_loss_pct: float = Field(ge=0.0, le=100.0, default=0.0)
    jitter_ms: float = Field(ge=0.0, default=0.0)
    audio_level: Optional[float] = Field(default=None, ge=0.0, le=1.0)


class VideoTrackStats(BaseModel):
    codec: str = Field(default="VP8")
    bitrate_kbps: float = Field(ge=0.0, default=0.0)
    frame_width: Optional[int] = Field(default=None, ge=0)
    frame_height: Optional[int] = Field(default=None, ge=0)
    fps: float = Field(ge=0.0, default=0.0)
    packets_lost: int = Field(ge=0, default=0)
    packets_received: int = Field(ge=0, default=0)
    packet_loss_pct: float = Field(ge=0.0, le=100.0, default=0.0)
    freeze_count: int = Field(ge=0, default=0)
    total_freeze_duration_s: float = Field(ge=0.0, default=0.0)
    quality_limitation_reason: Optional[str] = "none"


class ConnectionStats(BaseModel):
    rtt_ms: float = Field(ge=0.0, default=0.0)
    candidate_type: str = Field(default="host")
    protocol: str = Field(default="udp")
    available_outgoing_bitrate_kbps: Optional[float] = None
    bytes_sent: int = Field(ge=0, default=0)
    bytes_received: int = Field(ge=0, default=0)


class ClientTelemetryReport(BaseModel):
    room_id: str
    user_id: int
    peer_id: str
    role: str = "attendee"
    timestamp: datetime = Field(default_factory=datetime.utcnow)
    interval_seconds: float = Field(ge=0.1, le=60.0, default=3.0)
    connection: ConnectionStats = Field(default_factory=ConnectionStats)
    audio: AudioTrackStats = Field(default_factory=AudioTrackStats)
    video: Optional[VideoTrackStats] = None
    calculated_mos: Optional[float] = None
    quality_tier: Optional[NetworkQualityTier] = None


class TelemetryReportAck(BaseModel):
    status: str = "ok"
    room_id: str
    peer_id: str
    mos: float
    quality_tier: NetworkQualityTier
    recommended_action: Optional[str] = None


class QualityAlertMessage(BaseSignalingMessage):
    type: str = "quality_alert"
    level: str = "poor"  # "poor" or "critical"
    mos: float
    rtt_ms: float
    jitter_ms: float
    packet_loss_pct: float
    suggestion: str = "disable_video"
    message: str = "Conexão instável detectada."


class QualityDistribution(BaseModel):
    excellent_pct: float = Field(default=0.0, description="% time MOS >= 4.3")
    good_pct: float = Field(default=0.0, description="% time 4.0 <= MOS < 4.3")
    fair_pct: float = Field(default=0.0, description="% time 3.6 <= MOS < 4.0")
    poor_pct: float = Field(default=0.0, description="% time 3.1 <= MOS < 3.6")
    bad_pct: float = Field(default=0.0, description="% time MOS < 3.1")


class SessionTelemetrySummary(BaseModel):
    room_id: str
    peer_id: str
    user_id: int
    role: str
    sample_count: int
    duration_seconds: float
    avg_mos: float
    min_mos: float
    max_mos: float
    p95_mos: float
    overall_quality_tier: str
    quality_distribution: QualityDistribution = Field(default_factory=QualityDistribution)
    avg_rtt_ms: float
    max_rtt_ms: float
    avg_jitter_ms: float
    max_jitter_ms: float
    overall_packet_loss_pct: float
    avg_video_bitrate_kbps: float = 0.0
    avg_audio_bitrate_kbps: float = 0.0
    total_bytes_transferred: int = 0
    avg_fps: float = 0.0
    total_freezes: int = 0
    total_freeze_duration_s: float = 0.0
    resolution_changes_count: int = 0
    final_resolution: str = "unknown"
    poor_network_alerts_count: int = 0


# ==========================================
# 6. Queue & Waiting Room Schemas
# ==========================================

class QueueTicket(BaseModel):
    ticket_id: str
    unit_id: str
    user_id: int
    name: str
    municipio: str
    prioridade: QueuePriority = QueuePriority.NORMAL
    motivo: str = "atendimento_geral"
    created_at: datetime = Field(default_factory=datetime.utcnow)
    status: str = "WAITING"  # "WAITING", "CLAIMED", "ADMITTED", "CANCELLED", "EXPIRED"
    claimed_by_id: Optional[int] = None
    claimed_by_name: Optional[str] = None
    room_id: Optional[str] = None
    claimed_at: Optional[datetime] = None


class JoinQueueRequest(BaseModel):
    type: str = "join_queue"
    user_id: int
    name: str
    municipio: str = "Vitória"
    prioridade: QueuePriority = QueuePriority.NORMAL
    motivo: str = "acolhimento_inicial"


class QueueJoinedResponse(BaseModel):
    type: str = "queue_joined"
    ticket_id: str
    unit_id: str
    position: int
    estimated_wait_minutes: int


class QueuePositionUpdate(BaseModel):
    type: str = "position_update"
    ticket_id: str
    position: int
    estimated_wait_minutes: int
    total_waiting: int


class QueueItem(BaseModel):
    ticket_id: str
    user_id: int
    name: str
    municipio: str
    prioridade: str
    motivo: str
    waiting_seconds: int
    status: str = "WAITING"


class QueueStatusBroadcast(BaseModel):
    type: str = "queue_status"
    unit_id: str
    total_waiting: int
    items: List[QueueItem] = Field(default_factory=list)


class AdmitAttendeeRequest(BaseModel):
    type: str = "admit_attendee"
    ticket_id: str
    room_id: str


class CallAttendeeMessage(BaseModel):
    type: str = "call_attendee"
    ticket_id: str
    room_id: str
    token: str
    ws_url: str
    tecnico_name: str


class AttendeeAdmittedBroadcast(BaseModel):
    type: str = "attendee_admitted"
    ticket_id: str
    room_id: str
    user_id: int
    tecnico_id: int


# ==========================================
# 7. Webhook Payload Schemas (to Laravel)
# ==========================================

class WebhookPayload(BaseModel):
    event: str
    timestamp: datetime = Field(default_factory=datetime.utcnow)
    room_id: Optional[str] = None
    unit_id: Optional[str] = None
    data: Dict[str, Any] = Field(default_factory=dict)
