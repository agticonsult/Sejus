<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Models\User;
use App\Models\Perfil;
use App\Models\MunicipioEs;
use App\Models\Egresso;
use App\Services\LgpdSecurityService;
use App\Services\AuditService;

class UserController extends Controller
{
    public function __construct(
        protected LgpdSecurityService $lgpd,
        protected AuditService $audit
    ) {}

    /**
     * Render the Usuarios management Inertia view with full datasets.
     */
    public function indexView(Request $request): InertiaResponse
    {
        $query = User::with(['perfil', 'municipio']);

        // Filter by text search (name, email, or CPF)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $cleanDigits = preg_replace('/\D/', '', $q);

            $query->where(function ($sub) use ($q, $cleanDigits) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('email', 'ILIKE', "%{$q}%");

                if (strlen($cleanDigits) === 11) {
                    $hashCpf = $this->lgpd->generateBlindIndex($cleanDigits);
                    $sub->orWhere('hash_cpf', $hashCpf);
                }
            });
        }

        // Filter by role slug or perfil_id
        if ($request->filled('role')) {
            $roleSlug = trim($request->input('role'));
            $query->whereHas('perfil', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug);
            });
        } elseif ($request->filled('perfil_id')) {
            $query->where('perfil_id', (int) $request->input('perfil_id'));
        }

        // Filter by municipality
        if ($request->filled('municipio_id')) {
            $munId = (int) $request->input('municipio_id');
            $query->where('municipio_id', $munId);
        }

        // Filter by status (active/inactive)
        if ($request->has('ativo') && $request->input('ativo') !== '') {
            $ativo = filter_var($request->input('ativo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ativo !== null) {
                $query->where('ativo', $ativo);
            }
        }

        $perPage = (int) $request->input('per_page', 20);
        $usersPaginator = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();

        $formattedUsers = collect($usersPaginator->items())->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'perfil_id' => $u->perfil_id,
                'role' => $u->perfil?->slug ?? 'egresso',
                'role_name' => $u->perfil?->nome ?? 'Egresso',
                'cpf_masked' => $u->cpf ? $this->lgpd->maskCpf($u->cpf) : null,
                'telefone' => $u->telefone,
                'ativo' => (bool) $u->ativo,
                'municipio_id' => $u->municipio_id,
                'municipio_nome' => $u->municipio?->nome ?? 'Não informado',
                'microrregiao' => $u->municipio?->microrregiao,
                'created_at' => $u->created_at?->toIso8601String(),
            ];
        });

        $perfis = Perfil::where('ativo', true)->orderBy('id', 'asc')->get(['id', 'nome', 'slug', 'descricao']);
        $municipios = MunicipioEs::orderBy('nome', 'asc')->get(['id', 'codigo_ibge', 'nome', 'microrregiao', 'macrorregiao', 'tem_escritorio_fisico']);

        // Overall stats for dashboard counters
        $stats = [
            'total' => User::count(),
            'ativos' => User::where('ativo', true)->count(),
            'inativos' => User::where('ativo', false)->count(),
            'gestores_tecnicos' => User::whereHas('perfil', fn($p) => $p->whereIn('slug', ['gestor', 'tecnico']))->count(),
            'egressos_familiares' => User::whereHas('perfil', fn($p) => $p->whereIn('slug', ['egresso', 'familiar']))->count(),
            'suporte' => User::whereHas('perfil', fn($p) => $p->where('slug', 'suporte'))->count(),
        ];

        return Inertia::render('Usuarios', [
            'users' => [
                'data' => $formattedUsers,
                'current_page' => $usersPaginator->currentPage(),
                'last_page' => $usersPaginator->lastPage(),
                'per_page' => $usersPaginator->perPage(),
                'total' => $usersPaginator->total(),
            ],
            'perfis' => $perfis,
            'municipios' => $municipios,
            'filters' => $request->only(['q', 'role', 'perfil_id', 'municipio_id', 'ativo', 'per_page']),
            'stats' => $stats,
        ]);
    }

    /**
     * API JSON list of users with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['perfil', 'municipio']);

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $cleanDigits = preg_replace('/\D/', '', $q);

            $query->where(function ($sub) use ($q, $cleanDigits) {
                $sub->where('name', 'ILIKE', "%{$q}%")
                    ->orWhere('email', 'ILIKE', "%{$q}%");

                if (strlen($cleanDigits) === 11) {
                    $hashCpf = $this->lgpd->generateBlindIndex($cleanDigits);
                    $sub->orWhere('hash_cpf', $hashCpf);
                }
            });
        }

        if ($request->filled('role')) {
            $roleSlug = trim($request->input('role'));
            $query->whereHas('perfil', function ($q) use ($roleSlug) {
                $q->where('slug', $roleSlug);
            });
        } elseif ($request->filled('perfil_id')) {
            $query->where('perfil_id', (int) $request->input('perfil_id'));
        }

        if ($request->filled('municipio_id')) {
            $munId = (int) $request->input('municipio_id');
            $query->where('municipio_id', $munId);
        }

        if ($request->has('ativo')) {
            $ativo = filter_var($request->input('ativo'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($ativo !== null) {
                $query->where('ativo', $ativo);
            }
        }

        $users = $query->orderBy('id', 'desc')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'perfil_id' => $u->perfil_id,
                'role' => $u->perfil?->slug ?? 'egresso',
                'role_name' => $u->perfil?->nome ?? 'Egresso',
                'cpf_masked' => $u->cpf ? $this->lgpd->maskCpf($u->cpf) : null,
                'telefone' => $u->telefone,
                'ativo' => (bool) $u->ativo,
                'municipio_id' => $u->municipio_id,
                'municipio_nome' => $u->municipio?->nome ?? 'Não informado',
                'created_at' => $u->created_at?->toIso8601String(),
            ];
        });

        $perfis = Perfil::where('ativo', true)->orderBy('id', 'asc')->get(['id', 'nome', 'slug']);

        return response()->json([
            'status' => 'success',
            'users' => $users,
            'total' => $users->count(),
            'perfis' => $perfis,
        ]);
    }

    /**
     * Store a newly created user in database with encryption, blind indexing, and audit logging.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|string|min:6',
            'cpf' => 'required|string',
            'perfil_id' => 'required|integer|exists:perfis,id',
            'municipio_id' => 'nullable|integer',
            'telefone' => 'nullable|string|max:50',
            'ativo' => 'nullable|boolean',
        ]);

        // 1. Algorithmic CPF verification
        $cleanCpf = preg_replace('/\D/', '', (string) $validated['cpf']);
        if (strlen($cleanCpf) !== 11 || !$this->lgpd->validateCpf($cleanCpf)) {
            if ($expectsJson) {
                return response()->json([
                    'error' => 'CPF inválido: dígitos verificadores incorretos ou formato inválido.',
                    'errors' => ['cpf' => ['O CPF informado é inválido.']],
                    'code' => 'INVALID_CPF',
                ], 422);
            }
            return back()->withErrors(['cpf' => 'O CPF informado é inválido.'])->withInput();
        }

        // 2. Blind index collision check for duplicate CPF
        $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);
        if (User::where('hash_cpf', $hashCpf)->exists()) {
            if ($expectsJson) {
                return response()->json([
                    'error' => 'CPF já cadastrado no sistema.',
                    'errors' => ['cpf' => ['Este CPF já pertence a outro usuário cadastrado.']],
                    'code' => 'DUPLICATE_CPF',
                ], 409);
            }
            return back()->withErrors(['cpf' => 'Este CPF já pertence a outro usuário cadastrado.'])->withInput();
        }

        // 3. Resolve municipality ID (supports both primary id and 7-digit IBGE code)
        $municipioId = null;
        if (!empty($validated['municipio_id'])) {
            $munInput = (int) $validated['municipio_id'];
            $mun = MunicipioEs::where('id', $munInput)->orWhere('codigo_ibge', $munInput)->first();
            $municipioId = $mun?->id;
        }

        // 4. Create User entity
        $user = new User();
        $user->name = trim($validated['name']);
        $user->email = strtolower(trim($validated['email']));
        $user->password = Hash::make($validated['password']);
        $user->perfil_id = (int) $validated['perfil_id'];
        $user->municipio_id = $municipioId;
        $user->cpf = $cleanCpf; // Mutator automatically encrypts and sets hash_cpf
        $user->telefone = $validated['telefone'] ?? null;
        $user->ativo = $request->boolean('ativo', true);
        $user->save();

        // 5. If role is Egresso, link or create Egresso record
        if ($user->perfil?->slug === 'egresso') {
            Egresso::firstOrCreate(
                ['hash_cpf' => $hashCpf],
                [
                    'user_id' => $user->id,
                    'nome_completo' => $user->name,
                    'cpf' => $cleanCpf,
                    'status_penal' => 'egresso',
                    'municipio_residencia_id' => $municipioId ?? MunicipioEs::first()?->id ?? 1,
                ]
            );
        }

        // 6. Cryptographic Audit Log
        $this->audit->log(
            null,
            'USER_CREATED',
            [
                'created_user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->perfil?->slug,
                'municipio_id' => $user->municipio_id,
            ],
            Auth::id(),
            $request->ip(),
            $request->userAgent()
        );

        $userPayload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->perfil?->slug,
            'role_name' => $user->perfil?->nome,
            'perfil_id' => $user->perfil_id,
            'cpf' => $validated['cpf'],
            'cpf_masked' => $this->lgpd->maskCpf($user->cpf),
            'telefone' => $user->telefone,
            'ativo' => (bool) $user->ativo,
            'municipio_id' => $user->municipio_id,
            'municipio_nome' => $user->municipio?->nome ?? 'Não informado',
        ];

        if ($expectsJson) {
            return response()->json([
                'status' => 'created',
                'message' => 'Usuário criado com sucesso.',
                'user' => $userPayload,
            ], 201);
        }

        return redirect()->route('usuarios.index')->with('success', "Usuário {$user->name} cadastrado com sucesso!");
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        $user = User::find($id);
        if (!$user) {
            if ($expectsJson) {
                return response()->json(['error' => 'Usuário não encontrado.'], 404);
            }
            return back()->with('error', 'Usuário não encontrado.');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|min:2|max:150',
            'email' => 'nullable|email|max:191|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'cpf' => 'nullable|string',
            'perfil_id' => 'nullable|integer|exists:perfis,id',
            'municipio_id' => 'nullable|integer',
            'telefone' => 'nullable|string|max:50',
            'ativo' => 'nullable|boolean',
        ]);

        if (isset($validated['name'])) {
            $user->name = trim($validated['name']);
        }

        if (isset($validated['email'])) {
            $user->email = strtolower(trim($validated['email']));
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (!empty($validated['cpf'])) {
            $cleanCpf = preg_replace('/\D/', '', (string) $validated['cpf']);
            if (strlen($cleanCpf) !== 11 || !$this->lgpd->validateCpf($cleanCpf)) {
                if ($expectsJson) {
                    return response()->json([
                        'error' => 'CPF informado é inválido.',
                        'errors' => ['cpf' => ['O CPF informado é inválido.']],
                        'code' => 'INVALID_CPF',
                    ], 422);
                }
                return back()->withErrors(['cpf' => 'O CPF informado é inválido.']);
            }

            $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);
            if (User::where('hash_cpf', $hashCpf)->where('id', '!=', $user->id)->exists()) {
                if ($expectsJson) {
                    return response()->json([
                        'error' => 'CPF já cadastrado por outro usuário.',
                        'errors' => ['cpf' => ['Este CPF já pertence a outro usuário.']],
                        'code' => 'DUPLICATE_CPF',
                    ], 409);
                }
                return back()->withErrors(['cpf' => 'Este CPF já pertence a outro usuário.']);
            }

            $user->cpf = $cleanCpf;
        }

        if (isset($validated['perfil_id'])) {
            $user->perfil_id = (int) $validated['perfil_id'];
        }

        if (array_key_exists('municipio_id', $validated)) {
            if (!empty($validated['municipio_id'])) {
                $munInput = (int) $validated['municipio_id'];
                $mun = MunicipioEs::where('id', $munInput)->orWhere('codigo_ibge', $munInput)->first();
                $user->municipio_id = $mun?->id;
            } else {
                $user->municipio_id = null;
            }
        }

        if (array_key_exists('telefone', $validated)) {
            $user->telefone = $validated['telefone'];
        }

        if (array_key_exists('ativo', $validated)) {
            $user->ativo = (bool) $validated['ativo'];
        }

        $user->save();

        // Cryptographic Audit Log
        $this->audit->log(
            null,
            'USER_UPDATED',
            [
                'updated_user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->perfil?->slug,
                'municipio_id' => $user->municipio_id,
                'ativo' => $user->ativo,
            ],
            Auth::id(),
            $request->ip(),
            $request->userAgent()
        );

        $userPayload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->perfil?->slug,
            'role_name' => $user->perfil?->nome,
            'perfil_id' => $user->perfil_id,
            'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
            'telefone' => $user->telefone,
            'ativo' => (bool) $user->ativo,
            'municipio_id' => $user->municipio_id,
            'municipio_nome' => $user->municipio?->nome ?? 'Não informado',
        ];

        if ($expectsJson) {
            return response()->json([
                'status' => 'updated',
                'message' => 'Usuário atualizado com sucesso.',
                'user' => $userPayload,
            ], 200);
        }

        return redirect()->route('usuarios.index')->with('success', "Dados do usuário {$user->name} atualizados com sucesso!");
    }

    /**
     * Delete / Deactivate a user with cryptographic audit trail.
     */
    public function destroy(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        $user = User::find($id);
        if (!$user) {
            if ($expectsJson) {
                return response()->json(['error' => 'Usuário não encontrado.'], 404);
            }
            return back()->with('error', 'Usuário não encontrado.');
        }

        // Soft deactivation for security & audit preservation
        $user->ativo = false;
        $user->save();

        $this->audit->log(
            null,
            'USER_DELETED',
            [
                'target_user_id' => $user->id,
                'email' => $user->email,
                'action' => 'DEACTIVATE_USER',
            ],
            Auth::id(),
            $request->ip(),
            $request->userAgent()
        );

        if ($expectsJson) {
            return response()->json([
                'status' => 'deactivated',
                'message' => 'Usuário desativado com sucesso.',
                'user_id' => $user->id,
            ], 200);
        }

        return redirect()->route('usuarios.index')->with('success', "Usuário {$user->name} foi desativado do sistema.");
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        $user = User::findOrFail($id);
        $user->ativo = !$user->ativo;
        $user->save();

        $this->audit->log(
            null,
            'USER_STATUS_TOGGLED',
            [
                'target_user_id' => $user->id,
                'email' => $user->email,
                'new_status' => $user->ativo ? 'ativo' : 'inativo',
            ],
            Auth::id(),
            $request->ip(),
            $request->userAgent()
        );

        if ($expectsJson) {
            return response()->json([
                'status' => 'toggled',
                'ativo' => (bool) $user->ativo,
                'message' => $user->ativo ? 'Usuário ativado com sucesso.' : 'Usuário desativado com sucesso.',
            ], 200);
        }

        return redirect()->route('usuarios.index')->with('success', "Status do usuário {$user->name} alterado.");
    }
}
