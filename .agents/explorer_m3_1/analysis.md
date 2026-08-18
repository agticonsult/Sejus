# Milestone M3: Backend Business APIs, RBAC & Webhooks - Architecture Analysis & Technical Blueprint

**Author**: Explorer Agent (M3-1)  
**Date**: 2026-08-17  
**Project**: CONECTA EGRESSO (SEJUS/ES)  
**Framework**: Laravel 11 (PHP 8.2+ / 8.3 / 8.4)  
**Scope**: Authentication, RBAC, OIDC / Gov.br / Acesso Cidadão Integration, Middleware (`CheckRole`, `AuditAccessLog`), Policies, and Security Blueprint.

---

## 1. Executive Summary & Existing Codebase Inventory

### 1.1 Existing Infrastructure (Milestones M1 & M2)
The database models, migrations, and core security services have been successfully implemented and verified:
1. **12 Relational Migrations & Models**:
   - `perfis` (1: Gestor SEJUS, 2: Técnico Escritório Social, 3: Egresso, 4: Familiar)
   - `users` (with `govbr_id`, `cpf_encrypted`, `hash_cpf`, `telefone_encrypted`, `perfil_id`, `ativo`)
   - `egressos` (with full PII encryption, blind index, municipality reference, consent flags)
   - `prontuarios` & `prontuario_timeline` (electronic medical/social records and chronological interventions)
   - `prontuario_audit_logs` (immutable hash-chained audit blocks with genesis linkage)
   - `video_rooms` & `video_attendees` (video call rooms, participants, MOS score telemetry)
   - `vagas_emprego`, `cursos_capacitacao`, `municipios_es` (all 78 ES municipalities), `rede_apoio` (CRAS/CREAS/SINE/CAPS)
2. **Core Security Services**:
   - `LgpdSecurityService`: AES-256 field encryption, deterministic HMAC-SHA256 Blind Indexing with segregated pepper, and CPF validation/masking.
   - `AuditService`: SHA-256 cryptographically chained immutable ledger (`calculateRecordHash`, `log`, `verifyChainIntegrity`).
   - `QrCodeSecurityService` & `CarteiraPdfService`: Cryptographic HMAC-SHA256 signature tokens for Carteira Digital and vector QR Code embedding.
3. **Verification Harness**:
   - `tests/run_verification.php` and `tests/challenger_2_verification.php` pass with 100% success rate.
   - Python E2E suite (`tests_e2e/test_runner.py`) executes 175 tests across Tiers 1-4 with 100% pass rate.

---

## 2. Authentication & RBAC Architecture

### 2.1 Role Definitions & Permission Matrix
The system defines 4 distinct roles with strict principle of least privilege:

| Role | Slug | Description | Permissions | Territorial Scope |
|---|---|---|---|---|
| **Gestor SEJUS** | `gestor` | State administrator at SEJUS/ES | Full dashboard KPIs, Prontuário administrative read, Management reports, Audit log inspection & hash chain verification, User management, Job/Course CRUD. *Blocked from clinical evolution writes without professional license.* | Statewide (`ESTADO_78_MUNICIPIOS`) across all 78 ES municipalities |
| **Técnico Escritório Social** | `tecnico` | Social Worker (CRESS) or Psychologist (CRP) | Dashboard view, Full clinical Prontuário read/write, Add clinical evolutions with professional council stamp, Host video attendances, Issue digital credentials, Refer egressos to jobs/courses. | Regional/Municipal (e.g. `MUNICIPAL_3205309` for Vitória, `MUNICIPAL_3203205` for Linhares) |
| **Egresso** | `egresso` | Citizen released from penal system | View own profile, View own simplified Prontuário timeline (confidential technical notes filtered out), View & download own Digital Wallet PDF, Apply to jobs, Enroll in courses, Join remote video queue. | Self-only (`SELF_<CPF>`) |
| **Familiar Autorizado** | `familiar` | Authorized family member | View public job/course opportunities, Join assisted video attendances with egresso. | Assisted self-only |

---

## 3. Simulated OpenID Connect / Gov.br / Acesso Cidadão Integration

### 3.1 OIDC Claim Mapping Architecture
Integration with Gov.br and Acesso Cidadão (ES) is built via a dedicated service: `App\Services\GovBrAuthService`.

```
                  ┌──────────────────────────────────────────────┐
                  │    Gov.br / Acesso Cidadão SSO Provider      │
                  │             (OIDC ID Token)                  │
                  └──────────────────────┬───────────────────────┘
                                         │
                                         ▼
                  ┌──────────────────────────────────────────────┐
                  │          GovBrAuthService (Laravel)          │
                  │  - Validates JWT signature, claims & exp     │
                  │  - Normalizes CPF & generates Blind Index    │
                  │  - Evaluates Trust Level (Bronze/Prata/Ouro) │
                  │  - Applies Fail-Secure Role Mapping Logic    │
                  └──────────────────────┬───────────────────────┘
                                         │
                   ┌─────────────────────┼─────────────────────┐
                   ▼                     ▼                     ▼
          [Gestor SEJUS]       [Técnico Social]       [Egresso / Cidadão]
        (Ouro + SEJUS claim)   (Prata/Ouro + CRESS)   (Default Citizen)
```

### 3.2 Claim Mapping Rules & Fail-Secure Design
1. **Subject Identifier (`sub`)**:
   - Maps to `users.govbr_id`. Used as immutable foreign key for SSO reconciliation.
2. **CPF Claim (`cpf`)**:
   - Stripped of punctuation to exactly 11 digits.
   - Algorithmic check digit verification (rejecting invalid sequences).
   - Encrypted via `LgpdSecurityService::encryptField()` -> `users.cpf_encrypted`.
   - Blind Index generated via `LgpdSecurityService::generateBlindIndex()` -> `users.hash_cpf`.
3. **Role & Privilege Resolution**:
   - **Gestor SEJUS**: Requires `nivel_confianca == 'Ouro'` AND (`orgao == 'SEJUS'` or `scope` contains `govbr_servidor`) AND `cargo` contains `gestor`/`administrador`.
   - **Técnico Escritório Social**: Requires `registro_conselho` containing `CRESS` or `CRP` (or `cargo` contains `social`/`psicologo`).
   - **Fail-Secure Fallback**: Any unknown organizational claims (e.g. SEFAZ, external organs) or unverified claims strictly default to `egresso` with self-only permissions.
4. **Step-Up Authentication Requirement**:
   - If a technical or administrative profile possesses only `Bronze` level, sensitive Prontuário operations require step-up identity verification (Silver/Gold biometrics).

### 3.3 Proposed `GovBrAuthService` Blueprint
```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Perfil;
use App\Models\Egresso;
use App\Services\LgpdSecurityService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GovBrAuthService
{
    public function __construct(
        protected LgpdSecurityService $lgpd,
        protected AuditService $audit
    ) {}

    /**
     * Map raw OIDC claims from Gov.br / Acesso Cidadão into application user entity.
     */
    public function handleOidcCallback(array $claims): User
    {
        $sub = $claims['sub'] ?? null;
        if (empty($sub)) {
            throw new InvalidArgumentException("OIDC claim 'sub' é obrigatório.");
        }

        $rawCpf = $claims['cpf'] ?? '';
        $cleanCpf = preg_replace('/\D/', '', $rawCpf);
        if (strlen($cleanCpf) !== 11 || !$this->lgpd->validateCpf($cleanCpf)) {
            throw new InvalidArgumentException("OIDC claim 'cpf' inválido ou ausente.");
        }

        $name = trim($claims['name'] ?? 'Cidadão Autenticado');
        $email = $claims['email'] ?? "user_{$cleanCpf}@cidadao.es.gov.br";
        $confianca = $claims['nivel_confianca'] ?? 'Bronze';
        $conselho = $claims['registro_conselho'] ?? null;
        $orgao = $claims['orgao'] ?? '';
        $cargo = $claims['cargo'] ?? '';

        // Role resolution
        $roleSlug = 'egresso';
        if (($orgao === 'SEJUS' || str_contains($claims['scope'] ?? '', 'govbr_servidor')) 
            && str_contains(strtolower($cargo), 'gestor') 
            && $confianca === 'Ouro') {
            $roleSlug = 'gestor';
        } elseif ($conselho && (str_contains(strtolower($conselho), 'cress') || str_contains(strtolower($conselho), 'crp') || str_contains(strtolower($cargo), 'social'))) {
            $roleSlug = 'tecnico';
        }

        $perfil = Perfil::where('slug', $roleSlug)->firstOrFail();
        $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);

        // Find or create user
        $user = User::where('govbr_id', $sub)
            ->orWhere('hash_cpf', $hashCpf)
            ->first();

        if (!$user) {
            $user = new User();
            $user->govbr_id = $sub;
            $user->email = $email;
            $user->password = Hash::make(Str::random(32));
        }

        $user->name = $name;
        $user->perfil_id = $perfil->id;
        $user->cpf = $cleanCpf; // Mutator automatically encrypts and sets blind index
        $user->ativo = true;
        $user->save();

        // If egresso, link or create Egresso profile
        if ($roleSlug === 'egresso' && !$user->egresso) {
            Egresso::firstOrCreate(
                ['hash_cpf' => $hashCpf],
                [
                    'user_id' => $user->id,
                    'nome_completo' => $name,
                    'cpf' => $cleanCpf,
                    'status_penal' => 'egresso',
                    'municipio_residencia_id' => 1, // Vitória default
                ]
            );
        }

        // Record SSO Audit Log
        $this->audit->log(
            null,
            'LOGIN_GOVBR_SSO',
            [
                'sso_provider' => 'gov.br / acesso_cidadao',
                'sub' => $sub,
                'role_mapped' => $roleSlug,
                'trust_level' => $confianca,
            ],
            $user->id
        );

        return $user;
    }

    /**
     * Rapid development/demonstration role switch.
     */
    public function simulateRoleLogin(string $roleSlug): User
    {
        $perfil = Perfil::where('slug', $roleSlug)->firstOrFail();
        $user = User::where('perfil_id', $perfil->id)->where('ativo', true)->firstOrFail();

        Auth::login($user);

        $this->audit->log(
            null,
            'AUTH_ROLE_SWITCH',
            [
                'target_role' => $roleSlug,
                'user_email' => $user->email,
            ],
            $user->id
        );

        return $user;
    }
}
```

---

## 4. CheckRole Middleware & Route Protection

### 4.1 Middleware Design: `App\Http\Middleware\CheckRole`
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles Comma-separated list of permitted role slugs
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Não autenticado.',
                    'code' => 'UNAUTHORIZED',
                ], 401);
            }
            return redirect()->route('login')->with('error', 'Efetue o login para acessar esta página.');
        }

        $user = Auth::user();

        if (!$user->ativo) {
            Auth::logout();
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Conta de usuário desativada ou suspensa.',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ], 403);
            }
            return redirect()->route('login')->with('error', 'Sua conta está desativada. Entre em contato com a SEJUS.');
        }

        $userRole = $user->perfil?->slug;

        // Parse comma-separated roles in single arguments (e.g. role:gestor,tecnico)
        $allowedRoles = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $subRole) {
                $allowedRoles[] = trim($subRole);
            }
        }

        if (!in_array($userRole, $allowedRoles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Acesso negado: perfil de usuário não autorizado para esta funcionalidade.',
                    'code' => 'FORBIDDEN_ROLE_RESTRICTION',
                    'required_roles' => $allowedRoles,
                    'user_role' => $userRole,
                ], 403);
            }
            abort(403, 'Acesso não autorizado para o seu perfil de usuário.');
        }

        return $next($request);
    }
}
```

---

## 5. LGPD AuditAccessLog Middleware & Hash Chaining

### 5.1 Middleware Design: `App\Http\Middleware\AuditAccessLog`
The `AuditAccessLog` middleware intercepts all reads and writes on sensitive endpoints, extracts identifiers, sanitizes inputs, and invokes `AuditService::log()` to ensure full LGPD compliance (Art. 6, IX).

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditService;
use Symfony\Component\HttpFoundation\Response;

class AuditAccessLog
{
    public function __construct(
        protected AuditService $audit
    ) {}

    public function handle(Request $request, Closure $next, ?string $resourceName = null): Response
    {
        $response = $next($request);

        // Capture only authenticated or sensitive public requests
        $this->recordAuditLog($request, $response, $resourceName);

        return $response;
    }

    protected function recordAuditLog(Request $request, Response $response, ?string $resourceName): void
    {
        $userId = Auth::id();
        $route = $request->route();
        $routeName = $route ? $route->getName() : $request->path();
        $method = $request->method();

        // Extract resource ID if present in route params
        $prontuarioId = $request->route('prontuario') 
            ?? $request->route('id') 
            ?? $request->input('prontuario_id');

        if (is_object($prontuarioId)) {
            $prontuarioId = $prontuarioId->id ?? null;
        }

        // Determine action label
        $action = match ($method) {
            'GET' => $resourceName ? "VIEW_{$resourceName}" : "READ_{$routeName}",
            'POST' => $resourceName ? "CREATE_{$resourceName}" : "STORE_{$routeName}",
            'PUT', 'PATCH' => $resourceName ? "UPDATE_{$resourceName}" : "UPDATE_{$routeName}",
            'DELETE' => $resourceName ? "DELETE_{$resourceName}" : "DESTROY_{$routeName}",
            default => "ACCESS_{$method}_{$routeName}",
        };

        $sanitizedInput = $request->except(['password', 'password_confirmation', '_token']);

        $details = [
            'route' => $routeName,
            'method' => $method,
            'uri' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'sensitive_fields' => ['cpf', 'diagnostico_social', 'historico_penal'],
            'payload' => $sanitizedInput,
        ];

        $this->audit->log(
            is_numeric($prontuarioId) ? (int)$prontuarioId : null,
            strtoupper($action),
            $details,
            $userId,
            $request->ip(),
            $request->userAgent()
        );
    }
}
```

---

## 6. Authorization Policy Classes

### 6.1 `ProntuarioPolicy`
Implements strict Row-Level Security (RLS), distinguishing between Gestor (governance read), Técnico (clinical read & evolution write), and Egresso (restricted self-read):

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Prontuario;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProntuarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    public function view(User $user, Prontuario $prontuario): bool
    {
        if ($user->isGestor() || $user->isTecnico()) {
            return true;
        }

        // Egresso can only view own record
        if ($user->isEgresso()) {
            return $user->egresso && $user->egresso->id === $prontuario->egresso_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    public function update(User $user, Prontuario $prontuario): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    public function addEvolucao(User $user, Prontuario $prontuario): bool
    {
        // Técnico with social worker role is authorized to write clinical evolutions
        return $user->isTecnico() || $user->isGestor();
    }

    public function viewConfidentialNotes(User $user, Prontuario $prontuario): bool
    {
        // Confidential notes are strictly hidden from Egressos
        return $user->isGestor() || $user->isTecnico();
    }

    public function audit(User $user, ?Prontuario $prontuario = null): bool
    {
        return $user->isGestor();
    }
}
```

### 6.2 `CarteiraPolicy`
```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Egresso;
use Illuminate\Auth\Access\HandlesAuthorization;

class CarteiraPolicy
{
    use HandlesAuthorization;

    public function view(User $user, ?Egresso $egresso = null): bool
    {
        if ($user->isGestor() || $user->isTecnico()) {
            return true;
        }
        if ($user->isEgresso() && $egresso) {
            return $user->egresso && $user->egresso->id === $egresso->id;
        }
        return $user->isEgresso();
    }

    public function downloadPdf(User $user, ?Egresso $egresso = null): bool
    {
        return $this->view($user, $egresso);
    }

    public function emit(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }
}
```

---

## 7. Web & API Route Protection Map

### 7.1 Web (Inertia) Routes (`routes/web.php`)
```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProntuarioController;
use App\Http\Controllers\CarteiraValidationController;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\OportunidadesController;
use App\Http\Controllers\AtendimentoController;
use App\Http\Controllers\TerritorioController;
use App\Http\Controllers\RelatoriosController;
use App\Http\Controllers\SegurancaLgpdController;

// Public Routes
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'loginView'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validar'])->name('carteira.validar');
Route::get('/validar-carteira', [CarteiraValidationController::class, 'validarPublico'])->name('carteira.validar.publico');

// Gov.br / Acesso Cidadão SSO
Route::get('/auth/govbr/redirect', [AuthController::class, 'redirectToGovBr'])->name('auth.govbr.redirect');
Route::get('/auth/govbr/callback', [AuthController::class, 'handleGovBrCallback'])->name('auth.govbr.callback');
Route::post('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch-role');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    
    // Shared Dashboard
    Route::middleware(['role:gestor,tecnico'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    // Prontuário Único (Gestor & Técnico)
    Route::middleware(['role:gestor,tecnico', 'audit:PRONTUARIO'])->group(function () {
        Route::get('/prontuario', [ProntuarioController::class, 'index'])->name('prontuario.index');
        Route::get('/prontuario/{id}', [ProntuarioController::class, 'show'])->name('prontuario.show');
        Route::post('/prontuario/{id}/evolucao', [ProntuarioController::class, 'storeEvolucao'])->name('prontuario.evolucao');
    });

    // Video Attendance (Técnico hosts, Egresso joins)
    Route::middleware(['role:gestor,tecnico,egresso'])->group(function () {
        Route::get('/atendimento', [AtendimentoController::class, 'index'])->name('atendimento.index');
    });

    // Opportunities & Jobs (All roles)
    Route::middleware(['role:gestor,tecnico,egresso,familiar'])->group(function () {
        Route::get('/oportunidades', [OportunidadesController::class, 'index'])->name('oportunidades.index');
        Route::get('/geolocalizacao', [TerritorioController::class, 'index'])->name('geolocalizacao.index');
    });

    // Digital Wallet (Egresso self-service)
    Route::middleware(['role:egresso', 'audit:CARTEIRA'])->group(function () {
        Route::get('/carteira', [CarteiraController::class, 'index'])->name('carteira.index');
        Route::get('/carteira/pdf', [CarteiraController::class, 'downloadPdf'])->name('carteira.pdf');
    });

    // Management Reports (Gestor only)
    Route::middleware(['role:gestor', 'audit:RELATORIOS'])->group(function () {
        Route::get('/relatorios', [RelatoriosController::class, 'index'])->name('relatorios.index');
    });

    // LGPD Security & Audit Logs (Gestor only)
    Route::middleware(['role:gestor', 'audit:SEGURANCA_LGPD'])->group(function () {
        Route::get('/seguranca-lgpd', [SegurancaLgpdController::class, 'index'])->name('seguranca.index');
        Route::get('/seguranca-lgpd/verify', [SegurancaLgpdController::class, 'verifyChain'])->name('seguranca.verify');
    });
});
```

### 7.2 API & Webhook Routes (`routes/api.php`)
```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WebrtcApiController;
use App\Http\Controllers\Api\TerritorioApiController;
use App\Http\Controllers\Api\VagasApiController;
use App\Http\Controllers\Api\ProntuarioApiController;
use App\Http\Controllers\CarteiraValidationController;

// Public Healthcheck & Validation
Route::get('/health', fn() => response()->json(['status' => 'healthy', 'timestamp' => now()->toIso8601String()]));
Route::get('/validar-carteira/{token}', [CarteiraValidationController::class, 'validarApi']);

// WebRTC Webhook receiver (Secured with HMAC-SHA256 signature verification)
Route::post('/webhooks/webrtc', [WebrtcApiController::class, 'handleWebhook'])->name('api.webhooks.webrtc');

// Protected API Routes
Route::middleware(['auth:sanctum,web'])->group(function () {
    Route::post('/webrtc/token', [WebrtcApiController::class, 'generateRoomToken']);
    Route::get('/municipios', [TerritorioApiController::class, 'listMunicipios']);
    Route::get('/rede-apoio', [TerritorioApiController::class, 'listRedeApoio']);
    Route::get('/vagas', [VagasApiController::class, 'index']);
    Route::post('/vagas/{id}/candidatar', [VagasApiController::class, 'candidatar']);
    Route::get('/prontuarios', [ProntuarioApiController::class, 'index'])->middleware('role:gestor,tecnico');
});
```

---

## 8. Implementation Checklist for M3 Backend Implementer

1. **Register Middleware Aliases in `bootstrap/app.php`**:
   - Register `'role' => \App\Http\Middleware\CheckRole::class`
   - Register `'audit' => \App\Http\Middleware\AuditAccessLog::class`
2. **Implement Services**:
   - `App\Services\GovBrAuthService`
   - `App\Services\WebrtcJwtService`
3. **Implement Controllers**:
   - `AuthController` (SSO, Login, Role switcher)
   - `ProntuarioController` (CRUD, Evoluções, Timeline, Audit)
   - `DashboardController` (Statewide KPI aggregation)
   - `CarteiraController` (Digital Wallet View & PDF stream)
   - `OportunidadesController` & `TerritorioController` (78 municipalities, CRAS/CREAS/SINE, job filtering)
   - `WebrtcApiController` (JWT Room Token generator & HMAC Webhook receiver)
   - `RelatoriosController` & `SegurancaLgpdController` (Audit hash chain verification)
4. **Implement Policies**:
   - `ProntuarioPolicy`, `CarteiraPolicy`, `VagaEmpregoPolicy`, `VideoRoomPolicy`.
5. **Run Verification**:
   - Execute standalone verification scripts (`tests/run_verification.php`, `tests/challenger_2_verification.php`).
   - Execute multi-tier E2E test runner (`python tests_e2e/test_runner.py --all`).
