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

        if ($user->isEgresso()) {
            $egresso = $user->egresso;
        } else {
            $requestedId = $request->input('egresso_id');
            $egresso = $requestedId && is_numeric($requestedId)
                ? Egresso::find((int) $requestedId)
                : Egresso::orderBy('id', 'asc')->first();
        }

        if (!$egresso) {
            return Inertia::render('Carteira', [
                'egresso' => null,
                'token' => '',
                'pdfDownloadUrl' => '/carteira/pdf',
            ]);
        }

        $egresso->loadMissing(['municipio', 'prontuario']);

        $payload = $this->qrService->generatePayload($egresso);
        $token = $this->qrService->generateToken($payload);
        $registroSejus = $egresso->registro_sejus ?? ('ES-2026-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT));

        $dataNascimento = 'Não informada';
        if ($egresso->data_nascimento) {
            $dataNascimento = is_string($egresso->data_nascimento)
                ? substr($egresso->data_nascimento, 0, 10)
                : $egresso->data_nascimento->format('d/m/Y');
        }

        return Inertia::render('Carteira', [
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
            'pdfDownloadUrl' => '/carteira/pdf' . ($user->isEgresso() ? '' : '?egresso_id=' . $egresso->id),
        ]);
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
