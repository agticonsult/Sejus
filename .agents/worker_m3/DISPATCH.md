## 2026-08-18T10:19:58-03:00
You are Worker M3 for the Conecta Egresso project.
Your Working Directory: d:\Agile\projeto dia 18\.agents\worker_m3
Original Request File: d:\Agile\projeto dia 18\.agents\ORIGINAL_REQUEST.md
Project Document: d:\Agile\projeto dia 18\PROJECT.md
Test Infrastructure Document: d:\Agile\projeto dia 18\TEST_READY.md
Survey Report: d:\Agile\projeto dia 18\.agents\explorer_survey_3\handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Mission: Implement Milestone 3 - Complete Authentication (Login/Logout, Gov.br UI, Session, Protected Routes).
1. Read ORIGINAL_REQUEST.md, PROJECT.md, and explorer_survey_3/handoff.md.
2. Create `resources/js/Pages/Login.vue`:
   - Visual Identity: Gov.br official blue (`#1351b4`), Governo ES colors (`#003366`, `#e63946`), SEJUS institutional badge.
   - Dual Authentication:
     a) Primary Gov.br / Acesso Cidadão SSO one-click simulation button (`Entrar com Gov.br / Acesso Cidadão`) calling `POST /auth/govbr/login`.
     b) Standard credentials form (Email / CPF input, Password input, Show/Hide password toggle, "Lembrar-me" checkbox, Submit button calling `POST /login`).
   - Quick-Fill Demo Credentials bar (buttons for Suporte Agile, Gestor Estadual, Técnico Social, Egresso Cidadão that autofill form).
   - Reactive feedback using `useToast` for error/success notifications.
   - High contrast mode support and accessibility toolbar integration.
3. Update `app/Http/Controllers/AuthController.php`:
   - Add `showLogin()` method rendering Inertia `Login` page (redirect to `/dashboard` if already authenticated).
   - Ensure `login()` handles email or CPF authentication, validates password using `Hash::check()`, authenticates user with `Auth::login()`, regenerates session (`$request->session()->regenerate()`), and redirects to `/dashboard` (or returns JSON for API).
   - Ensure `logout()` logs out user (`Auth::logout()`), invalidates session, regenerates CSRF token, and redirects to `/login` (or returns redirect response).
   - Ensure `govbrLogin()` resolves user and authenticates session smoothly.
4. Create `app/Http/Middleware/HandleInertiaRequests.php` and configure in `bootstrap/app.php`:
   - Share `auth.user`, `auth.role`, `auth.permissions`, and `flash` messages into `$page.props` for all Inertia components.
5. Update `resources/js/Layouts/AppLayout.vue`:
   - Add visible **Sair / Logout** button in the header user menu and sidebar footer.
   - Wire Logout button to call `router.post('/logout')` with feedback.
   - Dynamically bind user profile name and email from `$page.props.auth.user` if available.
6. In `routes/web.php`:
   - Register `Route::get('/login', [AuthController::class, 'showLogin'])->name('login');`.
7. Verify implementation:
   - Run `npm run build` to verify frontend compilation.
   - Run `python tests_e2e/test_runner.py --filter auth` or `python tests_e2e/tier1_features/test_f09_f11_auth_govbr.py`.
   - Run `php tests/run_m3_verification.php` or `php artisan test --filter=AuthControllerTest`.
8. Write comprehensive handoff to `d:\Agile\projeto dia 18\.agents\worker_m3\handoff.md` and notify parent orchestrator via send_message.
