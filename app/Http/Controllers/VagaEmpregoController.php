<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Models\VagaEmprego;
use App\Models\CursoCapacitacao;
use App\Models\Conhecimento;
use App\Models\MunicipioEs;
use App\Models\Egresso;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;
use App\Services\CsvVagaImportService;
use Carbon\Carbon;

class VagaEmpregoController extends Controller
{
    public function __construct(
        protected AuditService $audit,
        protected CsvVagaImportService $csvImporter
    ) {}

    /**
     * Render Inertia page for Oportunidades & Inserir Vaga (Empregador / Gestor / Egresso).
     *
     * GET /oportunidades
     * GET /oportunidades/inserir-vaga
     */
    public function indexView(Request $request): InertiaResponse
    {
        $user = Auth::user();

        $vagasQuery = VagaEmprego::with([
            'municipio:id,nome,codigo_ibge,macrorregiao',
            'conhecimentos:id,nome,categoria',
        ])->where('status', 'aberta');

        $vagas = $vagasQuery->orderBy('id', 'desc')->get()->map(function (VagaEmprego $v) {
            return [
                'id' => $v->id,
                'user_id' => $v->user_id,
                'empresa' => $v->empresa,
                'titulo' => $v->titulo,
                'descricao' => $v->descricao,
                'categoria' => $v->categoria,
                'municipio_id' => $v->municipio_id,
                'municipio' => $v->municipio ? [
                    'id' => $v->municipio->id,
                    'nome' => $v->municipio->nome,
                    'codigo_ibge' => $v->municipio->codigo_ibge,
                    'macrorregiao' => $v->municipio->macrorregiao,
                ] : ['nome' => 'Vitória'],
                'salario' => $v->salario !== null ? (float) $v->salario : null,
                'regime_contratacao' => $v->regime_contratacao ?? 'CLT',
                'afirmativa_egresso' => (bool) $v->afirmativa_egresso,
                'empresa_amiga_reintegracao' => (bool) $v->empresa_amiga_reintegracao,
                'escolaridade_minima' => $v->escolaridade_minima ?? 'Ensino Fundamental',
                'vagas_totais' => (int) $v->vagas_totais,
                'vagas_preenchidas' => (int) $v->vagas_preenchidas,
                'inicio_imediato' => (bool) $v->inicio_imediato,
                'data_inicio' => $v->data_inicio?->format('Y-m-d'),
                'data_inicio_formatada' => $v->inicio_imediato
                    ? 'Imediato'
                    : ($v->data_inicio ? $v->data_inicio->format('d/m/Y') : null),
                'fim_permanente' => (bool) $v->fim_permanente,
                'data_fim' => $v->data_fim?->format('Y-m-d'),
                'data_fim_formatada' => $v->fim_permanente
                    ? 'Permanente'
                    : ($v->data_fim ? $v->data_fim->format('d/m/Y') : null),
                'status' => $v->status,
                'beneficios' => is_array($v->beneficios) ? $v->beneficios : [],
                'conhecimentos' => $v->conhecimentos->map(fn($c) => [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'categoria' => $c->categoria,
                ])->values()->all(),
            ];
        })->values()->all();

        $cursos = CursoCapacitacao::with('municipio:id,nome,codigo_ibge,macrorregiao')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (CursoCapacitacao $c) {
                return [
                    'id' => $c->id,
                    'instituicao' => $c->instituicao,
                    'titulo' => $c->titulo,
                    'descricao' => $c->descricao,
                    'modalidade' => $c->modalidade,
                    'carga_horaria' => (int) $c->carga_horaria,
                    'municipio' => $c->municipio ? ['id' => $c->municipio->id, 'nome' => $c->municipio->nome] : null,
                    'bolsa_auxilio' => $c->bolsa_auxilio !== null ? (float) $c->bolsa_auxilio : 0.0,
                    'certificado_reconhecido' => true,
                    'vagas_disponiveis' => (int) $c->vagas_disponiveis,
                ];
            })->values()->all();

        $municipios = MunicipioEs::orderBy('nome', 'asc')
            ->get(['id', 'nome', 'codigo_ibge', 'macrorregiao'])
            ->values()
            ->all();

        $catalogoConhecimentos = Conhecimento::query()
            ->where('ativo', true)
            ->orderBy('nome', 'asc')
            ->get(['id', 'nome', 'categoria', 'descricao'])
            ->values()
            ->all();

        $initialTab = 'vagas';
        if (
            $request->routeIs('oportunidades.inserir_vaga') ||
            $request->input('tab') === 'inserir-vaga' ||
            ($user && $user->isEmpregador())
        ) {
            $initialTab = 'inserir-vaga';
        }

        // Fetch User's Applications and Course Enrollments from Prontuário Timeline
        $minhasCandidaturas = [];
        $meusCursos = [];
        if ($user && ($user->isEgresso() || $user->egresso)) {
            $egressoId = $user->egresso?->id;
            $prontuario = Prontuario::where('egresso_id', $egressoId)->first();
            if ($prontuario) {
                $timelineEvents = ProntuarioTimeline::where('prontuario_id', $prontuario->id)
                    ->whereIn('tipo_evento', ['encaminhamento_vaga', 'inscricao_curso', 'matricula_curso'])
                    ->orderBy('data_evento', 'desc')
                    ->get();

                foreach ($timelineEvents as $evt) {
                    if ($evt->tipo_evento === 'encaminhamento_vaga') {
                        $minhasCandidaturas[] = [
                            'id' => $evt->id,
                            'vaga_id' => $evt->metadata['vaga_id'] ?? null,
                            'titulo' => $evt->metadata['titulo'] ?? $evt->titulo,
                            'empresa' => $evt->metadata['empresa'] ?? 'Empresa Parceira',
                            'municipio_nome' => $evt->metadata['municipio_nome'] ?? 'Espírito Santo',
                            'salario' => $evt->metadata['salario'] ?? null,
                            'data_evento' => $evt->data_evento?->format('d/m/Y H:i'),
                            'status' => 'Encaminhado ao Empregador',
                        ];
                    } else {
                        $meusCursos[] = [
                            'id' => $evt->id,
                            'curso_id' => $evt->metadata['curso_id'] ?? null,
                            'titulo' => $evt->metadata['titulo'] ?? $evt->titulo,
                            'instituicao' => $evt->metadata['instituicao'] ?? 'Instituição Conveniada',
                            'carga_horaria' => $evt->metadata['carga_horaria'] ?? null,
                            'modalidade' => $evt->metadata['modalidade'] ?? 'Presencial',
                            'bolsa_auxilio' => $evt->metadata['bolsa_auxilio'] ?? null,
                            'data_evento' => $evt->data_evento?->format('d/m/Y H:i'),
                            'status' => 'Matrícula Confirmada',
                        ];
                    }
                }
            }
        }

        $currentUser = Auth::user();
        $isStaff = $currentUser && in_array($currentUser->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);
        $egressosList = $isStaff
            ? \App\Models\Egresso::orderBy('nome_completo')
                ->limit(100)
                ->get(['id', 'nome_completo'])
                ->map(fn ($e) => ['id' => $e->id, 'nome' => $e->nome_completo])
                ->values()
                ->all()
            : [];

        return Inertia::render('Oportunidades', [
            'vagas' => $vagas,
            'cursos' => $cursos,
            'municipios' => $municipios,
            'catalogoConhecimentos' => $catalogoConhecimentos,
            'initialTab' => $initialTab,
            'minhasCandidaturas' => $minhasCandidaturas,
            'meusCursos' => $meusCursos,
            'egressosList' => $egressosList,
        ]);
    }

    /**
     * List job vacancies with municipality, category, affirmative action filters and accent-insensitive search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = VagaEmprego::with([
            'municipio:id,nome,codigo_ibge,macrorregiao',
            'conhecimentos:id,nome,categoria',
        ]);

        // Status filter (defaults to 'aberta' if not specified)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'aberta');
        }

        // CEP Resolution filter (Resolves ES CEP to Municipio)
        if ($request->filled('cep')) {
            $cepService = app(\App\Services\CepMunicipioService::class);
            $munByCep = $cepService->resolveByCep($request->input('cep'));
            if ($munByCep) {
                $query->where('municipio_id', $munByCep->id);
            }
        } elseif ($request->filled('municipio_id')) {
            $query->where('municipio_id', (int) $request->input('municipio_id'));
        } elseif ($request->filled('municipio')) {
            $munSearch = trim($request->input('municipio'));
            $cleanIbge = preg_replace('/\D/', '', $munSearch);

            $query->whereHas('municipio', function ($mq) use ($munSearch, $cleanIbge) {
                if (strlen($cleanIbge) === 7 && str_starts_with($cleanIbge, '32')) {
                    $mq->where('codigo_ibge', $cleanIbge);
                } else {
                    $mq->whereRaw('LOWER(nome) LIKE ?', ['%' . mb_strtolower($munSearch, 'UTF-8') . '%']);
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
            $qLower = '%' . mb_strtolower($q, 'UTF-8') . '%';
            $query->where(function ($sub) use ($qLower) {
                $sub->whereRaw('LOWER(titulo) LIKE ?', [$qLower])
                    ->orWhereRaw('LOWER(empresa) LIKE ?', [$qLower])
                    ->orWhereRaw('LOWER(descricao) LIKE ?', [$qLower]);
            });
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

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $vagas = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $vagas->items(),
            'current_page' => $vagas->currentPage(),
            'per_page' => $vagas->perPage(),
            'total' => $vagas->total(),
            'last_page' => $vagas->lastPage(),
            'meta' => [
                'server_timestamp' => now()->toIso8601String(),
                'sync_count' => count($vagas->items()),
                'since' => $sinceParam ?: null,
                'total' => $vagas->total(),
                'current_page' => $vagas->currentPage(),
                'per_page' => $vagas->perPage(),
                'last_page' => $vagas->lastPage(),
            ],
        ]);
    }

    /**
     * Show single job opening details.
     */
    public function show(string $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['error' => 'Vaga de emprego não encontrada.'], 404);
        }

        $vaga = VagaEmprego::with([
            'municipio:id,nome,codigo_ibge,macrorregiao,latitude,longitude',
            'conhecimentos:id,nome,categoria',
        ])->find((int) $id);

        if (!$vaga) {
            return response()->json(['error' => 'Vaga de emprego não encontrada.'], 404);
        }

        return response()->json(['data' => $vaga]);
    }

    /**
     * Create a new job vacancy (Empregador / Gestor / Técnico / Suporte).
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.', 'code' => 'UNAUTHENTICATED'], 401);
        }

        if (!$user->isEmpregador() && !$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json(['error' => 'Acesso não autorizado para cadastro de vagas.', 'code' => 'FORBIDDEN'], 403);
        }

        // Normalize aliases for manual form submissions (nome_vaga -> titulo, quantidade_vaga -> vagas_totais)
        $payload = $request->all();
        if (empty($payload['titulo']) && !empty($payload['nome_vaga'])) {
            $payload['titulo'] = $payload['nome_vaga'];
        }
        if (!isset($payload['vagas_totais']) && isset($payload['quantidade_vaga'])) {
            $payload['vagas_totais'] = $payload['quantidade_vaga'];
        }
        if (empty($payload['empresa'])) {
            $payload['empresa'] = $user->name;
        }
        if (empty($payload['categoria'])) {
            $payload['categoria'] = 'geral';
        }
        if (empty($payload['municipio_id'])) {
            $payload['municipio_id'] = $user->municipio_id
                ?? MunicipioEs::where('codigo_ibge', 3205309)->value('id')
                ?? MunicipioEs::orderBy('id', 'asc')->value('id');
        }

        $validated = validator($payload, [
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
            'inicio_imediato' => 'nullable|boolean',
            'data_inicio' => 'nullable|date',
            'fim_permanente' => 'nullable|boolean',
            'data_fim' => 'nullable|date',
            'beneficios' => 'nullable|array',
            'conhecimentos' => 'nullable|array',
            'conhecimentos.*' => 'integer|exists:conhecimentos,id',
            'status' => 'nullable|string|in:aberta,preenchida,pausada,cancelada',
        ])->validate();

        $inicioImediato = (bool) ($validated['inicio_imediato'] ?? false);
        $fimPermanente = (bool) ($validated['fim_permanente'] ?? false);
        $dataInicio = $inicioImediato ? null : ($validated['data_inicio'] ?? null);
        $dataFim = $fimPermanente ? null : ($validated['data_fim'] ?? null);

        if ($dataInicio && $dataFim && Carbon::parse($dataFim)->lt(Carbon::parse($dataInicio))) {
            return response()->json([
                'error' => 'A data de fim não pode ser anterior à data de início.',
                'errors' => ['data_fim' => ['A data de fim não pode ser anterior à data de início.']],
            ], 422);
        }

        $conhecimentoIds = $validated['conhecimentos'] ?? [];
        unset($validated['conhecimentos']);

        $vaga = VagaEmprego::create(array_merge($validated, [
            'user_id' => $user->id,
            'inicio_imediato' => $inicioImediato,
            'data_inicio' => $dataInicio,
            'fim_permanente' => $fimPermanente,
            'data_fim' => $dataFim,
            'status' => $validated['status'] ?? 'aberta',
            'vagas_preenchidas' => 0,
            'regime_contratacao' => $validated['regime_contratacao'] ?? 'CLT',
            'afirmativa_egresso' => $validated['afirmativa_egresso'] ?? true,
            'empresa_amiga_reintegracao' => $validated['empresa_amiga_reintegracao'] ?? true,
        ]));

        if (!empty($conhecimentoIds)) {
            $vaga->conhecimentos()->sync($conhecimentoIds);
        }

        return response()->json([
            'status' => 'created',
            'message' => 'Vaga de emprego cadastrada com sucesso.',
            'data' => $vaga->load(['municipio', 'conhecimentos:id,nome,categoria']),
        ], 201);
    }

    /**
     * Update job vacancy.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        if (!$user->isEmpregador() && !$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $vaga = VagaEmprego::find($id);
        if (!$vaga) {
            return response()->json(['error' => 'Vaga não encontrada.'], 404);
        }

        if ($user->isEmpregador() && (int) $vaga->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Acesso negado: você só pode editar vagas publicadas pela sua empresa.'], 403);
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
            'inicio_imediato' => 'nullable|boolean',
            'data_inicio' => 'nullable|date',
            'fim_permanente' => 'nullable|boolean',
            'data_fim' => 'nullable|date',
            'beneficios' => 'nullable|array',
            'conhecimentos' => 'nullable|array',
            'conhecimentos.*' => 'integer|exists:conhecimentos,id',
            'status' => 'nullable|string|in:aberta,preenchida,pausada,cancelada',
        ]);

        $conhecimentoIds = $validated['conhecimentos'] ?? null;
        unset($validated['conhecimentos']);

        $vaga->update($validated);

        if (is_array($conhecimentoIds)) {
            $vaga->conhecimentos()->sync($conhecimentoIds);
        }

        return response()->json([
            'status' => 'updated',
            'data' => $vaga->load(['municipio', 'conhecimentos:id,nome,categoria']),
        ]);
    }

    /**
     * Delete / Close job vacancy.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        if (!$user->isEmpregador() && !$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json(['error' => 'Acesso não autorizado.'], 403);
        }

        $vaga = VagaEmprego::find($id);
        if (!$vaga) {
            return response()->json(['error' => 'Vaga não encontrada.'], 404);
        }

        if ($user->isEmpregador() && (int) $vaga->user_id !== (int) $user->id) {
            return response()->json(['error' => 'Acesso negado: você só pode encerrar vagas publicadas pela sua empresa.'], 403);
        }

        $vaga->update(['status' => 'cancelada']);

        return response()->json(['status' => 'deleted', 'message' => 'Vaga encerrada com sucesso.']);
    }

    /**
     * Download CSV template for job vacancy import.
     *
     * GET /api/vagas/modelo-csv
     * GET /vagas/modelo-csv
     */
    public function downloadModeloCsv(Request $request): Response|JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isEmpregador() && !$user->isGestor() && !$user->isSuporte() && !$user->isTecnico()) {
            return response()->json([
                'error' => 'Acesso negado: apenas empregadores e gestores podem baixar o modelo de importação de vagas.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $csvContent = $this->csvImporter->generateTemplateCsv();

        $this->audit->log(
            null,
            'EXPORT',
            ['action' => 'download_modelo_csv_vagas'],
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo_importacao_vagas.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Import job vacancies from uploaded CSV file.
     *
     * POST /api/vagas/importar-csv
     * POST /vagas/importar-csv
     */
    public function importarCsv(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isEmpregador() && !$user->isGestor() && !$user->isSuporte() && !$user->isTecnico()) {
            return response()->json([
                'error' => 'Acesso negado: apenas empregadores e gestores podem importar vagas via CSV.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $uploadedFile = $request->file('arquivo') ?? $request->file('file') ?? $request->file('csv');

        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return response()->json([
                'status' => 'validation_error',
                'error' => 'Envie um arquivo CSV válido no campo "arquivo".',
                'message' => 'Envie um arquivo CSV válido no campo "arquivo".',
                'erros' => [
                    ['linha' => 1, 'campo' => 'arquivo', 'erro' => 'Nenhum arquivo CSV válido foi enviado.'],
                ],
            ], 422);
        }

        // Max 5 MB
        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            return response()->json([
                'status' => 'validation_error',
                'error' => 'O arquivo CSV excede o tamanho máximo permitido de 5 MB.',
                'message' => 'O arquivo CSV excede o tamanho máximo permitido de 5 MB.',
                'erros' => [
                    ['linha' => 1, 'campo' => 'arquivo', 'erro' => 'Arquivo superior ao limite de 5 MB.'],
                ],
            ], 422);
        }

        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt'], true)) {
            return response()->json([
                'status' => 'validation_error',
                'error' => 'Formato de arquivo inválido. Envie um arquivo com extensão .csv.',
                'message' => 'Formato de arquivo inválido. Envie um arquivo com extensão .csv.',
                'erros' => [
                    ['linha' => 1, 'campo' => 'arquivo', 'erro' => 'Formato inválido. Utilize arquivo .csv.'],
                ],
            ], 422);
        }

        $rawContent = (string) file_get_contents($uploadedFile->getRealPath());
        $requestedMunicipioId = $request->filled('municipio_id') ? (int) $request->input('municipio_id') : null;

        $result = $this->csvImporter->importFromCsvContent($rawContent, $user, $requestedMunicipioId);

        if ($result['ok']) {
            $this->audit->log(
                null,
                'CREATE',
                [
                    'action' => 'import_csv_vagas',
                    'sucesso_count' => $result['payload']['sucesso_count'],
                ],
                $user->id,
                $request->ip(),
                $request->userAgent()
            );
        }

        return response()->json($result['payload'], $result['http_status']);
    }

    /**
     * Apply / Forward egresso to vacancy and automatically insert timeline event on Prontuário.
     */
    public function candidatar(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.', 'code' => 'UNAUTHENTICATED'], 401);
        }

        if (!$user->isEgresso() && !$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json(['error' => 'Acesso não autorizado para candidatura a vagas.', 'code' => 'FORBIDDEN'], 403);
        }

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
        $egressoId = $user->isEgresso()
            ? $user->egresso?->id
            : $request->input('egresso_id');

        if (!$egressoId) {
            return response()->json(['error' => 'Egresso não identificado para a candidatura.'], 422);
        }

        $egresso = Egresso::find($egressoId);
        if (!$egresso) {
            return response()->json(['error' => 'Egresso não encontrado.'], 404);
        }

        // Find or create Prontuário
        $prontuario = Prontuario::firstOrCreate(
            ['egresso_id' => $egresso->id],
            [
                'numero_prontuario' => 'PRONT-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT),
                'situacao' => 'ativo',
                'data_abertura' => now(),
                'ultima_atualizacao' => now(),
            ]
        );

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

            $vaga->increment('vagas_preenchidas');

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
