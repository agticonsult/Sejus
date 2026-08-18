<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Carteira Digital do Egresso - SEJUS/ES</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.4;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            border: 2px solid #0284c7;
            border-radius: 12px;
            padding: 24px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-title {
            text-align: center;
        }
        .header-title h1 {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .header-title h2 {
            font-size: 12px;
            font-weight: 600;
            color: #0284c7;
            margin: 4px 0 0 0;
        }
        .header-title h3 {
            font-size: 10px;
            font-weight: 500;
            color: #64748b;
            margin: 2px 0 0 0;
        }
        .badge-status {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-weight: bold;
            font-size: 10px;
            padding: 4px 10px;
            border-radius: 12px;
            border: 1px solid #86efac;
            margin-bottom: 16px;
        }
        .card-body-table {
            width: 100%;
            border-collapse: collapse;
        }
        .card-body-table td {
            vertical-align: top;
        }
        .info-col {
            width: 65%;
            padding-right: 16px;
        }
        .qr-col {
            width: 35%;
            text-align: center;
            border-left: 1px dashed #cbd5e1;
            padding-left: 16px;
        }
        .field-group {
            margin-bottom: 10px;
        }
        .field-label {
            font-size: 9px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .field-value {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }
        .field-value-highlight {
            font-size: 14px;
            font-weight: 800;
            color: #0284c7;
        }
        .field-value-mono {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #334155;
        }
        .qr-image {
            width: 140px;
            height: 140px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 4px;
            background: #ffffff;
        }
        .qr-caption {
            font-size: 9px;
            color: #64748b;
            margin-top: 6px;
        }
        .footer-note {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 9px;
            color: #64748b;
            text-align: center;
            line-height: 1.5;
        }
        .legal-stamp {
            font-weight: 700;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="container">
        <table class="header-table">
            <tr>
                <td class="header-title">
                    <h1>GOVERNO DO ESTADO DO ESPÍRITO SANTO</h1>
                    <h2>SECRETARIA DE ESTADO DA JUSTIÇA — SEJUS / ESCRITÓRIO SOCIAL DIGITAL</h2>
                    <h3>CREDENCIAL OFICIAL DO EGRESSO • PROGRAMA CONECTA EGRESSO</h3>
                </td>
            </tr>
        </table>

        <div style="text-align: center;">
            <div class="badge-status">✓ CREDENCIAL OFICIAL AUTENTICADA & VERIFICADA</div>
        </div>

        <table class="card-body-table">
            <tr>
                <td class="info-col">
                    <div class="field-group">
                        <div class="field-label">Nome do Titular</div>
                        <div class="field-value-highlight">{{ $nome }}</div>
                    </div>

                    @if(!empty($nomeSocial))
                    <div class="field-group">
                        <div class="field-label">Nome Social</div>
                        <div class="field-value">{{ $nomeSocial }}</div>
                    </div>
                    @endif

                    <div class="field-group">
                        <div class="field-label">CPF (Protegido pela LGPD)</div>
                        <div class="field-value-mono">{{ $cpfMasked }}</div>
                    </div>

                    <div class="field-group">
                        <div class="field-label">Registro Geral SEJUS / ES</div>
                        <div class="field-value-highlight">{{ $registroSejus }}</div>
                    </div>

                    <div class="field-group">
                        <div class="field-label">Município de Referência / Residência</div>
                        <div class="field-value">{{ $municipio }} / Espírito Santo</div>
                    </div>

                    <div class="field-group">
                        <div class="field-label">Período de Validade Oficial</div>
                        <div class="field-value">{{ $dataEmissao }} até {{ $dataValidade }}</div>
                    </div>

                    <div class="field-group">
                        <div class="field-label">Código de Assinatura Criptográfica</div>
                        <div class="field-value-mono" style="color: #0284c7; font-weight: bold;">{{ $authCode }}</div>
                    </div>
                </td>
                <td class="qr-col">
                    <img src="{{ $qrCodeDataUri }}" alt="QR Code Oficial SEJUS/ES" class="qr-image" />
                    <div class="qr-caption">
                        <strong>Autenticidade Instantânea</strong><br>
                        Escaneie com a câmera para conferência pública
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">
            <span class="legal-stamp">Validade Jurídica em todo o Território Estadual — Lei Complementar Estadual nº 182/2021.</span><br>
            A verificação deste documento pode ser realizada a qualquer momento no portal oficial do Governo do Estado do Espírito Santo.<br>
            URL Pública: <strong>{{ $validationUrl }}</strong>
        </div>
    </div>
</body>
</html>
