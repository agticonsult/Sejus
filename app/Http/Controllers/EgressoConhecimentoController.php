<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Egresso;
use App\Models\Conhecimento;
use App\Models\EgressoConhecimento;
use App\Services\AuditService;

class EgressoConhecimentoController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * Retorna o catálogo de conhecimentos disponíveis para dropdowns e autocomplete.
     * Acesso permitido a todos os usuários autenticados.
     *
     * GET /api/conhecimentos
     */
    public function indexCatalog(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $query = Conhecimento::query()->where('ativo', true);

        // Filtro de busca textual por nome ou categoria (compatível com SQLite e PostgreSQL)
        if ($request->filled('q')) {
            $searchTerm = trim((string) $request->input('q'));
            $lowerSearch = mb_strtolower($searchTerm);
            $query->where(function ($q) use ($lowerSearch) {
                $q->whereRaw('LOWER(nome) LIKE ?', ["%{$lowerSearch}%"])
                  ->orWhereRaw('LOWER(categoria) LIKE ?', ["%{$lowerSearch}%"]);
            });
        }

        // Filtro por categoria específica
        if ($request->filled('categoria')) {
            $query->where('categoria', $request->input('categoria'));
        }

        $conhecimentos = $query->orderBy('nome', 'asc')->get();

        $data = $conhecimentos->map(function ($c) {
            return [
                'id' => $c->id,
                'nome' => $c->nome,
                'descricao' => $c->descricao,
                'categoria' => $c->categoria,
                'ativo' => (bool) $c->ativo,
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    /**
     * Retorna a lista de conhecimentos registrados na ficha de um egresso específico.
     * Egresso pode consultar apenas os seus próprios dados; Técnicos e Gestores consultam qualquer egresso.
     *
     * GET /api/egressos/{egresso}/conhecimentos
     */
    public function index(Request $request, Egresso $egresso): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Controle de acesso: apenas gestor, técnico, suporte ou o próprio egresso titular
        if ($user->isEgresso()) {
            if (!$user->egresso || $user->egresso->id !== $egresso->id) {
                return response()->json([
                    'error' => 'Acesso negado: você só pode consultar os seus próprios conhecimentos.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        } elseif (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json([
                'error' => 'Acesso negado: perfil sem permissão para consultar a ficha do egresso.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        $egresso->load(['conhecimentos']);

        $data = $egresso->conhecimentos->map(function ($c) {
            return [
                'id' => $c->pivot->id ?? $c->id,
                'conhecimento_id' => $c->id,
                'nome' => $c->nome,
                'categoria' => $c->categoria,
                'nivel' => $c->pivot->nivel,
                'necessita_treinamento' => (bool) $c->pivot->necessita_treinamento,
                'observacoes' => $c->pivot->observacoes ?? null,
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $data->count(),
            'egresso_id' => $egresso->id,
        ]);
    }

    /**
     * Sincroniza os conhecimentos do egresso.
     * Permite cadastro ilimitado, adição dinâmica de novos conhecimentos via firstOrCreate,
     * edição de níveis (básico, intermediário, avançado), alternância de necessidade de treinamento e remoção.
     *
     * PUT/POST /api/egressos/{egresso}/conhecimentos
     */
    public function sync(Request $request, Egresso $egresso): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Controle de acesso: apenas gestor, técnico, suporte ou o próprio egresso titular
        if ($user->isEgresso()) {
            if (!$user->egresso || $user->egresso->id !== $egresso->id) {
                return response()->json([
                    'error' => 'Acesso negado: você só pode alterar os seus próprios conhecimentos.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        } elseif (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte()) {
            return response()->json([
                'error' => 'Acesso negado: perfil sem permissão para alterar a ficha do egresso.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        // Normalização de payload: aceita { "conhecimentos": [...] } ou array raiz [...]
        $rawConhecimentos = $request->input('conhecimentos');
        if ($rawConhecimentos === null && is_array($request->all()) && !empty($request->all()) && isset($request->all()[0])) {
            $rawConhecimentos = $request->all();
        } elseif ($rawConhecimentos === null) {
            $rawConhecimentos = [];
        }

        // Validação dos dados recebidos
        $validator = Validator::make(
            ['conhecimentos' => $rawConhecimentos],
            [
                'conhecimentos' => 'present|array',
                'conhecimentos.*.conhecimento_id' => 'nullable|integer|exists:conhecimentos,id',
                'conhecimentos.*.nome' => 'required_without:conhecimentos.*.conhecimento_id|nullable|string|max:100',
                'conhecimentos.*.nivel' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        $normalized = mb_strtolower(trim((string) $value));
                        $valid = ['basico', 'básico', 'intermediario', 'intermediário', 'avancado', 'avançado'];
                        if (!in_array($normalized, $valid, true)) {
                            $fail('O nível de proficiência deve ser Básico, Intermediário ou Avançado.');
                        }
                    },
                ],
                'conhecimentos.*.necessita_treinamento' => 'required|boolean',
                'conhecimentos.*.observacoes' => 'nullable|string|max:1000',
            ],
            [
                'conhecimentos.*.nome.required_without' => 'O nome do conhecimento é obrigatório quando o ID não é informado.',
                'conhecimentos.*.necessita_treinamento.required' => 'O campo indicando se necessita de treinamento é obrigatório.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Dados de conhecimento inválidos.',
                'code' => 'VALIDATION_FAILED',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $items = $validated['conhecimentos'];

        $nivelMap = [
            'basico' => EgressoConhecimento::NIVEL_BASICO ?? 'basico',
            'básico' => EgressoConhecimento::NIVEL_BASICO ?? 'basico',
            'intermediario' => EgressoConhecimento::NIVEL_INTERMEDIARIO ?? 'intermediario',
            'intermediário' => EgressoConhecimento::NIVEL_INTERMEDIARIO ?? 'intermediario',
            'avancado' => EgressoConhecimento::NIVEL_AVANCADO ?? 'avancado',
            'avançado' => EgressoConhecimento::NIVEL_AVANCADO ?? 'avancado',
        ];

        DB::beginTransaction();
        try {
            $syncData = [];

            foreach ($items as $item) {
                $conhecimentoId = $item['conhecimento_id'] ?? null;
                $nome = isset($item['nome']) ? trim((string) $item['nome']) : null;

                // Resolução dinâmica ou criação via firstOrCreate
                if (!$conhecimentoId && !empty($nome)) {
                    $existing = Conhecimento::whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])->first();
                    if ($existing) {
                        $conhecimentoId = $existing->id;
                    } else {
                        $newConhecimento = Conhecimento::create([
                            'nome' => $nome,
                            'ativo' => true,
                        ]);
                        $conhecimentoId = $newConhecimento->id;
                    }
                }

                if (!$conhecimentoId) {
                    continue;
                }

                $rawNivel = mb_strtolower(trim((string) $item['nivel']));
                $canonicalNivel = $nivelMap[$rawNivel] ?? 'basico';
                $necessitaTreinamento = filter_var($item['necessita_treinamento'], FILTER_VALIDATE_BOOLEAN);

                // Sobrescreve por ID para desduplicar chaves enviadas no mesmo lote
                $syncData[$conhecimentoId] = [
                    'nivel' => $canonicalNivel,
                    'necessita_treinamento' => $necessitaTreinamento,
                    'observacoes' => $item['observacoes'] ?? null,
                ];
            }

            // Sincroniza registros na tabela pivot egresso_conhecimentos
            $egresso->conhecimentos()->sync($syncData);

            // Registro imutável na trilha de auditoria SEJUS
            $this->audit->log(
                $egresso->prontuario?->id,
                'UPDATE',
                [
                    'action' => 'sync_conhecimentos',
                    'egresso_id' => $egresso->id,
                    'total_conhecimentos' => count($syncData),
                ],
                $user->id,
                $request->ip(),
                $request->userAgent()
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Falha ao sincronizar conhecimentos: ' . $e->getMessage(),
                'code' => 'INTERNAL_ERROR',
            ], 500);
        }

        // Recarrega e retorna o estado persistido atualizado
        $egresso->load(['conhecimentos']);
        $responseData = $egresso->conhecimentos->map(function ($c) {
            return [
                'id' => $c->pivot->id ?? $c->id,
                'conhecimento_id' => $c->id,
                'nome' => $c->nome,
                'categoria' => $c->categoria,
                'nivel' => $c->pivot->nivel,
                'necessita_treinamento' => (bool) $c->pivot->necessita_treinamento,
                'observacoes' => $c->pivot->observacoes ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Conhecimentos sincronizados com sucesso.',
            'data' => $responseData,
            'total' => $responseData->count(),
            'egresso_id' => $egresso->id,
        ], 200);
    }
}
