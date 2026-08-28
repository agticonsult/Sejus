# Project: Conecta Egresso (SEJUS/ES) - Full Authentication, Support User, PDF Microservice, Toasts & Route Audit

## Architecture
- **Backend**: Laravel 11 (PHP 8.2+), Eloquent ORM, Session Auth, SQLite / MySQL, Dompdf, Custom Inertia.js Middleware.
- **Frontend**: Vue 3.4 (Composition API, `<script setup>`), Inertia.js Vue 3, Tailwind CSS 3.4, Lucide Icons, Reactive Singleton Composables.
- **Microservices**: Document Generator API (`http://localhost:8080`, Key: `token-secreto-dev`) with 3-tier fallback to Dompdf and text PDF stream.
- **Security & LGPD**: AES-256-CBC field-level encryption for CPF and sensitive data, HMAC-SHA256 blind indexing, RBAC (Gestor, Técnico, Egresso, Familiar, Suporte).

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Reactive Toast Composable & Store | `useToast.js` singleton state supporting success, error, warning, info, auto-dismiss, manual dismiss | M1 | ORIGINAL_REQUEST §R1 |
| 2 | ToastContainer Component | `<ToastContainer />` with top-right fixed positioning, Lucide icons, smooth transitions, high-contrast support | M1 | ORIGINAL_REQUEST §R1 |
| 3 | Eliminate Native Alerts in Vue Pages | Replace 5 `alert()` calls in Atendimento.vue, Carteira.vue, Oportunidades.vue, Relatorios.vue, SegurancaLgpd.vue with Toasts | M1 | ORIGINAL_REQUEST §R1 |
| 4 | Additional Toast Touchpoints | Add Toast notifications in Prontuario.vue and AppLayout.vue role change / flash message listener | M1 | ORIGINAL_REQUEST §R1 |
| 5 | Document Generator API Integration | In `CarteiraPdfService.php`, POST compiled HTML to `http://localhost:8080` with API Key `token-secreto-dev` | M2 | ORIGINAL_REQUEST §R2 |
| 6 | Graceful Dompdf Fallback | Seamless automatic fallback to local Dompdf / PDF stream on network timeout, offline service, or error | M2 | ORIGINAL_REQUEST §R2 |
| 7 | Carteira Digital PDF Route | Register `GET /carteira/pdf` in `routes/web.php` with CarteiraPdfController returning PDF stream | M2 | ORIGINAL_REQUEST §R2 |
| 8 | Unauthenticated/Demo PDF Fallback | In CarteiraPdfController, fallback to first Egresso when user is unauthenticated or in demo mode | M2 | ORIGINAL_REQUEST §R2 |
| 9 | Gov.br / Acesso Cidadão Login Page | Create `Login.vue` with official Gov.br (`#1351b4`) & ES state design (`#003366`, `#e63946`), dual login (Gov.br SSO simulation + standard credentials), quick-fill demo bar | M3 | ORIGINAL_REQUEST §R3 |
| 10 | Route Protection & GET /login Route | Register `GET /login` in `routes/web.php`, configure `HandleInertiaRequests` middleware for `auth.user` sharing | M3 | ORIGINAL_REQUEST §R3 |
| 11 | Secure Logout Action | Add Logout button in header and sidebar of `AppLayout.vue` posting to `/logout` and redirecting to `/login` | M3 | ORIGINAL_REQUEST §R3 |
| 12 | Suporte Profile & Permissions | Add `suporte` profile (id 5, full admin permissions) in `PerfilSeeder.php`, add `isSuporte()` helper in `User.php` | M4 | ORIGINAL_REQUEST §R4 |
| 13 | Agile Support User Seeder | Seed `suporte.agile@sejus.es.gov.br` (password `secret123`, role `suporte`) in `UserSeeder.php` | M4 | ORIGINAL_REQUEST §R4 |
| 14 | User Management Controller & API | Create `UserController.php` with listing, creation, editing, CPF encryption, municipality selection, audit logging | M4 | ORIGINAL_REQUEST §R4 |
| 15 | User Management Interface | Create `Usuarios.vue` page with responsive table, filters, and modal for create/edit profiles (Gestor, Técnico, Egresso, Familiar, Suporte) | M4 | ORIGINAL_REQUEST §R4 |
| 16 | User Management Navigation | Add "Gerenciamento de Usuários" link in `AppLayout.vue` visible for Gestor and Suporte | M4 | ORIGINAL_REQUEST §R4 |
| 17 | Route & Link 404 Audit | Audit and eliminate all 404 errors across frontend navigation and backend routes | M5 | ORIGINAL_REQUEST §R5 |
| 18 | E2E Testing & Verification Suite | Opaque-box and unit testing covering all features, zero-404 audit, and forensic integrity verification | M6 | ORIGINAL_REQUEST §Acceptance Criteria |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Reactive Toast Notifications | useToast composable, ToastContainer component, AppLayout integration, replace 5 alert() calls | none | PLANNED |
| 2 | PDF Generation & Fallback | CarteiraPdfService API integration, Dompdf fallback, CarteiraPdfController, GET /carteira/pdf route | none | PLANNED |
| 3 | Authentication & Gov.br Login | Login.vue, AuthController showLogin/logout, HandleInertiaRequests middleware, AppLayout logout button | none | PLANNED |
| 4 | Agile Support User & User Mgmt | PerfilSeeder (suporte), UserSeeder (suporte.agile), User model, UserController, Usuarios.vue, navigation | M3 | PLANNED |
| 5 | Route & Link 404 Audit | Verify and test all navigation links, web routes, and API endpoints for zero 404s | M1, M2, M3, M4 | PLANNED |
| 6 | E2E Verification & Final Audit | Automated test suite execution, Challenger stress tests, Forensic Auditor integrity gate | M5 | PLANNED |

## Interface Contracts

### Toast System (`useToast.js` & `ToastContainer.vue`)
- `useToast().success(title: string, message?: string, options?: { duration?: number })`
- `useToast().error(title: string, message?: string, options?: { duration?: number })`
- `useToast().warning(title: string, message?: string, options?: { duration?: number })`
- `useToast().info(title: string, message?: string, options?: { duration?: number })`
- `useToast().toasts: Ref<Array<{ id: number, type: string, title: string, message: string, duration: number }>>`

### Document Generator Microservice (`CarteiraPdfService.php`)
- **Endpoint**: `POST http://localhost:8080/generate` (or configured URL)
- **Headers**: `X-API-Key: token-secreto-dev`, `Content-Type: application/json`
- **Body**: `{"html": "...", "format": "A4", "orientation": "portrait"}`
- **Fallback**: Local `Dompdf\Dompdf` rendering -> Text PDF Stream.
- **Route**: `GET /carteira/pdf` -> Binary Stream (`application/pdf`, `inline; filename="carteira-digital-sejus.pdf"`).

### User Management API (`UserController.php`)
- `GET /usuarios` -> Inertia `Usuarios` page with users list, roles, and municipalities.
- `POST /usuarios` -> Validates `name`, `email`, `password`, `cpf`, `perfil_id`, `municipio_id`. Encrypts CPF, generates blind index, hashes password, writes audit log.
- `PUT /usuarios/{id}` -> Updates user fields, optional password update, updates audit log.
- `DELETE /usuarios/{id}` -> Toggles active status or deletes user with audit log.

## Code Layout
- `app/Services/CarteiraPdfService.php` - PDF rendering, microservice client, and Dompdf fallback
- `app/Http/Controllers/CarteiraPdfController.php` - PDF download route handler
- `app/Http/Controllers/AuthController.php` - Login, logout, gov.br SSO, and role switching
- `app/Http/Controllers/UserController.php` - User management CRUD and listing
- `app/Http/Middleware/HandleInertiaRequests.php` - Inertia shared props (auth user, flash)
- `app/Http/Middleware/CheckRole.php` - Role authorization middleware
- `app/Models/User.php` - User model, LGPD encryption mutators, role helper methods
- `database/seeders/PerfilSeeder.php` - System profiles seeder (includes suporte)
- `database/seeders/UserSeeder.php` - Default users seeder (includes suporte.agile)
- `resources/js/Composables/useToast.js` - Reactive Toast composable
- `resources/js/Components/ToastContainer.vue` - Global Toast container UI
- `resources/js/Pages/Login.vue` - Gov.br / ES styled Login page
- `resources/js/Pages/Usuarios.vue` - User management page with CRUD modal
- `resources/js/Layouts/AppLayout.vue` - Main layout with ToastContainer, Logout button, and User Mgmt nav item
- `routes/web.php` - Web routes definition
