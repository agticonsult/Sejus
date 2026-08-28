<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles Comma-separated list of permitted role slugs
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Não autenticado.',
                    'code' => 'UNAUTHORIZED',
                ], 401);
            }
            return redirect()->route('login')->with('error', 'Efetue o login para acessar esta página.');
        }

        $user = Auth::user();

        if (!$user->ativo) {
            Auth::logout();
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Conta de usuário desativada ou suspensa.',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ], 403);
            }
            return redirect()->route('login')->with('error', 'Sua conta está desativada. Entre em contato com a SEJUS.');
        }

        $userRole = $user->perfil?->slug;

        // Grant the 'suporte' role unrestricted bypass across all role permission checks
        if ($userRole === 'suporte' || $user->isSuporte()) {
            return $next($request);
        }

        // Parse comma-separated roles in single arguments (e.g. role:gestor,tecnico)
        $allowedRoles = [];
        foreach ($roles as $r) {
            foreach (explode(',', $r) as $subRole) {
                $trimmed = trim($subRole);
                if (!empty($trimmed)) {
                    $allowedRoles[] = $trimmed;
                }
            }
        }

        if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Acesso negado: perfil de usuário não autorizado para esta funcionalidade.',
                    'code' => 'FORBIDDEN_ROLE_RESTRICTION',
                    'required_roles' => $allowedRoles,
                    'user_role' => $userRole,
                ], 403);
            }
            abort(403, 'Acesso não autorizado para o seu perfil de usuário.');
        }

        return $next($request);
    }
}
