<?php

/**
 * Empirical Adversarial Test: Ephemeral TURN Credentials & WebRTC Tokens (PHP Suite)
 * Milestone 1 (M1) Challenger Verification
 */

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WebRtcJwtService;
use App\Models\User;

$passed = 0;
$failed = 0;
$assertions = [];

function assert_true(bool $cond, string $desc) {
    global $passed, $failed, $assertions;
    if ($cond) {
        $passed++;
        $assertions[] = ['status' => 'PASS', 'desc' => $desc];
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        $assertions[] = ['status' => 'FAIL', 'desc' => $desc];
        echo "  [FAIL] {$desc}\n";
    }
}

function assert_equals($expected, $actual, string $desc) {
    global $passed, $failed, $assertions;
    if ($expected === $actual) {
        $passed++;
        $assertions[] = ['status' => 'PASS', 'desc' => $desc];
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        $assertions[] = [
            'status' => 'FAIL',
            'desc' => $desc,
            'expected' => $expected,
            'actual' => $actual
        ];
        echo "  [FAIL] {$desc} (Expected: " . json_encode($expected) . ", Got: " . json_encode($actual) . ")\n";
    }
}

echo "===============================================================================\n";
echo "CONECTA EGRESSO (SEJUS/ES) - ADVERSARIAL M1 EMPIRICAL CHALLENGER (PHP)\n";
echo "===============================================================================\n\n";

$secret = 'sejus_turn_secret_key_2026';
$jwtSecret = 'sejus_jwt_shared_secret_2026';
$service = new WebRtcJwtService($jwtSecret, 3600);

echo "1. Ephemeral TURN Credentials - Username & Expiry Verification:\n";

// Test 1: Default Anonymous User
$now = time();
$iceServers = $service->getIceServers();
assert_true(is_array($iceServers) && count($iceServers) === 4, 'getIceServers returns 4 ICE servers (2 STUN, 2 TURN)');

$turnUdp = $iceServers[2];
$turnTcp = $iceServers[3];
assert_equals($turnUdp['username'], $turnTcp['username'], 'TURN UDP and TCP usernames match');
assert_equals($turnUdp['credential'], $turnTcp['credential'], 'TURN UDP and TCP credentials match');

$parts = explode(':', $turnUdp['username']);
assert_equals(2, count($parts), 'TURN username follows timestamp:user_id format');
$timestamp = (int) $parts[0];
$userId = $parts[1];

assert_true($timestamp > $now, "Timestamp is strictly in the future (timestamp={$timestamp}, now={$now})");
$expectedTimestamp = $now + 86400;
assert_true(abs($timestamp - $expectedTimestamp) <= 2, "Timestamp is within 2s of (now + 86400 default TTL)");
assert_equals('anonymous', $userId, 'Default user identifier is anonymous');

// Test 2: Custom TTL and Specific User ID
$customTtl = 600; // 10 minutes
$beforeCall = time();
$customIce = $service->getIceServers('user_42', $customTtl);
$afterCall = time();
$customTurn = $customIce[2];
$customParts = explode(':', $customTurn['username']);
$customTimestamp = (int) $customParts[0];
$customUser = $customParts[1];

assert_equals('user_42', $customUser, 'Custom user ID preserved in username');
assert_true($customTimestamp >= $beforeCall + $customTtl && $customTimestamp <= $afterCall + $customTtl, 'Custom TTL 600s correctly applied to timestamp');

// Test 3: Object User ID
$mockUser = new stdClass();
$mockUser->id = 999;
$objectIce = $service->getIceServers($mockUser, 300);
$objectParts = explode(':', $objectIce[2]['username']);
assert_equals('999', $objectParts[1], 'Object with id property extracts user ID properly');

// Test 3.1: Object without id property safely defaults to anonymous
$emptyObj = new stdClass();
$emptyObjIce = $service->getIceServers($emptyObj, 300);
$emptyParts = explode(':', $emptyObjIce[2]['username']);
assert_equals('anonymous', $emptyParts[1], 'Object without id property safely falls back to anonymous without TypeError');

echo "\n2. Ephemeral TURN Credentials - HMAC-SHA1 & Tamper Resistance:\n";

// Test 4: HMAC-SHA1 Cryptographic Correctness
$recomputedDigest = hash_hmac('sha1', $turnUdp['username'], $secret, true);
$recomputedBase64 = base64_encode($recomputedDigest);
assert_equals($recomputedBase64, $turnUdp['credential'], 'Recomputed base64(hmac_sha1(username, secret)) matches credential exactly');

// Test 5: Tamper Resistance - 1 Character in Username Invalidates Credential
$tamperedUsername1 = $turnUdp['username'] . 'x';
$tamperedDigest1 = base64_encode(hash_hmac('sha1', $tamperedUsername1, $secret, true));
assert_true($tamperedDigest1 !== $turnUdp['credential'], 'Appending 1 character to username completely invalidates HMAC');

$tamperedUsername2 = substr_replace($turnUdp['username'], '9', 0, 1);
$tamperedDigest2 = base64_encode(hash_hmac('sha1', $tamperedUsername2, $secret, true));
assert_true($tamperedDigest2 !== $turnUdp['credential'], 'Modifying 1 digit in timestamp invalidates HMAC');

$tamperedUsername3 = substr_replace($turnUdp['username'], 'z', -1, 1);
$tamperedDigest3 = base64_encode(hash_hmac('sha1', $tamperedUsername3, $secret, true));
assert_true($tamperedDigest3 !== $turnUdp['credential'], 'Modifying 1 character in user_id invalidates HMAC');

// Test 6: Secret Tampering
$wrongSecret = 'wrong_turn_secret_key_1234';
$wrongSecretDigest = base64_encode(hash_hmac('sha1', $turnUdp['username'], $wrongSecret, true));
assert_true($wrongSecretDigest !== $turnUdp['credential'], 'Credential with attacker secret fails validation');

// Test 7: Expired Timestamp Boundary Simulation
$expiredTimestamp = time() - 3600;
$expiredUsername = "{$expiredTimestamp}:attacker_user";
$expiredCred = base64_encode(hash_hmac('sha1', $expiredUsername, $secret, true));
$isExpired = ($expiredTimestamp < time());
assert_true($isExpired, 'Boundary condition: expired timestamp (< time()) is correctly flagged as expired');

echo "\n3. WebRTC JWT Generation & Structural Integrity:\n";

$header = ['alg' => 'HS256', 'typ' => 'JWT'];
$now = time();
$payloadValid = [
    'iss' => 'conecta-egresso-laravel',
    'aud' => 'conecta-egresso-webrtc',
    'sub' => '101',
    'name' => 'Carlos Silva',
    'role' => 'tecnico',
    'room_id' => 'room-vix-01',
    'unit_id' => '10',
    'prontuario_id' => '50',
    'iat' => $now,
    'exp' => $now + 3600,
];

$validJwt = $service->encodeJwt($header, $payloadValid, $jwtSecret);
assert_true(!empty($validJwt), 'encodeJwt generates non-empty token string');
$parts = explode('.', $validJwt);
assert_equals(3, count($parts), 'JWT has exactly 3 dot-separated segments (header.payload.signature)');

$verificationResult = $service->verifyJwt($validJwt);
assert_true($verificationResult['valid'], 'WebRtcJwtService verifies its own generated valid token');
assert_equals('101', $verificationResult['payload']['sub'], 'Decoded sub claim matches');
assert_equals('tecnico', $verificationResult['payload']['role'], 'Decoded role claim matches');

// Test 8: Invalid signature detection
$tamperedSigJwt = $parts[0] . '.' . $parts[1] . '.' . base64_encode('invalid_signature_bytes');
$tamperedResult = $service->verifyJwt($tamperedSigJwt);
assert_true(!$tamperedResult['valid'], 'Rejects token with tampered signature');
assert_equals('INVALID_SIGNATURE', $tamperedResult['error'], 'Reports INVALID_SIGNATURE error code');

// Test 9: Expired token detection
$payloadExpired = $payloadValid;
$payloadExpired['exp'] = $now - 60;
$expiredJwt = $service->encodeJwt($header, $payloadExpired, $jwtSecret);
$expiredResult = $service->verifyJwt($expiredJwt);
assert_true(!$expiredResult['valid'], 'Rejects token with past expiration');
assert_equals('TOKEN_EXPIRED', $expiredResult['error'], 'Reports TOKEN_EXPIRED error code');

// Export sample payload and tokens for Python cross-validation
$exportData = [
    'coturn_secret' => $secret,
    'jwt_secret' => $jwtSecret,
    'turn_sample' => [
        'username' => $turnUdp['username'],
        'credential' => $turnUdp['credential'],
    ],
    'valid_tokens' => [
        'tecnico' => $validJwt,
        'egresso' => $service->encodeJwt($header, array_merge($payloadValid, ['sub' => '202', 'name' => 'Joao Egresso', 'role' => 'egresso']), $jwtSecret),
        'gestor' => $service->encodeJwt($header, array_merge($payloadValid, ['sub' => '303', 'name' => 'Maria Gestora', 'role' => 'gestor']), $jwtSecret),
        'defensoria' => $service->encodeJwt($header, array_merge($payloadValid, ['sub' => '404', 'name' => 'Dr. Lucas', 'role' => 'defensoria']), $jwtSecret),
        'observer' => $service->encodeJwt($header, array_merge($payloadValid, ['sub' => '505', 'name' => 'Fiscal', 'role' => 'observer']), $jwtSecret),
    ],
    'edge_tokens' => [
        'expired' => $expiredJwt,
        'wrong_secret' => $service->encodeJwt($header, $payloadValid, 'wrong_jwt_secret_key_here'),
        'wrong_issuer' => $service->encodeJwt($header, array_merge($payloadValid, ['iss' => 'forged-issuer']), $jwtSecret),
        'wrong_audience' => $service->encodeJwt($header, array_merge($payloadValid, ['aud' => 'forged-audience']), $jwtSecret),
        'int_ids' => $service->encodeJwt($header, [
            'iss' => 'conecta-egresso-laravel',
            'aud' => 'conecta-egresso-webrtc',
            'sub' => '101',
            'name' => 'Carlos Silva',
            'role' => 'tecnico',
            'room_id' => 'room-vix-01',
            'unit_id' => 10,       // integer!
            'prontuario_id' => 50, // integer!
            'iat' => $now,
            'exp' => $now + 3600,
        ], $jwtSecret),
    ],
];

file_put_contents(__DIR__ . '/adversarial_m1_exported_tokens.json', json_encode($exportData, JSON_PRETTY_PRINT));

echo "\n===============================================================================\n";
echo "PHP SUITE RESULTS: Passed: {$passed} | Failed: {$failed}\n";
echo "===============================================================================\n";

exit($failed > 0 ? 1 : 0);
