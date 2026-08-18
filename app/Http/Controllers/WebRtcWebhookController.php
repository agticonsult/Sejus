<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\VideoRoom;
use App\Models\VideoAttendee;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;
use Throwable;

class WebRtcWebhookController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Ingest and process signed WebRTC lifecycle webhooks from Python FastAPI.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Cryptographic HMAC-SHA256 Signature Verification
        $signatureHeader = $request->header('X-Signature')
            ?? $request->header('X-Signature-SHA256')
            ?? $request->header('x-signature')
            ?? $request->header('x-signature-sha256');

        if (!$signatureHeader) {
            return response()->json([
                'error' => 'Missing signature header (X-Signature)',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $receivedSig = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        $secret = (string) (config('services.webrtc.webhook_secret') ?: (getenv('WEBRTC_WEBHOOK_SECRET') ?: 'sejus_webrtc_webhook_secret_2026'));
        $rawPayload = $request->getContent();
        $computedSig = hash_hmac('sha256', $rawPayload, $secret);

        if (!hash_equals($computedSig, $receivedSig)) {
            return response()->json([
                'error' => 'Invalid HMAC-SHA256 signature',
                'code' => 'INVALID_SIGNATURE',
            ], 401);
        }

        // 2. Parse Payload
        $payload = $request->json()->all();
        $event = $payload['event'] ?? 'unknown';
        $roomId = $payload['room_id'] ?? null;
        $data = $payload['data'] ?? $payload;

        // Normalize event name (support both session.ended and session_ended)
        $normalizedEvent = str_replace('_', '.', strtolower($event));

        try {
            switch ($normalizedEvent) {
                case 'session.started':
                    return $this->handleSessionStarted($roomId, $data, $payload);

                case 'session.ended':
                    return $this->handleSessionEnded($roomId, $data, $payload);

                case 'recording.ready':
                    return $this->handleRecordingReady($roomId, $data, $payload);

                case 'session.quality.alert':
                case 'session.quality_alert':
                    return $this->handleQualityAlert($roomId, $data, $payload);

                default:
                    return response()->json([
                        'status' => 'acknowledged',
                        'event' => $event,
                        'message' => 'Event acknowledged without specific handler',
                    ], 200);
            }
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Webhook processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function handleSessionStarted(?string $roomId, array $data, array $payload): JsonResponse
    {
        $roomCode = $data['room_code'] ?? $roomId;
        $room = VideoRoom::firstOrCreate(
            ['room_code' => $roomCode],
            [
                'prontuario_id' => $data['prontuario_id'] ?? null,
                'tecnico_id' => $data['tecnico_id'] ?? null,
                'egresso_id' => $data['egresso_id'] ?? null,
                'municipio_id' => $data['municipio_id'] ?? null,
                'status' => 'em_andamento',
                'started_at' => $data['started_at'] ?? now(),
            ]
        );

        $room->update([
            'status' => 'em_andamento',
            'started_at' => $data['started_at'] ?? now(),
        ]);

        $this->auditService->log(
            $room->prontuario_id,
            'WEBRTC_SESSION_STARTED',
            [
                'room_code' => $roomCode,
                'room_id' => $roomId,
                'started_at' => $data['started_at'] ?? now()->toIso8601String(),
            ]
        );

        return response()->json([
            'status' => 'processed',
            'event' => 'session.started',
            'room_code' => $roomCode,
        ]);
    }

    protected function handleSessionEnded(?string $roomId, array $data, array $payload): JsonResponse
    {
        $roomCode = $data['room_code'] ?? $roomId;
        $durationSeconds = (int) ($data['duration_seconds'] ?? 0);
        $summaryTelemetry = $data['summary_telemetry'] ?? [];
        $attendees = $data['attendees'] ?? [];

        // 1. Update Video Room
        $room = VideoRoom::where('room_code', $roomCode)
            ->orWhere('id', is_numeric($roomId) ? (int)$roomId : 0)
            ->first();

        if ($room) {
            $room->update([
                'status' => 'encerrada',
                'ended_at' => $data['ended_at'] ?? now(),
            ]);
        }

        // 2. Persist Video Attendees Telemetry
        if ($room && !empty($attendees)) {
            foreach ($attendees as $attendeeData) {
                VideoAttendee::updateOrCreate(
                    [
                        'video_room_id' => $room->id,
                        'user_id' => $attendeeData['user_id'] ?? null,
                    ],
                    [
                        'role' => $attendeeData['role'] ?? 'egresso',
                        'peer_id' => $attendeeData['peer_id'] ?? null,
                        'duration_seconds' => $attendeeData['duration_seconds'] ?? $durationSeconds,
                        'mos_score' => $attendeeData['mos_score'] ?? ($summaryTelemetry['avg_mos'] ?? null),
                        'packet_loss' => $attendeeData['packet_loss'] ?? ($summaryTelemetry['overall_packet_loss_pct'] ?? null),
                        'jitter' => $attendeeData['jitter'] ?? ($summaryTelemetry['avg_jitter_ms'] ?? null),
                        'rtt_ms' => $attendeeData['rtt_ms'] ?? ($summaryTelemetry['avg_rtt_ms'] ?? null),
                        'telemetry_data' => $attendeeData['telemetry'] ?? $summaryTelemetry,
                        'left_at' => $data['ended_at'] ?? now(),
                    ]
                );
            }
        }

        // 3. Resolve Target Prontuário
        $prontuarioId = $data['prontuario_id'] ?? ($room?->prontuario_id);
        $prontuario = null;
        if ($prontuarioId) {
            $prontuario = Prontuario::find($prontuarioId);
        } elseif (!empty($data['egresso_id'])) {
            $prontuario = Prontuario::where('egresso_id', $data['egresso_id'])->first();
        }

        $timelineId = null;

        // 4. Automatic Prontuário Timeline Insertion
        if ($prontuario) {
            $minutes = floor($durationSeconds / 60);
            $seconds = $durationSeconds % 60;
            $durationFormatted = sprintf('%02d min %02d seg', $minutes, $seconds);
            $avgMos = $summaryTelemetry['avg_mos'] ?? 4.0;
            $qualityTier = $summaryTelemetry['overall_quality_tier'] ?? 'BOM';
            $lossPct = $summaryTelemetry['overall_packet_loss_pct'] ?? 0.0;
            $avgRtt = $summaryTelemetry['avg_rtt_ms'] ?? 0.0;

            $descricao = "Atendimento psicossocial remoto por videoconferência realizado com sucesso pelo Escritório Social.\n" .
                         "Duração total: {$durationFormatted}.\n" .
                         "Qualidade técnica da conexão: {$qualityTier} (Score MOS Médio: " . number_format($avgMos, 2, ',', '.') .
                         " | Perda de pacotes: {$lossPct}% | RTT: {$avgRtt}ms).\n" .
                         "Sessão concluída normalmente.";

            $metadata = [
                'room_id' => $roomId,
                'room_code' => $roomCode,
                'duration_seconds' => $durationSeconds,
                'duration_formatted' => $durationFormatted,
                'started_at' => $data['started_at'] ?? null,
                'ended_at' => $data['ended_at'] ?? null,
                'hangup_reason' => $data['hangup_reason'] ?? 'normal_closure',
                'summary_telemetry' => $summaryTelemetry,
                'participants' => $attendees,
                'source' => 'webrtc_webhook_fastapi',
            ];

            $responsavelId = $data['tecnico_id'] ?? $room?->tecnico_id ?? $prontuario->tecnico_responsavel_id ?? 1;

            $timeline = ProntuarioTimeline::create([
                'prontuario_id' => $prontuario->id,
                'tipo_evento' => 'acolhimento_video',
                'titulo' => "Atendimento Psicossocial Remoto via Videochamada (Sala: {$roomCode})",
                'descricao' => htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'),
                'metadata' => $metadata,
                'responsavel_id' => $responsavelId,
                'data_evento' => $data['ended_at'] ?? now(),
            ]);

            $timelineId = $timeline->id;

            // 5. Append Immutable Chained Audit Log
            $this->auditService->log(
                $prontuario->id,
                'WEBRTC_ATTENDANCE_RECORDED',
                [
                    'room_code' => $roomCode,
                    'room_id' => $roomId,
                    'duration_seconds' => $durationSeconds,
                    'timeline_id' => $timelineId,
                    'avg_mos' => $avgMos,
                    'quality_tier' => $qualityTier,
                ],
                $responsavelId
            );
        }

        return response()->json([
            'status' => 'processed',
            'event' => 'session.ended',
            'room_code' => $roomCode,
            'prontuario_id' => $prontuario?->id,
            'timeline_id' => $timelineId,
            'message' => 'Atendimento e telemetria registrados com sucesso.',
        ]);
    }

    protected function handleRecordingReady(?string $roomId, array $data, array $payload): JsonResponse
    {
        $roomCode = $data['room_code'] ?? $roomId;
        $room = VideoRoom::where('room_code', $roomCode)
            ->orWhere('id', is_numeric($roomId) ? (int)$roomId : 0)
            ->first();

        if ($room) {
            $room->update([
                'token_sala' => json_encode([
                    'recording_url' => $data['recording_url'] ?? null,
                    'recording_hash' => $data['recording_hash'] ?? null,
                    'file_size' => $data['file_size_bytes'] ?? null,
                ]),
            ]);
        }

        $this->auditService->log(
            $room?->prontuario_id,
            'WEBRTC_RECORDING_ATTACHED',
            [
                'room_code' => $roomCode,
                'recording_hash' => $data['recording_hash'] ?? null,
            ]
        );

        return response()->json([
            'status' => 'processed',
            'event' => 'recording.ready',
            'room_code' => $roomCode,
        ]);
    }

    protected function handleQualityAlert(?string $roomId, array $data, array $payload): JsonResponse
    {
        $this->auditService->log(
            null,
            'WEBRTC_QUALITY_ALERT',
            [
                'room_id' => $roomId,
                'user_id' => $data['user_id'] ?? null,
                'mos' => $data['current_mos'] ?? null,
                'packet_loss' => $data['packet_loss_pct'] ?? null,
                'recommendation' => $data['recommended_action'] ?? 'switch_to_audio_only',
            ]
        );

        return response()->json([
            'status' => 'processed',
            'event' => 'session.quality_alert',
        ]);
    }
}
