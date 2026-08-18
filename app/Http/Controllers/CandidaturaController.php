<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\ProntuarioTimeline;
use App\Models\Prontuario;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;

class CandidaturaController extends Controller
{
    /**
     * List job applications and course enrollments.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = ProntuarioTimeline::with([
            'prontuario.egresso:id,nome_completo,status_penal,municipio_residencia_id',
            'responsavel:id,name',
        ])->whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso']);

        // If Egresso, restrict to own prontuario
        if ($user && $user->isEgresso()) {
            $egressoId = $user->egresso?->id;
            $query->whereHas('prontuario', fn($pq) => $pq->where('egresso_id', $egressoId));
        }

        if ($request->filled('tipo')) {
            $query->where('tipo_evento', $request->input('tipo'));
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $records = $query->orderBy('data_evento', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
            'total' => $records->total(),
            'last_page' => $records->lastPage(),
        ]);
    }

    /**
     * Show single candidatura details.
     */
    public function show(string $id): JsonResponse
    {
        $item = ProntuarioTimeline::with([
            'prontuario.egresso.municipio',
            'responsavel:id,name',
        ])->whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso'])
          ->find($id);

        if (!$item) {
            return response()->json(['error' => 'Candidatura ou inscrição não encontrada.'], 404);
        }

        return response()->json(['data' => $item]);
    }

    /**
     * Store new candidatura / forward.
     */
    public function store(Request $request): JsonResponse
    {
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
