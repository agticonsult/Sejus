<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\ProntuarioTimeline;
use App\Models\Prontuario;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use Carbon\Carbon;

class CandidaturaController extends Controller
{
    /**
     * List job applications and course enrollments.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte() && !$user->isEgresso()) {
            return response()->json([
                'error' => 'Acesso negado às candidaturas.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $query = ProntuarioTimeline::with([
            'prontuario.egresso:id,nome_completo,status_penal,municipio_residencia_id',
            'responsavel:id,name',
        ])->whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso']);

        // If Egresso, restrict to own prontuario
        if ($user->isEgresso()) {
            $egressoId = $user->egresso?->id;
            $query->whereHas('prontuario', fn($pq) => $pq->where('egresso_id', $egressoId));
        }

        if ($request->filled('tipo')) {
            $query->where('tipo_evento', $request->input('tipo'));
        }

        // Delta sync filter (since / updated_after)
        $sinceParam = $request->input('since', $request->input('updated_after'));
        if ($sinceParam !== null && $sinceParam !== '') {
            $isValid = false;
            $since = null;

            if (is_numeric($sinceParam)) {
                $isValid = true;
                $since = Carbon::createFromTimestamp((int) $sinceParam);
            } elseif (is_string($sinceParam) && preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}):(\d{2})(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/i', trim($sinceParam), $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];

                if (checkdate($month, $day, $year)) {
                    try {
                        $since = Carbon::parse($sinceParam);
                        $isValid = true;
                    } catch (\Throwable $e) {
                        $isValid = false;
                    }
                }
            }

            if (!$isValid || $since === null) {
                return response()->json([
                    'error' => 'Parâmetro de data inválido para sincronização delta. Formato esperado: ISO-8601 (ex: 2026-09-15T12:00:00Z).',
                    'code' => 'INVALID_SYNC_TIMESTAMP',
                ], 422);
            }

            $query->where('updated_at', '>=', $since->toDateTimeString());
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $records = $query->orderBy('data_evento', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
            'last_page' => $records->lastPage(),
            'meta' => [
                'server_timestamp' => now()->toIso8601String(),
                'sync_count' => count($records->items()),
                'since' => $sinceParam ?: null,
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    /**
     * Show single candidatura details.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte() && !$user->isEgresso()) {
            return response()->json([
                'error' => 'Acesso negado.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $item = ProntuarioTimeline::with([
            'prontuario.egresso.municipio',
            'responsavel:id,name',
        ])->whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso'])
          ->find($id);

        if (!$item) {
            return response()->json(['error' => 'Candidatura ou inscrição não encontrada.'], 404);
        }

        if ($user->isEgresso() && (!$user->egresso || $item->prontuario?->egresso_id !== $user->egresso->id)) {
            return response()->json([
                'error' => 'Acesso negado a esta candidatura.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return response()->json(['data' => $item]);
    }

    /**
     * Store new candidatura / forward.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte() && !$user->isEgresso()) {
            return response()->json([
                'error' => 'Acesso negado para registrar candidaturas.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $vagaId = $request->input('vaga_id');
        $cursoId = $request->input('curso_id');

        if ($vagaId) {
            return app(VagaEmpregoController::class)->candidatar($request, (string) $vagaId);
        }

        if ($cursoId) {
            return app(CursoCapacitacaoController::class)->inscrever($request, (string) $cursoId);
        }

        return response()->json(['error' => 'É necessário informar vaga_id ou curso_id.'], 422);
    }
}
