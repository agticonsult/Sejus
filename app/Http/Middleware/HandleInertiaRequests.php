<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\LgpdSecurityService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $lgpd = app(LgpdSecurityService::class);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->perfil?->slug,
                    'role_name' => $user->perfil?->nome,
                    'perfil' => $user->perfil?->slug,
                    'cpf_masked' => $user->cpf ? $lgpd->maskCpf($user->cpf) : null,
                    'telefone_masked' => $user->telefone ? $lgpd->maskTelefone($user->telefone) : null,
                    'ativo' => (bool) $user->ativo,
                    'egresso_id' => $user->egresso?->id,
                ] : null,
                'role' => $user?->perfil?->slug,
                'permissions' => $user?->perfil?->permissoes ?? [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                'message' => fn () => $request->session()->get('message'),
            ],
        ]);
    }
}
