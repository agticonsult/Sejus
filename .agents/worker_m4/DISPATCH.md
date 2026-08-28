## 2026-08-18T13:27:48Z

You are Worker M4 for the Conecta Egresso project.
Your Working Directory: d:\Agile\projeto dia 18\.agents\worker_m4
Original Request File: d:\Agile\projeto dia 18\.agents\ORIGINAL_REQUEST.md
Project Document: d:\Agile\projeto dia 18\PROJECT.md
Test Infrastructure Document: d:\Agile\projeto dia 18\TEST_READY.md
Survey Report: d:\Agile\projeto dia 18\.agents\explorer_survey_3\handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Mission: Implement Milestone 4 - Agile Support User & User Management CRUD Interface.
1. Read ORIGINAL_REQUEST.md, PROJECT.md, and explorer_survey_3/handoff.md.
2. Update `database/seeders/PerfilSeeder.php`:
   - Add `suporte` profile (id 5, nome 'Suporte Técnico Agile', slug 'suporte', descricao 'Administrador do sistema e suporte técnico com acesso irrestrito...', permissoes with full permissions across all modules: prontuario, relatorios, vagas, cursos, webrtc, carteira, usuarios, sistema, ativo true).
3. Update `database/seeders/UserSeeder.php`:
   - Seed initial support user:
     - Name: `Suporte Agile SEJUS`
     - Email: `suporte.agile@sejus.es.gov.br`
     - Password: `Hash::make('secret123')`
     - CPF: `99988877700` (encrypted + blind indexed)
     - Telefone: `(27) 3636-5700`
     - Perfil ID: 5 (`suporte`)
     - Ativo: `true`
4. Update `app/Models/User.php`:
   - Add `isSuporte(): bool` helper method.
   - Ensure `municipio_id` / `municipio()` relationship if applicable, and ensure CPF mutator/accessor encrypts and indexes correctly.
5. Update `app/Http/Middleware/CheckRole.php`:
   - Grant the `suporte` role unrestricted bypass across all role permission checks.
6. Create `app/Http/Controllers/UserController.php`:
   - `indexView(Request $request)`: renders Inertia page `Usuarios` with users list (with masked CPFs, perfil, municipio, search/filters), perfis list, and 78 ES municipios list.
   - `index(Request $request)`: API JSON list with pagination and filters.
   - `store(Request $request)`: validates name, email, password, cpf, perfil_id, municipio_id. Encrypts CPF, calculates blind index, hashes password, saves user, writes audit log (`USER_CREATED`).
   - `update(Request $request, $id)`: updates user, hashes password if provided, updates CPF/perfil/municipio, writes audit log (`USER_UPDATED`).
   - `destroy(Request $request, $id)` / `toggleStatus()`: deletes or deactivates user with audit log (`USER_DELETED` / `USER_STATUS_TOGGLED`).
7. Create `resources/js/Pages/Usuarios.vue`:
   - Responsive, filterable data table of users (Avatar, Name, Email, Role badge, Masked CPF, Municipality from 78 ES list, Status badge, Actions).
   - Filter bar: Search by name/email/CPF, filter by Role (Gestor, Técnico, Egresso, Familiar, Suporte), filter by Municipality, filter by Status.
   - Modal for "Novo Usuário" / "Editar Usuário" with validation (Nome, Email, Senha, CPF com máscara, Perfil, Município) and reactive `useToast` feedback.
8. Update `resources/js/Layouts/AppLayout.vue`:
   - Add "Gerenciamento de Usuários" navigation item in the sidebar / navigation menu under "GESTÃO & GOVERNANÇA", linking to `/usuarios` and visible to `gestor` and `suporte`.
9. Register routes in `routes/web.php`:
   - `Route::get('/usuarios', [UserController::class, 'indexView'])->name('usuarios.index');`
   - `Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');`
   - `Route::put('/usuarios/{id}', [UserController::class, 'update'])->name('usuarios.update');`
   - `Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->name('usuarios.destroy');`
10. Execute database seeder:
   - `php artisan db:seed`
11. Verify implementation:
   - Run `npm run build` to verify frontend compilation.
   - Run `python tests_e2e/test_runner.py --filter user_mgmt` and `python tests_e2e/tier1_features/test_f12_f16_user_mgmt_suporte.py`.
   - Run `php artisan test --filter=SuporteProfileTest` and `php artisan test --filter=UserControllerTest`.
12. Write comprehensive handoff to `d:\Agile\projeto dia 18\.agents\worker_m4\handoff.md` and notify parent orchestrator via send_message.
