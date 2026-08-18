<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\CursoCapacitacao;
use App\Models\Egresso;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;

class CursoCapacitacaoController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * List training courses with modality, municipality, and aid allowance filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CursoCapacitacao::with('municipio:id,nome,codigo_ibge,macrorregiao');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'aberto');
        }

        // Modality filter
        if ($request->filled('modalidade')) {
            $query->where('modalidade', strtolower($request->input('modalidade')));
        }

        // Category filter
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        // EAD only filter (null municipality or ead modality)
        if ($request->has('ead_only') && filter_var($request->input('ead_only'), FILTER_VALIDATE_BOOLEAN)) {
            $query->where(function ($q) {
                $q->whereNull('municipio_id')->orWhere('modalidade', 'ead');
            });
        } elseif ($request->filled('municipio_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('municipio_id', (int) $request->input('municipio_id'))
                  ->orWhereNull('municipio_id'); // EAD courses accessible statewide
            });
        } elseif ($request->filled('municipio')) {
            $munSearch = trim($request->input('municipio'));
            $cleanIbge = preg_replace('/\D/', '', $munSearch);

            $query->where(function ($q) use ($munSearch, $cleanIbge) {
                $q->whereNull('municipio_id')
                  ->orWhereHas('municipio', function ($mq) use ($munSearch, $cleanIbge) {
                      if (strlen($cleanIbge) === 7 && str_starts_with($cleanIbge, '32')) {
                          $mq->where('codigo_ibge', $cleanIbge);
                      } else {
                          $mq->where('nome', 'ILIKE', "%{$munSearch}%");
                      }
                  });
            });
        }

        // Bolsa auxilio filter
        if ($request->has('com_bolsa')) {
            $comBolsa = filter_var($request->input('com_bolsa'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($comBolsa === true) {
                $query->where('bolsa_auxilio', '>', 0);
            } elseif ($comBolsa === false) {
                $query->where(function ($q) {
                    $q->whereNull('bolsa_auxilio')->orWhere('bolsa_auxilio', '<=', 0);
                });
            }
        }

        // Search query q
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('titulo', 'ILIKE', "%{$q}%")
                    ->orWhere('instituicao', 'ILIKE', "%{$q}%")
                    ->orWhere('descricao', 'ILIKE', "%{$q}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $cursos = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $cursos->items(),
            'current_page' => $cursos->currentPage(),
            'per_page' => $cursos->perPage(),
            'total' => $cursos->total(),
            'last_page' => $cursos->lastPage(),
        ]);
    }

    /**
     * Show single course details.
     */
    public function show(string $id): JsonResponse
    {
        $curso = CursoCapacitacao::with('municipio:id,nome,codigo_ibge,macrorregiao')->find($id);

        if (!$curso) {
            return response()->json(['error' => 'Curso de capacitação não encontrado.'], 404);
        }

        return response()->json(['data' => $curso]);
    }

    /**
     * Create new course (Gestor / Técnico).
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $validated = $request->validate([
            'instituicao' => 'required|string|max:150',
            'titulo' => 'required|string|max:150',
            'descricao' => 'required|string',
            'categoria' => 'required|string|max:100',
            'municipio_id' => 'nullable|integer|exists:municipios_es,id',
            'carga_horaria' => 'required|integer|min:1',
            'modalidade' => 'required|string|in:presencial,ead,hibrido',
            'bolsa_auxilio' => 'nullable|numeric|min:0',
            'vagas_disponiveis' => 'required|integer|min:1',
            'status' => 'nullable|string|in:aberto,em_andamento,encerrado,cancelado',
            'link_inscricao' => 'nullable|string|max:255',
        ]);

        $curso = CursoCapacitacao::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'aberto',
        ]));

        return response()->json([
            'status' => 'created',
            'message' => 'Curso cadastrado com sucesso.',
            'data' => $curso->load('municipio'),
        ], 201);
    }

    /**
     * Update course.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $curso = CursoCapacitacao::find($id);
        if (!$curso) {
            return response()->json(['error' => 'Curso não encontrado.'], 404);
        }

        $validated = $request->validate([
            'instituicao' => 'nullable|string|max:150',
            'titulo' => 'nullable|string|max:150',
            'descricao' => 'nullable|string',
            'categoria' => 'nullable|string|max:100',
            'municipio_id' => 'nullable|integer|exists:municipios_es,id',
            'carga_horaria' => 'nullable|integer|min:1',
            'modalidade' => 'nullable|string|in:presencial,ead,hibrido',
            'bolsa_auxilio' => 'nullable|numeric|min:0',
            'vagas_disponiveis' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:aberto,em_andamento,encerrado,cancelado',
            'link_inscricao' => 'nullable|string|max:255',
        ]);

        $curso->update($validated);

        return response()->json([
            'status' => 'updated',
            'data' => $curso->load('municipio'),
        ]);
    }

    /**
     * Delete course.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $curso = CursoCapacitacao::find($id);
        if (!$curso) {
            return response()->json(['error' => 'Curso não encontrado.'], 404);
        }

        $curso->update(['status' => 'encerrado']);

        return response()->json(['status' => 'deleted', 'message' => 'Curso encerrado com sucesso.']);
    }

    /**
     * Enroll egresso in training course and automatically insert timeline event on Prontuário.
     */
    public function inscrever(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $curso = CursoCapacitacao::with('municipio')->find($id);

        if (!$curso) {
            return response()->json(['error' => 'Curso de capacitação não encontrado.'], 404);
        }

        if ($curso->status !== 'aberto') {
            return response()->json([
                'error' => 'Este curso não está com inscrições abertas.',
                'code' => 'COURSE_CLOSED',
            ], 422);
        }

        // Determine target egresso
        $egressoId = null;
        if ($user && $user->isEgresso()) {
            $egressoId = $user->egresso?->id;
        } else {
            $egressoId = $request->input('egresso_id');
        }

        if (!$egressoId) {
            return response()->json(['error' => 'Egresso não identificado para inscrição no curso.'], 422);
        }

        $egresso = Egresso::find($egressoId);
        if (!$egresso) {
            return response()->json(['error' => 'Egresso não encontrado.'], 404);
        }

        // Find or create Prontuário
        $prontuario = Prontuario::where('egresso_id', $egresso->id)->first();

        $timelineId = null;
        if ($prontuario) {
            $bolsaStr = $curso->bolsa_auxilio > 0 ? ' (Bolsa Auxílio: R$ ' . number_format($curso->bolsa_auxilio, 2, ',', '.') . ')' : '';
            $modalidadeStr = strtoupper($curso->modalidade);

            $descricao = "Inscrição confirmada no curso de qualificação '{$curso->titulo}' ofertado pela instituição {$curso->instituicao}.\n" .
                         "Modalidade: {$modalidadeStr} | Carga Horária: {$curso->carga_horaria} horas{$bolsaStr}.\n" .
                         "Inscrição realizada via portal Conecta Egresso.";

            $timeline = ProntuarioTimeline::create([
                'prontuario_id' => $prontuario->id,
                'tipo_evento' => 'inscricao_curso',
                'titulo' => "Inscrição em Curso: {$curso->titulo} ({$curso->instituicao})",
                'descricao' => htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'),
                'metadata' => [
                    'curso_id' => $curso->id,
                    'instituicao' => $curso->instituicao,
                    'titulo' => $curso->titulo,
                    'carga_horaria' => $curso->carga_horaria,
                    'modalidade' => $curso->modalidade,
                    'bolsa_auxilio' => (float) $curso->bolsa_auxilio,
                ],
                'responsavel_id' => $user?->id ?? 1,
                'data_evento' => now(),
            ]);

            $timelineId = $timeline->id;

            $this->audit->log(
                $prontuario->id,
                'COURSE_ENROLLMENT_RECORDED',
                [
                    'curso_id' => $curso->id,
                    'curso_titulo' => $curso->titulo,
                    'timeline_id' => $timelineId,
                ],
                $user?->id,
                $request->ip(),
                $request->userAgent()
            );
        }

        return response()->json([
            'status' => 'enrolled',
            'message' => 'Inscrição realizada com sucesso.',
            'curso_id' => $curso->id,
            'prontuario_id' => $prontuario?->id,
            'timeline_id' => $timelineId,
        ], 201);
    }
}
