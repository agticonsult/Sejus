<?php

/**
 * CONECTA EGRESSO (SEJUS/ES) - Milestone M1 & M2 Independent Verification Runner
 * Validates cryptography, blind indexes, immutable hash chains, QR signatures, and ES data integrity.
 */

// Simple autoloader for standalone execution without Composer vendor directory
spl_autoload_register(function ($class) {
    $prefixApp = 'App\\';
    $prefixDatabase = 'Database\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

    if (strncmp($prefixApp, $class, strlen($prefixApp)) === 0) {
        $relativeClass = substr($class, strlen($prefixApp));
        $file = $baseDir . 'app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    if (strncmp($prefixDatabase, $class, strlen($prefixDatabase)) === 0) {
        $relativeClass = substr($class, strlen($prefixDatabase));
        $file = $baseDir . 'database' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Mock minimal helper functions if not in full Laravel context
if (!function_exists('config')) {
    function config($key, $default = null) {
        $configs = [
            'app.url' => 'http://localhost',
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
            public function isAfter($datetime): bool {
                $target = is_string($datetime) ? new \DateTimeImmutable($datetime) : $datetime;
                return $this > $target;
            }
        };
    }
}

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, ?string $details = null): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}" . ($details ? " - {$details}" : "") . "\n";
    }
}

echo "===============================================================================\n";
echo "CONECTA EGRESSO (SEJUS/ES) - MILESTONE M1 & M2 VERIFICATION SUITE\n";
echo "===============================================================================\n\n";

// ----------------------------------------------------------------------------
// TEST SUITE 1: LGPD Security Service (Blind Index & AES-256)
// ----------------------------------------------------------------------------
echo "1. Testing LgpdSecurityService (Blind Index, AES-256, CPF Masking & Validation):\n";

$lgpd = new \App\Services\LgpdSecurityService('test_pepper_key_2026');

// 1.1 CPF Normalization
$norm = $lgpd->normalizeCpf('192.830.456-78');
assertTest("CPF normalization strips punctuation", $norm === '19283045678');

// 1.2 CPF Validation Algorithm
assertTest("Rejects invalid repeated sequence 111.111.111-11", $lgpd->validateCpf('111.111.111-11') === false);
assertTest("Rejects invalid checksum CPF", $lgpd->validateCpf('123.456.789-00') === false);
assertTest("Accepts valid test CPF (529.982.247-25)", $lgpd->validateCpf('529.982.247-25') === true);

// 1.3 Deterministic Blind Index Hashing
$hash1 = $lgpd->generateBlindIndex('192.830.456-78');
$hash2 = $lgpd->generateBlindIndex('19283045678');
assertTest("Blind index is deterministic (same input produces identical hash)", $hash1 === $hash2);
assertTest("Blind index is SHA-256 (length 64 hex chars)", strlen($hash1) === 64);
assertTest("Blind index matches HMAC-SHA256 with pepper key", $hash1 === hash_hmac('sha256', '19283045678', 'test_pepper_key_2026'));

// 1.4 Pepper Isolation
$lgpdAlt = new \App\Services\LgpdSecurityService('alternative_pepper_key');
$hashAlt = $lgpdAlt->generateBlindIndex('192.830.456-78');
assertTest("Different pepper key produces completely different hash", $hash1 !== $hashAlt);

// 1.5 AES-256 Encryption / Decryption
$sensitiveData = "Laudo Psicossocial: Reintegração recomendada com suporte de moradia.";
$encrypted = $lgpd->encryptField($sensitiveData);
assertTest("Field encryption does not expose plaintext", $encrypted !== $sensitiveData && !str_contains($encrypted, 'Laudo'));
$decrypted = $lgpd->decryptField($encrypted);
assertTest("Field decryption recovers exact original plaintext", $decrypted === $sensitiveData);

// 1.6 Masking
$maskedCpf = $lgpd->maskCpf('192.830.456-78');
assertTest("CPF is masked to ***.830.456-**", $maskedCpf === '***.830.456-**');
$maskedName = $lgpd->maskName('Lucas Silva Santos');
assertTest("Name is masked to Lucas S. Santos", $maskedName === 'Lucas S. Santos');

echo "\n";

// ----------------------------------------------------------------------------
// TEST SUITE 2: AuditService & Cryptographic Hash Chaining
// ----------------------------------------------------------------------------
echo "2. Testing AuditService (Hash Chaining & Tamper Detection):\n";

$audit = new \App\Services\AuditService();

// 2.1 Genesis Hash
assertTest("Genesis hash constant is exactly 64 zeros", \App\Services\AuditService::GENESIS_HASH === str_repeat('0', 64));

// 2.2 Canonical Block Hashing
$prev = \App\Services\AuditService::GENESIS_HASH;
$h1 = $audit->calculateRecordHash($prev, 1, 2, 'CREATE_PRONTUARIO', '10.0.0.1', '2026-08-17T12:00:00Z', ['numero' => 'PRT-2026-000001']);
assertTest("Block 1 hash calculated correctly (64 chars)", strlen($h1) === 64);

// 2.3 Unbroken Chain Linking
$h2 = $audit->calculateRecordHash($h1, 1, 2, 'VIEW', '10.0.0.1', '2026-08-17T12:10:00Z', ['motivo' => 'Consulta']);
assertTest("Block 2 links to Block 1 hash", $h2 !== $h1 && strlen($h2) === 64);

// 2.4 Tampering Detection
$hTampered = $audit->calculateRecordHash($prev, 1, 2, 'UPDATE_UNAUTHORIZED', '10.0.0.1', '2026-08-17T12:00:00Z', ['numero' => 'PRT-2026-000001']);
assertTest("Tampered action produces distinct hash immediately detected", $hTampered !== $h1);

echo "\n";

// ----------------------------------------------------------------------------
// TEST SUITE 3: QrCodeSecurityService & Carteira Validation
// ----------------------------------------------------------------------------
echo "3. Testing QrCodeSecurityService & Digital Wallet Cryptography:\n";

$qr = new \App\Services\QrCodeSecurityService($lgpd, 'sejus_test_secret_key_2026');

$docPayload = [
    'doc_id' => '1',
    'registro_sejus' => 'ES-2026-000001',
    'cpf_masked' => '***.830.456-**',
    'nome' => 'LUCAS SANTOS',
    'municipio' => 'São Mateus',
    'issued_at' => (new \DateTimeImmutable())->format('c'),
    'expires_at' => (new \DateTimeImmutable('+1 year'))->format('c'),
    'legal_basis' => 'Lei Complementar Estadual nº 182/2021',
];

// 3.1 Signature Generation
$sig = $qr->signPayload($docPayload);
assertTest("HMAC-SHA256 signature generated (64 hex chars)", strlen($sig) === 64);

// 3.2 Token Generation & Verification
$token = $qr->generateToken($docPayload);
assertTest("Token generated as URL-safe string", !empty($token) && !str_contains($token, '+') && !str_contains($token, '/'));

$verifyGenuine = $qr->verifyToken($token);
assertTest("Genuine token verified with VALID_DOCUMENT status", $verifyGenuine['valid'] === true && $verifyGenuine['status'] === 'VALID_DOCUMENT');
assertTest("Payload correctly restored from token envelope", ($verifyGenuine['payload']['nome'] ?? '') === 'LUCAS SANTOS');

// 3.3 Tampered Document Detection
$tamperedEnvelope = [
    'p' => array_merge($docPayload, ['nome' => 'ROBERTO ADULTERADO']),
    's' => $sig, // original signature
];
$tamperedToken = rtrim(strtr(base64_encode(json_encode($tamperedEnvelope)), '+/', '-_'), '=');
$verifyTampered = $qr->verifyToken($tamperedToken);
assertTest("Tampered document rejected with TAMPERED_DOCUMENT status", $verifyTampered['valid'] === false && $verifyTampered['status'] === 'TAMPERED_DOCUMENT');

// 3.4 Expired Document Detection
$expiredPayload = array_merge($docPayload, [
    'issued_at' => (new \DateTimeImmutable('-2 years'))->format('c'),
    'expires_at' => (new \DateTimeImmutable('-1 year'))->format('c'),
]);
$expiredToken = $qr->generateToken($expiredPayload);
$verifyExpired = $qr->verifyToken($expiredToken);
assertTest("Expired document rejected with EXPIRED_DOCUMENT status", $verifyExpired['valid'] === false && $verifyExpired['status'] === 'EXPIRED_DOCUMENT');

// 3.5 QR SVG & Data-URI Generation
$svg = $qr->generateQrCodeSvg('https://conectaegresso.es.gov.br/validar-carteira/sample');
assertTest("QR Code vector SVG generated", str_contains($svg, '<svg') && str_contains($svg, '</svg>'));
$dataUri = $qr->generateQrCodeDataUri('https://conectaegresso.es.gov.br/validar-carteira/sample');
assertTest("QR Code Data-URI generated with base64 SVG", str_starts_with($dataUri, 'data:image/svg+xml;base64,'));

echo "\n";

// ----------------------------------------------------------------------------
// TEST SUITE 4: CarteiraPdfService HTML Generation
// ----------------------------------------------------------------------------
echo "4. Testing CarteiraPdfService Layout & Rendering:\n";

$pdfService = new \App\Services\CarteiraPdfService($qr, $lgpd);

// Use anonymous class mockup of Egresso for standalone testing
$mockEgresso = new class {
    public int $id = 1;
    public string $nome_completo = 'Lucas Santos';
    public ?string $nome_social = null;
    public ?string $cpf = '19283045678';
    public ?string $registro_sejus = 'ES-2026-000001';
    public $municipio;

    public function __construct() {
        $this->municipio = new class {
            public string $nome = 'São Mateus';
        };
    }
};

$html = $pdfService->renderHtml($mockEgresso);
assertTest("PDF HTML contains State Header", str_contains($html, 'GOVERNO DO ESTADO DO ESPÍRITO SANTO'));
assertTest("PDF HTML contains SEJUS Digital Social Office", str_contains($html, 'SECRETARIA DE ESTADO DA JUSTIÇA'));
assertTest("PDF HTML contains Egresso Name", str_contains($html, 'LUCAS SANTOS'));
assertTest("PDF HTML contains Masked CPF", str_contains($html, '***.830.456-**'));
assertTest("PDF HTML contains Embedded QR Code Data-URI", str_contains($html, 'data:image/svg+xml;base64,'));
assertTest("PDF HTML contains Legal Basis Stamp (Lei 182/2021)", str_contains($html, '182/2021'));

echo "\n";

// ----------------------------------------------------------------------------
// TEST SUITE 5: 78 ES Municipalities Seeder Verification
// ----------------------------------------------------------------------------
echo "5. Testing Espírito Santo 78 Municipalities Seeder Data Integrity:\n";

$seederFile = dirname(__DIR__) . '/database/seeders/MunicipioEsSeeder.php';
assertTest("MunicipioEsSeeder.php file exists", file_exists($seederFile));

$seederContent = file_get_contents($seederFile);
preg_match_all("/'codigo_ibge'\s*=>\s*(\d{7})/", $seederContent, $ibgeMatches);
$totalIbge = count($ibgeMatches[1]);
assertTest("Contains exactly 78 unique IBGE codes for Espírito Santo", $totalIbge === 78);

$uniqueCodes = array_unique($ibgeMatches[1]);
assertTest("All 78 IBGE codes are distinct and unique", count($uniqueCodes) === 78);

// Check all codes start with 32 (State of Espírito Santo)
$allEsCodes = true;
foreach ($uniqueCodes as $code) {
    if (!str_starts_with($code, '32')) {
        $allEsCodes = false;
        break;
    }
}
assertTest("All IBGE codes have UF code 32 (Espírito Santo)", $allEsCodes);

// Check 4 physical offices
preg_match_all("/'tem_escritorio_fisico'\s*=>\s*true/", $seederContent, $physicalMatches);
assertTest("Exactly 4 municipalities have physical social office (Vitória, Vila Velha, Serra, Cariacica)", count($physicalMatches[0]) === 4);

assertTest("Contains Vitória (IBGE 3205309)", str_contains($seederContent, '3205309'));
assertTest("Contains Vila Velha (IBGE 3205200)", str_contains($seederContent, '3205200'));
assertTest("Contains Serra (IBGE 3205002)", str_contains($seederContent, '3205002'));
assertTest("Contains Cariacica (IBGE 3201308)", str_contains($seederContent, '3201308'));
assertTest("Contains Linhares (IBGE 3203205)", str_contains($seederContent, '3203205'));
assertTest("Contains São Mateus (IBGE 3204906)", str_contains($seederContent, '3204906'));
assertTest("Contains Colatina (IBGE 3201506)", str_contains($seederContent, '3201506'));
assertTest("Contains Cachoeiro de Itapemirim (IBGE 3201209)", str_contains($seederContent, '3201209'));

echo "\n";

// ----------------------------------------------------------------------------
// TEST SUITE 6: M1 Docker Infrastructure Artifacts Verification
// ----------------------------------------------------------------------------
echo "6. Testing M1 Docker Infrastructure Artifacts:\n";

$composeFile = dirname(__DIR__) . '/docker-compose.yml';
$nginxFile = dirname(__DIR__) . '/docker/nginx/nginx.conf';
$phpDockerFile = dirname(__DIR__) . '/docker/php/Dockerfile';
$phpIniFile = dirname(__DIR__) . '/docker/php/php.ini';
$pythonDockerFile = dirname(__DIR__) . '/docker/python/Dockerfile';
$coturnFile = dirname(__DIR__) . '/docker/coturn/turnserver.conf';
$postgresInitFile = dirname(__DIR__) . '/docker/postgres/init.sql';
$envExampleFile = dirname(__DIR__) . '/.env.example';

assertTest("docker-compose.yml exists", file_exists($composeFile));
assertTest("docker/nginx/nginx.conf exists", file_exists($nginxFile));
assertTest("docker/php/Dockerfile exists", file_exists($phpDockerFile));
assertTest("docker/php/php.ini exists", file_exists($phpIniFile));
assertTest("docker/python/Dockerfile exists", file_exists($pythonDockerFile));
assertTest("docker/coturn/turnserver.conf exists", file_exists($coturnFile));
assertTest("docker/postgres/init.sql exists", file_exists($postgresInitFile));
assertTest(".env.example exists", file_exists($envExampleFile));

$composeContent = file_get_contents($composeFile);
assertTest("docker-compose defines postgres service with PostGIS", str_contains($composeContent, 'postgis/postgis:16-3.4'));
assertTest("docker-compose defines redis service", str_contains($composeContent, 'redis:7.2-alpine'));
assertTest("docker-compose defines php service", str_contains($composeContent, 'conecta_php'));
assertTest("docker-compose defines python service (FastAPI)", str_contains($composeContent, 'conecta_python'));
assertTest("docker-compose defines nginx service", str_contains($composeContent, 'conecta_nginx'));
assertTest("docker-compose defines coturn service (STUN/TURN)", str_contains($composeContent, 'conecta_coturn'));

$nginxContent = file_get_contents($nginxFile);
assertTest("nginx.conf routes /ws/ to python_upstream", str_contains($nginxContent, 'location /ws/'));
assertTest("nginx.conf routes PHP to php_upstream", str_contains($nginxContent, 'fastcgi_pass php_upstream'));
assertTest("nginx.conf contains Gzip compression", str_contains($nginxContent, 'gzip on'));

$coturnContent = file_get_contents($coturnFile);
assertTest("turnserver.conf specifies sejus.es.gov.br realm", str_contains($coturnContent, 'sejus.es.gov.br'));
assertTest("turnserver.conf enables MICE mobility", str_contains($coturnContent, 'mobility'));

$sqlContent = file_get_contents($postgresInitFile);
assertTest("init.sql enables postgis extension", str_contains($sqlContent, 'CREATE EXTENSION IF NOT EXISTS "postgis"'));
assertTest("init.sql enables pgcrypto extension", str_contains($sqlContent, 'CREATE EXTENSION IF NOT EXISTS "pgcrypto"'));
assertTest("init.sql enables uuid-ossp extension", str_contains($sqlContent, 'CREATE EXTENSION IF NOT EXISTS "uuid-ossp"'));

echo "\n===============================================================================\n";
echo "SUMMARY: Total Passed: {$passed} | Total Failed: {$failed}\n";
echo "===============================================================================\n";

if ($failed === 0) {
    echo "\n>>> VERIFICATION COMPLETE: ALL M1 & M2 TEST ASSERTIONS PASSED (100%) <<<\n";
    exit(0);
} else {
    echo "\n>>> VERIFICATION FAILED: {$failed} TEST(S) FAILED <<<\n";
    exit(1);
}
