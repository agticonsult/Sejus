<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MobileAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $mode
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $mode = null)
    {
        $token = $request->bearerToken();

        if ($token) {
            $hashed = hash('sha256', $token);
            $accessToken = MobileAccessToken::with(['user.perfil', 'user.egresso'])
                ->where('token', $hashed)
                ->first();

            if (!$accessToken || $accessToken->isExpired()) {
                return response()->json([
                    'error' => 'Não autenticado ou token inválido.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }

            $user = $accessToken->user;
            if (!$user || !$user->ativo) {
                return response()->json([
                    'error' => 'Conta de usuário desativada. Entre em contato com a administração SEJUS.',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ], 403);
            }

            $accessToken->forceFill(['last_used_at' => now()])->save();
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
            $request->attributes->set('mobile_access_token', $accessToken);

            return $next($request);
        }

        // If an Authorization header was provided but no valid Bearer token could be extracted
        if ($request->hasHeader('Authorization')) {
            return response()->json([
                'error' => 'Não autenticado ou token inválido.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Support session authentication fallback
        if (Auth::check()) {
            $user = Auth::user();
            if (!$user->ativo) {
                return response()->json([
                    'error' => 'Conta de usuário desativada. Entre em contato com a administração SEJUS.',
                    'code' => 'ACCOUNT_DEACTIVATED',
                ], 403);
            }
            return $next($request);
        }

        // If optional mode is specified, allow through unauthenticated
        if ($mode === 'optional') {
            return $next($request);
        }

        return response()->json([
            'error' => 'Não autenticado ou token inválido.',
            'code' => 'UNAUTHENTICATED',
        ], 401);
    }
}
