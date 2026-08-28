# Handoff Report — Explorer 3: Authentication, User Management, Roles & Route/Link Audit

**Author**: Explorer Survey 3  
**Date**: 2026-08-18  
**Scope**: Survey of Authentication Architecture, Gov.br / Acesso Cidadão Integration, Roles & Permissions, Agile Support User Seeding, User Management UI/API, and Comprehensive 404 Route/Link Audit.

---

## 1. Observation

### 1.1 Authentication & Session Architecture
- **Laravel Framework Version & Stack**:
  - `composer.json` (lines 8-15): Laravel 11 (`"laravel/framework": "^11.0"`), Inertia Laravel (`"inertiajs/inertia-laravel": "^1.0|^2.0"`).
  - `package.json` (lines 10-13): Vue 3 (`"vue": "^3.4.30"`), Inertia Vue 3 (`"@inertiajs/vue3": "^1.2.0"`), TailwindCSS (`"tailwindcss": "^3.4.4"`), Lucide Icons (`"lucide-vue-next": "^0.460.0"`).
  - No third-party auth packages (Breeze, Jetstream, Fortify, Spatie Permission) are installed. The architecture is custom-built on Laravel core Session Auth (`Illuminate\Support\Facades\Auth`) and Inertia.js.
- **Current Routes (`routes/web.php` & `routes/api.php`)**:
  - `routes/web.php` (lines 13-44) exposes public GET routes: `/dashboard`, `/atendimento`, `/oportunidades`, `/carteira`, `/geolocalizacao`, `/prontuario/{id?}`, `/relatorios`, `/seguranca-lgpd`.
  - **No `GET /login` route exists** in `routes/web.php`.
  - `routes/web.php` (lines 50-53) registers:
    - `Route::post('/login', [AuthController::class, 'login'])->name('login.post');`
    - `Route::post('/logout', [AuthController::class, 'logout'])->name('logout');`
    - `Route::post('/auth/govbr/login', [AuthController::class, 'govbrLogin'])->name('auth.govbr.login');`
    - `Route::post('/auth/switch-role', [AuthController::class, 'switchRole'])->name('auth.switch_role');`
  - `routes/api.php` (lines 51-87) wraps protected API endpoints under `middleware(['web'])` instead of `['auth']` or `['role:..']`.
- **Missing Inertia Shared Auth Props**:
  - No `HandleInertiaRequests` middleware exists in `app/Http/Middleware/`.
  - `Inertia::share` is not configured in `bootstrap/app.php` or `AppServiceProvider`.
  - In `resources/js/Layouts/AppLayout.vue` (lines 218-246), `userProfile` uses hardcoded fallback strings because `$page.props.auth.user` is not shared from backend sessions.
- **Middleware & Route Guards**:
  - `app/Http/Middleware/CheckRole.php` (line 28): `return redirect()->route('login')->with('error', 'Efetue o login para acessar esta página.');`
  - Because `route('login')` does not exist, triggering this redirection causes a fatal `RouteNotFoundException: Route [login] not defined.`.

### 1.2 Roles, Permissions, and User Model
- **Database Schema**:
  - `database/migrations/2026_01_01_000001_create_perfis_table.php` defines `perfis` table with: `id`, `nome` (string 50), `slug` (string 50 unique), `descricao` (string 255), `permissoes` (json), `ativo` (boolean default true).
  - `database/migrations/2026_01_01_000003_create_users_table.php` defines `users` table with: `perfil_id` (foreign key to `perfis`), `name`, `email` (unique), `password`, `govbr_id` (nullable unique), `cpf_encrypted` (text), `hash_cpf` (string 64 unique index), `telefone_encrypted`, `foto_url`, `ativo` (boolean).
- **Existing Seeders**:
  - `database/seeders/PerfilSeeder.php` (lines 15-72) seeds 4 profiles: `gestor` (id 1), `tecnico` (id 2), `egresso` (id 3), `familiar` (id 4).
  - **The `suporte` profile is missing** in `PerfilSeeder.php`.
  - `database/seeders/UserSeeder.php` (lines 23-72) seeds 4 demo users:
    1. `gestor@sejus.es.gov.br` (`gestor`)
    2. `marcia.oliveira@sejus.es.gov.br` (`tecnico`)
    3. `lucas.santos@cidadao.es.gov.br` (`egresso`)
    4. `roberto.fonseca@cidadao.es.gov.br` (`egresso`)
  - **The user `suporte.agile@sejus.es.gov.br` is missing** in `UserSeeder.php`.
- **User Model (`app/Models/User.php`)**:
  - Has role check methods `isGestor()`, `isTecnico()`, `isEgresso()`, `isFamiliar()`.
  - Missing `isSuporte()`.
  - Mutators `setCpfAttribute` / `getCpfAttribute` automatically perform AES-256 encryption and HMAC-SHA256 blind indexing via `LgpdSecurityService`.

### 1.3 User Management Interface & API
- **Missing Controller & Routes**:
  - No `UserController.php` or `UsuarioController.php` exists in `app/Http/Controllers/`.
  - No User Management route exists in `routes/web.php` or `routes/api.php`.
  - No `Usuarios.vue` page exists in `resources/js/Pages/`.
  - No navigation entry for User Management exists in `resources/js/Layouts/AppLayout.vue`.

### 1.4 Frontend Navigation, Header, & 404 Audit
- **Navigation in `AppLayout.vue`**:
  - Line 68-76: Dummy `<select id="userRoleSelect">` switches local mock text without authenticating to backend or calling `/auth/switch-role`.
  - **No Logout button** exists anywhere in the header or sidebar.
  - Navigation items in `navigationItems` (lines 248-323):
    1. `Dashboard & KPIs` (`/dashboard`) -> Route exists.
    2. `Atendimento Remoto & Vídeo` (`/atendimento`) -> Route exists.
    3. `Oportunidades & Trabalho` (`/oportunidades`) -> Route exists.
    4. `Carteira Digital & Documentos` (`/carteira`) -> Route exists.
    5. `Mapeamento dos 78 Municípios` (`/geolocalizacao`) -> Route exists.
    6. `Prontuário & Histórico` (`/prontuario`) -> Route exists.
    7. `Relatorios & Análise SEJUS` (`/relatorios`) -> Route exists.
    8. `Segurança & LGPD` (`/seguranca-lgpd`) -> Route exists.
- **Broken Links & 404 Findings**:
  - `resources/js/Pages/Carteira.vue` (line 23 & line 239): The button "Baixar PDF Oficial" links to `pdfDownloadUrl: '/carteira/pdf'`. **No route `/carteira/pdf` exists in `routes/web.php`** -> Returns **404 Not Found**.
  - Direct visit to `/login` -> **404 Not Found**.
  - Direct visit to `/usuarios` -> **404 Not Found**.
- **Alert Calls in Vue Files (Relevant to R1 & R4 Toasts)**:
  - `resources/js/Pages/Atendimento.vue` (line 422): `alert('💾 Registro salvo...')`
  - `resources/js/Pages/Carteira.vue` (line 255): `alert('💳 Requisição de 2ª via...')`
  - `resources/js/Pages/Oportunidades.vue` (line 438): `alert('✉️ Egresso encaminhado...')`
  - `resources/js/Pages/Relatorios.vue` (line 246): `alert('📊 Relatório consolidado...')`
  - `resources/js/Pages/SegurancaLgpd.vue` (line 179): `alert('⚖️ Solicitação protocolada...')`

---

## 2. Logic Chain

```
[Observation 1.1: routes/web.php lacks GET /login and GET /carteira/pdf]
                                  │
                                  ▼
[CheckRole redirects to route('login'), but route('login') is undefined]
                                  │
                                  ▼
[Deduction 1: Unauthenticated access throws RouteNotFoundException / 404]

[Observation 1.2: PerfilSeeder and UserSeeder lack 'suporte' role and 'suporte.agile' user]
                                  │
                                  ▼
[Deduction 2: Requirements R4 acceptance criteria fails without seeding suporte.agile@sejus.es.gov.br]

[Observation 1.3: No UserController, no /usuarios route, no Usuarios.vue page]
                                  │
                                  ▼
[Deduction 3: Requirements R4 User Management (listing, create, edit, CPF, municipality) must be implemented]

[Observation 1.4: Carteira.vue links to /carteira/pdf, which is missing in routes/web.php]
                                  │
                                  ▼
[Deduction 4: Requirements R2 & R5 require adding GET /carteira/pdf to routes/web.php backed by CarteiraPdfService]
```

---

## 3. Caveats
- **Document Generator Microservice**: The external Document Generator service is expected at `http://localhost:8080` with API Key `token-secreto-dev`. When offline during testing or local development, `CarteiraPdfService` must gracefully fall back to local `Dompdf` rendering without throwing unhandled exceptions.
- **PHPUnit App Key Length**: In `phpunit.xml`, the dummy `APP_KEY` string is 27 bytes instead of 32 bytes base64 encoded, which caused cipher errors when running test suite with testing env. Standalone verification scripts (`php tests/run_verification.php` and `php tests/run_m3_verification.php`) executed cleanly with 100% pass rate (65 + 49 = 114 assertions).

---

## 4. Conclusion & Actionable Architecture Plan

### 4.1 Required Implementations for Full Compliance

#### 1. Authentication Layer (R3)
- **Create `resources/js/Pages/Login.vue`**:
  - Official Gov.br (`#1351b4`) & Espírito Santo state visual identity (`#003366`, `#e63946`).
  - Dual login support: Gov.br / Acesso Cidadão SSO one-click simulation + Standard credentials (Email/CPF + Password).
  - Quick-fill demo credentials bar (Suporte Agile, Gestor, Técnico, Egresso).
  - Accessibility toolbar integration & LGPD security badge.
- **Register Web Routes in `routes/web.php`**:
  - `Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');` (or direct Inertia render).
  - Group internal pages (`/dashboard`, `/atendimento`, `/oportunidades`, `/carteira`, `/geolocalizacao`, `/prontuario`, `/relatorios`, `/seguranca-lgpd`, `/usuarios`) under `middleware(['web', 'auth'])` with appropriate role checks.
- **Inertia Shared Props Middleware (`app/Http/Middleware/HandleInertiaRequests.php`)**:
  - Create and register `HandleInertiaRequests` in `bootstrap/app.php` to share `auth.user`, `auth.role`, `auth.permissions`, and `flash` messages to Vue.
- **Logout Action in Navigation (`AppLayout.vue`)**:
  - Add "Sair" button in top header profile section and sidebar footer.
  - Triggers `router.post('/logout')` and redirects cleanly to `/login`.

#### 2. Suporte Profile & Agile Support User (R4)
- **Update `database/seeders/PerfilSeeder.php`**:
  - Add profile:
    ```php
    [
        'id' => 5,
        'nome' => 'Suporte Técnico Agile',
        'slug' => 'suporte',
        'descricao' => 'Administrador do sistema e suporte técnico com acesso irrestrito a todas as funcionalidades, gerenciamento de usuários e infraestrutura.',
        'permissoes' => [
            'prontuario' => ['read', 'write', 'delete', 'export', 'audit'],
            'relatorios' => ['read', 'export'],
            'vagas' => ['read', 'write', 'delete'],
            'cursos' => ['read', 'write', 'delete'],
            'webrtc' => ['manage', 'observe', 'host'],
            'carteira' => ['view', 'emit', 'download'],
            'usuarios' => ['read', 'create', 'edit', 'delete', 'manage'],
            'sistema' => ['manage', 'logs', 'settings'],
        ],
        'ativo' => true,
    ]
    ```
- **Update `database/seeders/UserSeeder.php`**:
  - Seed user:
    - Email: `suporte.agile@sejus.es.gov.br`
    - Password: `Hash::make('secret123')`
    - Name: `Suporte Agile SEJUS`
    - CPF: `99988877700` (auto-encrypted + blind indexed)
    - Telefone: `(27) 3636-5700`
    - Perfil: `suporte`
    - Ativo: `true`
- **Update `app/Models/User.php` & `app/Http/Middleware/CheckRole.php`**:
  - Add `isSuporte(): bool` helper.
  - Allow `suporte` role unrestricted bypass in `CheckRole.php`.

#### 3. User Management Interface & API (R4)
- **Create `app/Http/Controllers/UserController.php`**:
  - `index()`: List users with pagination, filters (name, email, CPF, role, municipality, status), masked CPF.
  - `store()`: Create new user with password hashing, CPF encryption/blind indexing, municipality assignment, audit logging (`USER_CREATED`).
  - `update()`: Update user details, optional password reset, role/municipality change, audit logging (`USER_UPDATED`).
  - `destroy()` / `toggleStatus()`: Deactivate/activate user with audit logging.
- **Create `resources/js/Pages/Usuarios.vue`**:
  - Responsive table showing Avatar, Name, Email, Role badge, Masked CPF, Municipality (from 78 ES list), Status badge, Action buttons.
  - Reactive Modal for Create / Edit with validation and Toast integration.
  - Filter bar (search query, role filter, municipality filter, status filter).
- **Register Web & API Routes**:
  - `Route::get('/usuarios', [UserController::class, 'indexView'])->name('usuarios.index')->middleware(['auth', 'role:gestor,suporte']);`
  - `Route::apiResource('users', UserController::class)->middleware(['auth', 'role:gestor,suporte']);`
- **Update `AppLayout.vue` Navigation**:
  - Add "Gerenciamento de Usuários" under "GESTÃO & GOVERNANÇA" visible for `gestor` and `suporte`.

#### 4. Route & Link 404 Fixes (R2, R5)
- **Register `GET /carteira/pdf` in `routes/web.php`**:
  - Acouple to `CarteiraPdfService::generatePdf()` returning a stream response with headers:
    - `Content-Type: application/pdf`
    - `Content-Disposition: inline; filename="carteira-digital-sejus.pdf"`
  - If user is unauthenticated, use `Egresso::first()` as fallback.

---

## 5. Verification Method

### 5.1 Route & Link Verification Matrix

| Route Path | Expected Method | Middleware | Target Controller / View | Expected Status |
|------------|-----------------|------------|--------------------------|-----------------|
| `/` | GET | `web` | Redirect `/dashboard` | 302 |
| `/login` | GET | `web`, `guest` | `Inertia::render('Login')` | 200 |
| `/login` | POST | `web` | `AuthController::login` | 200 / 401 |
| `/logout` | POST | `web` | `AuthController::logout` | 200 / 302 |
| `/dashboard` | GET | `web`, `auth` | `Inertia::render('Dashboard')` | 200 |
| `/atendimento` | GET | `web`, `auth` | `Inertia::render('Atendimento')` | 200 |
| `/oportunidades` | GET | `web`, `auth` | `Inertia::render('Oportunidades')` | 200 |
| `/carteira` | GET | `web`, `auth` | `Inertia::render('Carteira')` | 200 |
| `/carteira/pdf` | GET | `web` | `CarteiraPdfService` PDF Stream | 200 (PDF) |
| `/geolocalizacao` | GET | `web`, `auth` | `Inertia::render('Geolocalizacao')` | 200 |
| `/prontuario/{id?}` | GET | `web`, `auth` | `Inertia::render('Prontuario')` | 200 |
| `/relatorios` | GET | `web`, `auth`, `role:gestor,tecnico,suporte` | `Inertia::render('Relatorios')` | 200 |
| `/seguranca-lgpd` | GET | `web`, `auth` | `Inertia::render('SegurancaLgpd')` | 200 |
| `/usuarios` | GET | `web`, `auth`, `role:gestor,suporte` | `Inertia::render('Usuarios')` | 200 |
| `/validar-carteira/{token}` | GET | `web` | `CarteiraValidationController::validar` | 200 |
| `/api/users` | GET/POST | `web`, `auth`, `role:gestor,suporte` | `UserController` | 200 / 201 |

### 5.2 Independent Commands to Verify
```bash
# 1. Verify database seeders with Suporte user
php artisan db:seed

# 2. Verify backend test runners
php tests/run_verification.php
php tests/run_m3_verification.php

# 3. Test HTTP routes status codes
php artisan route:list
```
