<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Models\Egresso;
use App\Services\QrCodeSecurityService;
use App\Services\LgpdSecurityService;
use App\Services\AuditService;

class CarteiraController extends Controller
{
    public function __construct(
        protected QrCodeSecurityService $qrService,
        protected LgpdSecurityService $lgpdService,
        protected AuditService $auditService
    ) {}

    /**
     * Render Inertia page for Carteira Digital with real DB citizen record and authentic HMAC token.
     */
    public function showView(Request $request): InertiaResponse
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Não autenticado.');
        }

        if (!$user->isGestor() && !$user->isTecnico() && !$user->isSuporte() && !$user->isEgresso()) {
            abort(403, 'Acesso negado à Carteira Digital.');
        }

        $isStaff = $user->isGestor() || $user->isTecnico() || $user->isSuporte();

        if ($user->isEgresso()) {
            $egresso = $user->egresso;
        } else {
            $requestedId = $request->input('egresso_id');
            $searchQuery = trim((string) $request->input('q', ''));

            if ($requestedId && is_numeric($requestedId)) {
                $egresso = Egresso::find((int) $requestedId);
            } elseif (!empty($searchQuery)) {
                // Busca por CPF ou nome
                $cleanCpf = preg_replace('/\D/', '', $searchQuery);
                if (strlen($cleanCpf) === 11) {
                    $hash = $this->lgpdService->generateBlindIndex($cleanCpf);
                    $egresso = Egresso::where('hash_cpf', $hash)->first();
                } else {
                    $egresso = Egresso::where('nome_completo', 'ilike', "%{$searchQuery}%")->first();
                }
            } else {
                $egresso = null;
            }
        }

        // Lista de egressos recentes para seleção rápida pelo Gestor/Técnico
        $recentEgressos = [];
        if ($isStaff) {
            $recentEgressos = Egresso::select(['id', 'nome_completo', 'cpf_encrypted', 'hash_cpf'])
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'nome_completo' => $e->nome_completo,
                    'cpf_masked' => $this->lgpdService->maskCpf($e->cpf ?? '00000000000'),
                ]);
        }

        if (!$egresso) {
            return Inertia::render('Carteira', [
                'isStaff' => $isStaff,
                'recentEgressos' => $recentEgressos,
                'egresso' => null,
                'token' => '',
                'validationUrl' => '',
                'pdfDownloadUrl' => '',
            ]);
        }

        $egresso->loadMissing(['municipio', 'prontuario']);

        $payload = $this->qrService->generatePayload($egresso);
        $token = $this->qrService->generateToken($payload);
        $validationUrl = $this->qrService->getValidationUrl($token);
        $registroSejus = $egresso->registro_sejus ?? ('ES-2026-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT));

        $dataNascimento = 'Não informada';
        if ($egresso->data_nascimento) {
            $dataNascimento = is_string($egresso->data_nascimento)
                ? substr($egresso->data_nascimento, 0, 10)
                : $egresso->data_nascimento->format('d/m/Y');
        }

        return Inertia::render('Carteira', [
            'isStaff' => $isStaff,
            'recentEgressos' => $recentEgressos,
            'egresso' => [
                'id' => $egresso->id,
                'nome_completo' => $egresso->nome_completo,
                'cpf_masked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
                'data_nascimento' => $dataNascimento,
                'municipio' => ($egresso->municipio?->nome ?? 'Vitória') . ' / ES',
                'registro_sejus' => $registroSejus,
                'data_emissao' => now()->format('d/m/Y'),
                'data_validade' => now()->addYear()->format('d/m/Y'),
            ],
            'token' => $token,
            'validationUrl' => $validationUrl,
            'pdfDownloadUrl' => '/carteira/pdf?egresso_id=' . $egresso->id,
        ]);
    }

    /**
     * Persist 2ª via or Declaração de Vínculo request in Prontuário & AuditService.
     */
    public function solicitarSegundaVia(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        $validated = $request->validate([
            'egresso_id' => 'nullable|integer|exists:egressos,id',
            'tipo_documento' => 'required|string|max:120',
        ]);

        $isStaff = $user->isGestor() || $user->isTecnico() || $user->isSuporte();
        $egresso = $isStaff && !empty($validated['egresso_id'])
            ? Egresso::find($validated['egresso_id'])
            : $user->egresso;

        if (!$egresso) {
            return response()->json(['error' => 'Nenhum egresso selecionado para emissão do documento.'], 422);
        }

        $prontuario = \App\Models\Prontuario::firstOrCreate(
            ['egresso_id' => $egresso->id],
            [
                'numero_prontuario' => sprintf('PRT-%s-%06d', now()->format('Y'), (\App\Models\Prontuario::max('id') ?? 0) + 1),
                'situacao' => 'ativo',
                'resumo_diagnostico' => 'Prontuário inicializado na solicitação de credencial.',
                'data_abertura' => now(),
            ]
        );

        $timelineEntry = \App\Models\ProntuarioTimeline::create([
            'prontuario_id' => $prontuario->id,
            'tipo_evento' => 'emissao_carteira',
            'titulo' => 'Solicitação de ' . $validated['tipo_documento'],
            'descricao' => 'Reemissão / declaração digital solicitada via plataforma Web.',
            'metadata' => [
                'tipo_documento' => $validated['tipo_documento'],
                'solicitante_id' => $user->id,
            ],
            'responsavel_id' => $user->id,
            'data_evento' => now(),
        ]);

        $protocolo = 'DOC-' . now()->format('Y') . '-' . str_pad((string) $timelineEntry->id, 6, '0', STR_PAD_LEFT);

        $this->auditService->log($prontuario->id, 'CARTEIRA_SEGUNDA_VIA_SOLICITADA', [
            'egresso_id' => $egresso->id,
            'tipo_documento' => $validated['tipo_documento'],
            'protocolo' => $protocolo,
        ], $user->id, $request->ip(), $request->userAgent());

        return response()->json([
            'status' => 'created',
            'protocolo' => $protocolo,
            'message' => "Requisição {$protocolo} registrada no Prontuário Único.",
        ], 201);
    }

    /**
     * Get the digital wallet (Carteira Digital) for the authenticated Egresso.
     */
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $egresso = $user->egresso;

        if (!$egresso) {
            return response()->json([
                'error' => 'Perfil de egresso não encontrado para o usuário autenticado.',
                'code' => 'EGRESSO_NOT_FOUND',
            ], 404);
        }

        $egresso->loadMissing(['municipio', 'prontuario']);

        $payload = $this->qrService->generatePayload($egresso);
        $token = $this->qrService->generateToken($payload);
        $validationUrl = $this->qrService->getValidationUrl($token);
        $qrCodeDataUri = $this->qrService->generateQrCodeDataUri($validationUrl);

        $authCode = strtoupper(implode('-', str_split(substr($this->qrService->signPayload($payload), 0, 16), 4)));
        $registroSejus = $egresso->registro_sejus ?? ('ES-2026-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT));

        $dataNascimento = null;
        if ($egresso->data_nascimento) {
            $dataNascimento = is_string($egresso->data_nascimento)
                ? substr($egresso->data_nascimento, 0, 10)
                : $egresso->data_nascimento->format('Y-m-d');
        }

        $walletData = [
            'id' => $egresso->id,
            'registro_sejus' => $registroSejus,
            'nome' => mb_strtoupper($egresso->nome_completo),
            'nome_social' => !empty($egresso->nome_social) ? mb_strtoupper($egresso->nome_social) : null,
            'cpf_masked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
            'data_nascimento' => $dataNascimento,
            'municipio' => $egresso->municipio?->nome ?? 'Espírito Santo',
            'status_penal' => $egresso->status_penal ?? 'egresso',
            'status_documento' => 'REGULAR',
            'data_emissao' => now()->format('d/m/Y'),
            'data_validade' => now()->addYear()->format('d/m/Y'),
            'auth_code' => $authCode,
            'token' => $token,
            'validation_url' => $validationUrl,
            'qr_code_data_uri' => $qrCodeDataUri,
            'legal_basis' => 'Lei Complementar Estadual nº 182/2021 - SEJUS/ES',
        ];

        try {
            $this->auditService->log(
                $egresso->prontuario?->id,
                'VIEW_CARTEIRA_DIGITAL',
                [
                    'registro_sejus' => $registroSejus,
                    'auth_code' => $authCode,
                ],
                $user->id,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $e) {
            // Non-blocking audit failure
        }

        return response()->json([
            'data' => $walletData,
        ]);
    }
}
