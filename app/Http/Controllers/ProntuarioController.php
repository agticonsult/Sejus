<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Prontuario;
use App\Models\Egresso;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LgpdSecurityService;

class ProntuarioController extends Controller
{
    public function __construct(
        protected AuditService $audit,
        protected LgpdSecurityService $lgpd
    ) {}

    /**
     * List prontuários with filtering, blind-index search, and pagination clamping.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // If Egresso, restrict directly to own prontuario
        if ($user && $user->isEgresso()) {
            $egresso = $user->egresso;
            if (!$egresso) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'message' => 'Nenhum perfil de egresso vinculado ao usuário autenticado.',
                ]);
            }

            $prontuario = Prontuario::with([
                'egresso.municipio',
                'tecnicoResponsavel:id,name,email',
                'timeline' => fn($q) => $q->orderBy('data_evento', 'desc'),
            ])->where('egresso_id', $egresso->id)->first();

            $this->audit->log(
                $prontuario?->id,
                'VIEW',
                ['action' => 'egresso_self_prontuario_list'],
                $user->id,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'data' => $prontuario ? [$this->formatProntuario($prontuario, true)] : [],
                'total' => $prontuario ? 1 : 0,
                'current_page' => 1,
                'per_page' => 15,
                'last_page' => 1,
            ]);
        }

        $query = Prontuario::with([
            'egresso.municipio',
            'tecnicoResponsavel:id,name,email',
        ]);

        // Search query filter (numero_prontuario, egresso name, or CPF blind index)
        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $cleanCpf = preg_replace('/\D/', '', $searchTerm);

            $query->where(function ($q) use ($searchTerm, $cleanCpf) {
                $q->where('numero_prontuario', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereHas('egresso', function ($eq) use ($searchTerm, $cleanCpf) {
                      $eq->where('nome_completo', 'ILIKE', "%{$searchTerm}%");
                      if (strlen($cleanCpf) === 11) {
                          $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);
                          $eq->orWhere('hash_cpf', $hashCpf);
                      }
                  });
            });
        }

        // Situacao filter
        if ($request->filled('situacao')) {
            $query->where('situacao', $request->input('situacao'));
        }

        // Tecnico filter
        if ($request->filled('tecnico_id')) {
            $query->where('tecnico_responsavel_id', $request->input('tecnico_id'));
        }

        // Municipio filter
        if ($request->filled('municipio_id')) {
            $municipioId = (int) $request->input('municipio_id');
            $query->whereHas('egresso', fn($eq) => $eq->where('municipio_residencia_id', $municipioId));
        }

        // Clamped pagination between 1 and 100
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $prontuarios = $query->orderBy('id', 'desc')->paginate($perPage);

        // Record Audit Log for index view
        $this->audit->log(
            null,
            'VIEW',
            [
                'action' => 'list_prontuarios',
                'filters' => $request->only(['q', 'situacao', 'tecnico_id', 'municipio_id', 'per_page']),
                'total_results' => $prontuarios->total(),
            ],
            $user?->id,
            $request->ip(),
            $request->userAgent()
        );

        $formattedData = collect($prontuarios->items())->map(fn($p) => $this->formatProntuario($p, false));

        return response()->json([
            'data' => $formattedData,
            'current_page' => $prontuarios->currentPage(),
            'per_page' => $prontuarios->perPage(),
            'total' => $prontuarios->total(),
            'last_page' => $prontuarios->lastPage(),
        ]);
    }

    /**
     * Create a new Prontuário Único.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json([
                'error' => 'Acesso negado: egressos não possuem permissão para criar prontuários.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $validated = $request->validate([
            'egresso_id' => 'required|integer|exists:egressos,id|unique:prontuarios,egresso_id',
            'tecnico_responsavel_id' => 'nullable|integer|exists:users,id',
            'situacao' => 'required|string|in:ativo,em_acompanhamento,arquivado,desligado',
            'resumo_diagnostico' => 'nullable|string|max:65536',
            'meta_plano_individual' => 'nullable|string|max:65536',
        ]);

        // Sequential PRT-2026-XXXXXX generation
        $nextNum = (Prontuario::max('id') ?? 0) + 1;
        $numeroProntuario = sprintf('PRT-2026-%06d', $nextNum);

        $tecnicoId = $validated['tecnico_responsavel_id'] ?? ($user?->isTecnico() ? $user->id : null);

        $prontuario = Prontuario::create([
            'numero_prontuario' => $numeroProntuario,
            'egresso_id' => $validated['egresso_id'],
            'tecnico_responsavel_id' => $tecnicoId,
            'situacao' => $validated['situacao'],
            'resumo_diagnostico' => $validated['resumo_diagnostico'] ?? null,
            'meta_plano_individual' => $validated['meta_plano_individual'] ?? null,
            'data_abertura' => now(),
        ]);

        $this->audit->log(
            $prontuario->id,
            'CREATE',
            [
                'numero_prontuario' => $numeroProntuario,
                'egresso_id' => $validated['egresso_id'],
                'situacao' => $validated['situacao'],
            ],
            $user?->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'created',
            'message' => 'Prontuário criado com sucesso.',
            'data' => $this->formatProntuario($prontuario->load(['egresso.municipio', 'tecnicoResponsavel']), false),
        ], 201);
    }

    /**
     * Show single Prontuário details.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $prontuario = Prontuario::with([
            'egresso.municipio',
            'tecnicoResponsavel:id,name,email',
            'timeline.responsavel:id,name',
            'videoRooms',
        ])
        ->where('id', is_numeric($id) ? (int)$id : 0)
        ->orWhere('numero_prontuario', $id)
        ->first();

        if (!$prontuario) {
            return response()->json([
                'error' => 'Prontuário não encontrado.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        // Authorization check: Egresso can only view own record
        if ($user && $user->isEgresso()) {
            if (!$user->egresso || $user->egresso->id !== $prontuario->egresso_id) {
                return response()->json([
                    'error' => 'Acesso negado: você só pode consultar o seu próprio prontuário.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        }

        $this->audit->log(
            $prontuario->id,
            'VIEW',
            [
                'action' => 'show_prontuario',
                'numero_prontuario' => $prontuario->numero_prontuario,
            ],
            $user?->id,
            $request->ip(),
            $request->userAgent()
        );

        $isEgressoSelf = $user && $user->isEgresso();

        return response()->json([
            'data' => $this->formatProntuario($prontuario, $isEgressoSelf),
        ]);
    }

    /**
     * Update Prontuário record.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if ($user && $user->isEgresso()) {
            return response()->json([
                'error' => 'Acesso negado: egressos não podem alterar dados do prontuário.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $prontuario = Prontuario::where('id', is_numeric($id) ? (int)$id : 0)
            ->orWhere('numero_prontuario', $id)
            ->first();

        if (!$prontuario) {
            return response()->json(['error' => 'Prontuário não encontrado.'], 404);
        }

        $validated = $request->validate([
            'situacao' => 'nullable|string|in:ativo,em_acompanhamento,arquivado,desligado',
            'resumo_diagnostico' => 'nullable|string|max:65536',
            'meta_plano_individual' => 'nullable|string|max:65536',
            'tecnico_responsavel_id' => 'nullable|integer|exists:users,id',
        ]);

        $prontuario->update(array_filter($validated, fn($val) => $val !== null));

        $this->audit->log(
            $prontuario->id,
            'UPDATE',
            [
                'updated_fields' => array_keys($validated),
                'situacao' => $prontuario->situacao,
            ],
            $user?->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'updated',
            'message' => 'Prontuário atualizado com sucesso.',
            'data' => $this->formatProntuario($prontuario->load(['egresso.municipio', 'tecnicoResponsavel']), false),
        ]);
    }

    /**
     * Archive/Delete Prontuário (Gestor only).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isGestor()) {
            return response()->json([
                'error' => 'Acesso negado: apenas Gestores SEJUS podem arquivar prontuários.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $prontuario = Prontuario::where('id', is_numeric($id) ? (int)$id : 0)
            ->orWhere('numero_prontuario', $id)
            ->first();

        if (!$prontuario) {
            return response()->json(['error' => 'Prontuário não encontrado.'], 404);
        }

        $prontuario->update(['situacao' => 'arquivado']);

        $this->audit->log(
            $prontuario->id,
            'DELETE',
            ['status' => 'arquivado', 'action' => 'prontuario_archived'],
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'archived',
            'message' => 'Prontuário arquivado com sucesso.',
        ]);
    }

    /**
     * Format Prontuário output with LGPD masking.
     */
    protected function formatProntuario(Prontuario $prontuario, bool $restrictedForEgresso): array
    {
        $egresso = $prontuario->egresso;

        $timelineEvents = $prontuario->relationLoaded('timeline')
            ? $prontuario->timeline->map(function ($t) {
                return [
                    'id' => $t->id,
                    'tipo_evento' => $t->tipo_evento,
                    'titulo' => $t->titulo,
                    'descricao' => $t->descricao,
                    'metadata' => $t->metadata,
                    'responsavel' => $t->responsavel ? [
                        'id' => $t->responsavel->id,
                        'name' => $t->responsavel->name,
                    ] : null,
                    'data_evento' => $t->data_evento?->toIso8601String(),
                ];
            })
            : null;

        return [
            'id' => $prontuario->id,
            'numero_prontuario' => $prontuario->numero_prontuario,
            'situacao' => $prontuario->situacao,
            'resumo_diagnostico' => $prontuario->resumo_diagnostico,
            'meta_plano_individual' => $prontuario->meta_plano_individual,
            'data_abertura' => $prontuario->data_abertura?->toIso8601String(),
            'created_at' => $prontuario->created_at?->toIso8601String(),
            'updated_at' => $prontuario->updated_at?->toIso8601String(),
            'egresso' => $egresso ? [
                'id' => $egresso->id,
                'nome_completo' => $egresso->nome_completo,
                'nome_social' => $egresso->nome_social,
                'cpf_masked' => $egresso->cpf ? $this->lgpd->maskCpf($egresso->cpf) : null,
                'status_penal' => $egresso->status_penal,
                'municipio_id' => $egresso->municipio_residencia_id,
                'municipio_nome' => $egresso->municipio?->nome,
                'macrorregiao' => $egresso->municipio?->macrorregiao,
            ] : null,
            'tecnico_responsavel' => $prontuario->tecnicoResponsavel ? [
                'id' => $prontuario->tecnicoResponsavel->id,
                'name' => $prontuario->tecnicoResponsavel->name,
                'email' => $prontuario->tecnicoResponsavel->email,
            ] : null,
            'timeline' => $timelineEvents,
        ];
    }
}
