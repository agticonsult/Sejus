<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\QrCodeSecurityService;
use App\Services\AuditService;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class CarteiraValidationController extends Controller
{
    protected QrCodeSecurityService $qrService;
    protected AuditService $auditService;

    public function __construct(QrCodeSecurityService $qrService, AuditService $auditService)
    {
        $this->qrService = $qrService;
        $this->auditService = $auditService;
    }

    /**
     * Validate Digital Wallet Token via public web page.
     */
    public function validar(Request $request, string $token)
    {
        $result = $this->qrService->verifyToken($token);

        $prontuarioId = null;
        if ($result['valid'] && isset($result['payload']['doc_id'])) {
            $prontuarioId = (int) $result['payload']['doc_id'];
        }

        // Grava registro de auditoria imutavel para fins de rastreabilidade
        try {
            $this->auditService->log(
                $prontuarioId,
                'VALIDATE_QR',
                [
                    'status' => $result['status'],
                    'valid' => $result['valid'],
                    'token_prefix' => substr($token, 0, 10) . '...',
                    'ip' => $request->ip(),
                ],
                null,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $e) {
            // Em caso de execucao sem BD instanciado, nao bloqueia exibicao
        }

        if ($request->header('X-Inertia') && class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('ValidarCarteira', [
                'result' => $result,
                'token' => $token,
            ]);
        }

        if (view()->exists('carteira.validacao')) {
            return view('carteira.validacao', [
                'result' => $result,
                'token' => $token,
            ]);
        }

        if (class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('ValidarCarteira', [
                'result' => $result,
                'token' => $token,
            ]);
        }

        return response()->json($result);
    }

    /**
     * API verification endpoint returning structured JSON.
     */
    public function validarApi(Request $request, string $token): JsonResponse
    {
        $result = $this->qrService->verifyToken($token);

        try {
            $this->auditService->log(
                $result['valid'] && isset($result['payload']['doc_id']) ? (int) $result['payload']['doc_id'] : null,
                'VALIDATE_QR_API',
                [
                    'status' => $result['status'],
                    'valid' => $result['valid'],
                ],
                null,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $e) {
            //
        }

        return response()->json($result);
    }

    /**
     * Public page with token input form.
     */
    public function validarPublico(Request $request)
    {
        $token = $request->query('token');
        if ($token) {
            return $this->validar($request, $token);
        }

        if ($request->header('X-Inertia') && class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('ValidarCarteira', [
                'result' => null,
                'token' => null,
            ]);
        }

        if (view()->exists('carteira.validacao')) {
            return view('carteira.validacao', [
                'result' => null,
                'token' => null,
            ]);
        }

        if (class_exists(\Inertia\Inertia::class)) {
            return \Inertia\Inertia::render('ValidarCarteira', [
                'result' => null,
                'token' => null,
            ]);
        }

        return response()->json([
            'service' => 'CONECTA EGRESSO - Validador Publico de Carteira Digital SEJUS/ES',
            'instruction' => 'Informe o token no caminho /validar-carteira/{token}',
        ]);
    }
}
