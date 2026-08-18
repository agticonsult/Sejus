<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\VagaEmprego;
use App\Models\MunicipioEs;
use App\Models\Egresso;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;

class VagaEmpregoController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * List job vacancies with municipality, category, affirmative action filters and accent-insensitive search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VagaEmprego::with('municipio:id,nome,codigo_ibge,macrorregiao');

        // Status filter (defaults to 'aberta' if not specified)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'aberta');
        }

        // Municipio filter by ID or name/IBGE code
        if ($request->filled('municipio_id')) {
            $query->where('municipio_id', (int) $request->input('municipio_id'));
        } elseif ($request->filled('municipio')) {
            $munSearch = trim($request->input('municipio'));
            $cleanIbge = preg_replace('/\D/', '', $munSearch);

            $query->whereHas('municipio', function ($mq) use ($munSearch, $cleanIbge) {
                if (strlen($cleanIbge) === 7 && str_starts_with($cleanIbge, '32')) {
                    $mq->where('codigo_ibge', $cleanIbge);
                } else {
                    $mq->where('nome', 'ILIKE', "%{$munSearch}%");
                }
            });
        }

        // Affirmative action filter
        if ($request->has('afirmativa_egresso')) {
            $afirmativa = filter_var($request->input('afirmativa_egresso'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($afirmativa !== null) {
                $query->where('afirmativa_egresso', $afirmativa);
            }
        }

        // Empresa amiga filter
        if ($request->has('empresa_amiga') || $request->has('empresa_amiga_reintegracao')) {
            $empAmiga = filter_var($request->input('empresa_amiga', $request->input('empresa_amiga_reintegracao')), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($empAmiga !== null) {
                $query->where('empresa_amiga_reintegracao', $empAmiga);
            }
        }

        // Category filter
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        // Regime contratacao
        if ($request->filled('regime_contratacao')) {
            $query->where('regime_contratacao', $request->input('regime_contratacao'));
        }

        // Minimum salary filter (clamped >= 0)
        if ($request->filled('salario_min')) {
            $salarioMin = max(0.0, (float) $request->input('salario_min'));
            $query->where('salario', '>=', $salarioMin);
        }

        // Search string q (title, company, description)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('titulo', 'ILIKE', "%{$q}%")
                    ->orWhere('empresa', 'ILIKE', "%{$q}%")
                    ->orWhere('descricao', 'ILIKE', "%{$q}%");
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $vagas = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $vagas->items(),
            'current_page' => $vagas->currentPage(),
            'per_page' => $vagas->perPage(),
            'total' => $vagas->total(),
            'last_page' => $vagas->lastPage(),
        ]);
    }

    /**
     * Show single job opening details.
     */
    public function show(string $id): JsonResponse
    {
        $vaga = VagaEmprego::with('municipio:id,nome,codigo_ibge,macrorregiao,latitude,longitude')
            ->find($id);

        if (!$vaga) {
            return response()->json(['error' => 'Vaga de emprego não encontrada.'], 404);
        }

        return response()->json(['data' => $vaga]);
    }

    /**
     * Create a new job vacancy (Gestor / Técnico).
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $validated = $request->validate([
            'empresa' => 'required|string|max:150',
            'titulo' => 'required|string|max:150',
            'descricao' => 'required|string',
            'categoria' => 'required|string|max:100',
            'municipio_id' => 'required|integer|exists:municipios_es,id',
            'salario' => 'nullable|numeric|min:0',
            'regime_contratacao' => 'nullable|string|max:50',
            'afirmativa_egresso' => 'nullable|boolean',
            'empresa_amiga_reintegracao' => 'nullable|boolean',
            'escolaridade_minima' => 'nullable|string|max:100',
            'vagas_totais' => 'required|integer|min:1',
            'beneficios' => 'nullable|array',
            'status' => 'nullable|string|in:aberta,preenchida,pausada,cancelada',
        ]);

        $vaga = VagaEmprego::create(array_merge($validated, [
            'status' => $validated['status'] ?? 'aberta',
            'vagas_preenchidas' => 0,
            'afirmativa_egresso' => $validated['afirmativa_egresso'] ?? true,
            'empresa_amiga_reintegracao' => $validated['empresa_amiga_reintegracao'] ?? true,
        ]));

        return response()->json([
            'status' => 'created',
            'message' => 'Vaga de emprego cadastrada com sucesso.',
            'data' => $vaga->load('municipio'),
        ], 201);
    }

    /**
     * Update job vacancy.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $vaga = VagaEmprego::find($id);
        if (!$vaga) {
            return response()->json(['error' => 'Vaga não encontrada.'], 404);
        }

        $validated = $request->validate([
            'empresa' => 'nullable|string|max:150',
            'titulo' => 'nullable|string|max:150',
            'descricao' => 'nullable|string',
            'categoria' => 'nullable|string|max:100',
            'municipio_id' => 'nullable|integer|exists:municipios_es,id',
            'salario' => 'nullable|numeric|min:0',
            'regime_contratacao' => 'nullable|string|max:50',
            'afirmativa_egresso' => 'nullable|boolean',
            'empresa_amiga_reintegracao' => 'nullable|boolean',
            'escolaridade_minima' => 'nullable|string|max:100',
            'vagas_totais' => 'nullable|integer|min:1',
            'vagas_preenchidas' => 'nullable|integer|min:0',
            'beneficios' => 'nullable|array',
            'status' => 'nullable|string|in:aberta,preenchida,pausada,cancelada',
        ]);

        $vaga->update($validated);

        return response()->json([
            'status' => 'updated',
            'data' => $vaga->load('municipio'),
        ]);
    }

    /**
     * Delete / Close job vacancy.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $vaga = VagaEmprego::find($id);
        if (!$vaga) {
            return response()->json(['error' => 'Vaga não encontrada.'], 404);
        }

        $vaga->update(['status' => 'cancelada']);

        return response()->json(['status' => 'deleted', 'message' => 'Vaga encerrada com sucesso.']);
    }

    /**
     * Apply / Forward egresso to vacancy and automatically insert timeline event on Prontuário.
     */
    public function candidatar(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $vaga = VagaEmprego::with('municipio')->find($id);

        if (!$vaga) {
            return response()->json(['error' => 'Vaga de emprego não encontrada.'], 404);
        }

        if ($vaga->status !== 'aberta') {
            return response()->json([
                'error' => 'Esta vaga de emprego não está mais recebendo candidaturas.',
                'code' => 'VACANCY_CLOSED',
            ], 422);
        }

        if ($vaga->vagas_preenchidas >= $vaga->vagas_totais) {
            return response()->json([
                'error' => 'Todas as vagas disponíveis para esta oportunidade foram preenchidas.',
                'code' => 'VACANCY_FULL',
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
            return response()->json(['error' => 'Egresso não identificado para a candidatura.'], 422);
        }

        $egresso = Egresso::find($egressoId);
        if (!$egresso) {
            return response()->json(['error' => 'Egresso não encontrado.'], 404);
        }

        // Find or create Prontuário
        $prontuario = Prontuario::where('egresso_id', $egresso->id)->first();

        $timelineId = null;
        if ($prontuario) {
            $municipioNome = $vaga->municipio?->nome ?? 'Espírito Santo';
            $salarioFormatado = $vaga->salario ? 'R$ ' . number_format($vaga->salario, 2, ',', '.') : 'A combinar';

            $descricao = "Encaminhamento realizado para o processo seletivo da vaga de {$vaga->titulo} na empresa {$vaga->empresa} ({$municipioNome}).\n" .
                         "Salário base: {$salarioFormatado}. Regime: {$vaga->regime_contratacao}.\n" .
                         "Observações do encaminhamento: " . ($request->input('observacoes') ?? 'Candidatura submetida via plataforma Conecta Egresso.');

            $timeline = ProntuarioTimeline::create([
                'prontuario_id' => $prontuario->id,
                'tipo_evento' => 'encaminhamento_vaga',
                'titulo' => "Encaminhamento para Vaga: {$vaga->titulo} ({$vaga->empresa})",
                'descricao' => htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'),
                'metadata' => [
                    'vaga_id' => $vaga->id,
                    'empresa' => $vaga->empresa,
                    'titulo' => $vaga->titulo,
                    'municipio_id' => $vaga->municipio_id,
                    'municipio_nome' => $municipioNome,
                    'salario' => (float) $vaga->salario,
                    'categoria' => $vaga->categoria,
                ],
                'responsavel_id' => $user?->id ?? 1,
                'data_evento' => now(),
            ]);

            $timelineId = $timeline->id;

            $this->audit->log(
                $prontuario->id,
                'JOB_APPLICATION_FORWARDED',
                [
                    'vaga_id' => $vaga->id,
                    'vaga_titulo' => $vaga->titulo,
                    'timeline_id' => $timelineId,
                ],
                $user?->id,
                $request->ip(),
                $request->userAgent()
            );
        }

        return response()->json([
            'status' => 'applied',
            'message' => 'Candidatura e encaminhamento registrados com sucesso.',
            'vaga_id' => $vaga->id,
            'prontuario_id' => $prontuario?->id,
            'timeline_id' => $timelineId,
        ], 201);
    }
}
