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
use App\Models\MobileAccessToken;
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
            $defaultTarget = Auth::user()?->isEmpregador() ? '/oportunidades/inserir-vaga' : '/dashboard';
            return redirect()->intended($defaultTarget);
        }

        return Inertia::render('Login', [
            'govbrAuthUrl' => config('services.govbr.authorize_url'),
        ]);
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
            $deviceName = $request->input('device_name', 'Mobile App');
            $tokenData = MobileAccessToken::createTokenForUser($user, $deviceName);

            return response()->json([
                'status' => 'authenticated',
                'token_type' => 'Bearer',
                'access_token' => $tokenData['plainTextToken'],
                'expires_at' => $tokenData['accessToken']->expires_at?->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->perfil?->slug,
                    'role_name' => $user->perfil?->nome,
                    'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                    'telefone_masked' => $user->telefone ? $this->lgpd->maskTelefone($user->telefone) : null,
                    'ativo' => (bool) $user->ativo,
                    'egresso_id' => $user->egresso?->id,
                ],
            ]);
        }

        $defaultRedirect = $user->isEmpregador() ? '/oportunidades/inserir-vaga' : '/dashboard';
        return redirect()->intended($defaultRedirect)->with('success', 'Bem-vindo ao Conecta Egresso!');
    }

    /**
     * Gov.br / Acesso Cidadão OIDC login callback endpoint.
     * Raw unsigned claim simulation is strictly disabled in production.
     */
    public function govbrLogin(Request $request): JsonResponse|RedirectResponse
    {
        $isInertia = (bool) $request->header('X-Inertia');
        $expectsJson = $request->expectsJson() && !$isInertia;

        if (app()->environment('production') && empty(config('services.govbr.client_secret'))) {
            if ($expectsJson) {
                return response()->json([
                    'error' => 'Autenticação simulada Gov.br desabilitada em produção.',
                    'code' => 'FORBIDDEN_IN_PRODUCTION',
                ], 403);
            }
            abort(403, 'Autenticação simulada Gov.br desabilitada em produção.');
        }

        $claims = $request->all();

        if (empty($claims['sub'])) {
            $claims['sub'] = 'govbr_' . ($claims['cpf'] ?? uniqid());
        }

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

            $defaultRedirect = $user->isEmpregador() ? '/oportunidades/inserir-vaga' : '/dashboard';
            return redirect()->intended($defaultRedirect)->with('success', 'Autenticação Gov.br / Acesso Cidadão realizada com sucesso!');
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
     * Switch role for automated test suites ONLY (strictly disabled with 404 in production).
     */
    public function switchRole(Request $request): JsonResponse|RedirectResponse
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $role = $request->input('role', 'egresso');

        if (!in_array($role, ['gestor', 'tecnico', 'egresso', 'familiar', 'suporte', 'empregador'], true)) {
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

        if (!$user && $request->bearerToken()) {
            $token = $request->bearerToken();
            $hashed = hash('sha256', $token);
            $accessToken = MobileAccessToken::with(['user.perfil', 'user.egresso'])
                ->where('token', $hashed)
                ->first();

            if ($accessToken && $accessToken->isValid() && $accessToken->user && $accessToken->user->ativo) {
                $accessToken->forceFill(['last_used_at' => now()])->save();
                $user = $accessToken->user;
                Auth::setUser($user);
            }
        }

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
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
                'nome_social' => $user->egresso->nome_social,
                'status_penal' => $user->egresso->status_penal,
                'municipio_id' => $user->egresso->municipio_residencia_id,
                'municipio_nome' => $user->egresso->municipio?->nome,
                'macrorregiao' => $user->egresso->municipio?->macrorregiao,
            ] : null,
        ]);
    }

    /**
     * Refresh mobile access token: revoke previous token and issue a fresh 30-day token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = Auth::user() ?? $request->user();

        if (!$user && $request->bearerToken()) {
            $token = $request->bearerToken();
            $hashed = hash('sha256', $token);
            $accessToken = MobileAccessToken::with(['user.perfil', 'user.egresso'])
                ->where('token', $hashed)
                ->first();

            if ($accessToken && $accessToken->isValid() && $accessToken->user) {
                $user = $accessToken->user;
                Auth::setUser($user);
            }
        }

        if (!$user) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->ativo) {
            return response()->json([
                'error' => 'Conta de usuário desativada. Entre em contato com a administração SEJUS.',
                'code' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        $deviceName = 'Mobile App';
        $bearerToken = $request->bearerToken();

        // Invalidate / delete current MobileAccessToken record to prevent replay and token proliferation
        if ($bearerToken) {
            $hashed = hash('sha256', $bearerToken);
            $oldToken = MobileAccessToken::where('token', $hashed)->first();
            if ($oldToken) {
                $deviceName = $oldToken->name ?? $deviceName;
                $oldToken->delete();
            }
        }

        // Issue fresh secure mobile token with 30-day expiration
        $tokenData = MobileAccessToken::createTokenForUser($user, $deviceName, now()->addDays(30));

        // Record audit log
        $this->audit->log(
            null,
            'AUTH_TOKEN_REFRESHED',
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'device_name' => $deviceName,
            ],
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        $user->loadMissing(['perfil', 'egresso']);

        return response()->json([
            'status' => 'refreshed',
            'token_type' => 'Bearer',
            'access_token' => $tokenData['plainTextToken'],
            'expires_at' => $tokenData['accessToken']->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->perfil?->slug,
                'role_name' => $user->perfil?->nome,
                'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                'telefone_masked' => $user->telefone ? $this->lgpd->maskTelefone($user->telefone) : null,
                'ativo' => (bool) $user->ativo,
                'egresso_id' => $user->egresso?->id,
            ],
        ]);
    }

    /**
     * Logout session.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $userId = Auth::id();
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            $hashed = hash('sha256', $bearerToken);
            MobileAccessToken::where('token', $hashed)->delete();
        }

        if ($userId) {
            $this->audit->log(
                null,
                'AUTH_LOGOUT',
                [
                    'user_id' => $userId,
                    'auth_type' => $bearerToken ? 'bearer_token' : 'session',
                ],
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
            return response()->json([
                'status' => 'logged_out',
                'message' => 'Sessão e token revogados com sucesso.',
            ]);
        }

        return redirect()->route('login')->with('success', 'Sessão encerrada com sucesso.');
    }
}
