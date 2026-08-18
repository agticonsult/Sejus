<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * Standard credentials login (email or CPF + password).
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'nullable|string',
            'email' => 'nullable|string',
            'cpf' => 'nullable|string',
            'password' => 'required|string',
        ]);

        $loginIdentifier = $validated['login'] ?? $validated['email'] ?? $validated['cpf'] ?? null;

        if (empty($loginIdentifier)) {
            return response()->json(['error' => 'Identificador de login (email ou CPF) é obrigatório.'], 422);
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
            return response()->json([
                'error' => 'Credenciais inválidas. Verifique seu login e senha.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (!$user->ativo) {
            return response()->json([
                'error' => 'Conta de usuário desativada. Entre em contato com a administração SEJUS.',
                'code' => 'ACCOUNT_DEACTIVATED',
            ], 403);
        }

        Auth::login($user);

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

        return response()->json([
            'status' => 'authenticated',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->perfil?->slug,
                'role_name' => $user->perfil?->nome,
                'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                'ativo' => $user->ativo,
                'egresso_id' => $user->egresso?->id,
            ],
        ]);
    }

    /**
     * Simulated Gov.br / Acesso Cidadão OIDC login endpoint.
     */
    public function govbrLogin(Request $request): JsonResponse
    {
        $claims = $request->all();

        if (empty($claims['sub'])) {
            $claims['sub'] = 'govbr_' . ($claims['cpf'] ?? uniqid());
        }

        try {
            $user = $this->govBrService->handleOidcCallback($claims);
            Auth::login($user);

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
                    'ativo' => $user->ativo,
                    'egresso_id' => $user->egresso?->id,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Falha na autenticação Gov.br: ' . $e->getMessage(),
                'code' => 'GOVBR_AUTH_FAILED',
            ], 422);
        }
    }

    /**
     * Switch demo role rapidly for development / testing.
     */
    public function switchRole(Request $request): JsonResponse
    {
        $role = $request->input('role', 'egresso');

        if (!in_array($role, ['gestor', 'tecnico', 'egresso', 'familiar'], true)) {
            return response()->json(['error' => 'Perfil inválido especificado.'], 422);
        }

        try {
            $user = $this->govBrService->simulateRoleLogin($role);

            return response()->json([
                'status' => 'role_switched',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->perfil?->slug,
                    'role_name' => $user->perfil?->nome,
                    'cpf_masked' => $user->cpf ? $this->lgpd->maskCpf($user->cpf) : null,
                    'ativo' => $user->ativo,
                    'egresso_id' => $user->egresso?->id,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao alternar perfil: ' . $e->getMessage()], 500);
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
            'ativo' => $user->ativo,
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
    public function logout(Request $request): JsonResponse
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

        return response()->json(['status' => 'logged_out', 'message' => 'Sessão encerrada com sucesso.']);
    }
}
