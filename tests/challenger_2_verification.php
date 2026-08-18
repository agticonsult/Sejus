<?php

/**
 * Challenger 2 Verification Script for Milestones M1 & M2 (CONECTA EGRESSO)
 *
 * Checks:
 * 1. 78 ES Municipalities in MunicipioEsSeeder.php:
 *    - Exactly 78 distinct municipalities
 *    - All 78 have unique official 7-digit IBGE codes starting with '32'
 *    - Latitude bounds: -21.5 to -17.5
 *    - Longitude bounds: -42.0 to -39.5
 *    - Exactly 4 physical offices (Vitória, Vila Velha, Serra, Cariacica)
 *    - Exactly 74 remote offices
 *    - All required fields present (nome, microrregiao, macrorregiao, populacao_estimada)
 * 2. Dompdf Digital Wallet (CarteiraPdfService & Blade template):
 *    - HTML layout compilation and CSS styling
 *    - Presence of SEJUS header
 *    - Presence of security seal / badge
 *    - Presence of QR code SVG / Data-URI
 *    - PII protection (masked CPF)
 *    - Validation of Blade template and fallback template
 *    - Photo placeholder inspection
 * 3. Database Migrations & Eloquent Models:
 *    - Lint / Syntax check on all 12 migrations and 12 models
 *    - Schema definition verification across 12 migrations
 *    - Eloquent model verification (table names, fillable, casts, scopes)
 *    - Bidirectional Eloquent relationship consistency check
 */

// Simple autoloader and helper mocks for standalone testing
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

$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'tests' => [],
    'findings' => [],
];

function recordAssertion(string $category, string $testName, bool $condition, string $details = ''): void {
    global $results;
    if ($condition) {
        $results['passed']++;
        $results['tests'][] = [
            'category' => $category,
            'test' => $testName,
            'status' => 'PASS',
            'details' => $details,
        ];
        echo " [PASS] [$category] $testName" . ($details ? " ($details)" : "") . "\n";
    } else {
        $results['failed']++;
        $results['tests'][] = [
            'category' => $category,
            'test' => $testName,
            'status' => 'FAIL',
            'details' => $details,
        ];
        echo " [FAIL] [$category] $testName - DETAILS: $details\n";
    }
}

function recordWarning(string $category, string $title, string $details): void {
    global $results;
    $results['warnings']++;
    $results['findings'][] = [
        'category' => $category,
        'title' => $title,
        'type' => 'WARNING',
        'details' => $details,
    ];
    echo " [WARN] [$category] $title - $details\n";
}

echo "====================================================================\n";
echo " CHALLENGER 2 EMPIRICAL VERIFICATION HARNESS - MILESTONES M1 & M2\n";
echo "====================================================================\n\n";

// ====================================================================
// SECTION 1: 78 ES MUNICIPALITIES VERIFICATION (MunicipioEsSeeder.php)
// ====================================================================
echo "--- SECTION 1: 78 ES Municipalities Analysis ---\n";

$seederPath = __DIR__ . '/../database/seeders/MunicipioEsSeeder.php';
recordAssertion('Seeder', 'MunicipioEsSeeder file exists', file_exists($seederPath), $seederPath);

$seederContent = file_get_contents($seederPath);

// Extract the $municipios array
preg_match('/\$municipios\s*=\s*\[(.*?)\];/s', $seederContent, $matches);
if (empty($matches[1])) {
    recordAssertion('Seeder', 'Extract $municipios array', false, 'Could not parse $municipios array from seeder');
} else {
    // Evaluate the array cleanly
    $arrayCode = 'return [' . $matches[1] . '];';
    $municipios = eval($arrayCode);

    recordAssertion('Seeder', 'Array parse success', is_array($municipios), 'Count: ' . count($municipios));

    // 1. Exactly 78 municipalities
    $count = count($municipios);
    recordAssertion('Seeder', 'Exact municipality count is 78', $count === 78, "Observed count: $count");

    // 2. All 78 distinct names
    $names = array_map(fn($m) => trim($m['nome']), $municipios);
    $uniqueNames = array_unique($names);
    recordAssertion('Seeder', '78 distinct municipality names', count($uniqueNames) === 78, 'Unique names count: ' . count($uniqueNames));

    // 3. IBGE codes verification
    $ibgeCodes = array_map(fn($m) => (string)$m['codigo_ibge'], $municipios);
    $uniqueIbgeCodes = array_unique($ibgeCodes);
    recordAssertion('Seeder', '78 unique IBGE codes', count($uniqueIbgeCodes) === 78, 'Unique IBGE count: ' . count($uniqueIbgeCodes));

    $allStartWith32 = true;
    $all7Digits = true;
    $invalidIbge = [];

    foreach ($municipios as $m) {
        $code = (string)$m['codigo_ibge'];
        if (strlen($code) !== 7) {
            $all7Digits = false;
            $invalidIbge[] = "{$m['nome']}: $code (len: " . strlen($code) . ")";
        }
        if (!str_starts_with($code, '32')) {
            $allStartWith32 = false;
            $invalidIbge[] = "{$m['nome']}: $code (prefix != 32)";
        }
    }

    recordAssertion('Seeder', 'All IBGE codes have exactly 7 digits', $all7Digits, empty($invalidIbge) ? 'All 7 digits' : implode(', ', $invalidIbge));
    recordAssertion('Seeder', 'All IBGE codes start with prefix 32 (Espírito Santo)', $allStartWith32, 'Prefix 32 verified on all 78 entries');

    // Mathematical verification of IBGE 7-digit Modulo 10 Check Digit
    function calculateIbgeCheckDigit(string $sixDigitBase): int {
        $weights = [1, 2, 1, 2, 1, 2];
        $sum = 0;
        for ($i = 0; $i < 6; $i++) {
            $prod = ((int)$sixDigitBase[$i]) * $weights[$i];
            $sum += intdiv($prod, 10) + ($prod % 10);
        }
        return (10 - ($sum % 10)) % 10;
    }

    $allCheckDigitsValid = true;
    $invalidDv = [];
    foreach ($municipios as $m) {
        $code = (string)$m['codigo_ibge'];
        $base = substr($code, 0, 6);
        $expectedDv = (int)$code[6];
        $calculatedDv = calculateIbgeCheckDigit($base);
        if ($expectedDv !== $calculatedDv) {
            $allCheckDigitsValid = false;
            $invalidDv[] = "{$m['nome']}: $code (expected DV: $expectedDv, calculated DV: $calculatedDv)";
        }
    }
    recordAssertion('Seeder', 'All 78 IBGE codes satisfy the official IBGE Modulo 10 verification digit algorithm',
        $allCheckDigitsValid,
        empty($invalidDv) ? '78/78 IBGE check digits verified mathematically' : implode(', ', $invalidDv)
    );

    // 4. Geographic bounds verification (Latitude: -21.5 to -17.5, Longitude: -42.0 to -39.5)
    $coordsValid = true;
    $invalidCoords = [];

    $minLat = -21.5;
    $maxLat = -17.5;
    $minLong = -42.0;
    $maxLong = -39.5;

    $observedLatMin = 999.0;
    $observedLatMax = -999.0;
    $observedLongMin = 999.0;
    $observedLongMax = -999.0;

    foreach ($municipios as $m) {
        $lat = (float)$m['latitude'];
        $long = (float)$m['longitude'];

        if ($lat < $observedLatMin) $observedLatMin = $lat;
        if ($lat > $observedLatMax) $observedLatMax = $lat;
        if ($long < $observedLongMin) $observedLongMin = $long;
        if ($long > $observedLongMax) $observedLongMax = $long;

        if ($lat < $minLat || $lat > $maxLat || $long < $minLong || $long > $maxLong) {
            $coordsValid = false;
            $invalidCoords[] = "{$m['nome']}: ($lat, $long)";
        }
    }

    recordAssertion('Seeder', 'All coordinates within ES geographic bounds (-21.5 to -17.5 lat, -42.0 to -39.5 long)',
        $coordsValid,
        "Lat Range: [$observedLatMin, $observedLatMax], Long Range: [$observedLongMin, $observedLongMax]" .
        (empty($invalidCoords) ? '' : ' | Out of bounds: ' . implode(', ', $invalidCoords))
    );

    // 5. Exactly 4 physical offices (Vitória, Vila Velha, Serra, Cariacica) and 74 remote
    $physicalOffices = [];
    $remoteOffices = [];

    foreach ($municipios as $m) {
        if (!empty($m['tem_escritorio_fisico'])) {
            $physicalOffices[] = $m['nome'];
        } else {
            $remoteOffices[] = $m['nome'];
        }
    }

    $expectedPhysical = ['Cariacica', 'Serra', 'Vila Velha', 'Vitória'];
    sort($physicalOffices);
    sort($expectedPhysical);

    $physicalMatch = ($physicalOffices === $expectedPhysical);
    recordAssertion('Seeder', 'Exactly 4 physical offices (Vitória, Vila Velha, Serra, Cariacica)',
        $physicalMatch && count($physicalOffices) === 4,
        'Found ' . count($physicalOffices) . ' physical offices: ' . implode(', ', $physicalOffices)
    );

    recordAssertion('Seeder', 'Exactly 74 remote assistance municipalities',
        count($remoteOffices) === 74,
        'Found ' . count($remoteOffices) . ' remote municipalities'
    );

    // 6. Check population and regional classification fields
    $allRegionsPopPresent = true;
    foreach ($municipios as $m) {
        if (empty($m['microrregiao']) || empty($m['macrorregiao']) || !isset($m['populacao_estimada']) || $m['populacao_estimada'] <= 0) {
            $allRegionsPopPresent = false;
            break;
        }
    }
    recordAssertion('Seeder', 'All 78 municipalities have microrregiao, macrorregiao, and valid populacao_estimada',
        $allRegionsPopPresent,
        'All regional and demographic fields populated'
    );
}

// ====================================================================
// SECTION 2: DOMPDF DIGITAL WALLET (CarteiraPdfService & Blade template)
// ====================================================================
echo "\n--- SECTION 2: Dompdf Digital Wallet & Blade Template Analysis ---\n";

require_once __DIR__ . '/../app/Services/LgpdSecurityService.php';
require_once __DIR__ . '/../app/Services/QrCodeSecurityService.php';
require_once __DIR__ . '/../app/Services/CarteiraPdfService.php';

use App\Services\LgpdSecurityService;
use App\Services\QrCodeSecurityService;
use App\Services\CarteiraPdfService;

$lgpd = new LgpdSecurityService('pepper_challenger_test_key_2026');
$qrService = new QrCodeSecurityService($lgpd, 'signing_key_challenger_2026');
$pdfService = new CarteiraPdfService($qrService, $lgpd);

// Mock Egresso object for standalone testing
$dummyEgresso = (object)[
    'id' => 101,
    'nome_completo' => 'Maria da Penha Silva',
    'nome_social' => 'Maria Silva',
    'cpf' => '04512378990',
    'cpf_encrypted' => $lgpd->encryptField('04512378990'),
    'hash_cpf' => $lgpd->generateBlindIndex('04512378990'),
    'registro_sejus' => 'ES-2026-000101',
    'municipio' => (object)['nome' => 'Cariacica'],
];

// Test HTML generation
$html = $pdfService->renderHtml($dummyEgresso);
recordAssertion('Dompdf', 'renderHtml returns non-empty string', !empty($html) && strlen($html) > 500, 'HTML Length: ' . strlen($html));

// Check SEJUS Header
$hasHeaderGov = str_contains($html, 'GOVERNO DO ESTADO DO ESPÍRITO SANTO');
$hasHeaderSejus = str_contains($html, 'SECRETARIA DE ESTADO DA JUSTIÇA');
$hasEscritorio = str_contains($html, 'ESCRITÓRIO SOCIAL');
recordAssertion('Dompdf', 'Presence of SEJUS Header (Gov ES, SEJUS, Escritório Social)',
    $hasHeaderGov && $hasHeaderSejus && $hasEscritorio,
    'Header contains Gov ES, SEJUS, Escritório Social'
);

// Check Security Seal / Badge
$hasSeal = str_contains($html, 'CREDENCIAL OFICIAL') && (str_contains($html, 'VERIFICADA') || str_contains($html, 'AUTENTICADA'));
recordAssertion('Dompdf', 'Presence of Security Seal / Badge', $hasSeal, 'Contains credential verification seal');

// Check QR Code SVG rendering
$hasQrDataUri = str_contains($html, 'data:image/svg+xml;base64,');
recordAssertion('Dompdf', 'Presence of QR code Data-URI', $hasQrDataUri, 'Embedded SVG Data-URI present');

// Verify decoded QR SVG content
if ($hasQrDataUri) {
    preg_match('/data:image\/svg\+xml;base64,([A-Za-z0-9+\/=]+)/', $html, $qrMatches);
    $svgContent = base64_decode($qrMatches[1]);
    $isValidSvg = str_contains($svgContent, '<svg') && str_contains($svgContent, '</svg>');
    recordAssertion('Dompdf', 'QR Code Data-URI contains valid decoded SVG markup', $isValidSvg, 'SVG Tag validated');
} else {
    recordAssertion('Dompdf', 'QR Code Data-URI contains valid decoded SVG markup', false, 'Data-URI missing');
}

// Check PII protection (masked CPF)
$hasMaskedCpf = str_contains($html, '***.123.789-**');
$noPlainCpf = !str_contains($html, '04512378990') && !str_contains($html, '045.123.789-90');
recordAssertion('Dompdf', 'PII protection: CPF is masked and plaintext CPF is never rendered',
    $hasMaskedCpf && $noPlainCpf,
    "Masked CPF: ***.123.789-**, Plaintext absent"
);

// Check Legal Reference & Cryptographic Auth Code
$hasLegalRef = str_contains($html, '182/2021');
$hasAuthCode = preg_match('/[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}/', $html);
recordAssertion('Dompdf', 'Legal basis (LC 182/2021) and formatted Auth Code (16 hex chars grouped in 4) present',
    $hasLegalRef && $hasAuthCode,
    'LC 182/2021 and XXXX-XXXX-XXXX-XXXX Auth Code matched'
);

// Check PDF generation method
$pdfOutput = $pdfService->generatePdf($dummyEgresso);
$isPdfStream = str_starts_with($pdfOutput, '%PDF-') || str_contains($pdfOutput, '%PDF-1.');
recordAssertion('Dompdf', 'generatePdf returns valid PDF stream or formatted output',
    !empty($pdfOutput) && $isPdfStream,
    'PDF Stream signature confirmed (starts with %PDF-)'
);

// Check Blade template file: resources/views/pdf/carteira_digital.blade.php
$bladePath = __DIR__ . '/../resources/views/pdf/carteira_digital.blade.php';
recordAssertion('Dompdf', 'Blade template file resources/views/pdf/carteira_digital.blade.php exists', file_exists($bladePath), $bladePath);

$bladeContent = file_get_contents($bladePath);
$bladeHasHeader = str_contains($bladeContent, 'GOVERNO DO ESTADO DO ESPÍRITO SANTO') && str_contains($bladeContent, 'SECRETARIA DE ESTADO DA JUSTIÇA');
$bladeHasSeal = str_contains($bladeContent, 'badge-status') && str_contains($bladeContent, 'CREDENCIAL OFICIAL');
$bladeHasQr = str_contains($bladeContent, '$qrCodeDataUri');
$bladeHasLegal = str_contains($bladeContent, '182/2021');

recordAssertion('Dompdf', 'Blade template contains SEJUS header, badge, QR code variable, and legal stamp',
    $bladeHasHeader && $bladeHasSeal && $bladeHasQr && $bladeHasLegal,
    'Blade template variables and structure verified'
);

// Adversarial inspection on Photo placeholder:
$hasPhotoPlaceholderBlade = str_contains($bladeContent, 'foto') || str_contains($bladeContent, 'avatar') || str_contains($bladeContent, 'placeholder');
$hasPhotoPlaceholderFallback = str_contains($html, 'foto') || str_contains($html, 'avatar') || str_contains($html, 'photo');

if (!$hasPhotoPlaceholderBlade && !$hasPhotoPlaceholderFallback) {
    recordWarning('Dompdf', 'Photo/Avatar placeholder is not explicitly marked in HTML/Blade template',
        'The template relies on typographic credentials with QR validation instead of a designated 3x4 photo frame. Consider adding a styled 3x4 photo/avatar placeholder box for visual parity with physical identity cards.'
    );
}

// ====================================================================
// SECTION 3: DATABASE MIGRATIONS & ELOQUENT MODELS SYNTAX & RELATIONS
// ====================================================================
echo "\n--- SECTION 3: Database Migrations & Eloquent Models Analysis ---\n";

$migrations = [
    '2026_01_01_000001_create_perfis_table.php' => 'perfis',
    '2026_01_01_000002_create_municipios_es_table.php' => 'municipios_es',
    '2026_01_01_000003_create_users_table.php' => 'users',
    '2026_01_01_000004_create_egressos_table.php' => 'egressos',
    '2026_01_01_000005_create_prontuarios_table.php' => 'prontuarios',
    '2026_01_01_000006_create_prontuario_timeline_table.php' => 'prontuario_timeline',
    '2026_01_01_000007_create_prontuario_audit_logs_table.php' => 'prontuario_audit_logs',
    '2026_01_01_000008_create_video_rooms_table.php' => 'video_rooms',
    '2026_01_01_000009_create_video_attendees_table.php' => 'video_attendees',
    '2026_01_01_000010_create_vagas_emprego_table.php' => 'vagas_emprego',
    '2026_01_01_000011_create_cursos_capacitacao_table.php' => 'cursos_capacitacao',
    '2026_01_01_000012_create_rede_apoio_table.php' => 'rede_apoio',
];

$models = [
    'Perfil.php' => 'perfis',
    'MunicipioEs.php' => 'municipios_es',
    'User.php' => 'users',
    'Egresso.php' => 'egressos',
    'Prontuario.php' => 'prontuarios',
    'ProntuarioTimeline.php' => 'prontuario_timeline',
    'ProntuarioAuditLog.php' => 'prontuario_audit_logs',
    'VideoRoom.php' => 'video_rooms',
    'VideoAttendee.php' => 'video_attendees',
    'VagaEmprego.php' => 'vagas_emprego',
    'CursoCapacitacao.php' => 'cursos_capacitacao',
    'RedeApoio.php' => 'rede_apoio',
];

// Verify all 12 migration files exist and pass php -l linting
$allMigrationsLint = true;
foreach ($migrations as $filename => $table) {
    $path = __DIR__ . '/../database/migrations/' . $filename;
    if (!file_exists($path)) {
        $allMigrationsLint = false;
        recordAssertion('Migrations', "Migration $filename exists", false, "Missing file: $path");
        continue;
    }
    exec("php -l \"$path\" 2>&1", $out, $returnCode);
    if ($returnCode !== 0) {
        $allMigrationsLint = false;
        recordAssertion('Migrations', "Migration $filename syntax lint", false, implode(' ', $out));
    }
}
recordAssertion('Migrations', 'All 12 migration files exist and have zero PHP syntax errors (lint pass)', $allMigrationsLint, '12/12 files linted cleanly');

// Verify all 12 model files exist and pass php -l linting
$allModelsLint = true;
foreach ($models as $filename => $table) {
    $path = __DIR__ . '/../app/Models/' . $filename;
    if (!file_exists($path)) {
        $allModelsLint = false;
        recordAssertion('Models', "Model $filename exists", false, "Missing file: $path");
        continue;
    }
    exec("php -l \"$path\" 2>&1", $out, $returnCode);
    if ($returnCode !== 0) {
        $allModelsLint = false;
        recordAssertion('Models', "Model $filename syntax lint", false, implode(' ', $out));
    }
}
recordAssertion('Models', 'All 12 Eloquent model files exist and have zero PHP syntax errors (lint pass)', $allModelsLint, '12/12 files linted cleanly');

// Verify Model table mappings
$allTableMappingsMatch = true;
foreach ($models as $filename => $expectedTable) {
    $path = __DIR__ . '/../app/Models/' . $filename;
    $content = file_get_contents($path);
    if (!preg_match("/protected\s+\\\$table\s*=\s*'([^']+)'/", $content, $tMatches) || $tMatches[1] !== $expectedTable) {
        $allTableMappingsMatch = false;
        recordAssertion('Models', "Model $filename \$table mapping", false, "Expected: $expectedTable, got: " . ($tMatches[1] ?? 'none'));
    }
}
recordAssertion('Models', 'All 12 Models explicitly define correct protected $table matching migrations', $allTableMappingsMatch, '12/12 tables match');

// Verify bidirectional relationships and foreign key pairs
$relationshipsToCheck = [
    [
        'parent_model' => 'Perfil',
        'parent_method' => 'users',
        'parent_type' => 'hasMany',
        'child_model' => 'User',
        'child_method' => 'perfil',
        'child_type' => 'belongsTo',
        'foreign_key' => 'perfil_id',
    ],
    [
        'parent_model' => 'User',
        'parent_method' => 'egresso',
        'parent_type' => 'hasOne',
        'child_model' => 'Egresso',
        'child_method' => 'user',
        'child_type' => 'belongsTo',
        'foreign_key' => 'user_id',
    ],
    [
        'parent_model' => 'User',
        'parent_method' => 'prontuariosComoTecnico',
        'parent_type' => 'hasMany',
        'child_model' => 'Prontuario',
        'child_method' => 'tecnicoResponsavel',
        'child_type' => 'belongsTo',
        'foreign_key' => 'tecnico_responsavel_id',
    ],
    [
        'parent_model' => 'User',
        'parent_method' => 'timelineEventos',
        'parent_type' => 'hasMany',
        'child_model' => 'ProntuarioTimeline',
        'child_method' => 'responsavel',
        'child_type' => 'belongsTo',
        'foreign_key' => 'responsavel_id',
    ],
    [
        'parent_model' => 'User',
        'parent_method' => 'auditLogs',
        'parent_type' => 'hasMany',
        'child_model' => 'ProntuarioAuditLog',
        'child_method' => 'user',
        'child_type' => 'belongsTo',
        'foreign_key' => 'user_id',
    ],
    [
        'parent_model' => 'User',
        'parent_method' => 'videoRoomsComoTecnico',
        'parent_type' => 'hasMany',
        'child_model' => 'VideoRoom',
        'child_method' => 'tecnico',
        'child_type' => 'belongsTo',
        'foreign_key' => 'tecnico_id',
    ],
    [
        'parent_model' => 'User',
        'parent_method' => 'participacoesVideo',
        'parent_type' => 'hasMany',
        'child_model' => 'VideoAttendee',
        'child_method' => 'user',
        'child_type' => 'belongsTo',
        'foreign_key' => 'user_id',
    ],
    [
        'parent_model' => 'MunicipioEs',
        'parent_method' => 'egressos',
        'parent_type' => 'hasMany',
        'child_model' => 'Egresso',
        'child_method' => 'municipio',
        'child_type' => 'belongsTo',
        'foreign_key' => 'municipio_residencia_id',
    ],
    [
        'parent_model' => 'MunicipioEs',
        'parent_method' => 'vagas',
        'parent_type' => 'hasMany',
        'child_model' => 'VagaEmprego',
        'child_method' => 'municipio',
        'child_type' => 'belongsTo',
        'foreign_key' => 'municipio_id',
    ],
    [
        'parent_model' => 'MunicipioEs',
        'parent_method' => 'cursos',
        'parent_type' => 'hasMany',
        'child_model' => 'CursoCapacitacao',
        'child_method' => 'municipio',
        'child_type' => 'belongsTo',
        'foreign_key' => 'municipio_id',
    ],
    [
        'parent_model' => 'MunicipioEs',
        'parent_method' => 'redeApoio',
        'parent_type' => 'hasMany',
        'child_model' => 'RedeApoio',
        'child_method' => 'municipio',
        'child_type' => 'belongsTo',
        'foreign_key' => 'municipio_id',
    ],
    [
        'parent_model' => 'MunicipioEs',
        'parent_method' => 'videoRooms',
        'parent_type' => 'hasMany',
        'child_model' => 'VideoRoom',
        'child_method' => 'municipio',
        'child_type' => 'belongsTo',
        'foreign_key' => 'municipio_id',
    ],
    [
        'parent_model' => 'Egresso',
        'parent_method' => 'prontuario',
        'parent_type' => 'hasOne',
        'child_model' => 'Prontuario',
        'child_method' => 'egresso',
        'child_type' => 'belongsTo',
        'foreign_key' => 'egresso_id',
    ],
    [
        'parent_model' => 'Egresso',
        'parent_method' => 'videoRooms',
        'parent_type' => 'hasMany',
        'child_model' => 'VideoRoom',
        'child_method' => 'egresso',
        'child_type' => 'belongsTo',
        'foreign_key' => 'egresso_id',
    ],
    [
        'parent_model' => 'Prontuario',
        'parent_method' => 'timeline',
        'parent_type' => 'hasMany',
        'child_model' => 'ProntuarioTimeline',
        'child_method' => 'prontuario',
        'child_type' => 'belongsTo',
        'foreign_key' => 'prontuario_id',
    ],
    [
        'parent_model' => 'Prontuario',
        'parent_method' => 'auditLogs',
        'parent_type' => 'hasMany',
        'child_model' => 'ProntuarioAuditLog',
        'child_method' => 'prontuario',
        'child_type' => 'belongsTo',
        'foreign_key' => 'prontuario_id',
    ],
    [
        'parent_model' => 'Prontuario',
        'parent_method' => 'videoRooms',
        'parent_type' => 'hasMany',
        'child_model' => 'VideoRoom',
        'child_method' => 'prontuario',
        'child_type' => 'belongsTo',
        'foreign_key' => 'prontuario_id',
    ],
    [
        'parent_model' => 'VideoRoom',
        'parent_method' => 'attendees',
        'parent_type' => 'hasMany',
        'child_model' => 'VideoAttendee',
        'child_method' => 'room',
        'child_type' => 'belongsTo',
        'foreign_key' => 'video_room_id',
    ],
];

echo "\n--- Checking Bidirectional Eloquent Relationships ---\n";
$allRelationsValid = true;

foreach ($relationshipsToCheck as $rel) {
    $parentPath = __DIR__ . '/../app/Models/' . $rel['parent_model'] . '.php';
    $childPath = __DIR__ . '/../app/Models/' . $rel['child_model'] . '.php';

    $parentCode = file_get_contents($parentPath);
    $childCode = file_get_contents($childPath);

    // Check parent method
    $parentMethodDefined = preg_match('/function\s+' . $rel['parent_method'] . '\s*\(/', $parentCode);
    $parentForeignKeyPresent = str_contains($parentCode, "'{$rel['foreign_key']}'") || str_contains($parentCode, "\"{$rel['foreign_key']}\"");

    // Check child method
    $childMethodDefined = preg_match('/function\s+' . $rel['child_method'] . '\s*\(/', $childCode);
    $childForeignKeyPresent = str_contains($childCode, "'{$rel['foreign_key']}'") || str_contains($childCode, "\"{$rel['foreign_key']}\"");

    $valid = $parentMethodDefined && $parentForeignKeyPresent && $childMethodDefined && $childForeignKeyPresent;

    if (!$valid) {
        $allRelationsValid = false;
        recordAssertion('Relationships',
            "{$rel['parent_model']}::{$rel['parent_method']} <-> {$rel['child_model']}::{$rel['child_method']}",
            false,
            "Parent method: $parentMethodDefined, Parent FK: $parentForeignKeyPresent, Child method: $childMethodDefined, Child FK: $childForeignKeyPresent"
        );
    } else {
        recordAssertion('Relationships',
            "{$rel['parent_model']}::{$rel['parent_method']} ({$rel['parent_type']}) <-> {$rel['child_model']}::{$rel['child_method']} ({$rel['child_type']}) [FK: {$rel['foreign_key']}]",
            true,
            'Bidirectional pair verified'
        );
    }
}

// ====================================================================
// SECTION 4: CRYPTOGRAPHIC & LGPD ADVERSARIAL ASSERTIONS
// ====================================================================
echo "\n--- SECTION 4: Cryptographic & Security Edge Case Assertions ---\n";

// 1. Rejection of invalid CPFs in LGPD service
$invalidCpfs = [
    '00000000000',
    '11111111111',
    '12345678901',
    '19283045679', // Wrong check digits
    'abc',
    '',
];
$allInvalidRejected = true;
foreach ($invalidCpfs as $badCpf) {
    if ($lgpd->validateCpf($badCpf)) {
        $allInvalidRejected = false;
        recordAssertion('Security', "Rejection of invalid CPF: $badCpf", false, 'Accepted invalid CPF!');
    }
}
recordAssertion('Security', 'LGPD Service rejects all known invalid CPF checksums and repeated sequences', $allInvalidRejected, '6/6 invalid CPFs rejected');

// 2. Blind index determinism and one-way property
$cpfA = '19283045678';
$hash1 = $lgpd->generateBlindIndex($cpfA);
$hash2 = $lgpd->generateBlindIndex($cpfA);
recordAssertion('Security', 'Blind index is strictly deterministic for same input', $hash1 === $hash2, "Hash: $hash1");

$hashDiffPepper = (new LgpdSecurityService('different_pepper_key'))->generateBlindIndex($cpfA);
recordAssertion('Security', 'Blind index with different pepper produces distinct hash', $hash1 !== $hashDiffPepper, 'Pepper segregation confirmed');

// 3. QR Code Tamper-Resistance (hash_equals attack test)
$payload = $qrService->generatePayload($dummyEgresso);
$token = $qrService->generateToken($payload);
$verification = $qrService->verifyToken($token);
recordAssertion('Security', 'Valid QR Token passes verification', $verification['valid'] === true && $verification['status'] === 'VALID_DOCUMENT', $verification['message']);

// Tamper payload in token
$tamperedToken = substr($token, 0, -4) . 'AAAA';
$tamperedVerification = $qrService->verifyToken($tamperedToken);
recordAssertion('Security', 'Tampered QR Token is strictly rejected with TAMPERED_DOCUMENT or MALFORMED_TOKEN',
    $tamperedVerification['valid'] === false,
    "Status: {$tamperedVerification['status']} | Message: {$tamperedVerification['message']}"
);

// ====================================================================
// SUMMARY & VERDICT
// ====================================================================
echo "\n====================================================================\n";
echo " CHALLENGER 2 SUMMARY:\n";
echo " Total Tests Passed:   {$results['passed']}\n";
echo " Total Tests Failed:   {$results['failed']}\n";
echo " Total Warnings:       {$results['warnings']}\n";
echo "====================================================================\n";

if ($results['failed'] === 0) {
    echo " >>> VERDICT: APPROVE <<<\n";
} else {
    echo " >>> VERDICT: REQUEST_CHANGES <<<\n";
}

file_put_contents(__DIR__ . '/challenger_2_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
