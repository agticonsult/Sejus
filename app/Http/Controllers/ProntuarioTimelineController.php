<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Services\AuditService;
use Carbon\Carbon;
use Throwable;

class ProntuarioTimelineController extends Controller
{
    protected const ALLOWED_EVENT_TYPES = [
        'acolhimento_video',
        'atendimento_remoto',
        'atendimento_presencial',
        'encaminhamento_vaga',
        'inscricao_curso',
        'matricula_curso',
        'emissao_carteira',
        'emissao_documento',
        'solicitacao_documento',
        'parecer_tecnico',
        'apoio_psicossocial',
    ];

    public function __construct(
        protected AuditService $audit
    ) {}

    /**
     * List chronological timeline events for a prontuário.
     */
    public function index(Request $request, string $prontuarioId): JsonResponse
    {
        $user = Auth::user();

        $prontuario = Prontuario::where('id', is_numeric($prontuarioId) ? (int)$prontuarioId : 0)
            ->orWhere('numero_prontuario', $prontuarioId)
            ->first();

        if (!$prontuario) {
            return response()->json(['error' => 'Prontuário não encontrado.'], 404);
        }

        // Egresso authorization check
        if ($user && $user->isEgresso()) {
            if (!$user->egresso || $user->egresso->id !== $prontuario->egresso_id) {
                return response()->json([
                    'error' => 'Acesso negado ao histórico deste prontuário.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        }

        $query = ProntuarioTimeline::with('responsavel:id,name,email')
            ->where('prontuario_id', $prontuario->id);

        if ($request->filled('tipo_evento')) {
            $query->where('tipo_evento', $request->input('tipo_evento'));
        }

        $limit = max(1, min(100, (int) $request->input('per_page', $request->input('limit', 50))));
        $events = $query->orderBy('data_evento', 'desc')->paginate($limit);

        return response()->json([
            'prontuario_id' => $prontuario->id,
            'numero_prontuario' => $prontuario->numero_prontuario,
            'data' => $events->items(),
            'current_page' => $events->currentPage(),
            'per_page' => $events->perPage(),
            'total' => $events->total(),
            'last_page' => $events->lastPage(),
        ]);
    }

    /**
     * Record a new general timeline event.
     */
    public function store(Request $request, string $prontuarioId): JsonResponse
    {
        return $this->processTimelineStore($request, $prontuarioId, false);
    }

    /**
     * Record a clinical evolution entry (Escritório Social / Conecta Egresso).
     */
    public function storeEvolucao(Request $request, string $prontuarioId): JsonResponse
    {
        return $this->processTimelineStore($request, $prontuarioId, true);
    }

    /**
     * Core handler for timeline insertion with strict boundary, security, and size checks.
     */
    protected function processTimelineStore(Request $request, string $prontuarioId, bool $isEvolucao): JsonResponse
    {
        $user = Auth::user();

        // 1. RBAC Check: Egressos are strictly forbidden from writing evolutions
        if (!$user || $user->isEgresso() || $user->isFamiliar()) {
            return response()->json([
                'error' => 'Acesso negado: egressos ou familiares não podem registrar evoluções ou eventos na linha do tempo.',
                'code' => 'FORBIDDEN_ROLE_RESTRICTION',
            ], 403);
        }

        // 2. Prontuário existence check
        $prontuario = Prontuario::where('id', is_numeric($prontuarioId) ? (int)$prontuarioId : 0)
            ->orWhere('numero_prontuario', $prontuarioId)
            ->first();

        if (!$prontuario) {
            return response()->json([
                'error' => 'Prontuário não encontrado.',
                'code' => 'PRONTUARIO_NOT_FOUND',
            ], 404);
        }

        // 3. Payload size check (64KB = 65,536 bytes)
        $rawContent = $request->getContent();
        if (strlen($rawContent) > 65536) {
            return response()->json([
                'error' => 'Payload excede o limite máximo permitido de 64KB.',
                'code' => 'PAYLOAD_TOO_LARGE',
            ], 413);
        }

        $descricao = (string) ($request->input('descricao') ?? $request->input('texto') ?? $request->input('nota') ?? '');

        // 4. Empty / whitespace description validation
        if (trim($descricao) === '') {
            return response()->json([
                'error' => 'A descrição da evolução ou evento não pode ser vazia.',
                'code' => 'VALIDATION_ERROR_EMPTY_DESCRIPTION',
            ], 422);
        }

        if (strlen($descricao) > 65536) {
            return response()->json([
                'error' => 'A descrição excede o limite de 64KB.',
                'code' => 'PAYLOAD_TOO_LARGE',
            ], 413);
        }

        // 5. Event type validation
        $tipoEvento = $request->input('tipo_evento');
        if (empty($tipoEvento)) {
            $tipoEvento = $isEvolucao ? 'atendimento_presencial' : 'parecer_tecnico';
        }

        if (!in_array($tipoEvento, self::ALLOWED_EVENT_TYPES, true)) {
            return response()->json([
                'error' => "Tipo de evento inválido: '{$tipoEvento}'. Tipos válidos: " . implode(', ', self::ALLOWED_EVENT_TYPES),
                'code' => 'INVALID_EVENT_TYPE',
            ], 422);
        }

        // 6. Title and metadata
        $titulo = trim((string) ($request->input('titulo') ?? ($isEvolucao ? 'Evolução Socioassistencial' : 'Registro de Linha do Tempo')));
        if (empty($titulo)) {
            $titulo = 'Atendimento Socioassistencial';
        }

        $metadata = $request->input('metadata', []);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        // 7. Date parsing & validation
        $dataEvento = now();
        if ($request->filled('data_evento')) {
            try {
                $dataEvento = Carbon::parse($request->input('data_evento'));
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'Formato de data_evento inválido. Utilize o padrão ISO 8601.',
                    'code' => 'INVALID_DATE_FORMAT',
                ], 422);
            }
        }

        // 8. XSS Entity escaping
        $sanitizedDescricao = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
        $sanitizedTitulo = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');

        // 9. Author ID binding: Strictly bind to authenticated user
        $responsavelId = $user->id;

        // 10. Persist event
        $timelineEvent = ProntuarioTimeline::create([
            'prontuario_id' => $prontuario->id,
            'tipo_evento' => $tipoEvento,
            'titulo' => $sanitizedTitulo,
            'descricao' => $sanitizedDescricao,
            'metadata' => $metadata,
            'responsavel_id' => $responsavelId,
            'data_evento' => $dataEvento,
        ]);

        // 11. Append immutable audit log
        $this->audit->log(
            $prontuario->id,
            'ADD_TIMELINE_EVENT',
            [
                'timeline_id' => $timelineEvent->id,
                'tipo_evento' => $tipoEvento,
                'titulo' => $sanitizedTitulo,
            ],
            $responsavelId,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'status' => 'created',
            'message' => 'Evolução registrada na linha do tempo com sucesso.',
            'data' => [
                'id' => $timelineEvent->id,
                'prontuario_id' => $prontuario->id,
                'tipo_evento' => $timelineEvent->tipo_evento,
                'titulo' => $timelineEvent->titulo,
                'descricao' => $timelineEvent->descricao,
                'metadata' => $timelineEvent->metadata,
                'responsavel_id' => $timelineEvent->responsavel_id,
                'responsavel_nome' => $user->name,
                'data_evento' => $timelineEvent->data_evento?->toIso8601String(),
                'created_at' => $timelineEvent->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
