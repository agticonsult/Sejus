# Plan: CONECTA EGRESSO (SEJUS/ES) - Full Enhancement Suite

## Phase 0: Survey & Codebase Investigation
- Spawn parallel explorers / spec miners to inspect:
  1. Vue components, router configuration, state management, alert() calls, styling (Tailwind/custom), navbar/sidebar layout.
  2. Laravel routing (`routes/web.php`, `routes/api.php`), Controllers, Auth/Middleware, User model, Roles/Permissions, Seeders.
  3. `CarteiraPdfService` or PDF generation libraries (DomPDF / Snappy / TCPDF / etc.), template views, QR code generation.
- Produce unified architecture understanding and consolidate into `PROJECT.md`.

## Phase 1: M1 - Sistema de Notificações (Toasts)
- Design reactive Toast system (`ToastContainer.vue`, `useToast` composable or event bus / Pinia / reactive state).
- Support Success, Error, Warning, Info states, icons/emojis, smooth animations (Vue TransitionGroup), auto-dismiss (3-5s), manual close.
- Replace all native `alert()` calls in `Atendimento.vue`, `Carteira.vue`, `Oportunidades.vue`, `Relatorios.vue`, `SegurancaLgpd.vue`, and all other views/components.

## Phase 2: M2 - Download & Geração de PDF da Carteira Digital
- Verify/Implement `CarteiraPdfService` with proper styling, layout, QR Code generation and egresso data binding.
- Register GET `/carteira/pdf` in `routes/web.php`.
- Provide localhost / unauthenticated fallback to load first egresso from DB so it's instantly testable.
- Connect "Baixar PDF" buttons in frontend to this route.

## Phase 3: M3 - Autenticação Completa (Login & Logout na UI)
- Create `Login.vue` adhering to Gov.br / Acesso Cidadão & Governo do Estado do Espírito Santo visual identity.
- Include SEJUS / ES styling, clear branding, reactive form validation, error toasts.
- Implement Laravel auth endpoints or token/session handlers if needed.
- Protect frontend internal routes with auth navigation guards (with dev fallback / bypass toggle if needed, but authenticating properly against backend credentials).
- Implement secure Logout button in navbar/header with confirmation and state cleanup.

## Phase 4: M4 - Usuário de Suporte (Agile) & Gerenciamento de Usuários
- Ensure `suporte` role exists with full admin/support privileges.
- Add/update database seeder for `suporte.agile@sejus.es.gov.br` (password `secret123`).
- Implement User Management UI (`UserManagement.vue` / `Usuarios.vue`) accessible by `admin` and `suporte`.
- Support CRUD / listing / filtering for user profiles: Gestor, Técnico, Egresso, Familiar.
- Fields: Name, Email, Password, CPF, Municipality, Profile/Role.
- Backend API endpoints / controller for user management with authorization checks.

## Phase 5: M5 - Link & Route Audit, Verification & Forensic Audit
- Audit all routes and links in Vue and Laravel to ensure zero broken links or 404s.
- Run build/linting and automated tests.
- Reviewer, Challenger, and Forensic Auditor verification.
- Gate evaluation and final handoff.
