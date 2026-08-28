# Changes Log — Milestone 3: Complete Authentication

## Summary of Changes

### 1. `resources/js/Pages/Login.vue` (New Component)
- Implemented full Gov.br and Governo do Estado do Espírito Santo (SEJUS/ES) visual identity.
- Primary Gov.br / Acesso Cidadão SSO one-click simulation button calling `POST /auth/govbr/login`.
- Standard credentials authentication form supporting Email or CPF + Password, password visibility toggle, and "Lembrar-me" checkbox, calling `POST /login`.
- Quick-fill demo credentials bar with instant prefill buttons for:
  - **Suporte Agile** (`suporte.agile@sejus.es.gov.br`)
  - **Gestor Estadual** (`gestor@sejus.es.gov.br`)
  - **Técnico Social** (`marcia.oliveira@sejus.es.gov.br`)
  - **Egresso Cidadão** (`lucas.santos@cidadao.es.gov.br`)
- Integrated `useToast` composable for reactive feedback (success, error, info notifications).
- Embedded `AccessibilityToolbar` with high-contrast mode and font zoom support.
- Institutional security banner highlighting AES-256 field encryption and LGPD Blind Index protection.

### 2. `app/Http/Middleware/HandleInertiaRequests.php` (New Middleware)
- Created Inertia middleware extending `Inertia\Middleware`.
- Shared `auth.user`, `auth.role`, `auth.permissions` and `flash` messages (`success`, `error`, `warning`, `info`, `message`) globally into `$page.props`.

### 3. `bootstrap/app.php` (Updated Configuration)
- Registered `\App\Http\Middleware\HandleInertiaRequests::class` in the web middleware pipeline (`append`).

### 4. `routes/web.php` (Updated Web Routes)
- Registered `Route::get('/login', [AuthController::class, 'showLogin'])->name('login');`.

### 5. `app/Http/Controllers/AuthController.php` (Updated Controller)
- Added `showLogin(Request $request)` to render the Inertia `Login` page or redirect to `/dashboard` if already authenticated.
- Enhanced `login(Request $request)` to support both Email and CPF blind index lookup, password validation with `Hash::check()`, user activation check, session regeneration (`$request->session()->regenerate()`), and audit logging (`AUTH_LOGIN`).
- Enhanced `govbrLogin(Request $request)` to resolve user claims via `GovBrAuthService`, authenticate session, regenerate session, and log audit (`AUTH_GOVBR_LOGIN`).
- Enhanced `logout(Request $request)` to log audit (`AUTH_LOGOUT`), call `Auth::logout()`, invalidate session (`$request->session()->invalidate()`), regenerate CSRF token, and redirect to `login` or return JSON.

### 6. `resources/js/Layouts/AppLayout.vue` (Updated Main Layout)
- Added visible **Sair / Logout** action buttons in both the header user section and sidebar footer.
- Wired Logout action to `router.post('/logout')` with Toast feedback.
- Dynamically bound user profile name, initials, and email from `$page.props.auth.user`.
- Added support for `suporte` role in profile view and navigation items.

### 7. Models and Helper Fixes
- Added `cpf`, `telefone` to `$fillable` in `app/Models/User.php`.
- Added `cpf`, `rg`, `filiacao_mae`, `endereco`, `telefone` to `$fillable` in `app/Models/Egresso.php`.
- Improved resilience of `GovBrAuthService.php` when creating fallbacks in unseeded testing environments.
