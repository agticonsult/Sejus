<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\VideoRoom;
use App\Models\Prontuario;
use App\Services\WebRtcJwtService;
use App\Services\AuditService;

class WebRtcTokenController extends Controller
{
    public function __construct(
        protected WebRtcJwtService $jwtService,
        protected AuditService $audit
    ) {}

    /**
     * Generate signed JWT token and ICE server configuration for WebRTC signaling session.
     */
    public function generateToken(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado.',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        if (!$user->ativo) {
            return response()->json([
                'error' => 'Conta de usuário inativa.',
                'code' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        $validated = $request->validate([
            'room_id' => 'required|string|max:64',
            'room_code' => 'nullable|string|max:64',
            'prontuario_id' => 'nullable|integer|exists:prontuarios,id',
            'unit_id' => 'nullable|integer',
            'role' => 'nullable|string|in:tecnico,egresso,gestor,observador',
        ]);

        $roomId = $validated['room_id'];
        $roomCode = $validated['room_code'] ?? $roomId;
        $prontuarioId = $validated['prontuario_id'] ?? null;
        $unitId = $validated['unit_id'] ?? null;

        // Role resolution & privilege escalation protection
        $userRole = $user->perfil?->slug ?? 'egresso';
        $desiredRole = $validated['role'] ?? $userRole;

        // Prevent unauthorized role escalation:
        // - Non-gestores cannot claim 'gestor'
        // - Non-staff (egresso, familiar, etc.) cannot claim 'tecnico'
        if ($desiredRole === 'gestor' && !$user->isGestor()) {
            $desiredRole = $userRole;
        } elseif ($desiredRole === 'tecnico' && !$user->isGestor() && !$user->isTecnico()) {
            $desiredRole = $userRole;
        }

        // Check if room exists
        $room = VideoRoom::where('room_code', $roomId)
            ->orWhere('id', is_numeric($roomId) ? (int)$roomId : 0)
            ->first();

        if ($room) {
            if (in_array($room->status, ['encerrada', 'cancelada'], true)) {
                return response()->json([
                    'error' => 'Esta sala de atendimento já foi encerrada.',
                    'code' => 'ROOM_CLOSED',
                ], 403);
            }

            // Egresso authorization check: cannot join other egressos' private rooms
            if ($user->isEgresso() && $user->egresso) {
                if ($room->egresso_id !== null && $room->egresso_id !== $user->egresso->id) {
                    return response()->json([
                        'error' => 'Acesso não autorizado para esta sala de atendimento.',
                        'code' => 'FORBIDDEN_ROOM_ACCESS',
                    ], 403);
                }
            }

            if (!$prontuarioId && $room->prontuario_id) {
                $prontuarioId = $room->prontuario_id;
            }
        } elseif ($user->isTecnico() || $user->isGestor()) {
            // Automatically initialize room if host technician starts a new session
            $room = VideoRoom::create([
                'room_code' => $roomCode,
                'prontuario_id' => $prontuarioId,
                'tecnico_id' => $user->isTecnico() ? $user->id : null,
                'municipio_id' => $unitId,
                'status' => 'aguardando',
                'prioridade' => 'normal',
                'scheduled_at' => now(),
            ]);
        }

        $tokenPayload = $this->jwtService->generateRoomToken(
            $user,
            $roomId,
            $desiredRole,
            $prontuarioId,
            $unitId,
            $roomCode
        );

        $this->audit->log(
            $prontuarioId,
            'WEBRTC_TOKEN_ISSUED',
            [
                'room_id' => $roomId,
                'room_code' => $roomCode,
                'role' => $desiredRole,
            ],
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json(array_merge(['status' => 'success'], $tokenPayload));
    }
}
