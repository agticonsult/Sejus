<?php

/**
 * CONECTA EGRESSO (SEJUS/ES) - Milestone M1 & M2
 * EMPIRICAL ADVERSARIAL STRESS TEST HARNESS (Challenger 1)
 *
 * Exhaustive empirical testing of:
 * - LgpdSecurityService (CPF edge cases, blind indexing determinism & collision, AES-256 roundtrips, corrupted ciphertexts, masking)
 * - AuditService (Cryptographic hash chaining, genesis hash, tamper injection on all record fields, broken link forensics)
 * - QrCodeSecurityService (HMAC token generation, payload tampering, signature forgery, expiration window, timing attack resilience)
 */

declare(strict_types=1);

// Standalone autoloader
spl_autoload_register(function ($class) {
    $prefixApp = 'App\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (strncmp($prefixApp, $class, strlen($prefixApp)) === 0) {
        $relativeClass = substr($class, strlen($prefixApp));
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Mock minimal framework functions
if (!function_exists('config')) {
    function config($key, $default = null) {
        $configs = [
            'app.url' => 'https://conectaegresso.es.gov.br',
            'app.name' => 'CONECTA EGRESSO (SEJUS/ES)',
            'services.lgpd.pepper' => 'conecta_egresso_lgpd_pepper_2026_sejus_es',
            'services.carteira.signing_key' => 'sejus_carteira_digital_master_key_2026',
        ];
        return $configs[$key] ?? $default;
    }
}

if (!function_exists('now')) {
    function now() {
        return new class extends \DateTimeImmutable {
            public function __construct() {
                parent::__construct('now', new \DateTimeZone('America/Sao_Paulo'));
            }
            public function toIso8601String(): string {
                return $this->format('c');
            }
            public function addYear(): self {
                return $this->modify('+1 year');
            }
        };
    }
}

use App\Services\LgpdSecurityService;
use App\Services\AuditService;
use App\Services\QrCodeSecurityService;

$totalTests = 0;
$totalPassed = 0;
$totalFailed = 0;
$failures = [];

function recordTest(string $group, string $name, bool $condition, ?string $diagnostic = null): void {
    global $totalTests, $totalPassed, $totalFailed, $failures;
    $totalTests++;
    if ($condition) {
        $totalPassed++;
        echo "  [PASS] [{$group}] {$name}\n";
    } else {
        $totalFailed++;
        $failures[] = "[{$group}] {$name}: {$diagnostic}";
        echo "  [FAIL] [{$group}] {$name} --> {$diagnostic}\n";
    }
}

echo "===============================================================================\n";
echo "CONECTA EGRESSO (SEJUS/ES) - ADVERSARIAL CRYPTOGRAPHIC STRESS HARNESS\n";
echo "Challenger 1: Empirical Verification of Security & Cryptographic Invariants\n";
echo "===============================================================================\n\n";

// ============================================================================
// SECTION 1: LgpdSecurityService ADVERSARIAL STRESS SUITE
// ============================================================================
echo ">>> SECTION 1: LgpdSecurityService Adversarial Stress Testing\n";

$pepper = 'sejus_test_pepper_key_' . bin2hex(random_bytes(16));
$lgpd = new LgpdSecurityService($pepper);

// 1.1 CPF Normalization Boundary Tests (use indexed tuples to avoid PHP key int-casting)
$validRawFormats = [
    ['raw' => '123.456.789-01', 'expected' => '12345678901'],
    ['raw' => '12345678901', 'expected' => '12345678901'],
    ['raw' => '  123.456.789-01  ', 'expected' => '12345678901'],
    ['raw' => "\t123\n456\r78901\0", 'expected' => '12345678901'],
    ['raw' => 'CPF: 123-456-789/01 (ES)', 'expected' => '12345678901'],
    ['raw' => '..123..456..789..01..', 'expected' => '12345678901'],
];
foreach ($validRawFormats as $item) {
    try {
        $norm = $lgpd->normalizeCpf((string)$item['raw']);
        recordTest('LGPD_NORM', "Normalizes '{$item['raw']}' to '{$item['expected']}'", $norm === $item['expected']);
    } catch (\Throwable $e) {
        recordTest('LGPD_NORM', "Normalizes '{$item['raw']}'", false, "Exception: " . $e->getMessage());
    }
}

$invalidRawFormats = [
    '',
    '123.456.789', // 9 digits
    '123.456.789-0', // 10 digits
    '123.456.789-012', // 12 digits
    'abcdefghijk',
    '12345678901234567890',
    '   ',
    '123.456.789-XX',
];
foreach ($invalidRawFormats as $invalid) {
    $threw = false;
    try {
        $lgpd->normalizeCpf($invalid);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    } catch (\Throwable $e) {
        $threw = false;
    }
    recordTest('LGPD_NORM_EXC', "Throws InvalidArgumentException for invalid length: '{$invalid}'", $threw);
}

// 1.2 CPF Algorithmic Validation
// 1.2.1 Rejection of repeated digits
for ($d = 0; $d <= 9; $d++) {
    $repeated = str_repeat((string)$d, 11);
    $repeatedFormatted = sprintf('%d%d%d.%d%d%d.%d%d%d-%d%d', $d, $d, $d, $d, $d, $d, $d, $d, $d, $d, $d);
    recordTest('LGPD_CPF_REPEATED', "Rejects unformatted {$repeated}", $lgpd->validateCpf($repeated) === false);
    recordTest('LGPD_CPF_REPEATED', "Rejects formatted {$repeatedFormatted}", $lgpd->validateCpf($repeatedFormatted) === false);
}

// Helper to generate mathematically valid CPFs for testing
function generateValidCpf(int $regionDigit = 7): string {
    $digits = [];
    for ($i = 0; $i < 8; $i++) {
        $digits[] = random_int(0, 9);
    }
    $digits[] = $regionDigit; // 9th digit defines fiscal region (7 = ES/RJ)

    // Calculate 1st check digit
    $sum1 = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum1 += $digits[$i] * (10 - $i);
    }
    $d1 = ((10 * $sum1) % 11) % 10;
    $digits[] = $d1;

    // Calculate 2nd check digit
    $sum2 = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum2 += $digits[$i] * (11 - $i);
    }
    $d2 = ((10 * $sum2) % 11) % 10;
    $digits[] = $d2;

    $cpfStr = implode('', $digits);
    // Ensure not all identical
    if (preg_match('/^(\d)\1{10}$/', $cpfStr)) {
        return generateValidCpf($regionDigit);
    }
    return $cpfStr;
}

// 1.2.2 Test 100 generated valid CPFs across all 10 fiscal regions (0 to 9)
$allValidPassed = true;
for ($r = 0; $r <= 9; $r++) {
    for ($k = 0; $k < 10; $k++) {
        $validCpf = generateValidCpf($r);
        $isValid = $lgpd->validateCpf($validCpf);
        if (!$isValid) {
            $allValidPassed = false;
            recordTest('LGPD_CPF_VALID', "Valid CPF in region {$r}: {$validCpf}", false, "Failed validation");
            break 2;
        }
    }
}
if ($allValidPassed) {
    recordTest('LGPD_CPF_VALID', "100 mathematically valid CPFs across all 10 fiscal regions accepted", true);
}

// 1.2.3 Test 100 tampered CPFs (flip 1 digit)
$allTamperedRejected = true;
for ($k = 0; $k < 100; $k++) {
    $validCpf = generateValidCpf(7);
    $arr = str_split($validCpf);
    // Flip check digit or body digit
    $pos = random_int(0, 10);
    $arr[$pos] = (string)(($arr[$pos] + random_int(1, 9)) % 10);
    $tampered = implode('', $arr);
    // Recalculate true validity
    $sum1 = 0; for ($i = 0; $i < 9; $i++) $sum1 += (int)$arr[$i] * (10 - $i);
    $expD1 = ((10 * $sum1) % 11) % 10;
    $sum2 = 0; for ($i = 0; $i < 10; $i++) $sum2 += (int)$arr[$i] * (11 - $i);
    $expD2 = ((10 * $sum2) % 11) % 10;
    $isActuallyValid = ((int)$arr[9] === $expD1 && (int)$arr[10] === $expD2 && !preg_match('/^(\d)\1{10}$/', $tampered));

    $res = $lgpd->validateCpf($tampered);
    if ($res !== $isActuallyValid) {
        $allTamperedRejected = false;
        recordTest('LGPD_CPF_TAMPER', "Tampered CPF {$tampered}", false, "Expected " . ($isActuallyValid ? 'true' : 'false') . ", got " . ($res ? 'true' : 'false'));
        break;
    }
}
if ($allTamperedRejected) {
    recordTest('LGPD_CPF_TAMPER', "100 tampered/mutated CPFs correctly evaluated against check digits", true);
}

// 1.3 Blind Index Determinism & Collision Resistance
$sampleCpf = '529.982.247-25';
$bIndex1 = $lgpd->generateBlindIndex($sampleCpf);
$bIndex2 = $lgpd->generateBlindIndex('52998224725');
$bIndex3 = $lgpd->generateBlindIndex('  529 982 247 25  ');
recordTest('LGPD_BLIND_INDEX', "Blind index is deterministic across formats", ($bIndex1 === $bIndex2) && ($bIndex2 === $bIndex3));
recordTest('LGPD_BLIND_INDEX', "Blind index is valid SHA-256 (64 hex chars)", (bool)preg_match('/^[0-9a-f]{64}$/', $bIndex1));

// Test pepper separation
$lgpdPepperA = new LgpdSecurityService('pepper_alpha_key_1111');
$lgpdPepperB = new LgpdSecurityService('pepper_beta_key_2222');
$hashA = $lgpdPepperA->generateBlindIndex($sampleCpf);
$hashB = $lgpdPepperB->generateBlindIndex($sampleCpf);
recordTest('LGPD_PEPPER_ISO', "Different peppers produce distinct blind index hashes", $hashA !== $hashB);

// Collision resistance across 1,000 distinct CPFs
$hashes = [];
$collisionFound = false;
for ($i = 0; $i < 1000; $i++) {
    $cpf = generateValidCpf($i % 10);
    $h = $lgpd->generateBlindIndex($cpf);
    if (isset($hashes[$h])) {
        $collisionFound = true;
        recordTest('LGPD_COLLISION', "Blind index collision detected for {$cpf}", false);
        break;
    }
    $hashes[$h] = $cpf;
}
if (!$collisionFound) {
    recordTest('LGPD_COLLISION', "0 collisions found across 1,000 distinct generated CPFs (100% collision-resistant)", true);
}

// 1.4 AES-256 Encryption / Decryption Roundtrip Stress Tests
$testPayloads = [
    'Empty string' => '',
    'Simple ASCII' => 'Prontuario Social SEJUS 2026',
    'Portuguese with accents' => 'Acolhimento de egresso em São Mateus/ES; diagnóstico: vulnerabilidade crítica, sem moradia.',
    'Multibyte emojis & symbols' => '🔒 SEJUS/ES - Carteira Digital 🕊️ 🇧🇷 #123456 ✓ Verificado',
    'Control chars & null byte' => "Linha 1\nLinha 2\tTab\0NullByte\rLinha 3",
    'Special characters & XML/SQL' => "<root><egresso id='1'>O'Connor & Sons -- DROP TABLE users; --</egresso></root>",
    '10KB Large Payload' => str_repeat("Evolução psicossocial: Egresso compareceu ao Escritório Social de Vitória. ", 150),
    '100KB Massive Payload' => str_repeat(bin2hex(random_bytes(64)), 800),
];

foreach ($testPayloads as $desc => $plain) {
    $enc = $lgpd->encryptField($plain);
    if ($plain === '') {
        recordTest('LGPD_AES_ROUNDTRIP', "AES-256 handles '{$desc}' by returning null", $enc === null);
        continue;
    }
    recordTest('LGPD_AES_CIPHERTEXT', "Ciphertext for '{$desc}' hides plaintext", $enc !== null && !str_contains($enc, substr($plain, 0, min(10, strlen($plain)))));
    $dec = $lgpd->decryptField($enc);
    recordTest('LGPD_AES_ROUNDTRIP', "AES-256 roundtrip exact match for '{$desc}'", $dec === $plain);
}

// Handling of Null
recordTest('LGPD_AES_NULL', "encryptField(null) returns null", $lgpd->encryptField(null) === null);
recordTest('LGPD_AES_NULL', "decryptField(null) returns null", $lgpd->decryptField(null) === null);

// Decryption of corrupted ciphertexts (MUST NOT CRASH OR THROW UNCAUGHT FATAL ERRORS)
$corruptedInputs = [
    'Invalid prefix' => 'not_a_valid_cipher_prefix_12345',
    'Corrupted raw_aes base64' => 'raw_aes:@@@not_valid_base64@@@',
    'Truncated IV raw_aes' => 'raw_aes:' . base64_encode('short_iv'),
    'Tampered ciphertext payload' => 'raw_aes:' . base64_encode(random_bytes(48)),
    'Random binary string' => random_bytes(64),
    'Empty raw_aes' => 'raw_aes:',
];

foreach ($corruptedInputs as $desc => $corrupt) {
    try {
        $result = $lgpd->decryptField($corrupt);
        recordTest('LGPD_CORRUPT_CIPHER', "Corrupted ciphertext '{$desc}' safely handled without fatal crash", $result === null || is_string($result));
    } catch (\Throwable $e) {
        recordTest('LGPD_CORRUPT_CIPHER', "Corrupted ciphertext '{$desc}' threw uncaught exception", false, $e->getMessage());
    }
}

// 1.5 Masking Stress Tests
recordTest('LGPD_MASK_CPF', "Masks valid CPF 192.830.456-78", $lgpd->maskCpf('192.830.456-78') === '***.830.456-**');
recordTest('LGPD_MASK_CPF', "Masks unformatted CPF 19283045678", $lgpd->maskCpf('19283045678') === '***.830.456-**');
recordTest('LGPD_MASK_CPF', "Masks null CPF", $lgpd->maskCpf(null) === '***.***.***-**');
recordTest('LGPD_MASK_CPF', "Masks empty CPF", $lgpd->maskCpf('') === '***.***.***-**');
recordTest('LGPD_MASK_CPF', "Masks invalid length CPF", $lgpd->maskCpf('123') === '***.***.***-**');

recordTest('LGPD_MASK_NAME', "Masks 3-part name 'Lucas Silva Santos'", $lgpd->maskName('Lucas Silva Santos') === 'Lucas S. Santos');
recordTest('LGPD_MASK_NAME', "Masks multi-part name 'Ana Carolina dos Santos Pereira de Souza'", $lgpd->maskName('Ana Carolina dos Santos Pereira de Souza') === 'Ana C. d. S. P. d. Souza');
recordTest('LGPD_MASK_NAME', "Preserves single name 'Maria'", $lgpd->maskName('Maria') === 'Maria');

// Note empirical observation on 2-part name behavior
$masked2Part = $lgpd->maskName('João Silva');
$hasDoubleSpaceBug = ($masked2Part === 'João  Silva');
recordTest('LGPD_MASK_NAME', "Evaluates 2-part name 'João Silva' (Observing single space vs double space: '{$masked2Part}')", $masked2Part === 'João Silva', "Observed double space: '{$masked2Part}'");
recordTest('LGPD_MASK_NAME', "Masks null name", $lgpd->maskName(null) === '***');
recordTest('LGPD_MASK_NAME', "Masks empty name", $lgpd->maskName('') === '***');

echo "\n";

// ============================================================================
// SECTION 2: AuditService HASH CHAINING & FORENSIC TAMPER STRESS SUITE
// ============================================================================
echo ">>> SECTION 2: AuditService Forensic Hash Chaining & Tamper Detection\n";

$audit = new AuditService();

// 2.1 Genesis Hash Invariant
recordTest('AUDIT_GENESIS', "Genesis hash is exactly 64 zeros", AuditService::GENESIS_HASH === str_repeat('0', 64));

// 2.2 Canonical Details Sorting & JSON normalization
$detailsA = ['motivo' => 'Consulta', 'setor' => 'Social', 'urgencia' => 'Alta'];
$detailsB = ['urgencia' => 'Alta', 'motivo' => 'Consulta', 'setor' => 'Social'];
$hA = $audit->calculateRecordHash(AuditService::GENESIS_HASH, 1, 10, 'EVOLUCAO', '127.0.0.1', '2026-08-17T12:00:00Z', $detailsA);
$hB = $audit->calculateRecordHash(AuditService::GENESIS_HASH, 1, 10, 'EVOLUCAO', '127.0.0.1', '2026-08-17T12:00:00Z', $detailsB);
recordTest('AUDIT_CANONICAL', "Key order in details array produces identical canonical hash", $hA === $hB);

// 2.3 Build a 10-Event Sequential Hash Chain
$chain = [];
$currentPrev = AuditService::GENESIS_HASH;

$actions = ['CREATE', 'ATENDIMENTO', 'ENCAMINHAMENTO', 'CURSO_MATRICULA', 'VAGA_CANDIDATURA', 'EMISSAO_CARTEIRA', 'VALIDACAO_QR', 'ATUALIZACAO_CADASTRO', 'LAUDO_SOCIAL', 'ARQUIVAMENTO'];

for ($i = 0; $i < 10; $i++) {
    $timestamp = sprintf('2026-08-17T%02d:00:00+00:00', 10 + $i);
    $prontuarioId = ($i % 3 === 0) ? null : ($i + 100);
    $userId = ($i % 2 === 0) ? ($i + 1) : null;
    $acao = $actions[$i];
    $ip = sprintf('192.168.1.%d', 10 + $i);
    $details = ['step' => $i, 'action_name' => $acao, 'nonce' => bin2hex(random_bytes(8))];

    $hash = $audit->calculateRecordHash($currentPrev, $prontuarioId, $userId, $acao, $ip, $timestamp, $details);

    $chain[] = [
        'id' => $i + 1,
        'prontuario_id' => $prontuarioId,
        'user_id' => $userId,
        'acao' => $acao,
        'ip_address' => $ip,
        'timestamp' => $timestamp,
        'details' => $details,
        'previous_hash' => $currentPrev,
        'current_hash' => $hash,
    ];

    $currentPrev = $hash;
}

recordTest('AUDIT_CHAIN_BUILD', "10-event hash chain constructed with sequential cryptographic links", count($chain) === 10);

// Chain Verification Function implementing AuditService forensics logic
function verifyChainMemory(AuditService $service, array $logs): array {
    if (empty($logs)) {
        return ['valid' => true, 'total_verified' => 0, 'latest_hash' => AuditService::GENESIS_HASH];
    }

    $expectedPrev = AuditService::GENESIS_HASH;
    $verifiedCount = 0;

    foreach ($logs as $index => $log) {
        // Check previous_hash link
        if ($log['previous_hash'] !== $expectedPrev) {
            return [
                'valid' => false,
                'broken_record_id' => $log['id'],
                'broken_index' => $index,
                'reason' => "Chain break at #{$log['id']}: previous_hash [{$log['previous_hash']}] != expected [{$expectedPrev}]",
                'verified_count' => $verifiedCount,
            ];
        }

        // Recalculate hash
        $calcHash = $service->calculateRecordHash(
            $log['previous_hash'],
            $log['prontuario_id'],
            $log['user_id'],
            $log['acao'],
            $log['ip_address'],
            $log['timestamp'],
            $log['details'] ?? []
        );

        if (!hash_equals($log['current_hash'], $calcHash)) {
            return [
                'valid' => false,
                'broken_record_id' => $log['id'],
                'broken_index' => $index,
                'reason' => "Tampering at #{$log['id']}: stored [{$log['current_hash']}] != calculated [{$calcHash}]",
                'verified_count' => $verifiedCount,
            ];
        }

        $expectedPrev = $log['current_hash'];
        $verifiedCount++;
    }

    return [
        'valid' => true,
        'total_verified' => $verifiedCount,
        'latest_hash' => $expectedPrev,
    ];
}

// 2.4 Verify Intact Chain
$intactCheck = verifyChainMemory($audit, $chain);
recordTest('AUDIT_INTACT_CHAIN', "Intact 10-event chain verified with 100% integrity", $intactCheck['valid'] === true && $intactCheck['total_verified'] === 10);

// 2.5 Adversarial Tampering Matrix on Chain
// 2.5.1 Mutate payload in Block #5 (middle)
$tamperedChainPayload = $chain;
$tamperedChainPayload[4]['details']['action_name'] = 'UNAUTHORIZED_MUTATION';
$checkPayload = verifyChainMemory($audit, $tamperedChainPayload);
recordTest('AUDIT_TAMPER_PAYLOAD', "Detects payload tampering in Block #5 and locates broken record #5", $checkPayload['valid'] === false && $checkPayload['broken_record_id'] === 5 && $checkPayload['verified_count'] === 4);

// 2.5.2 Mutate timestamp in Block #5
$tamperedChainTime = $chain;
$tamperedChainTime[4]['timestamp'] = '2026-08-17T14:59:59+00:00';
$checkTime = verifyChainMemory($audit, $tamperedChainTime);
recordTest('AUDIT_TAMPER_TIMESTAMP', "Detects timestamp tampering in Block #5 and locates broken record #5", $checkTime['valid'] === false && $checkTime['broken_record_id'] === 5);

// 2.5.3 Mutate user_id in Block #5
$tamperedChainUser = $chain;
$tamperedChainUser[4]['user_id'] = 99999;
$checkUser = verifyChainMemory($audit, $tamperedChainUser);
recordTest('AUDIT_TAMPER_USER', "Detects user_id tampering in Block #5 and locates broken record #5", $checkUser['valid'] === false && $checkUser['broken_record_id'] === 5);

// 2.5.4 Mutate acao in Block #5
$tamperedChainAcao = $chain;
$tamperedChainAcao[4]['acao'] = 'DROP_PRONTUARIO';
$checkAcao = verifyChainMemory($audit, $tamperedChainAcao);
recordTest('AUDIT_TAMPER_ACTION', "Detects action tampering in Block #5 and locates broken record #5", $checkAcao['valid'] === false && $checkAcao['broken_record_id'] === 5);

// 2.5.5 Mutate ip_address in Block #5
$tamperedChainIp = $chain;
$tamperedChainIp[4]['ip_address'] = '10.99.99.99';
$checkIp = verifyChainMemory($audit, $tamperedChainIp);
recordTest('AUDIT_TAMPER_IP', "Detects IP address tampering in Block #5 and locates broken record #5", $checkIp['valid'] === false && $checkIp['broken_record_id'] === 5);

// 2.5.6 Mutate previous_hash in Block #5
$tamperedChainPrev = $chain;
$tamperedChainPrev[4]['previous_hash'] = hash('sha256', 'fake_prev_hash');
$checkPrev = verifyChainMemory($audit, $tamperedChainPrev);
recordTest('AUDIT_TAMPER_PREV_HASH', "Detects broken previous_hash link at Block #5", $checkPrev['valid'] === false && $checkPrev['broken_record_id'] === 5);

// 2.5.7 Mutate Genesis Hash in Block #1
$tamperedGenesis = $chain;
$tamperedGenesis[0]['previous_hash'] = '1111111111111111111111111111111111111111111111111111111111111111';
$checkGenesis = verifyChainMemory($audit, $tamperedGenesis);
recordTest('AUDIT_TAMPER_GENESIS', "Detects mutated genesis hash at Block #1 with verified_count = 0", $checkGenesis['valid'] === false && $checkGenesis['broken_record_id'] === 1 && $checkGenesis['verified_count'] === 0);

// 2.5.8 Block Splicing / Deletion Attack (Delete Block #4)
$splicedChain = $chain;
unset($splicedChain[3]); // delete block 4 (index 3)
$splicedChain = array_values($splicedChain);
$checkSpliced = verifyChainMemory($audit, $splicedChain);
recordTest('AUDIT_BLOCK_DELETION', "Detects block deletion / splicing attack at Block #5", $checkSpliced['valid'] === false && $checkSpliced['broken_record_id'] === 5);

echo "\n";

// ============================================================================
// SECTION 3: QrCodeSecurityService CRYPTOGRAPHIC ADVERSARIAL STRESS SUITE
// ============================================================================
echo ">>> SECTION 3: QrCodeSecurityService Adversarial Stress Testing\n";

$signingKey = 'sejus_signing_master_key_' . bin2hex(random_bytes(16));
$qr = new QrCodeSecurityService($lgpd, $signingKey);

// 3.1 Genuine Token Generation & Verification
$genuinePayload = [
    'doc_id' => '1042',
    'registro_sejus' => 'ES-2026-001042',
    'cpf_masked' => '***.830.456-**',
    'nome' => 'LUCAS DA SILVA SANTOS',
    'municipio' => 'Vitória',
    'issued_at' => (new \DateTimeImmutable())->format('c'),
    'expires_at' => (new \DateTimeImmutable('+1 year'))->format('c'),
    'legal_basis' => 'Lei Complementar Estadual nº 182/2021 - SEJUS/ES',
];

$genuineToken = $qr->generateToken($genuinePayload);
recordTest('QR_TOKEN_GEN', "Generated token is URL-safe string", !empty($genuineToken) && !str_contains($genuineToken, '+') && !str_contains($genuineToken, '/') && !str_contains($genuineToken, '='));

$verGenuine = $qr->verifyToken($genuineToken);
recordTest('QR_VERIFY_GENUINE', "Genuine token passes verification with VALID_DOCUMENT", $verGenuine['valid'] === true && $verGenuine['status'] === 'VALID_DOCUMENT');
recordTest('QR_RESTORE_PAYLOAD', "Payload restored exactly from genuine token envelope", ($verGenuine['payload']['nome'] ?? '') === 'LUCAS DA SILVA SANTOS');

// 3.2 Adversarial Payload Tampering Attacks (HMAC signature retained but payload modified)
$tamperAttacks = [
    'Modify Name' => ['nome' => 'FRAUDULENTO DA SILVA'],
    'Modify CPF' => ['cpf_masked' => '***.999.888-**'],
    'Modify Doc ID' => ['doc_id' => '9999'],
    'Modify Registro SEJUS' => ['registro_sejus' => 'ES-2026-999999'],
    'Modify Municipality' => ['municipio' => 'Outro Estado'],
    'Extend Expiration by 10 Years' => ['expires_at' => (new \DateTimeImmutable('+10 years'))->format('c')],
    'Modify Legal Basis' => ['legal_basis' => 'Decreto Fake 999/2026'],
    'Inject Extra Admin Field' => ['is_admin' => true],
];

$sigGenuine = $qr->signPayload($genuinePayload);

foreach ($tamperAttacks as $attackName => $mutations) {
    $mutatedPayload = array_merge($genuinePayload, $mutations);
    $envelope = ['p' => $mutatedPayload, 's' => $sigGenuine];
    $tamperedToken = rtrim(strtr(base64_encode(json_encode($envelope)), '+/', '-_'), '=');

    $res = $qr->verifyToken($tamperedToken);
    recordTest('QR_ATTACK_PAYLOAD', "Rejects payload tampering [{$attackName}] with TAMPERED_DOCUMENT", $res['valid'] === false && $res['status'] === 'TAMPERED_DOCUMENT');
}

// 3.3 Adversarial Signature Forgery & Tampering Attacks
$sigAttacks = [
    'Flipped 1 char in signature' => substr_replace($sigGenuine, $sigGenuine[0] === 'a' ? 'b' : 'a', 0, 1),
    'Truncated signature (32 hex chars)' => substr($sigGenuine, 0, 32),
    'All zeros signature' => str_repeat('0', 64),
    'Random hex signature' => bin2hex(random_bytes(32)),
    'Empty signature' => '',
    'Signature forged with different key' => (new QrCodeSecurityService($lgpd, 'attacker_secret_key'))->signPayload($genuinePayload),
];

foreach ($sigAttacks as $sigAttackName => $forgedSig) {
    $envelope = ['p' => $genuinePayload, 's' => $forgedSig];
    $forgedToken = rtrim(strtr(base64_encode(json_encode($envelope)), '+/', '-_'), '=');

    $res = $qr->verifyToken($forgedToken);
    recordTest('QR_ATTACK_SIG', "Rejects signature tampering [{$sigAttackName}] with TAMPERED_DOCUMENT", $res['valid'] === false && $res['status'] === 'TAMPERED_DOCUMENT');
}

// 3.4 Temporal Validity & Expiration Edge Cases
// 3.4.1 Expired 1 second ago
$expired1SecPayload = array_merge($genuinePayload, [
    'issued_at' => (new \DateTimeImmutable('-1 year - 1 second'))->format('c'),
    'expires_at' => (new \DateTimeImmutable('-1 second'))->format('c'),
]);
$expired1SecToken = $qr->generateToken($expired1SecPayload);
$resExp1Sec = $qr->verifyToken($expired1SecToken);
recordTest('QR_EXPIRY_1SEC', "Rejects token expired 1 second ago with EXPIRED_DOCUMENT", $resExp1Sec['valid'] === false && $resExp1Sec['status'] === 'EXPIRED_DOCUMENT');

// 3.4.2 Expired 2 years ago
$expired2YrPayload = array_merge($genuinePayload, [
    'issued_at' => (new \DateTimeImmutable('-3 years'))->format('c'),
    'expires_at' => (new \DateTimeImmutable('-2 years'))->format('c'),
]);
$expired2YrToken = $qr->generateToken($expired2YrPayload);
$resExp2Yr = $qr->verifyToken($expired2YrToken);
recordTest('QR_EXPIRY_2YR', "Rejects token expired 2 years ago with EXPIRED_DOCUMENT", $resExp2Yr['valid'] === false && $resExp2Yr['status'] === 'EXPIRED_DOCUMENT');

// 3.4.3 Active Token valid for 1 hour
$active1HrPayload = array_merge($genuinePayload, [
    'issued_at' => (new \DateTimeImmutable('-1 day'))->format('c'),
    'expires_at' => (new \DateTimeImmutable('+1 hour'))->format('c'),
]);
$active1HrToken = $qr->generateToken($active1HrPayload);
$resAct1Hr = $qr->verifyToken($active1HrToken);
recordTest('QR_ACTIVE_1HR', "Accepts active token valid for 1 hour with VALID_DOCUMENT", $resAct1Hr['valid'] === true && $resAct1Hr['status'] === 'VALID_DOCUMENT');

// 3.5 Malformed Tokens & Fuzzing / Injection Payloads
$malformedTokens = [
    'Garbage string' => 'not-a-valid-token-string!!!',
    'Invalid Base64 characters' => '***###@@@%%%',
    'Empty token' => '',
    'JSON without envelope keys' => rtrim(strtr(base64_encode(json_encode(['hello' => 'world'])), '+/', '-_'), '='),
    'Missing signature key in envelope' => rtrim(strtr(base64_encode(json_encode(['p' => $genuinePayload])), '+/', '-_'), '='),
    'Missing payload key in envelope' => rtrim(strtr(base64_encode(json_encode(['s' => $sigGenuine])), '+/', '-_'), '='),
    'Payload is string instead of array' => rtrim(strtr(base64_encode(json_encode(['p' => 'string_not_array', 's' => $sigGenuine])), '+/', '-_'), '='),
    'Null envelope' => rtrim(strtr(base64_encode('null'), '+/', '-_'), '='),
    'SQL Injection in token' => "' OR '1'='1; --",
    'XSS Script in token' => "<script>alert('xss')</script>",
];

foreach ($malformedTokens as $fuzzName => $fuzzToken) {
    try {
        $res = $qr->verifyToken($fuzzToken);
        recordTest('QR_FUZZ_INJECTION', "Fuzz/Injection test [{$fuzzName}] returns invalid safely", $res['valid'] === false);
    } catch (\Throwable $e) {
        recordTest('QR_FUZZ_INJECTION', "Fuzz/Injection test [{$fuzzName}] threw uncaught exception", false, $e->getMessage());
    }
}

// 3.6 XSS and SQL Injection in Egresso Metadata
$xssPayload = array_merge($genuinePayload, [
    'nome' => "<script>alert('xss')</script> ROBERTO",
    'municipio' => "Vitória'; DROP TABLE users; --",
]);
$xssToken = $qr->generateToken($xssPayload);
$verXss = $qr->verifyToken($xssToken);
recordTest('QR_XSS_METADATA', "XSS/SQL injection payload signed and verified without code execution", $verXss['valid'] === true && $verXss['payload']['nome'] === "<script>alert('xss')</script> ROBERTO");

// 3.7 Timing Attack Resilience Verification
// Inspect source code of QrCodeSecurityService for hash_equals usage
$qrClassCode = file_get_contents(dirname(__DIR__) . '/app/Services/QrCodeSecurityService.php');
$usesHashEquals = str_contains($qrClassCode, 'hash_equals(');
recordTest('QR_TIMING_ATTACK', "QrCodeSecurityService explicitly uses constant-time hash_equals()", $usesHashEquals);

// 3.8 QR Code SVG & Data-URI Rendering
$sampleSvg = $qr->generateQrCodeSvg('https://conectaegresso.es.gov.br/validar-carteira/test123');
recordTest('QR_SVG_RENDER', "generateQrCodeSvg renders valid SVG structure", str_contains($sampleSvg, '<svg') && str_contains($sampleSvg, '</svg>'));

$sampleDataUri = $qr->generateQrCodeDataUri('https://conectaegresso.es.gov.br/validar-carteira/test123');
recordTest('QR_DATA_URI', "generateQrCodeDataUri generates valid RFC 2397 Data-URI", str_starts_with($sampleDataUri, 'data:image/svg+xml;base64,') && strlen($sampleDataUri) > 50);

$validationUrl = $qr->getValidationUrl($genuineToken);
recordTest('QR_VAL_URL', "getValidationUrl builds absolute URL with token", str_starts_with($validationUrl, 'https://conectaegresso.es.gov.br/validar-carteira/') && str_ends_with($validationUrl, $genuineToken));

echo "\n";
echo "===============================================================================\n";
echo "STRESS HARNESS RESULTS SUMMARY\n";
echo "===============================================================================\n";
echo "Total Assertions: {$totalTests}\n";
echo "Total Passed:     {$totalPassed} (" . round(($totalPassed / max(1, $totalTests)) * 100, 2) . "%)\n";
echo "Total Failed:     {$totalFailed}\n";
echo "===============================================================================\n";

if ($totalFailed === 0) {
    echo "\n>>> VERDICT: APPROVE - ALL ADVERSARIAL STRESS TESTS PASSED WITH 100% FIDELITY <<<\n\n";
    exit(0);
} else {
    echo "\n>>> VERDICT: REQUEST_CHANGES - FAILURES DETECTED: <<<\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    echo "\n";
    exit(1);
}
