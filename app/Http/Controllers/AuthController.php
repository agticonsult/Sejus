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
use App\Services\GovBrAuthService;
use App\Services\LgpdSecurityService;
use App\Services\AuditService;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected GovBrAuthService $govBrService,
        protected LgpdSecurityService $lgpd,
        protected AuditService $audit
    ) {}

    /**
     * Show the Gov.br / Conecta Egresso login page.
     */
    public function showLogin(Request $request): InertiaResponse|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/dashboard');
        }

        return Inertia::render('Login');
    }

    /**
     * Standard credentials login (email or CPF + password).
     */
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'login' => 'nullable|string',
            'email' => 'nullable|string',
            'cpf' => 'nullable|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $loginIdentifier = $validated['login'] ?? $validated['email'] ?? $validated['cpf'] ?? null;

        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        if (empty($loginIdentifier)) {
            if ($expectsJson) {
                return response()->json(['error' => 'Identificador de login (email ou CPF) é obrigatório.'], 422);
            }
            return back()->withErrors(['login' => 'Identificador de login (email ou CPF) é obrigatório.'])->with('error', 'Identificador de login é obrigatório.');
        }

        $user = null;

        // Try lookup by email
        if (filter_var($loginIdentifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $loginIdentifier)->first();
        } else {
            // Try lookup by CPF blind index
            $cleanCpf = preg_replace('/\D/', '', $loginIdentifier);
            if (strlen($cleanCpf) === 11) {
                $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);
                $user = User::where('hash_cpf', $hashCpf)->first();
            } else {
                $user = User::where('email', $loginIdentifier)->first();
            }
        }

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            if ($expectsJson) {
                return response()->json([
                    'error' => 'Credenciais inválidas. Verifique seu login e senha.',
                    'code' => 'INVALID_CREDENTIALS',
                ], 401);
            }
            return back()->withErrors(['login' => 'Credenciais inválidas. Verifique seu login e senha.'])->with('error', 'Credenciais inválidas. Verifique seu login e senha.');
        }

        if (!$user->ativo) {
            if ($expectsJson) {
                return response()->json([
                    'error' => 'Conta de usuário desativada. Entre em contato com a administração SEJUS.',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ], 403);
            }
            return back()->withErrors(['login' => 'Conta de usuário desativada. Entre em contato com a administração SEJUS.'])->with('error', 'Conta de usuário desativada.');
        }

        $remember = (bool) ($validated['remember'] ?? false);
        Auth::login($user, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $this->audit->log(
            null,
            'AUTH_LOGIN',
            [
                'email' => $user->email,
                'role' => $user->perfil?->slug,
            ],
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        if ($expectsJson) {
            return response()->json([
                'status' => 'authenticated',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->perfil?->slug,
                    'role_name' => $user->perfil?->nome,
                    'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                    'ativo' => (bool) $user->ativo,
                    'egresso_id' => $user->egresso?->id,
                ],
            ]);
        }

        return redirect()->intended('/dashboard')->with('success', 'Bem-vindo ao Conecta Egresso!');
    }

    /**
     * Simulated Gov.br / Acesso Cidadão OIDC login endpoint.
     */
    public function govbrLogin(Request $request): JsonResponse|RedirectResponse
    {
        $claims = $request->all();

        if (empty($claims['sub'])) {
            $claims['sub'] = 'govbr_' . ($claims['cpf'] ?? uniqid());
        }

        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        try {
            $user = $this->govBrService->handleOidcCallback($claims);
            Auth::login($user);

            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $this->audit->log(
                null,
                'AUTH_GOVBR_LOGIN',
                [
                    'email' => $user->email,
                    'role' => $user->perfil?->slug,
                    'provider' => 'gov.br / acesso_cidadao',
                ],
                $user->id,
                $request->ip(),
                $request->userAgent()
            );

            if ($expectsJson) {
                return response()->json([
                    'status' => 'authenticated',
                    'provider' => 'gov.br / acesso_cidadao',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->perfil?->slug,
                        'role_name' => $user->perfil?->nome,
                        'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                        'ativo' => (bool) $user->ativo,
                        'egresso_id' => $user->egresso?->id,
                    ],
                ]);
            }

            return redirect()->intended('/dashboard')->with('success', 'Autenticação Gov.br / Acesso Cidadão realizada com sucesso!');
        } catch (Throwable $e) {
            if ($expectsJson) {
                return response()->json([
                    'error' => 'Falha na autenticação Gov.br: ' . $e->getMessage(),
                    'code' => 'GOVBR_AUTH_FAILED',
                ], 422);
            }
            return back()->withErrors(['govbr' => 'Falha na autenticação Gov.br: ' . $e->getMessage()])->with('error', 'Falha na autenticação Gov.br.');
        }
    }

    /**
     * Switch demo role rapidly for development / testing.
     */
    public function switchRole(Request $request): JsonResponse|RedirectResponse
    {
        $role = $request->input('role', 'egresso');

        if (!in_array($role, ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte'], true)) {
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => 'Perfil inválido especificado.'], 422);
            }
            return back()->withErrors(['role' => 'Perfil inválido especificado.']);
        }

        try {
            $user = $this->govBrService->simulateRoleLogin($role);

            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'status' => 'role_switched',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->perfil?->slug,
                        'role_name' => $user->perfil?->nome,
                        'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                        'ativo' => (bool) $user->ativo,
                        'egresso_id' => $user->egresso?->id,
                    ],
                ]);
            }

            return redirect()->intended('/dashboard')->with('success', "Perfil alterado para {$role}.");
        } catch (Throwable $e) {
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => 'Erro ao alternar perfil: ' . $e->getMessage()], 500);
            }
            return back()->withErrors(['role' => 'Erro ao alternar perfil: ' . $e->getMessage()]);
        }
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Não autenticado.'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->perfil?->slug,
            'role_name' => $user->perfil?->nome,
            'permissions' => $user->perfil?->permissoes ?? [],
            'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
            'telefone_masked' => $user->telefone ? $this->lgpd->maskTelefone($user->telefone) : null,
            'ativo' => (bool) $user->ativo,
            'egresso' => $user->egresso ? [
                'id' => $user->egresso->id,
                'nome_completo' => $user->egresso->nome_completo,
                'status_penal' => $user->egresso->status_penal,
                'municipio_id' => $user->egresso->municipio_residencia_id,
                'municipio_nome' => $user->egresso->municipio?->nome,
            ] : null,
        ]);
    }

    /**
     * Logout session.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $userId = Auth::id();

        if ($userId) {
            $this->audit->log(
                null,
                'AUTH_LOGOUT',
                ['user_id' => $userId],
                $userId,
                $request->ip(),
                $request->userAgent()
            );
        }

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['status' => 'logged_out', 'message' => 'Sessão encerrada com sucesso.']);
        }

        return redirect()->route('login')->with('success', 'Sessão encerrada com sucesso.');
    }
}
