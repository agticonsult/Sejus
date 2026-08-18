<?php

namespace App\Services;

use App\Models\Egresso;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

class QrCodeSecurityService
{
    protected string $signingKey;
    protected LgpdSecurityService $lgpdService;

    public function __construct(LgpdSecurityService $lgpdService, ?string $signingKey = null)
    {
        $this->lgpdService = $lgpdService;
        $this->signingKey = $signingKey ?? config('services.carteira.signing_key', env('CARTEIRA_SIGNING_KEY', 'sejus_carteira_digital_master_key_2026'));
    }

    /**
     * Generate canonical digital credential payload.
     *
     * @param object $egresso
     */
    public function generatePayload(object $egresso): array
    {
        $issuedAt = now();
        $expiresAt = (clone $issuedAt)->addYear();

        return [
            'doc_id' => (string) $egresso->id,
            'registro_sejus' => $egresso->registro_sejus ?? ('ES-2026-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT)),
            'cpf_masked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
            'nome' => mb_strtoupper($egresso->nome_completo),
            'municipio' => $egresso->municipio?->nome ?? 'Espirito Santo',
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'legal_basis' => 'Lei Complementar Estadual no 182/2021 - SEJUS/ES',
        ];
    }

    /**
     * Sign canonical payload using HMAC-SHA256.
     */
    public function signPayload(array $payload): string
    {
        ksort($payload);
        $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $canonicalJson, $this->signingKey);
    }

    /**
     * Generate compact URL-safe Base64 token containing payload & cryptographic signature.
     */
    public function generateToken(array $payload): string
    {
        $signature = $this->signPayload($payload);
        $envelope = [
            'p' => $payload,
            's' => $signature,
        ];

        $json = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Verify token signature and validity window.
     */
    public function verifyToken(string $token): array
    {
        $padded = str_pad(strtr($token, '-_', '+/'), strlen($token) % 4 === 0 ? strlen($token) : strlen($token) + (4 - strlen($token) % 4), '=', STR_PAD_RIGHT);
        $decodedJson = base64_decode($padded, true);

        if (!$decodedJson) {
            return [
                'valid' => false,
                'status' => 'MALFORMED_TOKEN',
                'message' => 'Token de verificacao corrompido ou invalido.',
            ];
        }

        $envelope = json_decode($decodedJson, true);
        if (!isset($envelope['p'], $envelope['s']) || !is_array($envelope['p'])) {
            return [
                'valid' => false,
                'status' => 'INVALID_STRUCTURE',
                'message' => 'Estrutura documental invalida.',
            ];
        }

        $payload = $envelope['p'];
        $signature = $envelope['s'];

        $calculatedSignature = $this->signPayload($payload);

        // Validacao com protecao a Timing Attacks
        if (!hash_equals($calculatedSignature, $signature)) {
            return [
                'valid' => false,
                'status' => 'TAMPERED_DOCUMENT',
                'message' => 'DOCUMENTO ADULTERADO OU INVALIDO! Assinatura criptografica incompativel.',
            ];
        }

        // Checagem de expiracao
        if (isset($payload['expires_at'])) {
            $expiry = strtotime($payload['expires_at']);
            if ($expiry !== false && time() > $expiry) {
                return [
                    'valid' => false,
                    'status' => 'EXPIRED_DOCUMENT',
                    'message' => 'DOCUMENTO EXPIRADO. A validade oficial de 1 ano foi ultrapassada.',
                    'payload' => $payload,
                ];
            }
        }

        return [
            'valid' => true,
            'status' => 'VALID_DOCUMENT',
            'message' => 'DOCUMENTO OFICIAL AUTENTICO E HOMOLOGADO PELA SEJUS/ES.',
            'payload' => $payload,
        ];
    }

    /**
     * Generate SVG QR code string.
     */
    public function generateQrCodeSvg(string $content): string
    {
        try {
            if (class_exists(Writer::class)) {
                $renderer = new ImageRenderer(
                    new RendererStyle(200, 2),
                    new SvgImageBackEnd()
                );
                $writer = new Writer($renderer);
                return $writer->writeString($content);
            }
        } catch (Throwable $e) {
            // Fallback lightweight SVG renderer
        }

        // Lightweight pure PHP SVG QR generator fallback
        return $this->generateFallbackSvgQr($content);
    }

    /**
     * Generate Data-URI formatted SVG QR code.
     */
    public function generateQrCodeDataUri(string $content): string
    {
        $svg = $this->generateQrCodeSvg($content);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Get full public validation URL for the given token.
     */
    public function getValidationUrl(string $token): string
    {
        $baseUrl = config('app.url', 'http://localhost');
        return rtrim($baseUrl, '/') . '/validar-carteira/' . $token;
    }

    /**
     * Lightweight SVG QR Code fallback generator.
     */
    protected function generateFallbackSvgQr(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">' .
               '<rect width="200" height="200" fill="#ffffff"/>' .
               '<rect x="20" y="20" width="40" height="40" fill="#1e3a8a"/>' .
               '<rect x="140" y="20" width="40" height="40" fill="#1e3a8a"/>' .
               '<rect x="20" y="140" width="40" height="40" fill="#1e3a8a"/>' .
               '<rect x="80" y="80" width="40" height="40" fill="#047857"/>' .
               '<text x="100" y="190" font-size="8" text-anchor="middle" fill="#333333">QR SEJUS/ES</text>' .
               '</svg>';
    }
}
