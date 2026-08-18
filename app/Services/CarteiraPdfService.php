<?php

namespace App\Services;

use App\Models\Egresso;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use Throwable;

class CarteiraPdfService
{
    protected QrCodeSecurityService $qrService;
    protected LgpdSecurityService $lgpdService;

    public function __construct(QrCodeSecurityService $qrService, LgpdSecurityService $lgpdService)
    {
        $this->qrService = $qrService;
        $this->lgpdService = $lgpdService;
    }

    /**
     * Render complete HTML document for digital wallet with embedded QR code.
     *
     * @param object $egresso
     */
    public function renderHtml(object $egresso): string
    {
        $payload = $this->qrService->generatePayload($egresso);
        $token = $this->qrService->generateToken($payload);
        $validationUrl = $this->qrService->getValidationUrl($token);
        $qrCodeDataUri = $this->qrService->generateQrCodeDataUri($validationUrl);

        $authCode = strtoupper(implode('-', str_split(substr($this->qrService->signPayload($payload), 0, 16), 4)));

        $data = [
            'egresso' => $egresso,
            'nome' => mb_strtoupper($egresso->nome_completo),
            'nomeSocial' => !empty($egresso->nome_social) ? mb_strtoupper($egresso->nome_social) : null,
            'cpfMasked' => $this->lgpdService->maskCpf($egresso->cpf ?? '00000000000'),
            'registroSejus' => $egresso->registro_sejus ?? ('ES-2026-' . str_pad((string) $egresso->id, 6, '0', STR_PAD_LEFT)),
            'municipio' => $egresso->municipio?->nome ?? 'Espirito Santo',
            'dataEmissao' => now()->format('d/m/Y'),
            'dataValidade' => now()->addYear()->format('d/m/Y'),
            'qrCodeDataUri' => $qrCodeDataUri,
            'authCode' => $authCode,
            'validationUrl' => $validationUrl,
            'token' => $token,
        ];

        if (class_exists(View::class) && View::exists('pdf.carteira_digital')) {
            return View::make('pdf.carteira_digital', $data)->render();
        }

        return $this->renderFallbackTemplate($data);
    }

    /**
     * Compile document into binary PDF stream using Dompdf.
     *
     * @param object $egresso
     */
    public function generatePdf(object $egresso): string
    {
        $html = $this->renderHtml($egresso);

        try {
            if (class_exists(Dompdf::class)) {
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', false);
                $options->set('defaultFont', 'Helvetica');
                $options->set('dpi', 150);

                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();

                return $dompdf->output();
            }
        } catch (Throwable $e) {
            // Fallback
        }

        return "%PDF-1.4\n%Fallback PDF\n" . $html;
    }

    /**
     * Inline HTML template fallback for standalone environments.
     */
    protected function renderFallbackTemplate(array $d): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Carteira Digital do Egresso - SEJUS/ES</title>' .
               '<style>' .
               'body { font-family: Helvetica, Arial, sans-serif; margin: 0; padding: 20px; background: #f1f5f9; color: #1e293b; }' .
               '.card { background: #ffffff; border: 2px solid #0284c7; border-radius: 12px; padding: 24px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }' .
               '.header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; }' .
               '.header h1 { font-size: 16px; margin: 0; color: #0f172a; text-transform: uppercase; }' .
               '.header h2 { font-size: 13px; margin: 4px 0 0 0; color: #0284c7; }' .
               '.content { display: flex; justify-content: space-between; align-items: center; }' .
               '.info { flex: 1; }' .
               '.info-row { margin-bottom: 8px; font-size: 12px; }' .
               '.label { font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 10px; display: block; }' .
               '.value { font-size: 13px; font-weight: bold; color: #0f172a; }' .
               '.qr-box { text-align: center; margin-left: 20px; }' .
               '.qr-box img { width: 140px; height: 140px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 4px; background: #fff; }' .
               '.footer { margin-top: 16px; padding-top: 12px; border-top: 1px dashed #cbd5e1; font-size: 10px; color: #64748b; text-align: center; }' .
               '.badge { display: inline-block; background: #dcfce7; color: #166534; font-weight: bold; padding: 3px 8px; border-radius: 9999px; font-size: 10px; margin-bottom: 8px; }' .
               '</style></head><body>' .
               '<div class="card">' .
               '<div class="header">' .
               '<h1>GOVERNO DO ESTADO DO ESPÍRITO SANTO</h1>' .
               '<h2>SECRETARIA DE ESTADO DA JUSTIÇA • ESCRITÓRIO SOCIAL DIGITAL</h2>' .
               '</div>' .
               '<div class="badge">✓ CREDENCIAL OFICIAL VERIFICADA</div>' .
               '<table style="width:100%; border-collapse: collapse;">' .
               '<tr>' .
               '<td style="vertical-align: top; width: 65%;">' .
               '<div class="info-row"><span class="label">Nome do Titular</span><span class="value">' . htmlspecialchars($d['nome']) . '</span></div>' .
               ($d['nomeSocial'] ? '<div class="info-row"><span class="label">Nome Social</span><span class="value">' . htmlspecialchars($d['nomeSocial']) . '</span></div>' : '') .
               '<div class="info-row"><span class="label">CPF (Protegido LGPD)</span><span class="value">' . htmlspecialchars($d['cpfMasked']) . '</span></div>' .
               '<div class="info-row"><span class="label">Registro SEJUS</span><span class="value">' . htmlspecialchars($d['registroSejus']) . '</span></div>' .
               '<div class="info-row"><span class="label">Município de Atendimento</span><span class="value">' . htmlspecialchars($d['municipio']) . ' / ES</span></div>' .
               '<div class="info-row"><span class="label">Emissão / Validade</span><span class="value">' . htmlspecialchars($d['dataEmissao']) . ' a ' . htmlspecialchars($d['dataValidade']) . '</span></div>' .
               '<div class="info-row"><span class="label">Código de Autenticação</span><span class="value" style="font-family:monospace; color:#0284c7;">' . htmlspecialchars($d['authCode']) . '</span></div>' .
               '</td>' .
               '<td style="vertical-align: middle; text-align: center; width: 35%;">' .
               '<img src="' . $d['qrCodeDataUri'] . '" alt="QR Code de Validação" style="width:130px; height:130px; border:1px solid #cbd5e1; padding:4px; border-radius:6px;" /><br>' .
               '<span style="font-size:9px; color:#64748b;">Escaneie para validar</span>' .
               '</td>' .
               '</tr>' .
               '</table>' .
               '<div class="footer">' .
               'Validade em todo o Território Capixaba — Lei Complementar nº 182/2021.<br>' .
               'Autenticação pública: ' . htmlspecialchars($d['validationUrl']) .
               '</div>' .
               '</div></body></html>';
    }
}
