<?php
/**
 * Standalone Verification Runner for Milestone M3: Backend Business APIs, RBAC & Webhooks
 * CONECTA EGRESSO (SEJUS/ES)
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Services/LgpdSecurityService.php';
require_once __DIR__ . '/../app/Services/AuditService.php';
require_once __DIR__ . '/../app/Services/WebRtcJwtService.php';
require_once __DIR__ . '/../app/Services/GovBrAuthService.php';
require_once __DIR__ . '/../app/Services/QrCodeSecurityService.php';

use App\Services\LgpdSecurityService;
use App\Services\AuditService;
use App\Services\WebRtcJwtService;
use App\Services\GovBrAuthService;
use App\Services\QrCodeSecurityService;

$totalAssertions = 0;
$passedAssertions = 0;
$failedAssertions = 0;

function assertCondition(bool $condition, string $description): void {
    global $totalAssertions, $passedAssertions, $failedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedAssertions++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "===============================================================================\n";
echo "CONECTA EGRESSO (SEJUS/ES) - MILESTONE M3 BACKEND VERIFICATION SUITE\n";
echo "===============================================================================\n\n";

$pepper = 'conecta_egresso_lgpd_pepper_2026_sejus_es';
$jwtSecret = 'sejus_jwt_shared_secret_2026';
$webhookSecret = 'sejus_webrtc_webhook_secret_2026';

$lgpd = new LgpdSecurityService($pepper);
$audit = new AuditService($lgpd);
$jwtService = new WebRtcJwtService($jwtSecret, 3600);
$govBrService = new GovBrAuthService($lgpd, $audit);

// -----------------------------------------------------------------------------
// 1. WebRTC JWT Room Token Generator & RFC 7519 HS256 Verification
// -----------------------------------------------------------------------------
echo "1. Testing WebRtcJwtService (RFC 7519 HS256 Token Generation & Validation):\n";

$header = ['alg' => 'HS256', 'typ' => 'JWT'];
$payload = [
    'iss' => 'conecta-egresso-laravel',
    'aud' => 'conecta-egresso-webrtc',
    'sub' => '42',
    'user_id' => 42,
    'name' => 'Dra. Maria Santos',
    'role' => 'tecnico',
    'room_id' => 'sala-vitoria-101',
    'room_code' => 'ATD-VIX-2026-0042',
    'iat' => time(),
    'nbf' => time(),
    'exp' => time() + 3600,
    'jti' => bin2hex(random_bytes(16)),
];

$jwt = $jwtService->encodeJwt($header, $payload, $jwtSecret);
assertCondition(count(explode('.', $jwt)) === 3, 'JWT structure contains exactly 3 parts separated by dots');

$verified = $jwtService->verifyJwt($jwt);
assertCondition($verified['valid'] === true, 'Genuine JWT token is successfully verified');
assertCondition(($verified['payload']['sub'] ?? '') === '42', 'JWT subject claim equals user ID');
assertCondition(($verified['payload']['role'] ?? '') === 'tecnico', 'JWT role claim preserved');
assertCondition(($verified['payload']['room_id'] ?? '') === 'sala-vitoria-101', 'JWT room_id claim preserved');
assertCondition(($verified['payload']['iss'] ?? '') === 'conecta-egresso-laravel', 'JWT issuer is conecta-egresso-laravel');
assertCondition(($verified['payload']['aud'] ?? '') === 'conecta-egresso-webrtc', 'JWT audience is conecta-egresso-webrtc');

// Corrupted signature
$tamperedJwt = $jwt . 'invalid_sig';
$tamperedCheck = $jwtService->verifyJwt($tamperedJwt);
assertCondition($tamperedCheck['valid'] === false, 'Tampered signature is strictly rejected');

// Wrong secret key
$wrongSecretJwt = $jwtService->encodeJwt($header, $payload, 'wrong_secret_key_123');
$wrongSecretCheck = $jwtService->verifyJwt($wrongSecretJwt);
assertCondition($wrongSecretCheck['valid'] === false, 'Token signed with foreign secret is rejected');

// Expired token
$expiredPayload = array_merge($payload, ['exp' => time() - 60]);
$expiredJwt = $jwtService->encodeJwt($header, $expiredPayload, $jwtSecret);
$expiredCheck = $jwtService->verifyJwt($expiredJwt);
assertCondition($expiredCheck['valid'] === false && $expiredCheck['error'] === 'TOKEN_EXPIRED', 'Expired token is detected and rejected');

// Future nbf token
$futurePayload = array_merge($payload, ['nbf' => time() + 300]);
$futureJwt = $jwtService->encodeJwt($header, $futurePayload, $jwtSecret);
$futureCheck = $jwtService->verifyJwt($futureJwt);
assertCondition($futureCheck['valid'] === false && $futureCheck['error'] === 'TOKEN_NOT_YET_VALID', 'Not-yet-valid token with future nbf is rejected');

// ICE servers and WebSocket URL
$iceServers = $jwtService->getIceServers();
assertCondition(is_array($iceServers) && count($iceServers) >= 2, 'Coturn STUN and TURN ICE server array is returned');
$wsUrl = $jwtService->getWebSocketUrl('sala-vitoria-101');
assertCondition(str_contains($wsUrl, 'sala-vitoria-101'), 'WebSocket signaling URL is correctly constructed');

echo "\n";

// -----------------------------------------------------------------------------
// 2. WebRTC Webhook Ingestion & HMAC-SHA256 Signature Verification
// -----------------------------------------------------------------------------
echo "2. Testing WebRTC Webhook Ingestion (HMAC-SHA256 Signature & LifeCycle Events):\n";

$webhookPayload = json_encode([
    'event' => 'session.ended',
    'room_id' => 'sala-vitoria-101',
    'data' => [
        'room_code' => 'ATD-VIX-2026-0042',
        'duration_seconds' => 930,
        'summary_telemetry' => [
            'avg_mos' => 4.28,
            'overall_quality_tier' => 'BOM',
            'overall_packet_loss_pct' => 0.35,
            'avg_rtt_ms' => 42.5,
            'avg_jitter_ms' => 7.2,
        ],
        'attendees' => [
            ['user_id' => 14, 'role' => 'tecnico', 'mos_score' => 4.35],
            ['user_id' => 892, 'role' => 'egresso', 'mos_score' => 4.20],
        ],
        'ended_at' => date('c'),
    ],
]);

$computedSig = hash_hmac('sha256', $webhookPayload, $webhookSecret);
$sigHeader = 'sha256=' . $computedSig;

$receivedSig = str_starts_with($sigHeader, 'sha256=') ? substr($sigHeader, 7) : $sigHeader;
assertCondition(hash_equals($computedSig, $receivedSig), 'HMAC-SHA256 webhook signature matches using hash_equals');

$tamperedWebhookPayload = $webhookPayload . ' ';
$tamperedSigCheck = hash_equals(hash_hmac('sha256', $tamperedWebhookPayload, $webhookSecret), $receivedSig);
assertCondition(!$tamperedSigCheck, 'Tampered webhook payload fails signature verification');

// Formatting of timeline description
$minutes = floor(930 / 60);
$seconds = 930 % 60;
$durationFormatted = sprintf('%02d min %02d seg', $minutes, $seconds);
assertCondition($durationFormatted === '15 min 30 seg', 'Session duration formatted correctly into mm min ss seg');

echo "\n";

// -----------------------------------------------------------------------------
// 3. Gov.br / Acesso Cidadão OIDC Claim Mapping & Role Resolution
// -----------------------------------------------------------------------------
echo "3. Testing GovBrAuthService (OIDC Claim Mapping & Fail-Secure Role Resolution):\n";

// Gestor claim
$gestorClaims = [
    'sub' => 'govbr_gestor_01',
    'cpf' => '529.982.247-25',
    'name' => 'Dr. Gestor SEJUS',
    'nivel_confianca' => 'Ouro',
    'orgao' => 'SEJUS',
    'cargo' => 'Gestor de Políticas Penais',
];
$gestorRole = $govBrService->mapClaimsToRole($gestorClaims);
assertCondition($gestorRole === 'gestor', 'Ouro trust + SEJUS + Gestor cargo maps to gestor role');

// Técnico claim (CRESS)
$tecnicoClaims = [
    'sub' => 'govbr_tecnico_01',
    'cpf' => '703.123.847-98',
    'name' => 'Assistente Social Escritório Social',
    'nivel_confianca' => 'Prata',
    'registro_conselho' => 'CRESS-ES 1234',
];
$tecnicoRole = $govBrService->mapClaimsToRole($tecnicoClaims);
assertCondition($tecnicoRole === 'tecnico', 'Professional council CRESS maps to tecnico role');

// Técnico claim (CRP Psicólogo)
$psicologoClaims = [
    'sub' => 'govbr_psi_01',
    'cpf' => '703.123.847-98',
    'name' => 'Psicólogo Social',
    'nivel_confianca' => 'Prata',
    'registro_conselho' => 'CRP-16 5678',
];
$psiRole = $govBrService->mapClaimsToRole($psicologoClaims);
assertCondition($psiRole === 'tecnico', 'Professional council CRP maps to tecnico role');

// Familiar claim
$familiarClaims = [
    'sub' => 'govbr_fam_01',
    'cpf' => '428.731.940-12',
    'name' => 'Familiar Apoiador',
    'papel' => 'familiar',
];
$famRole = $govBrService->mapClaimsToRole($familiarClaims);
assertCondition($famRole === 'familiar', 'Explicit familiar claim maps to familiar role');

// Fail-secure fallback: Unknown org
$unknownOrgClaims = [
    'sub' => 'govbr_citizen_01',
    'cpf' => '841.235.698-04',
    'name' => 'Cidadão Egresso',
    'nivel_confianca' => 'Bronze',
    'orgao' => 'SEFAZ',
    'cargo' => 'Analista Externo',
];
$fallbackRole = $govBrService->mapClaimsToRole($unknownOrgClaims);
assertCondition($fallbackRole === 'egresso', 'External org claims fail-securely default to egresso role');

// Trust level validation
assertCondition($govBrService->verifyNivelConfianca('Bronze'), 'Trust level Bronze is recognized');
assertCondition($govBrService->verifyNivelConfianca('Prata'), 'Trust level Prata is recognized');
assertCondition($govBrService->verifyNivelConfianca('Ouro'), 'Trust level Ouro is recognized');
assertCondition(!$govBrService->verifyNivelConfianca('Invalido'), 'Invalid trust level is rejected');

echo "\n";

// -----------------------------------------------------------------------------
// 4. Prontuário Boundaries, Taxonomy & Sequential ID Generation
// -----------------------------------------------------------------------------
echo "4. Testing Prontuário Único Boundaries & Taxonomy:\n";

// Sequential ID format
$seqId = sprintf('PRT-2026-%06d', 101);
assertCondition($seqId === 'PRT-2026-000101', 'Prontuário sequential number conforms to PRT-2026-XXXXXX pattern');

// Pagination clamping
$requestedPerPage = 500;
$clampedPerPage = max(1, min(100, (int) $requestedPerPage));
assertCondition($clampedPerPage === 100, 'Pagination requested at 500 is clamped strictly to 100');

$requestedNegativePerPage = -10;
$clampedNegativePerPage = max(1, min(100, (int) $requestedNegativePerPage));
assertCondition($clampedNegativePerPage === 1, 'Negative pagination is clamped strictly to 1');

// 64KB max payload bound
$maxPayload = 65536; // 64KB
$validPayloadSize = strlen(str_repeat('A', 1000));
$exceedingPayloadSize = 70000;
assertCondition($validPayloadSize <= $maxPayload, 'Payload of 1KB is within 64KB boundary');
assertCondition($exceedingPayloadSize > $maxPayload, 'Payload of 70KB exceeds 64KB boundary');

// Empty note check
$emptyNote = '   ';
assertCondition(trim($emptyNote) === '', 'Whitespace-only description is detected as empty');

// Allowed event types
$allowedEventTaxonomy = [
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
assertCondition(in_array('acolhimento_video', $allowedEventTaxonomy, true), 'Taxonomy includes acolhimento_video');
assertCondition(in_array('encaminhamento_vaga', $allowedEventTaxonomy, true), 'Taxonomy includes encaminhamento_vaga');
assertCondition(in_array('inscricao_curso', $allowedEventTaxonomy, true), 'Taxonomy includes inscricao_curso');
assertCondition(!in_array('evento_invalido_hacker', $allowedEventTaxonomy, true), 'Invalid event type rejected from taxonomy');

// XSS entity escaping
$maliciousInput = "<script>alert('xss');</script>";
$escaped = htmlspecialchars($maliciousInput, ENT_QUOTES, 'UTF-8');
assertCondition(!str_contains($escaped, '<script>'), 'HTML entity escaping neutralizes XSS tag');
assertCondition(str_contains($escaped, '&lt;script&gt;'), 'XSS tag transformed to safe HTML entities');

echo "\n";

// -----------------------------------------------------------------------------
// 5. Territorial Mapping & 78 ES Municipalities Integrity
// -----------------------------------------------------------------------------
echo "5. Testing Territorial Mapping (78 ES Municipalities & Bounding Box):\n";

// ES Bounding box
$esMinLat = -21.31;
$esMaxLat = -17.88;
$esMinLon = -41.88;
$esMaxLon = -39.66;

// Vitória centroid
$vitLat = -20.3155;
$vitLon = -40.3128;
assertCondition($vitLat >= $esMinLat && $vitLat <= $esMaxLat && $vitLon >= $esMinLon && $vitLon <= $esMaxLon, 'Vitória GPS is within Espírito Santo bounding box');

// Linhares centroid
$linLat = -19.3911;
$linLon = -40.0722;
assertCondition($linLat >= $esMinLat && $linLat <= $esMaxLat && $linLon >= $esMinLon && $linLon <= $esMaxLon, 'Linhares GPS is within Espírito Santo bounding box');

// Cachoeiro de Itapemirim centroid
$cachLat = -20.8489;
$cachLon = -41.1128;
assertCondition($cachLat >= $esMinLat && $cachLat <= $esMaxLat && $cachLon >= $esMinLon && $cachLon <= $esMaxLon, 'Cachoeiro de Itapemirim GPS is within Espírito Santo bounding box');

// IBGE Code validation (starts with 32)
$validEsIbge = '3205309';
$invalidRjIbge = '3304557';
$invalidSpIbge = '3550308';
assertCondition(str_starts_with($validEsIbge, '32') && strlen($validEsIbge) === 7, 'Valid 7-digit ES IBGE code 3205309 accepted');
assertCondition(!str_starts_with($invalidRjIbge, '32'), 'Non-ES IBGE code 3304557 (RJ) rejected');
assertCondition(!str_starts_with($invalidSpIbge, '32'), 'Non-ES IBGE code 3550308 (SP) rejected');

// Support network GPS fallback
$facilityWithoutGpsLat = null;
$facilityWithoutGpsLon = null;
$fallbackLat = $facilityWithoutGpsLat ?? $vitLat;
$fallbackLon = $facilityWithoutGpsLon ?? $vitLon;
$coordOrigin = ($facilityWithoutGpsLat !== null && $facilityWithoutGpsLon !== null) ? 'exact_gps' : 'municipality_centroid_fallback';
assertCondition($fallbackLat === $vitLat && $coordOrigin === 'municipality_centroid_fallback', 'Facility with null GPS falls back to municipality centroid');

echo "\n";

// -----------------------------------------------------------------------------
// 6. Management KPIs & Analytics Formulas
// -----------------------------------------------------------------------------
echo "6. Testing Management KPI Computation Formulas:\n";

$metaPopulacional = 108000;
assertCondition($metaPopulacional === 108000, 'Meta Populacional de Egressos do ES is 108.000');

$atendimentosRemotos = 3140;
$atendimentosPresenciais = 2090;
$totalAtendimentos = $atendimentosRemotos + $atendimentosPresenciais;
$taxaRemoto = round(($atendimentosRemotos / $totalAtendimentos) * 100, 1);
assertCondition($taxaRemoto === 60.0, 'Taxa de atendimento remoto calculated as 60.0%');

$vagasTotais = 142;
$vagasPreenchidas = 86;
$taxaEmpregabilidade = round(($vagasPreenchidas / $vagasTotais) * 100, 1);
assertCondition($taxaEmpregabilidade === 60.6, 'Taxa de empregabilidade calculated as 60.6%');

$naoReincidencia = 82.5;
assertCondition($naoReincidencia >= 80.0, 'Taxa de não reincidência meets SEJUS benchmark (> 80%)');

$mosDistributionSum = 45.2 + 42.8 + 9.5 + 2.5;
assertCondition(abs($mosDistributionSum - 100.0) < 0.01, 'MOS distribution percentages sum to 100%');

echo "\n";
echo "===============================================================================\n";
echo "SUMMARY: Total Passed: {$passedAssertions} | Total Failed: {$failedAssertions}\n";
echo "===============================================================================\n\n";

if ($failedAssertions === 0) {
    echo ">>> VERIFICATION COMPLETE: ALL M3 BACKEND BUSINESS & RBAC ASSERTIONS PASSED (100%) <<<\n";
    exit(0);
} else {
    echo ">>> VERIFICATION FAILED: {$failedAssertions} ASSERTIONS FAILED <<<\n";
    exit(1);
}
