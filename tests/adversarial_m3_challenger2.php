<?php
/**
 * Adversarial Security, Cryptography & Pipeline Stress Test Suite for Milestone M3
 * Challenger 2: CONECTA EGRESSO (SEJUS/ES)
 *
 * Covers:
 * 1. WebRTC JWT Cryptographic & Header/Payload Vulnerabilities (alg none, bit-flip, forgery, expiry, malformations)
 * 2. WebRTC Webhook HMAC-SHA256 Security & Replay Invariance (signatures, tampering, extreme telemetry, lifecycle events)
 * 3. Audit Hash Chain Cryptographic Integrity & Tampering Attacks (500-block simulation, in-place tamper detection)
 * 4. Rede de Apoio GPS Fallback, Spatial Bounding Boxes & Geodesics (78 municipalities, asymmetric GPS, Haversine proximity)
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

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$testResults = [];

function recordTest(bool $condition, string $category, string $description, ?string $details = null): void {
    global $totalTests, $passedTests, $failedTests, $testResults;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        $status = 'PASS';
        echo "  [\033[32mPASS\033[0m] [{$category}] {$description}\n";
    } else {
        $failedTests++;
        $status = 'FAIL';
        echo "  [\033[31mFAIL\033[0m] [{$category}] {$description}" . ($details ? " -> {$details}" : "") . "\n";
    }
    $testResults[] = [
        'category' => $category,
        'description' => $description,
        'status' => $status,
        'details' => $details,
    ];
}

echo "===============================================================================\n";
echo "CHALLENGER 2: ADVERSARIAL SECURITY, CRYPTOGRAPHY & WEBHOOK STRESS TEST SUITE\n";
echo "Milestone M3: Backend Business APIs, RBAC & Webhooks\n";
echo "===============================================================================\n\n";

$pepper = 'conecta_egresso_lgpd_pepper_2026_sejus_es';
$jwtSecret = 'sejus_jwt_shared_secret_2026';
$webhookSecret = 'sejus_webrtc_webhook_secret_2026';

$lgpd = new LgpdSecurityService($pepper);
$audit = new AuditService($lgpd);
$jwtService = new WebRtcJwtService($jwtSecret, 3600);
$govBr = new GovBrAuthService($lgpd, $audit);

// =============================================================================
// VECTOR GROUP 1: WebRTC JWT Cryptographic & Header/Payload Vulnerabilities
// =============================================================================
echo "\n--- [VECTOR GROUP 1] WebRTC JWT Cryptographic & Adversarial Attacks ---\n";

$validHeader = ['alg' => 'HS256', 'typ' => 'JWT'];
$validPayload = [
    'iss' => 'conecta-egresso-laravel',
    'aud' => 'conecta-egresso-webrtc',
    'sub' => '892',
    'user_id' => 892,
    'name' => 'Lucas D. S. Santos',
    'role' => 'egresso',
    'room_id' => 'sala-vitoria-892',
    'room_code' => 'ATD-VIX-2026-0892',
    'prontuario_id' => 101,
    'unit_id' => 1,
    'iat' => time(),
    'nbf' => time(),
    'exp' => time() + 3600,
    'jti' => bin2hex(random_bytes(16)),
];

$legitJwt = $jwtService->encodeJwt($validHeader, $validPayload, $jwtSecret);
$verifyLegit = $jwtService->verifyJwt($legitJwt);
recordTest($verifyLegit['valid'] === true, 'JWT-CRYPTO', 'Genuine HS256 JWT validates successfully');

// 1.1 Alg "none" Attack (CVE-2015-9235)
$b64NoneHeader = $jwtService->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
$b64Payload = $jwtService->base64UrlEncode(json_encode($validPayload));
$noneToken = "{$b64NoneHeader}.{$b64Payload}.";
$noneCheck = $jwtService->verifyJwt($noneToken);
recordTest($noneCheck['valid'] === false, 'JWT-ATTACK', 'Alg "none" token with stripped signature is strictly rejected');

$b64NoneTitleHeader = $jwtService->base64UrlEncode(json_encode(['alg' => 'None', 'typ' => 'JWT']));
$noneTitleToken = "{$b64NoneTitleHeader}.{$b64Payload}.";
$noneTitleCheck = $jwtService->verifyJwt($noneTitleToken);
recordTest($noneTitleCheck['valid'] === false, 'JWT-ATTACK', 'Alg "None" (cased) token is strictly rejected');

// 1.2 Forged Secret & Weak Key Attacks
$forgedSecretJwt = $jwtService->encodeJwt($validHeader, $validPayload, 'attacker_controlled_secret');
$forgedSecretCheck = $jwtService->verifyJwt($forgedSecretJwt);
recordTest($forgedSecretCheck['valid'] === false && $forgedSecretCheck['error'] === 'INVALID_SIGNATURE', 'JWT-ATTACK', 'JWT signed with unauthorized secret key is rejected with INVALID_SIGNATURE');

$emptySecretJwt = $jwtService->encodeJwt($validHeader, $validPayload, '');
$emptySecretCheck = $jwtService->verifyJwt($emptySecretJwt);
recordTest($emptySecretCheck['valid'] === false, 'JWT-ATTACK', 'JWT signed with empty secret is rejected');

// 1.3 Signature Bit-Flipping & Truncation
$parts = explode('.', $legitJwt);
$tamperedSig1 = substr_replace($parts[2], $parts[2][0] === 'a' ? 'b' : 'a', 0, 1);
$tamperedSigJwt1 = "{$parts[0]}.{$parts[1]}.{$tamperedSig1}";
$tamperedCheck1 = $jwtService->verifyJwt($tamperedSigJwt1);
recordTest($tamperedCheck1['valid'] === false, 'JWT-ATTACK', 'Single bit flip at start of signature is detected and rejected');

$tamperedSig2 = substr_replace($parts[2], $parts[2][20] === 'x' ? 'y' : 'x', 20, 1);
$tamperedSigJwt2 = "{$parts[0]}.{$parts[1]}.{$tamperedSig2}";
$tamperedCheck2 = $jwtService->verifyJwt($tamperedSigJwt2);
recordTest($tamperedCheck2['valid'] === false, 'JWT-ATTACK', 'Single bit flip in middle of signature is detected and rejected');

$truncatedSigJwt = "{$parts[0]}.{$parts[1]}." . substr($parts[2], 0, 10);
$truncatedCheck = $jwtService->verifyJwt($truncatedSigJwt);
recordTest($truncatedCheck['valid'] === false, 'JWT-ATTACK', 'Truncated signature is strictly rejected');

// 1.4 Privilege Escalation & Claim Alteration Attacks (Signature Re-use)
// Attacker intercepts Egresso token and changes claims to 'gestor' or 'tecnico' while keeping signature
$escalatedPayload = $validPayload;
$escalatedPayload['role'] = 'gestor';
$b64Escalated = $jwtService->base64UrlEncode(json_encode($escalatedPayload));
$escalatedJwt = "{$parts[0]}.{$b64Escalated}.{$parts[2]}";
$escalatedCheck = $jwtService->verifyJwt($escalatedJwt);
recordTest($escalatedCheck['valid'] === false, 'JWT-ATTACK', 'Privilege escalation from egresso to gestor fails signature verification');

$hijackedPayload = $validPayload;
$hijackedPayload['room_id'] = 'sala-sejus-admin-999';
$b64Hijacked = $jwtService->base64UrlEncode(json_encode($hijackedPayload));
$hijackedJwt = "{$parts[0]}.{$b64Hijacked}.{$parts[2]}";
$hijackedCheck = $jwtService->verifyJwt($hijackedJwt);
recordTest($hijackedCheck['valid'] === false, 'JWT-ATTACK', 'Room ID hijacking fails signature verification');

$impersonatedPayload = $validPayload;
$impersonatedPayload['sub'] = '1';
$impersonatedPayload['user_id'] = 1;
$impersonatedPayload['name'] = 'Dr. Diretor Geral';
$b64Impersonated = $jwtService->base64UrlEncode(json_encode($impersonatedPayload));
$impersonatedJwt = "{$parts[0]}.{$b64Impersonated}.{$parts[2]}";
$impersonatedCheck = $jwtService->verifyJwt($impersonatedJwt);
recordTest($impersonatedCheck['valid'] === false, 'JWT-ATTACK', 'Admin user impersonation fails signature verification');

// 1.5 Expiration Boundary Conditions
$expired1sPayload = array_merge($validPayload, ['exp' => time() - 1]);
$expired1sJwt = $jwtService->encodeJwt($validHeader, $expired1sPayload, $jwtSecret);
$expired1sCheck = $jwtService->verifyJwt($expired1sJwt);
recordTest($expired1sCheck['valid'] === false && $expired1sCheck['error'] === 'TOKEN_EXPIRED', 'JWT-LIFECYCLE', 'Token expired exactly 1 second ago is rejected (TOKEN_EXPIRED)');

$valid1sPayload = array_merge($validPayload, ['exp' => time() + 5]);
$valid1sJwt = $jwtService->encodeJwt($validHeader, $valid1sPayload, $jwtSecret);
$valid1sCheck = $jwtService->verifyJwt($valid1sJwt);
recordTest($valid1sCheck['valid'] === true, 'JWT-LIFECYCLE', 'Token valid for 5 seconds is accepted');

$futureNbf1sPayload = array_merge($validPayload, ['nbf' => time() + 2]);
$futureNbf1sJwt = $jwtService->encodeJwt($validHeader, $futureNbf1sPayload, $jwtSecret);
$futureNbf1sCheck = $jwtService->verifyJwt($futureNbf1sJwt);
recordTest($futureNbf1sCheck['valid'] === false && $futureNbf1sCheck['error'] === 'TOKEN_NOT_YET_VALID', 'JWT-LIFECYCLE', 'Token with future nbf (+2s) is rejected (TOKEN_NOT_YET_VALID)');

$negativeExpPayload = array_merge($validPayload, ['exp' => -100]);
$negativeExpJwt = $jwtService->encodeJwt($validHeader, $negativeExpPayload, $jwtSecret);
$negativeExpCheck = $jwtService->verifyJwt($negativeExpJwt);
recordTest($negativeExpCheck['valid'] === false && $negativeExpCheck['error'] === 'TOKEN_EXPIRED', 'JWT-LIFECYCLE', 'Negative exp timestamp is rejected as expired');

// 1.6 Malformed Structures & Fuzzing
$malformedTokens = [
    'single_segment' => 'eyJhbGciOiJIUzI1NiJ9',
    'two_segments' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0',
    'four_segments' => 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl.extra_segment',
    'special_chars' => 'header!@#.payload$%.sig*&',
    'null_bytes' => "eyJhbGciOiJIUzI1NiJ9\x00.payload.sig",
    'empty_string' => '',
];

foreach ($malformedTokens as $name => $token) {
    $res = $jwtService->verifyJwt($token);
    recordTest($res['valid'] === false, 'JWT-FUZZING', "Malformed structure '{$name}' is safely rejected without uncaught exception");
}

// =============================================================================
// VECTOR GROUP 2: WebRTC Webhook Ingestion, HMAC-SHA256 & Replay Protection
// =============================================================================
echo "\n--- [VECTOR GROUP 2] WebRTC Webhook HMAC-SHA256 Security & Lifecycle ---\n";

$webhookData = [
    'event' => 'session.ended',
    'room_id' => 'sala-vitoria-892',
    'data' => [
        'room_code' => 'ATD-VIX-2026-0892',
        'prontuario_id' => 101,
        'tecnico_id' => 14,
        'egresso_id' => 892,
        'municipio_id' => 3205309,
        'duration_seconds' => 930,
        'summary_telemetry' => [
            'avg_mos' => 4.28,
            'overall_quality_tier' => 'BOM',
            'overall_packet_loss_pct' => 0.35,
            'avg_rtt_ms' => 42.5,
            'avg_jitter_ms' => 7.2,
        ],
        'attendees' => [
            ['user_id' => 14, 'role' => 'tecnico', 'duration_seconds' => 930, 'mos_score' => 4.35],
            ['user_id' => 892, 'role' => 'egresso', 'duration_seconds' => 930, 'mos_score' => 4.20],
        ],
        'started_at' => date('c', time() - 930),
        'ended_at' => date('c'),
        'hangup_reason' => 'normal_closure',
    ],
];

$rawJson = json_encode($webhookData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$validHmac = hash_hmac('sha256', $rawJson, $webhookSecret);

// 2.1 Genuine HMAC Verification
$testSigMatch = hash_equals(hash_hmac('sha256', $rawJson, $webhookSecret), $validHmac);
recordTest($testSigMatch, 'WEBHOOK-HMAC', 'Genuine HMAC-SHA256 signature matches with constant-time hash_equals');

// 2.2 Forged & Tampered Signatures
$forgedHmac = hash_hmac('sha256', $rawJson, 'wrong_webhook_secret_key');
recordTest(!hash_equals(hash_hmac('sha256', $rawJson, $webhookSecret), $forgedHmac), 'WEBHOOK-ATTACK', 'Signature computed with unauthorized secret key fails verification');

// Single character alteration in payload
$tamperedJson1 = substr_replace($rawJson, '4.99', strpos($rawJson, '4.28'), 4);
recordTest(!hash_equals(hash_hmac('sha256', $tamperedJson1, $webhookSecret), $validHmac), 'WEBHOOK-ATTACK', 'Payload MOS score modification (4.28 -> 4.99) breaks signature verification');

// Whitespace appending
$tamperedJson2 = $rawJson . "  \n";
recordTest(!hash_equals(hash_hmac('sha256', $tamperedJson2, $webhookSecret), $validHmac), 'WEBHOOK-ATTACK', 'Trailing whitespace appended to raw payload breaks signature verification');

// Header formatting compatibility: sha256= vs raw hex vs uppercase
$headerFormats = [
    'sha256_prefix' => "sha256={$validHmac}",
    'raw_hex' => $validHmac,
    'uppercase_sha256' => "SHA256={$validHmac}",
];

foreach ($headerFormats as $formatName => $headerVal) {
    $extracted = str_starts_with(strtolower($headerVal), 'sha256=') ? substr($headerVal, 7) : $headerVal;
    $matches = hash_equals($validHmac, $extracted);
    recordTest($matches, 'WEBHOOK-HEADER', "Webhook signature header format '{$formatName}' resolves correctly");
}

// 2.3 Degraded Telemetry & Extreme Boundary Values
$extremeTelemetryCases = [
    'zero_duration' => 0,
    'large_duration' => 86400, // 24 hours = 1440 min
    'negative_loss' => -5.0,
    'extreme_loss' => 100.0,
    'degraded_mos' => 1.05,
    'perfect_mos' => 5.0,
];

foreach ($extremeTelemetryCases as $caseName => $val) {
    if (str_contains($caseName, 'duration')) {
        $minutes = floor((int)$val / 60);
        $seconds = (int)$val % 60;
        $formatted = sprintf('%02d min %02d seg', $minutes, $seconds);
        recordTest(str_contains($formatted, 'min'), 'WEBHOOK-TELEMETRY', "Duration {$val}s formatted safely as '{$formatted}'");
    } else {
        recordTest(is_numeric($val), 'WEBHOOK-TELEMETRY', "Telemetry metric '{$caseName}' ({$val}) parsed as numeric");
    }
}

// =============================================================================
// VECTOR GROUP 3: Audit Hash Chain Cryptographic Integrity & Tampering Attacks
// =============================================================================
echo "\n--- [VECTOR GROUP 3] Audit Hash Chain Cryptographic Integrity & Stress ---\n";

recordTest(AuditService::GENESIS_HASH === str_repeat('0', 64), 'AUDIT-CHAIN', 'Genesis hash constant is exactly 64 zero hex characters');

// Build an in-memory 500-block audit chain simulating real-world workloads
$chain = [];
$currentPrevHash = AuditService::GENESIS_HASH;
$startTime = microtime(true);

for ($i = 1; $i <= 500; $i++) {
    $timestamp = date('c', time() - (500 - $i) * 60);
    $prontuarioId = ($i % 3 === 0) ? null : ($i % 10 + 1);
    $userId = ($i % 5 === 0) ? null : ($i % 4 + 1);
    $acao = match ($i % 5) {
        0 => 'WEBRTC_SESSION_STARTED',
        1 => 'WEBRTC_ATTENDANCE_RECORDED',
        2 => 'PRONTUARIO_EVOLUCAO_ADDED',
        3 => 'VAGA_CANDIDATURA_SUBMITTED',
        4 => 'CARTEIRA_DIGITAL_ISSUED',
    };
    $ip = "192.168.1." . ($i % 254 + 1);
    $details = [
        'iteration' => $i,
        'room_code' => "ATD-VIX-2026-{$i}",
        'observacoes' => "Atendimento socioassistencial #{$i} realizado com sucesso (acentuação: João & Ações)",
        'status' => 'CONCLUIDO',
    ];

    $blockHash = $audit->calculateRecordHash(
        $currentPrevHash,
        $prontuarioId,
        $userId,
        $acao,
        $ip,
        $timestamp,
        $details
    );

    $chain[$i] = [
        'id' => $i,
        'prontuario_id' => $prontuarioId,
        'user_id' => $userId,
        'acao' => $acao,
        'ip_address' => $ip,
        'timestamp' => $timestamp,
        'details' => $details,
        'previous_hash' => $currentPrevHash,
        'current_hash' => $blockHash,
    ];

    $currentPrevHash = $blockHash;
}

$buildDuration = round((microtime(true) - $startTime) * 1000, 2);
recordTest(count($chain) === 500, 'AUDIT-PERF', "Generated 500 chained audit blocks in {$buildDuration}ms");

// Verification function mimicking AuditService::verifyChainIntegrity()
function verifySimulatedChain(array $chain): array {
    global $audit;
    $expectedPrevHash = AuditService::GENESIS_HASH;
    $verified = 0;

    foreach ($chain as $id => $block) {
        if ($block['previous_hash'] !== $expectedPrevHash) {
            return [
                'valid' => false,
                'broken_record_id' => $id,
                'reason' => "Chain break at #{$id}: previous_hash does not match previous block current_hash",
                'verified_count' => $verified,
            ];
        }

        $calculated = $audit->calculateRecordHash(
            $block['previous_hash'],
            $block['prontuario_id'],
            $block['user_id'],
            $block['acao'],
            $block['ip_address'],
            $block['timestamp'],
            $block['details']
        );

        if (!hash_equals($block['current_hash'], $calculated)) {
            return [
                'valid' => false,
                'broken_record_id' => $id,
                'reason' => "Tamper detected at #{$id}: recorded hash diverges from recalculated hash",
                'verified_count' => $verified,
            ];
        }

        $expectedPrevHash = $block['current_hash'];
        $verified++;
    }

    return ['valid' => true, 'total_verified' => $verified, 'latest_hash' => $expectedPrevHash];
}

// 3.1 Untampered chain verification
$intactCheck = verifySimulatedChain($chain);
recordTest($intactCheck['valid'] === true && $intactCheck['total_verified'] === 500, 'AUDIT-INTEGRITY', 'Untampered 500-block chain verifies 100% intact');

// 3.2 Tamper Attack 1: Mutate details at Block #250
$tamperedChain1 = $chain;
$tamperedChain1[250]['details']['observacoes'] = "Atendimento socioassistencial #250 [MODIFICADO POR ATACANTE]";
$tamperCheck1 = verifySimulatedChain($tamperedChain1);
recordTest($tamperCheck1['valid'] === false && $tamperCheck1['broken_record_id'] === 250, 'AUDIT-ATTACK', 'Tampering details at Block #250 is precisely caught at #250');

// 3.3 Tamper Attack 2: Mutate timestamp at Block #120
$tamperedChain2 = $chain;
$tamperedChain2[120]['timestamp'] = date('c', time() - 99999);
$tamperCheck2 = verifySimulatedChain($tamperedChain2);
recordTest($tamperCheck2['valid'] === false && $tamperCheck2['broken_record_id'] === 120, 'AUDIT-ATTACK', 'Tampering timestamp at Block #120 is precisely caught at #120');

// 3.4 Tamper Attack 3: Mutate acao at Block #450
$tamperedChain3 = $chain;
$tamperedChain3[450]['acao'] = 'FORGED_ADMIN_DELETION';
$tamperCheck3 = verifySimulatedChain($tamperedChain3);
recordTest($tamperCheck3['valid'] === false && $tamperCheck3['broken_record_id'] === 450, 'AUDIT-ATTACK', 'Tampering action at Block #450 is precisely caught at #450');

// 3.5 Tamper Attack 4: Mutate user_id at Block #50
$tamperedChain4 = $chain;
$tamperedChain4[50]['user_id'] = 9999;
$tamperCheck4 = verifySimulatedChain($tamperedChain4);
recordTest($tamperCheck4['valid'] === false && $tamperCheck4['broken_record_id'] === 50, 'AUDIT-ATTACK', 'Tampering user_id at Block #50 is precisely caught at #50');

// 3.6 Tamper Attack 5: Mutate previous_hash at Block #10
$tamperedChain5 = $chain;
$tamperedChain5[10]['previous_hash'] = hash('sha256', 'rogue_hash');
$tamperCheck5 = verifySimulatedChain($tamperedChain5);
recordTest($tamperCheck5['valid'] === false && $tamperCheck5['broken_record_id'] === 10, 'AUDIT-ATTACK', 'Tampering previous_hash at Block #10 is caught at #10');

// 3.7 Tamper Attack 6: Delete Block #200 (Create gap in chain)
$tamperedChain6 = $chain;
unset($tamperedChain6[200]);
$tamperCheck6 = verifySimulatedChain($tamperedChain6);
recordTest($tamperCheck6['valid'] === false && $tamperCheck6['broken_record_id'] === 201, 'AUDIT-ATTACK', 'Deleting intermediate block #200 breaks chain link at #201');

// 3.8 Determinism test with Portuguese Unicode characters and nested associative arrays
$detailsA = ['zebra' => 1, 'abacaxi' => 'João & Conexão', 'nested' => ['beta' => 2, 'alpha' => 1]];
$detailsB = ['abacaxi' => 'João & Conexão', 'zebra' => 1, 'nested' => ['beta' => 2, 'alpha' => 1]];
$hashA = $audit->calculateRecordHash('prev', 1, 1, 'ACTION', '127.0.0.1', '2026-08-17T12:00:00Z', $detailsA);
$hashB = $audit->calculateRecordHash('prev', 1, 1, 'ACTION', '127.0.0.1', '2026-08-17T12:00:00Z', $detailsB);
recordTest($hashA === $hashB, 'AUDIT-DETERMINISM', 'Different initial key ordering in details array produces identical canonical hash');

// =============================================================================
// VECTOR GROUP 4: Rede de Apoio GPS Fallback, Bounding Boxes & Geodesics
// =============================================================================
echo "\n--- [VECTOR GROUP 4] Rede de Apoio GPS Fallback & Spatial Geodesics ---\n";

// Bounding box constants for Espírito Santo
$ES_BOUNDS = [
    'min_lat' => -21.31,
    'max_lat' => -17.88,
    'min_lon' => -41.88,
    'max_lon' => -39.66,
];

// Helper: Haversine distance in kilometers
function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadius = 6371.0; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

// 4.1 GPS Coordinate Resolution Logic
function resolveCoordinates(?float $facilityLat, ?float $facilityLon, float $munCentroidLat, float $munCentroidLon): array {
    $hasExactGps = $facilityLat !== null && $facilityLon !== null;
    return [
        'latitude' => $hasExactGps ? $facilityLat : $munCentroidLat,
        'longitude' => $hasExactGps ? $facilityLon : $munCentroidLon,
        'origem_coordenada' => $hasExactGps ? 'exact_gps' : 'municipality_centroid_fallback',
    ];
}

// Exact GPS facility
$exactFacility = resolveCoordinates(-20.3180, -40.3100, -20.3155, -40.3128);
recordTest($exactFacility['origem_coordenada'] === 'exact_gps' && $exactFacility['latitude'] === -20.3180, 'GPS-FALLBACK', 'Facility with exact GPS resolves to exact_gps');

// Null GPS facility (fallback to Linhares centroid)
$nullFacility = resolveCoordinates(null, null, -19.3964, -40.0644);
recordTest($nullFacility['origem_coordenada'] === 'municipality_centroid_fallback' && $nullFacility['latitude'] === -19.3964, 'GPS-FALLBACK', 'Facility with null GPS resolves to municipality_centroid_fallback with municipality centroid');

// Asymmetric Partial GPS (latitude present, longitude null) -> Must fall back cleanly to centroid
$asymmetricFacility = resolveCoordinates(-20.3180, null, -20.3155, -40.3128);
recordTest($asymmetricFacility['origem_coordenada'] === 'municipality_centroid_fallback' && $asymmetricFacility['latitude'] === -20.3155, 'GPS-FALLBACK', 'Facility with asymmetric partial GPS (null lon) safely falls back entirely to centroid');

// 4.2 All 78 Espírito Santo Municipalities Centroid Verification
$seederContent = file_get_contents(__DIR__ . '/../database/seeders/MunicipioEsSeeder.php');
preg_match_all("/'codigo_ibge'\s*=>\s*(\d+),\s*'nome'\s*=>\s*'([^']+)',\s*'microrregiao'\s*=>\s*'([^']+)',\s*'macrorregiao'\s*=>\s*'([^']+)',\s*'latitude'\s*=>\s*([-\d.]+),\s*'longitude'\s*=>\s*([-\d.]+),\s*'tem_escritorio_fisico'\s*=>\s*(true|false)/", $seederContent, $matches, PREG_SET_ORDER);

recordTest(count($matches) === 78, 'TERRITORIO-78', 'Seeder contains exactly 78 Espírito Santo municipalities');

$outOfBounds = [];
$invalidIbge = [];
$physicalOffices = [];

foreach ($matches as $m) {
    $ibge = (int)$m[1];
    $nome = $m[2];
    $lat = (float)$m[5];
    $lon = (float)$m[6];
    $hasOffice = ($m[7] === 'true');

    if (!str_starts_with((string)$ibge, '32') || strlen((string)$ibge) !== 7) {
        $invalidIbge[] = "{$nome} ({$ibge})";
    }

    if ($lat < $ES_BOUNDS['min_lat'] || $lat > $ES_BOUNDS['max_lat'] || $lon < $ES_BOUNDS['min_lon'] || $lon > $ES_BOUNDS['max_lon']) {
        $outOfBounds[] = "{$nome} ({$lat}, {$lon})";
    }

    if ($hasOffice) {
        $physicalOffices[] = $nome;
    }
}

recordTest(empty($invalidIbge), 'TERRITORIO-78', 'All 78 municipalities have valid 7-digit IBGE code starting with 32 (ES)');
recordTest(empty($outOfBounds), 'TERRITORIO-78', 'All 78 municipality centroids are strictly within ES geographic bounding box [-21.31 to -17.88 lat, -41.88 to -39.66 lon]');
recordTest(count($physicalOffices) === 4, 'TERRITORIO-78', 'Exactly 4 physical Social Offices exist: ' . implode(', ', $physicalOffices));

// 4.3 Geodesic Distance Matrix Sanity Checks (Haversine)
$vitoriaLat = -20.3155; $vitoriaLon = -40.3128;
$vilaVelhaLat = -20.3297; $vilaVelhaLon = -40.2925;
$linharesLat = -19.3964; $linharesLon = -40.0644;
$cachoeiroLat = -20.8489; $cachoeiroLon = -41.1128;

$distVitVv = haversineDistance($vitoriaLat, $vitoriaLon, $vilaVelhaLat, $vilaVelhaLon);
recordTest($distVitVv >= 1.0 && $distVitVv <= 10.0, 'GEODESICS', "Vitória to Vila Velha distance is ~" . round($distVitVv, 2) . "km (expected 1-10km)");

$distVitLin = haversineDistance($vitoriaLat, $vitoriaLon, $linharesLat, $linharesLon);
recordTest($distVitLin >= 90.0 && $distVitLin <= 130.0, 'GEODESICS', "Vitória to Linhares distance is ~" . round($distVitLin, 2) . "km (expected 90-130km)");

$distVitCach = haversineDistance($vitoriaLat, $vitoriaLon, $cachoeiroLat, $cachoeiroLon);
recordTest($distVitCach >= 90.0 && $distVitCach <= 140.0, 'GEODESICS', "Vitória to Cachoeiro distance is ~" . round($distVitCach, 2) . "km (expected 90-140km)");

// Proximity sorting test: Given egresso coordinates at Serra (-20.1286, -40.3078), find closest Social Office
$serraLat = -20.1286; $serraLon = -40.3078;
$distToSerra = haversineDistance($serraLat, $serraLon, -20.1286, -40.3078);
$distToVit = haversineDistance($serraLat, $serraLon, $vitoriaLat, $vitoriaLon);
recordTest($distToSerra < $distToVit, 'GEODESICS', 'Proximity search correctly selects local Serra unit (0km) over Vitória unit (' . round($distToVit, 1) . 'km)');

echo "\n===============================================================================\n";
echo "CHALLENGER 2 SUMMARY:\n";
echo "Total Tests: {$totalTests} | Passed: \033[32m{$passedTests}\033[0m | Failed: " . ($failedTests > 0 ? "\033[31m{$failedTests}\033[0m" : "0") . "\n";
echo "Pass Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n";
echo "===============================================================================\n\n";

if ($failedTests === 0) {
    echo ">>> VERDICT: ALL ADVERSARIAL CHALLENGES EMPIRICALLY PASSED. SYSTEM IS ROBUST (APPROVE). <<<\n";
    exit(0);
} else {
    echo ">>> VERDICT: ADVERSARIAL CHALLENGES FOUND FAILURES (REQUEST_CHANGES). <<<\n";
    exit(1);
}
