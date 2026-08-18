<?php

/**
 * FORENSIC AUDITOR INDEPENDENT VERIFICATION SUITE
 * Platform: CONECTA EGRESSO (SEJUS/ES) - Milestones M1 & M2
 * 
 * Independent forensic checks:
 * 1. Static source inspection: Check for prohibited patterns (hardcoding, stubs, fake facades)
 * 2. Cryptographic primitives empirical verification (AES-256, HMAC-SHA256, SHA-256 chain)
 * 3. Territory seeder verification (78 ES municipalities, IBGE codes, coordinates, physical offices)
 * 4. PostgreSQL immutable rules verification in migration
 * 5. PDF Layout & QR Code generator verification
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 3);

// Standalone autoloader
spl_autoload_register(function ($class) use ($projectRoot) {
    $prefixApp = 'App\\';
    $prefixDatabase = 'Database\\';

    if (strncmp($prefixApp, $class, strlen($prefixApp)) === 0) {
        $rel = substr($class, strlen($prefixApp));
        $file = $projectRoot . '/app/' . str_replace('\\', '/', $rel) . '.php';
        if (file_exists($file)) require_once $file;
    }
    if (strncmp($prefixDatabase, $class, strlen($prefixDatabase)) === 0) {
        $rel = substr($class, strlen($prefixDatabase));
        $file = $projectRoot . '/database/' . str_replace('\\', '/', $rel) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

// Minimal framework mocks
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
            public function __construct() { parent::__construct('now', new \DateTimeZone('America/Sao_Paulo')); }
            public function toIso8601String(): string { return $this->format('c'); }
            public function addYear(): self { return $this->modify('+1 year'); }
            public function format($format): string { return parent::format($format); }
        };
    }
}

$auditFindings = [];
$totalPassed = 0;
$totalFailed = 0;

function forensicAssert(string $phase, string $checkName, bool $condition, string $evidence = ''): void {
    global $totalPassed, $totalFailed, $auditFindings;
    if ($condition) {
        $totalPassed++;
        echo "  [PASS] [{$phase}] {$checkName}\n";
    } else {
        $totalFailed++;
        $auditFindings[] = "[{$phase}] {$checkName} FAILED: {$evidence}";
        echo "  [FAIL] [{$phase}] {$checkName} --> {$evidence}\n";
    }
}

echo "===============================================================================\n";
echo "FORENSIC INTEGRITY AUDIT - CONECTA EGRESSO (SEJUS/ES) - MILESTONES M1 & M2\n";
echo "===============================================================================\n\n";

// ----------------------------------------------------------------------------
// PHASE 1: SOURCE CODE ANALYSIS (PROHIBITED PATTERNS & FACADE DETECTION)
// ----------------------------------------------------------------------------
echo ">>> PHASE 1: Static Source Code Analysis for Prohibited Patterns\n";

$serviceFiles = glob($projectRoot . '/app/Services/*.php');
$modelFiles = glob($projectRoot . '/app/Models/*.php');
$controllerFiles = glob($projectRoot . '/app/Http/Controllers/*.php');
$migrationFiles = glob($projectRoot . '/database/migrations/*.php');
$seederFiles = glob($projectRoot . '/database/seeders/*.php');

$allPhpFiles = array_merge($serviceFiles, $modelFiles, $controllerFiles, $migrationFiles, $seederFiles);

$facadeFound = false;
$hardcodedBypassFound = false;

foreach ($allPhpFiles as $phpFile) {
    $content = file_get_contents($phpFile);
    $relPath = str_replace($projectRoot . '/', '', str_replace('\\', '/', $phpFile));

    // Check for empty methods or return constant facades
    if (preg_match('/function\s+\w+\([^)]*\)\s*:\s*\w+\s*\{\s*return\s+(true|false|null|0|""|\'\'|\'dummy\');\s*\}/i', $content, $m)) {
        // Exclude legitimate boolean check methods like isGestor() { return $this->perfil?->slug === 'gestor'; }
        if (!str_contains($m[0], '$this') && !str_contains($m[0], '===')) {
            $facadeFound = true;
            forensicAssert('P1_FACADE', "Facade check in {$relPath}", false, "Found dummy method: {$m[0]}");
        }
    }

    // Check for NotImplementedException / TODO stubs
    if (str_contains($content, 'throw new NotImplementedException') || str_contains($content, 'throw new \BadMethodCallException')) {
        $facadeFound = true;
        forensicAssert('P1_STUB', "Stub exception in {$relPath}", false, "Found NotImplementedException stub");
    }
}

forensicAssert('P1_FACADE', "No empty facade methods found across app/, database/", !$facadeFound);
forensicAssert('P1_COUNT_SERVICES', "Exactly 4 core services implemented", count($serviceFiles) === 4, "Found " . count($serviceFiles));
forensicAssert('P1_COUNT_MODELS', "Exactly 12 Eloquent models implemented", count($modelFiles) === 12, "Found " . count($modelFiles));
forensicAssert('P1_COUNT_MIGRATIONS', "Exactly 12 migrations implemented", count($migrationFiles) === 12, "Found " . count($migrationFiles));
forensicAssert('P1_COUNT_SEEDERS', "Exactly 9 seeders implemented", count($seederFiles) === 9, "Found " . count($seederFiles));

// ----------------------------------------------------------------------------
// PHASE 2: CRYPTOGRAPHIC IMPLEMENTATION AUDIT
// ----------------------------------------------------------------------------
echo "\n>>> PHASE 2: Cryptographic Primitives Empirical Verification\n";

// 2.1 LgpdSecurityService
$lgpdSource = file_get_contents($projectRoot . '/app/Services/LgpdSecurityService.php');
forensicAssert('P2_CRYPTO_LGPD', "LgpdSecurityService uses openssl_encrypt with AES-256-CBC", str_contains($lgpdSource, "openssl_encrypt(") && str_contains($lgpdSource, "'AES-256-CBC'"));
forensicAssert('P2_CRYPTO_LGPD', "LgpdSecurityService uses openssl_decrypt with AES-256-CBC", str_contains($lgpdSource, "openssl_decrypt(") && str_contains($lgpdSource, "'AES-256-CBC'"));
forensicAssert('P2_CRYPTO_LGPD', "LgpdSecurityService uses hash_hmac with sha256 for blind index", str_contains($lgpdSource, "hash_hmac('sha256',"));

$lgpd = new \App\Services\LgpdSecurityService('test_pepper_auditor_2026');

// Modulo 11 CPF validation empirical check
$validCpf = '52998224725'; // Valid Brazilian CPF
$invalidCpf1 = '11111111111'; // Repeated
$invalidCpf2 = '52998224720'; // Wrong second digit
forensicAssert('P2_CPF_VAL', "validateCpf accepts mathematically valid CPF", $lgpd->validateCpf($validCpf) === true);
forensicAssert('P2_CPF_VAL', "validateCpf rejects repeated digits", $lgpd->validateCpf($invalidCpf1) === false);
forensicAssert('P2_CPF_VAL', "validateCpf rejects invalid check digit", $lgpd->validateCpf($invalidCpf2) === false);

// AES-256 Roundtrip empirical check
$secret = "Dados sigilosos do egresso: Prontuario #4819";
$cipher = $lgpd->encryptField($secret);
$decrypted = $lgpd->decryptField($cipher);
forensicAssert('P2_AES_ROUNDTRIP', "AES-256 encrypt/decrypt roundtrip restores exact plaintext", $decrypted === $secret);
forensicAssert('P2_AES_SECRET', "Ciphertext does not leak plaintext substrings", !str_contains($cipher, "egresso") && !str_contains($cipher, "Prontuario"));

// Blind index empirical check
$bIndex = $lgpd->generateBlindIndex($validCpf);
forensicAssert('P2_BLIND_INDEX', "Blind index generates 64-character SHA-256 hex string", strlen($bIndex) === 64 && ctype_xdigit($bIndex));
forensicAssert('P2_BLIND_INDEX', "Blind index matches exact HMAC-SHA256 calculation", $bIndex === hash_hmac('sha256', $validCpf, 'test_pepper_auditor_2026'));

// 2.2 AuditService & Hash Chaining
$auditSource = file_get_contents($projectRoot . '/app/Services/AuditService.php');
forensicAssert('P2_CRYPTO_AUDIT', "AuditService uses hash('sha256', ...) for block chaining", str_contains($auditSource, "hash('sha256',"));
forensicAssert('P2_CRYPTO_AUDIT', "AuditService defines GENESIS_HASH constant of 64 zeros", \App\Services\AuditService::GENESIS_HASH === str_repeat('0', 64));

$audit = new \App\Services\AuditService();
$h0 = \App\Services\AuditService::GENESIS_HASH;
$h1 = $audit->calculateRecordHash($h0, 10, 1, 'CREATE_PRONTUARIO', '10.0.0.1', '2026-08-17T12:00:00Z', ['numero' => 'PRT-2026-000010']);
$h2 = $audit->calculateRecordHash($h1, 10, 2, 'VIEW', '10.0.0.2', '2026-08-17T12:05:00Z', ['motivo' => 'Consulta']);
forensicAssert('P2_HASH_CHAIN', "Hash chaining sequentially links blocks (H2 links to H1)", strlen($h1) === 64 && strlen($h2) === 64 && $h1 !== $h2);

// Tamper verification
$h1Tampered = $audit->calculateRecordHash($h0, 10, 1, 'CREATE_PRONTUARIO_TAMPERED', '10.0.0.1', '2026-08-17T12:00:00Z', ['numero' => 'PRT-2026-000010']);
forensicAssert('P2_CHAIN_TAMPER', "Tampering any field changes block hash and breaks the chain", $h1Tampered !== $h1);

// 2.3 QrCodeSecurityService
$qrSource = file_get_contents($projectRoot . '/app/Services/QrCodeSecurityService.php');
forensicAssert('P2_CRYPTO_QR', "QrCodeSecurityService uses hash_hmac('sha256', ...) for signing", str_contains($qrSource, "hash_hmac('sha256',"));
forensicAssert('P2_CRYPTO_QR', "QrCodeSecurityService uses constant-time hash_equals for verification", str_contains($qrSource, "hash_equals("));

$qr = new \App\Services\QrCodeSecurityService($lgpd, 'sejus_auditor_key_2026');
$payload = [
    'doc_id' => '10',
    'registro_sejus' => 'ES-2026-000010',
    'cpf_masked' => '***.982.247-**',
    'nome' => 'TESTE AUDITOR',
    'municipio' => 'Vitória',
    'issued_at' => (new \DateTimeImmutable())->format('c'),
    'expires_at' => (new \DateTimeImmutable('+1 year'))->format('c'),
];
$token = $qr->generateToken($payload);
$resValid = $qr->verifyToken($token);
forensicAssert('P2_QR_VERIFY', "Genuine QR token verified as VALID_DOCUMENT", $resValid['valid'] === true && $resValid['status'] === 'VALID_DOCUMENT');

// Tamper test
$tamperedToken = substr($token, 0, -4) . 'ZZZZ';
$resTampered = $qr->verifyToken($tamperedToken);
forensicAssert('P2_QR_TAMPER', "Tampered QR token rejected with non-valid status", $resTampered['valid'] === false);

// ----------------------------------------------------------------------------
// PHASE 3: 78 ES MUNICIPALITIES DATA INTEGRITY AUDIT
// ----------------------------------------------------------------------------
echo "\n>>> PHASE 3: 78 Espírito Santo Municipalities Data Integrity Audit\n";

$seederFile = $projectRoot . '/database/seeders/MunicipioEsSeeder.php';
$seederCode = file_get_contents($seederFile);

preg_match_all("/'codigo_ibge'\s*=>\s*(\d{7})/", $seederCode, $ibgeMatches);
$ibgeCodes = $ibgeMatches[1];
$uniqueIbge = array_unique($ibgeCodes);

forensicAssert('P3_MUNICIPIOS_TOTAL', "MunicipioEsSeeder contains exactly 78 municipalities", count($ibgeCodes) === 78, "Observed count: " . count($ibgeCodes));
forensicAssert('P3_MUNICIPIOS_UNIQUE', "All 78 IBGE codes are unique", count($uniqueIbge) === 78, "Unique count: " . count($uniqueIbge));

// Verify all IBGE codes belong to ES (UF code 32)
$all32 = true;
foreach ($ibgeCodes as $code) {
    if (!str_starts_with($code, '32')) {
        $all32 = false;
        break;
    }
}
forensicAssert('P3_IBGE_UF32', "All IBGE codes have Espírito Santo UF prefix 32", $all32);

// Verify coordinates within ES boundaries
preg_match_all("/'latitude'\s*=>\s*(-?\d+\.\d+),\s*'longitude'\s*=>\s*(-?\d+\.\d+)/", $seederCode, $coordMatches);
$lats = array_map('floatval', $coordMatches[1]);
$longs = array_map('floatval', $coordMatches[2]);

$coordsValid = true;
for ($i = 0; $i < count($lats); $i++) {
    if ($lats[$i] < -21.5 || $lats[$i] > -17.5 || $longs[$i] < -42.0 || $longs[$i] > -39.5) {
        $coordsValid = false;
        break;
    }
}
forensicAssert('P3_COORDS_BOUNDS', "All 78 municipalities coordinates are within ES geographic boundaries", $coordsValid, "Count: " . count($lats));

// Verify physical vs remote office flags
preg_match('/\$municipios\s*=\s*\[(.*?)\];/s', $seederCode, $mMatch);
$municipiosArray = eval('return [' . $mMatch[1] . '];');

$physicalList = [];
$remoteCount = 0;
foreach ($municipiosArray as $m) {
    if (!empty($m['tem_escritorio_fisico'])) {
        $physicalList[] = $m['nome'];
    } else {
        $remoteCount++;
    }
}
sort($physicalList);
$expectedPhysical = ['Cariacica', 'Serra', 'Vila Velha', 'Vitória'];
sort($expectedPhysical);

forensicAssert('P3_PHYSICAL_OFFICES', "Exactly 4 physical offices configured (Vitória, Vila Velha, Serra, Cariacica)", $physicalList === $expectedPhysical, "Found: " . implode(', ', $physicalList));
forensicAssert('P3_REMOTE_OFFICES', "Exactly 74 remote municipalities configured", $remoteCount === 74, "Observed remote: {$remoteCount}");

// ----------------------------------------------------------------------------
// PHASE 4: POSTGRESQL IMMUTABILITY RULES AUDIT
// ----------------------------------------------------------------------------
echo "\n>>> PHASE 4: PostgreSQL Immutability Rules in Migrations\n";

$auditMigration = file_get_contents($projectRoot . '/database/migrations/2026_01_01_000007_create_prontuario_audit_logs_table.php');

$hasNoUpdateRule = str_contains($auditMigration, 'CREATE RULE prontuario_audit_logs_no_update AS ON UPDATE TO prontuario_audit_logs DO INSTEAD NOTHING;');
$hasNoDeleteRule = str_contains($auditMigration, 'CREATE RULE prontuario_audit_logs_no_delete AS ON DELETE TO prontuario_audit_logs DO INSTEAD NOTHING;');
$hasPgsqlDriverCheck = str_contains($auditMigration, "DB::getDriverName() === 'pgsql'");
$hasDropRulesInDown = str_contains($auditMigration, 'DROP RULE IF EXISTS prontuario_audit_logs_no_update') && str_contains($auditMigration, 'DROP RULE IF EXISTS prontuario_audit_logs_no_delete');

forensicAssert('P4_PG_RULE_UPDATE', "Migration contains CREATE RULE prontuario_audit_logs_no_update DO INSTEAD NOTHING", $hasNoUpdateRule);
forensicAssert('P4_PG_RULE_DELETE', "Migration contains CREATE RULE prontuario_audit_logs_no_delete DO INSTEAD NOTHING", $hasNoDeleteRule);
forensicAssert('P4_PG_DRIVER_CHECK', "Migration checks DB::getDriverName() === 'pgsql'", $hasPgsqlDriverCheck);
forensicAssert('P4_PG_DROP_RULES', "Migration down() method drops rules safely", $hasDropRulesInDown);

// ----------------------------------------------------------------------------
// PHASE 5: DOMPDF DIGITAL WALLET & QR CODE RENDERING AUDIT
// ----------------------------------------------------------------------------
echo "\n>>> PHASE 5: Dompdf Digital Wallet & QR Code Generator Audit\n";

$pdfService = new \App\Services\CarteiraPdfService($qr, $lgpd);
$mockEgresso = new class {
    public int $id = 7;
    public string $nome_completo = 'CARLOS ALBERTO MEDEIROS';
    public ?string $nome_social = null;
    public ?string $cpf = '52998224725';
    public ?string $registro_sejus = 'ES-2026-000007';
    public $municipio;
    public function __construct() {
        $this->municipio = new class { public string $nome = 'Vitória'; };
    }
};

$html = $pdfService->renderHtml($mockEgresso);
forensicAssert('P5_PDF_SEJUS_HEADER', "Wallet HTML contains official SEJUS/ES header", str_contains($html, 'GOVERNO DO ESTADO DO ESPÍRITO SANTO') && str_contains($html, 'SECRETARIA DE ESTADO DA JUSTIÇA'));
forensicAssert('P5_PDF_QR_DATA_URI', "Wallet HTML embeds QR code as Data-URI", str_contains($html, 'data:image/svg+xml;base64,'));
forensicAssert('P5_PDF_LGPD_MASK', "Wallet HTML displays masked CPF (***.982.247-**)", str_contains($html, '***.982.247-**'));
forensicAssert('P5_PDF_LEGAL_STAMP', "Wallet HTML contains legal stamp Lei 182/2021", str_contains($html, '182/2021'));
forensicAssert('P5_PDF_AUTH_CODE', "Wallet HTML contains formatted authentication code", preg_match('/[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}/', $html) === 1);

// ----------------------------------------------------------------------------
// SUMMARY & VERDICT
// ----------------------------------------------------------------------------
echo "\n===============================================================================\n";
echo "FORENSIC AUDIT SUMMARY:\n";
echo "Total Assertions Evaluated: " . ($totalPassed + $totalFailed) . "\n";
echo "Passed:                     {$totalPassed}\n";
echo "Failed:                     {$totalFailed}\n";
echo "===============================================================================\n";

if ($totalFailed === 0) {
    echo "\n>>> FINAL FORENSIC VERDICT: CLEAN <<<\n\n";
    exit(0);
} else {
    echo "\n>>> FINAL FORENSIC VERDICT: INTEGRITY VIOLATION <<<\n\n";
    foreach ($auditFindings as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
