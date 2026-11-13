<?php

namespace App\Http\Controllers;

use App\Models\Egresso;
use App\Models\MunicipioEs;
use App\Models\Prontuario;
use App\Models\ProntuarioTimeline;
use App\Models\VideoRoom;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AtendimentoController extends Controller
{
    public function __construct(
        protected AuditService $audit
    ) {}

    public function indexView(): Response
    {
        $user = Auth::user();
        $isStaff = $user && in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);

        $queueData = $this->buildQueuePayload($user, $isStaff);

        $municipios = MunicipioEs::orderBy('nome')->get(['id', 'nome']);

        $egressosList = $isStaff
            ? Egresso::with('prontuario:id,egresso_id,numero_prontuario')
                ->orderBy('nome_completo')
                ->limit(100)
                ->get(['id', 'nome_completo', 'municipio_residencia_id'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'nome' => $e->nome_completo,
                    'prontuario_id' => $e->prontuario?->id,
                    'municipio_id' => $e->municipio_residencia_id,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Atendimento', [
            'initialQueue' => $queueData['queue'],
            'activeCallForUser' => $queueData['active_call'],
            'municipios' => $municipios,
            'egressosList' => $egressosList,
        ]);
    }

    public function fila(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $isStaff = in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);
        $payload = $this->buildQueuePayload($user, $isStaff);

        return response()->json([
            'status' => 'success',
            'queue' => $payload['queue'],
            'active_call' => $payload['active_call'],
        ]);
    }

    public function entrarFila(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $validated = $request->validate([
            'egresso_id' => 'nullable|integer|exists:egressos,id',
            'name' => 'nullable|string|max:150',
            'municipio_id' => 'nullable|integer|exists:municipios_es,id',
            'municipio' => 'nullable|string|max:100',
            'prioridade' => 'nullable|string|in:normal,preferencial,urgente',
            'motivo' => 'required|string|max:500',
        ]);

        $isStaff = in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);

        $egresso = null;
        if ($isStaff && !empty($validated['egresso_id'])) {
            $egresso = Egresso::find($validated['egresso_id']);
        } elseif ($user->egresso) {
            $egresso = $user->egresso;
        }

        $prontuarioId = null;
        if ($egresso) {
            $prontuario = Prontuario::firstOrCreate(
                ['egresso_id' => $egresso->id],
                [
                    'numero_prontuario' => sprintf('PRT-%s-%06d', now()->format('Y'), (Prontuario::max('id') ?? 0) + 1),
                    'situacao' => 'ativo',
                    'resumo_diagnostico' => 'Prontuário inicializado automaticamente na fila de teleatendimento.',
                    'data_abertura' => now(),
                ]
            );
            $prontuarioId = $prontuario->id;

            // Reuse open queue ticket if user already has one waiting
            $existing = VideoRoom::where('egresso_id', $egresso->id)
                ->whereIn('status', ['aguardando', 'em_andamento'])
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Você já possui atendimento ativo ou aguardando na fila.',
                    'ticket' => $this->formatQueueItem($existing),
                ]);
            }
        }

        $municipioId = $validated['municipio_id'] ?? $egresso?->municipio_residencia_id;
        if (!$municipioId && !empty($validated['municipio'])) {
            $municipioId = MunicipioEs::where('nome', 'like', '%' . trim($validated['municipio']) . '%')->value('id');
        }
        if (!$municipioId) {
            $municipioId = MunicipioEs::where('nome', 'Vitória')->value('id') ?? MunicipioEs::first()?->id;
        }

        $solicitanteNome = trim((string) ($validated['name'] ?? $egresso?->nome_completo ?? $user->name));
        $motivoTexto = trim((string) $validated['motivo']);
        if (!$egresso && $solicitanteNome !== '') {
            $motivoTexto = "[Solicitante: {$solicitanteNome}] {$motivoTexto}";
        }

        $room = VideoRoom::create([
            'room_code' => 'SEJUS-ROOM-' . strtoupper(Str::random(6)),
            'prontuario_id' => $prontuarioId,
            'egresso_id' => $egresso?->id,
            'municipio_id' => $municipioId,
            'status' => 'aguardando',
            'prioridade' => $validated['prioridade'] ?? 'normal',
            'motivo_atendimento' => $motivoTexto,
            'scheduled_at' => now(),
        ]);

        $room->load(['egresso', 'municipio', 'tecnico']);

        $this->audit->log($prontuarioId, 'ATENDIMENTO_FILA_ENTRADA', [
            'video_room_id' => $room->id,
            'room_code' => $room->room_code,
            'prioridade' => $room->prioridade,
        ], $user->id, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'success',
            'ticket' => $this->formatQueueItem($room),
        ], 201);
    }

    public function chamar(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true)) {
            return response()->json(['error' => 'Apenas equipe técnica ou gestores podem chamar egressos da fila.'], 403);
        }

        $room = VideoRoom::with(['egresso', 'municipio', 'prontuario'])->find($id);
        if (!$room) {
            return response()->json(['error' => 'Atendimento não encontrado na fila.'], 404);
        }

        if (in_array($room->status, ['encerrada', 'cancelada'], true)) {
            return response()->json(['error' => 'Este atendimento já foi encerrado.'], 422);
        }

        $room->update([
            'status' => 'em_andamento',
            'tecnico_id' => $user->id,
            'started_at' => $room->started_at ?? now(),
        ]);

        $this->audit->log($room->prontuario_id, 'ATENDIMENTO_CHAMADA_INICIADA', [
            'video_room_id' => $room->id,
            'room_code' => $room->room_code,
            'tecnico_id' => $user->id,
        ], $user->id, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'success',
            'room' => $this->formatQueueItem($room->fresh(['egresso', 'municipio', 'tecnico'])),
        ]);
    }

    public function encerrar(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $validated = $request->validate([
            'tipo_encaminhamento' => 'nullable|string|max:200',
            'resumo' => 'nullable|string|max:2000',
            'duracao' => 'nullable|string|max:32',
        ]);

        $room = VideoRoom::with(['egresso', 'prontuario'])->find($id);
        if (!$room) {
            return response()->json(['error' => 'Sala de atendimento não encontrada.'], 404);
        }

        $isStaff = in_array($user->perfil?->slug, ['gestor', 'tecnico', 'suporte'], true);
        if (!$isStaff && $user->egresso && $room->egresso_id !== $user->egresso->id) {
            return response()->json(['error' => 'Acesso não autorizado a esta sala.'], 403);
        }

        $room->update([
            'status' => 'encerrada',
            'ended_at' => now(),
        ]);

        $prontuarioId = $room->prontuario_id;
        if (!$prontuarioId && $room->egresso_id) {
            $prontuario = Prontuario::firstOrCreate(
                ['egresso_id' => $room->egresso_id],
                [
                    'numero_prontuario' => sprintf('PRT-%s-%06d', now()->format('Y'), (Prontuario::max('id') ?? 0) + 1),
                    'tecnico_responsavel_id' => $isStaff ? $user->id : $room->tecnico_id,
                    'situacao' => 'ativo',
                    'resumo_diagnostico' => 'Prontuário criado após teleatendimento.',
                    'data_abertura' => now(),
                ]
            );
            $prontuarioId = $prontuario->id;
            $room->update(['prontuario_id' => $prontuarioId]);
        }

        $timelineEntry = null;
        if ($prontuarioId && !empty($validated['resumo'])) {
            $timelineEntry = ProntuarioTimeline::create([
                'prontuario_id' => $prontuarioId,
                'tipo_evento' => 'atendimento_remoto',
                'titulo' => 'Teleatendimento Remoto Concluído',
                'descricao' => trim($validated['resumo']),
                'metadata' => [
                    'encaminhamento' => $validated['tipo_encaminhamento'] ?? null,
                    'duracao' => $validated['duracao'] ?? null,
                    'room_code' => $room->room_code,
                    'video_room_id' => $room->id,
                ],
                'responsavel_id' => $user->id,
                'data_evento' => now(),
            ]);
        }

        $this->audit->log($prontuarioId, 'ATENDIMENTO_ENCERRADO', [
            'video_room_id' => $room->id,
            'room_code' => $room->room_code,
            'duracao' => $validated['duracao'] ?? null,
            'timeline_id' => $timelineEntry?->id,
        ], $user->id, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'success',
            'message' => 'Atendimento encerrado e registrado com sucesso.',
            'timeline_id' => $timelineEntry?->id,
        ]);
    }

    protected function buildQueuePayload($user, bool $isStaff): array
    {
        $query = VideoRoom::with(['egresso', 'municipio', 'tecnico'])
            ->whereIn('status', ['aguardando', 'em_andamento'])
            ->orderByRaw("CASE WHEN prioridade = 'urgente' THEN 0 WHEN prioridade = 'preferencial' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'asc');

        $rooms = $query->get();

        $activeCall = null;
        if (!$isStaff) {
            $egressoId = $user?->egresso?->id;
            $myRoom = ($egressoId
                ? ($rooms->first(fn ($r) => $r->egresso_id === $egressoId && $r->status === 'em_andamento')
                    ?? $rooms->first(fn ($r) => $r->egresso_id === $egressoId && $r->status === 'aguardando'))
                : null)
                ?? $rooms->first(fn ($r) => $r->egresso_id === null && $r->status === 'em_andamento');

            if ($myRoom) {
                $activeCall = $this->formatQueueItem($myRoom);
            }
            $rooms = $rooms->filter(fn ($r) => ($egressoId && $r->egresso_id === $egressoId) || ($r->egresso_id === null && $r->status === 'em_andamento'))->values();
        }

        return [
            'queue' => $rooms->map(fn ($r) => $this->formatQueueItem($r))->values()->all(),
            'active_call' => $activeCall,
        ];
    }

    protected function formatQueueItem(VideoRoom $room): array
    {
        $motivo = (string) ($room->motivo_atendimento ?? 'Atendimento Psicossocial');
        $displayName = $room->egresso?->nome_completo;
        if (!$displayName && preg_match('/^\[Solicitante:\s*(.+?)\]\s*(.*)$/', $motivo, $matches)) {
            $displayName = $matches[1];
            $motivo = $matches[2] ?: 'Atendimento Psicossocial';
        }
        $displayName = $displayName ?: 'Cidadão na Fila';

        $created = $room->scheduled_at ?? $room->created_at ?? now();
        $waitMins = max(1, (int) now()->diffInMinutes($created));

        return [
            'id' => $room->id,
            'ticket_id' => $room->id,
            'room_code' => $room->room_code,
            'prontuario_id' => $room->prontuario_id,
            'egresso_id' => $room->egresso_id,
            'name' => $displayName,
            'municipio' => $room->municipio?->nome ?? 'Vitória',
            'municipio_id' => $room->municipio_id,
            'prioridade' => $room->prioridade ?? 'normal',
            'motivo' => $motivo,
            'status' => $room->status,
            'tecnico_nome' => $room->tecnico?->name,
            'tempo_espera' => $waitMins < 60 ? "{$waitMins} min" : floor($waitMins / 60) . 'h ' . ($waitMins % 60) . 'm',
        ];
    }
}
