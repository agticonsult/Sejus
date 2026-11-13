<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Models\Prontuario;
use App\Models\Egresso;
use App\Models\Conhecimento;
use App\Models\MunicipioEs;
use App\Models\ProntuarioTimeline;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LgpdSecurityService;
use Carbon\Carbon;

class ProntuarioController extends Controller
{
    public function __construct(
        protected AuditService $audit,
        protected LgpdSecurityService $lgpd
    ) {}

    /**
     * Render Inertia page for Prontuário Único / Ficha do Egresso with persisted DB data.
     *
     * GET /prontuario/{id?}
     */
    public function showView(Request $request, ?string $id = null): InertiaResponse
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Não autenticado.');
        }

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte() && !$user->isEgresso()) {
            abort(403, 'Acesso negado ao Prontuário Único.');
        }

        $prontuario = null;
        $egresso = null;

        $isStaff = $user->isGestor() || $user->isTecnico() || $user->isSuporte();

        if ($user->isEgresso()) {
            $egresso = $user->egresso?->load(['municipio', 'conhecimentos', 'prontuario.tecnicoResponsavel', 'prontuario.timeline.responsavel']);
            $prontuario = $egresso?->prontuario;

            if ($id !== null && $egresso) {
                $matchesEgresso = is_numeric($id) && (int) $id === $egresso->id;
                $matchesProntuario = $prontuario && ((is_numeric($id) && (int) $id === $prontuario->id) || $id === $prontuario->numero_prontuario);
                if (!$matchesEgresso && !$matchesProntuario) {
                    abort(403, 'Acesso negado: você só pode consultar o seu próprio prontuário.');
                }
            }
        } else {
            $searchQuery = trim((string) $request->input('q', ''));

            if ($id !== null) {
                $prontuario = Prontuario::with([
                    'egresso.municipio',
                    'egresso.conhecimentos',
                    'tecnicoResponsavel:id,name,email',
                    'timeline' => fn($q) => $q->orderBy('data_evento', 'desc'),
                    'timeline.responsavel:id,name',
                ])
                ->where('numero_prontuario', $id)
                ->orWhere(function ($q) use ($id) {
                    if (is_numeric($id)) {
                        $q->where('id', (int) $id)->orWhere('egresso_id', (int) $id);
                    }
                })
                ->first();

                if (!$prontuario && is_numeric($id)) {
                    $egresso = Egresso::with(['municipio', 'conhecimentos', 'prontuario.tecnicoResponsavel', 'prontuario.timeline.responsavel'])
                        ->find((int) $id);
                    $prontuario = $egresso?->prontuario;
                } else {
                    $egresso = $prontuario?->egresso;
                }
            } elseif (!empty($searchQuery)) {
                $cleanCpf = preg_replace('/\D/', '', $searchQuery);
                if (strlen($cleanCpf) === 11) {
                    $hash = $this->lgpd->generateBlindIndex($cleanCpf);
                    $egresso = Egresso::with(['municipio', 'conhecimentos', 'prontuario.tecnicoResponsavel', 'prontuario.timeline.responsavel'])
                        ->where('hash_cpf', $hash)->first();
                    $prontuario = $egresso?->prontuario;
                } else {
                    $prontuario = Prontuario::with([
                        'egresso.municipio',
                        'egresso.conhecimentos',
                        'tecnicoResponsavel:id,name,email',
                        'timeline' => fn($q) => $q->orderBy('data_evento', 'desc'),
                        'timeline.responsavel:id,name',
                    ])
                    ->where('numero_prontuario', 'ilike', "%{$searchQuery}%")
                    ->orWhereHas('egresso', function ($q) use ($searchQuery) {
                        $q->where('nome_completo', 'ilike', "%{$searchQuery}%");
                    })
                    ->first();
                    $egresso = $prontuario?->egresso;
                }
            } else {
                // Modo Produção: Sem fallback cego!
                $prontuario = null;
                $egresso = null;
            }
        }

        // Auto-garante que todo Egresso localizado tenha Prontuário Único inicializado
        if ($egresso && !$prontuario) {
            $prontuario = Prontuario::firstOrCreate(
                ['egresso_id' => $egresso->id],
                [
                    'numero_prontuario' => sprintf('PRT-%s-%06d', now()->format('Y'), (Prontuario::max('id') ?? 0) + 1),
                    'situacao' => 'ativo',
                    'resumo_diagnostico' => 'Prontuário inicializado automaticamente na abertura.',
                    'meta_plano_individual' => 'Plano Individual em elaboração pela equipe multiprofissional.',
                    'data_abertura' => now(),
                ]
            );
            $prontuario->load([
                'tecnicoResponsavel:id,name,email',
                'timeline' => fn($q) => $q->orderBy('data_evento', 'desc'),
                'timeline.responsavel:id,name',
            ]);
        }

        $recentProntuarios = [];
        if ($isStaff) {
            $recentProntuarios = Prontuario::with('egresso:id,nome_completo,cpf_encrypted')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'numero_prontuario' => $p->numero_prontuario,
                    'situacao' => $p->situacao,
                    'egresso_nome' => $p->egresso?->nome_completo ?? 'Não vinculado',
                    'cpf_masked' => $p->egresso?->cpf ? $this->lgpd->maskCpf($p->egresso->cpf) : '',
                ]);
        }

        $conhecimentos = $egresso
            ? $egresso->conhecimentos->map(function ($c) {
                return [
                    'id' => $c->pivot->id ?? $c->id,
                    'conhecimento_id' => $c->id,
                    'nome' => $c->nome,
                    'categoria' => $c->categoria,
                    'nivel' => $c->pivot->nivel,
                    'necessita_treinamento' => (bool) $c->pivot->necessita_treinamento,
                    'observacoes' => $c->pivot->observacoes ?? null,
                ];
            })->values()->all()
            : [];

        $catalogoConhecimentos = Conhecimento::query()
            ->where('ativo', true)
            ->orderBy('nome', 'asc')
            ->get(['id', 'nome', 'categoria', 'descricao'])
            ->map(fn($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'categoria' => $c->categoria,
                'descricao' => $c->descricao,
            ])
            ->values()
            ->all();

        $egressoPayload = $egresso ? [
            'id' => $egresso->id,
            'nome_completo' => $egresso->nome_completo,
            'nome_social' => $egresso->nome_social,
            'cpf_masked' => $egresso->cpf ? $this->lgpd->maskCpf($egresso->cpf) : 'Não informado',
            'data_nascimento' => $egresso->data_nascimento ? $egresso->data_nascimento->format('d/m/Y') : 'Não informada',
            'data_nascimento_iso' => $egresso->data_nascimento ? $egresso->data_nascimento->format('Y-m-d') : '',
            'escolaridade' => $egresso->escolaridade ?? 'Não informada',
            'status_penal' => $egresso->status_penal ?? 'egresso',
            'unidade_prisional_origem' => $egresso->unidade_prisional_origem ?? '',
            'telefone' => $egresso->telefone ?? '',
            'endereco' => $egresso->endereco ?? '',
            'municipio' => [
                'id' => $egresso->municipio?->id,
                'nome' => $egresso->municipio?->nome ?? 'Vitória',
            ],
            'numero_processo_execucao' => $egresso->numero_processo_execucao ?? 'Sob sigilo legal',
            'vulnerabilidades' => is_array($egresso->vulnerabilidades) ? $egresso->vulnerabilidades : [],
            'conhecimentos' => $conhecimentos,
        ] : null;

        $prontuarioPayload = $prontuario ? [
            'id' => $prontuario->id,
            'numero_prontuario' => $prontuario->numero_prontuario,
            'situacao_raw' => (string) $prontuario->situacao,
            'situacao' => mb_strtoupper(str_replace('_', ' ', (string) $prontuario->situacao)),
            'data_abertura' => $prontuario->data_abertura ? $prontuario->data_abertura->format('d/m/Y') : '—',
            'tecnico_responsavel' => [
                'id' => $prontuario->tecnicoResponsavel?->id,
                'name' => $prontuario->tecnicoResponsavel?->name ?? 'Equipe Técnica Escritório Social',
            ],
            'resumo_diagnostico' => $prontuario->resumo_diagnostico ?? '',
            'meta_plano_individual' => $prontuario->meta_plano_individual ?? 'Plano Individual em elaboração pela equipe multiprofissional.',
        ] : null;

        $timelinePayload = ($prontuario && $prontuario->relationLoaded('timeline'))
            ? $prontuario->timeline->map(function ($t) {
                return [
                    'id' => $t->id,
                    'tipo_evento' => $t->tipo_evento,
                    'titulo' => $t->titulo,
                    'descricao' => $t->descricao,
                    'metadata' => is_array($t->metadata) ? $t->metadata : [],
                    'responsavel' => [
                        'name' => $t->responsavel?->name ?? 'Sistema Central SEJUS',
                    ],
                    'data_evento' => $t->data_evento ? $t->data_evento->format('d/m/Y \à\s H:i') : '',
                ];
            })->values()->all()
            : [];

        $municipios = $isStaff
            ? MunicipioEs::orderBy('nome')->get(['id', 'nome'])
            : [];

        return Inertia::render('Prontuario', [
            'isStaff' => $isStaff,
            'recentProntuarios' => $recentProntuarios,
            'egresso' => $egressoPayload,
            'prontuario' => $prontuarioPayload,
            'timeline' => $timelinePayload,
            'conhecimentos' => $conhecimentos,
            'catalogoConhecimentos' => $catalogoConhecimentos,
            'municipios' => $municipios,
        ]);
    }

    /**
     * List prontuários with filtering, blind-index search, and pagination clamping.
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
                'error' => 'Acesso negado ao Prontuário Único.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        // Delta sync filter (since / updated_after)
        $sinceParam = $request->input('since', $request->input('updated_after'));
        $since = null;
        if ($sinceParam !== null && $sinceParam !== '') {
            $isValid = false;
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
        }

        // If Egresso, restrict directly to own prontuario
        if ($user->isEgresso()) {
            $egresso = $user->egresso;
            if (!$egresso) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'current_page' => 1,
                    'per_page' => 15,
                    'last_page' => 1,
                    'message' => 'Nenhum perfil de egresso vinculado ao usuário autenticado.',
                    'meta' => [
                        'server_timestamp' => now()->toIso8601String(),
                        'sync_count' => 0,
                        'since' => $sinceParam ?: null,
                        'total' => 0,
                        'current_page' => 1,
                        'per_page' => 15,
                        'last_page' => 1,
                    ],
                ]);
            }

            $query = Prontuario::with([
                'egresso.municipio',
                'egresso.conhecimentos',
                'tecnicoResponsavel:id,name,email',
                'timeline' => fn($q) => $q->orderBy('data_evento', 'desc'),
            ])->where('egresso_id', $egresso->id);

            if ($since) {
                $query->where('updated_at', '>=', $since->toDateTimeString());
            }

            $prontuario = $query->first();

            $this->audit->log(
                $prontuario?->id,
                'VIEW',
                ['action' => 'egresso_self_prontuario_list'],
                $user->id,
                $request->ip(),
                $request->userAgent()
            );

            $data = $prontuario ? [$this->formatProntuario($prontuario, true)] : [];

            return response()->json([
                'data' => $data,
                'total' => count($data),
                'current_page' => 1,
                'per_page' => 15,
                'last_page' => 1,
                'meta' => [
                    'server_timestamp' => now()->toIso8601String(),
                    'sync_count' => count($data),
                    'since' => $sinceParam ?: null,
                    'total' => count($data),
                    'current_page' => 1,
                    'per_page' => 15,
                    'last_page' => 1,
                ],
            ]);
        }

        $query = Prontuario::with([
            'egresso.municipio',
            'egresso.conhecimentos',
            'tecnicoResponsavel:id,name,email',
        ]);

        if ($since) {
            $query->where('updated_at', '>=', $since->toDateTimeString());
        }

        // Search query filter (numero_prontuario, egresso name, or CPF blind index)
        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $searchLower = '%' . mb_strtolower($searchTerm, 'UTF-8') . '%';
            $cleanCpf = preg_replace('/\D/', '', $searchTerm);

            $query->where(function ($q) use ($searchLower, $cleanCpf) {
                $q->whereRaw('LOWER(numero_prontuario) LIKE ?', [$searchLower])
                  ->orWhereHas('egresso', function ($eq) use ($searchLower, $cleanCpf) {
                      $eq->whereRaw('LOWER(nome_completo) LIKE ?', [$searchLower]);
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
                'filters' => $request->only(['q', 'situacao', 'tecnico_id', 'municipio_id', 'per_page', 'since', 'updated_after']),
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
            'meta' => [
                'server_timestamp' => now()->toIso8601String(),
                'sync_count' => count($formattedData),
                'since' => $sinceParam ?: null,
                'total' => $prontuarios->total(),
                'current_page' => $prontuarios->currentPage(),
                'per_page' => $prontuarios->perPage(),
                'last_page' => $prontuarios->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new Prontuário Único.
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

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json([
                'error' => 'Acesso negado: perfil sem permissão para criar prontuários.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        if ($request->filled('egresso_id')) {
            $validated = $request->validate([
                'egresso_id' => 'required|integer|exists:egressos,id|unique:prontuarios,egresso_id',
                'tecnico_responsavel_id' => 'nullable|integer|exists:users,id',
                'situacao' => 'required|string|in:ativo,em_acompanhamento,arquivado,desligado',
                'resumo_diagnostico' => 'nullable|string|max:65536',
                'meta_plano_individual' => 'nullable|string|max:65536',
            ]);
            $egressoId = (int) $validated['egresso_id'];
        } else {
            $validated = $request->validate([
                'nome_completo' => 'required|string|max:255',
                'cpf' => 'required|string|max:18',
                'nome_social' => 'nullable|string|max:255',
                'data_nascimento' => 'nullable|date',
                'escolaridade' => 'nullable|string|max:120',
                'status_penal' => 'nullable|string|max:80',
                'unidade_prisional_origem' => 'nullable|string|max:255',
                'numero_processo_execucao' => 'nullable|string|max:120',
                'municipio_residencia_id' => 'required|integer|exists:municipios_es,id',
                'telefone' => 'nullable|string|max:30',
                'endereco' => 'nullable|string|max:500',
                'vulnerabilidades' => 'nullable|array',
                'tecnico_responsavel_id' => 'nullable|integer|exists:users,id',
                'situacao' => 'nullable|string|in:ativo,em_acompanhamento,arquivado,desligado',
                'resumo_diagnostico' => 'nullable|string|max:65536',
                'meta_plano_individual' => 'nullable|string|max:65536',
            ]);

            $cleanCpf = preg_replace('/\D/', '', $validated['cpf']);
            if (!$this->lgpd->validateCpf($cleanCpf)) {
                return response()->json([
                    'error' => 'CPF inválido segundo algoritmo oficial.',
                    'code' => 'INVALID_CPF',
                ], 422);
            }

            $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);
            if (Egresso::where('hash_cpf', $hashCpf)->exists()) {
                return response()->json([
                    'error' => 'Já existe um egresso cadastrado com este CPF.',
                    'code' => 'DUPLICATE_EGRESSO_CPF',
                ], 422);
            }

            $egresso = Egresso::create([
                'nome_completo' => $validated['nome_completo'],
                'nome_social' => $validated['nome_social'] ?? null,
                'cpf' => $cleanCpf,
                'data_nascimento' => $validated['data_nascimento'] ?? null,
                'escolaridade' => $validated['escolaridade'] ?? null,
                'status_penal' => $validated['status_penal'] ?? 'egresso',
                'unidade_prisional_origem' => $validated['unidade_prisional_origem'] ?? null,
                'numero_processo_execucao' => $validated['numero_processo_execucao'] ?? null,
                'municipio_residencia_id' => (int) $validated['municipio_residencia_id'],
                'telefone' => $validated['telefone'] ?? null,
                'endereco' => $validated['endereco'] ?? null,
                'vulnerabilidades' => $validated['vulnerabilidades'] ?? [],
                'consentimento_compartilhamento' => true,
                'termo_aceito_em' => now(),
            ]);
            $egressoId = $egresso->id;
        }

        // Sequential PRT-2026-XXXXXX generation
        $nextNum = (Prontuario::max('id') ?? 0) + 1;
        $numeroProntuario = sprintf('PRT-2026-%06d', $nextNum);

        $tecnicoId = $validated['tecnico_responsavel_id'] ?? ($user?->isTecnico() ? $user->id : null);

        $prontuario = Prontuario::create([
            'numero_prontuario' => $numeroProntuario,
            'egresso_id' => $egressoId,
            'tecnico_responsavel_id' => $tecnicoId,
            'situacao' => $validated['situacao'] ?? 'ativo',
            'resumo_diagnostico' => $validated['resumo_diagnostico'] ?? null,
            'meta_plano_individual' => $validated['meta_plano_individual'] ?? null,
            'data_abertura' => now(),
        ]);

        if (!$request->filled('egresso_id')) {
            ProntuarioTimeline::create([
                'prontuario_id' => $prontuario->id,
                'tipo_evento' => 'acolhimento_video',
                'titulo' => 'Abertura do Prontuário Único do Egresso',
                'descricao' => 'Cadastro inicial do cidadão egresso e abertura formal do Plano Individual de Reintegração (PIA).',
                'metadata' => ['numero_prontuario' => $numeroProntuario],
                'responsavel_id' => $user->id,
                'data_evento' => now(),
            ]);
        }

        $this->audit->log(
            $prontuario->id,
            'CREATE',
            [
                'numero_prontuario' => $numeroProntuario,
                'egresso_id' => $egressoId,
                'situacao' => $prontuario->situacao,
            ],
            $user?->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'created',
            'message' => 'Prontuário criado com sucesso.',
            'data' => $this->formatProntuario($prontuario->load(['egresso.municipio', 'egresso.conhecimentos', 'tecnicoResponsavel']), false),
        ], 201);
    }

    /**
     * Show single Prontuário details.
     */
    public function show(Request $request, string $id): JsonResponse
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
                'error' => 'Acesso negado ao Prontuário Único.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $prontuario = Prontuario::with([
            'egresso.municipio',
            'egresso.conhecimentos',
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
        if ($user->isEgresso()) {
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
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        $isEgressoSelf = $user->isEgresso();

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

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json([
                'error' => 'Acesso negado: seu perfil não pode alterar dados do prontuário.',
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
            'nome_completo' => 'nullable|string|max:255',
            'nome_social' => 'nullable|string|max:255',
            'data_nascimento' => 'nullable|date',
            'escolaridade' => 'nullable|string|max:120',
            'status_penal' => 'nullable|string|max:80',
            'unidade_prisional_origem' => 'nullable|string|max:255',
            'numero_processo_execucao' => 'nullable|string|max:120',
            'municipio_residencia_id' => 'nullable|integer|exists:municipios_es,id',
            'telefone' => 'nullable|string|max:30',
            'endereco' => 'nullable|string|max:500',
            'vulnerabilidades' => 'nullable|array',
        ]);

        $prontuarioFields = array_intersect_key($validated, array_flip([
            'situacao', 'resumo_diagnostico', 'meta_plano_individual', 'tecnico_responsavel_id',
        ]));
        $prontuario->update(array_filter($prontuarioFields, fn($val) => $val !== null));

        $egressoFields = array_intersect_key($validated, array_flip([
            'nome_completo', 'nome_social', 'data_nascimento', 'escolaridade',
            'status_penal', 'unidade_prisional_origem', 'numero_processo_execucao',
            'municipio_residencia_id', 'telefone', 'endereco', 'vulnerabilidades',
        ]));
        if ($prontuario->egresso && !empty($egressoFields)) {
            $prontuario->egresso->fill(array_filter($egressoFields, fn($val) => $val !== null));
            $prontuario->egresso->save();
        }

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
            'data' => $this->formatProntuario($prontuario->load(['egresso.municipio', 'egresso.conhecimentos', 'tecnicoResponsavel']), false),
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
                'conhecimentos' => $egresso->relationLoaded('conhecimentos')
                    ? $egresso->conhecimentos->map(function ($c) {
                        return [
                            'id' => $c->pivot->id ?? $c->id,
                            'conhecimento_id' => $c->id,
                            'nome' => $c->nome,
                            'categoria' => $c->categoria,
                            'nivel' => $c->pivot->nivel,
                            'necessita_treinamento' => (bool) $c->pivot->necessita_treinamento,
                            'observacoes' => $c->pivot->observacoes ?? null,
                        ];
                    })->values()->all()
                    : [],
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
