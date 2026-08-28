<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validação de Documento Oficial — CONECTA EGRESSO (SEJUS/ES)</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .validation-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            max-width: 550px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            font-size: 16px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            font-size: 13px;
            color: #38bdf8;
            margin: 6px 0 0 0;
            font-weight: 500;
        }
        .body {
            padding: 28px;
        }
        .status-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .status-valid {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .status-invalid {
            background-color: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .status-expired {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .info-grid {
            display: grid;
            grid-gap: 12px;
            margin-bottom: 24px;
        }
        .info-item {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid #0284c7;
        }
        .info-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .footer {
            background: #f1f5f9;
            padding: 16px 24px;
            text-align: center;
            font-size: 11px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="validation-card">
        <div class="header">
            <h1>Governo do Estado do Espírito Santo</h1>
            <h2>Secretaria de Estado da Justiça — SEJUS / Escritório Social Digital</h2>
        </div>
        <div class="body">
            <?php if(isset($result) && $result['valid']): ?>
                <div class="status-badge status-valid">
                    ✓ <?php echo e($result['message']); ?>

                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Nome do Titular</div>
                        <div class="info-value"><?php echo e($result['payload']['nome'] ?? '---'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">CPF Mascarado (LGPD)</div>
                        <div class="info-value"><?php echo e($result['payload']['cpf_masked'] ?? '---'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registro Geral SEJUS/ES</div>
                        <div class="info-value"><?php echo e($result['payload']['registro_sejus'] ?? '---'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Município de Referência</div>
                        <div class="info-value"><?php echo e($result['payload']['municipio'] ?? '---'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Validade do Documento</div>
                        <div class="info-value">Até <?php echo e(isset($result['payload']['expires_at']) ? date('d/m/Y H:i', strtotime($result['payload']['expires_at'])) : '---'); ?></div>
                    </div>
                </div>
            <?php elseif(isset($result) && ($result['status'] ?? '') === 'EXPIRED_DOCUMENT'): ?>
                <div class="status-badge status-expired">
                    ⚠ <?php echo e($result['message']); ?>

                </div>
            <?php elseif(isset($result)): ?>
                <div class="status-badge status-invalid">
                    ✗ <?php echo e($result['message']); ?>

                </div>
            <?php else: ?>
                <div class="status-badge" style="background:#f1f5f9; color:#475569;">
                    Insira um token de validação de credencial digital SEJUS.
                </div>
            <?php endif; ?>
        </div>
        <div class="footer">
            Autenticação Criptográfica Oficial • Amparo na Lei Complementar Estadual nº 182/2021
        </div>
    </div>
</body>
</html>
<?php /**PATH D:\Agile\projeto dia 18\resources\views/carteira/validacao.blade.php ENDPATH**/ ?>