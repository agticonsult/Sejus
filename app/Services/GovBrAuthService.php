<?php

namespace App\Services;

use App\Models\User;
use App\Models\Perfil;
use App\Models\Egresso;
use App\Services\LgpdSecurityService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GovBrAuthService
{
    public function __construct(
        protected LgpdSecurityService $lgpd,
        protected AuditService $audit
    ) {}

    /**
     * Map raw OIDC claims from Gov.br / Acesso Cidadão into application user entity.
     *
     * @param array $claims Raw JWT / OIDC claims
     * @return User Authenticated User model
     * @throws InvalidArgumentException
     */
    public function handleOidcCallback(array $claims): User
    {
        $sub = $claims['sub'] ?? null;
        if (empty($sub)) {
            throw new InvalidArgumentException("OIDC claim 'sub' é obrigatório.");
        }

        $rawCpf = $claims['cpf'] ?? '';
        $cleanCpf = preg_replace('/\D/', '', (string) $rawCpf);
        if (strlen($cleanCpf) !== 11 || !$this->lgpd->validateCpf($cleanCpf)) {
            throw new InvalidArgumentException("OIDC claim 'cpf' inválido ou ausente.");
        }

        $name = trim($claims['name'] ?? $claims['nome'] ?? 'Cidadão Autenticado');
        $email = $claims['email'] ?? "user_{$cleanCpf}@cidadao.es.gov.br";
        $confianca = $claims['nivel_confianca'] ?? $claims['confianca'] ?? 'Bronze';

        // Role resolution with fail-secure design
        $roleSlug = $this->mapClaimsToRole($claims);

        $perfil = Perfil::where('slug', $roleSlug)->first();
        if (!$perfil) {
            $perfil = Perfil::where('slug', 'egresso')->firstOrFail();
            $roleSlug = 'egresso';
        }

        $hashCpf = $this->lgpd->generateBlindIndex($cleanCpf);

        // Find or initialize user
        $user = User::where('govbr_id', (string) $sub)
            ->orWhere('hash_cpf', $hashCpf)
            ->first();

        if (!$user) {
            $user = new User();
            $user->govbr_id = (string) $sub;
            $user->email = $email;
            $user->password = Hash::make(Str::random(32));
        }

        $user->name = $name;
        $user->perfil_id = $perfil->id;
        $user->cpf = $cleanCpf; // Mutator handles encryption and blind index
        $user->ativo = true;
        $user->save();

        // If egresso, link or create Egresso profile
        if ($roleSlug === 'egresso' && !$user->egresso) {
            Egresso::firstOrCreate(
                ['hash_cpf' => $hashCpf],
                [
                    'user_id' => $user->id,
                    'nome_completo' => $name,
                    'cpf' => $cleanCpf,
                    'status_penal' => 'egresso',
                    'municipio_residencia_id' => 1, // Vitória default
                ]
            );
        }

        // Record SSO Audit Log
        $this->audit->log(
            null,
            'LOGIN_GOVBR_SSO',
            [
                'sso_provider' => 'gov.br / acesso_cidadao',
                'sub' => $sub,
                'role_mapped' => $roleSlug,
                'trust_level' => $confianca,
            ],
            $user->id
        );

        return $user;
    }

    /**
     * Map OIDC claims to internal RBAC role slug following the principle of least privilege.
     *
     * @param array $claims
     * @return string Role slug ('gestor', 'tecnico', 'egresso', 'familiar')
     */
    public function mapClaimsToRole(array $claims): string
    {
        $orgao = strtoupper(trim((string) ($claims['orgao'] ?? '')));
        $cargo = strtolower(trim((string) ($claims['cargo'] ?? '')));
        $scope = (string) ($claims['scope'] ?? '');
        $confianca = strtolower(trim((string) ($claims['nivel_confianca'] ?? $claims['confianca'] ?? 'bronze')));
        $conselho = strtolower(trim((string) ($claims['registro_conselho'] ?? '')));
        $papel = strtolower(trim((string) ($claims['papel'] ?? $claims['role'] ?? '')));

        // Gestor SEJUS: Requires Ouro trust level + SEJUS affiliation or govbr_servidor + gestor/administrador cargo
        if (($confianca === 'ouro' || $confianca === 'gold') &&
            ($orgao === 'SEJUS' || str_contains($scope, 'govbr_servidor')) &&
            (str_contains($cargo, 'gestor') || str_contains($cargo, 'administrador') || str_contains($cargo, 'diretor') || $papel === 'gestor')) {
            return 'gestor';
        }

        // Técnico Escritório Social: Requires professional registration (CRESS/CRP) or technical role
        if (!empty($conselho) && (str_contains($conselho, 'cress') || str_contains($conselho, 'crp'))) {
            return 'tecnico';
        }

        if (str_contains($cargo, 'assistente social') || str_contains($cargo, 'psicolog') || str_contains($cargo, 'tecnico') || $papel === 'tecnico') {
            return 'tecnico';
        }

        // Familiar
        if ($papel === 'familiar' || str_contains($cargo, 'familiar')) {
            return 'familiar';
        }

        // Fail-secure fallback: Default to egresso
        return 'egresso';
    }

    /**
     * Validate trust level (Bronze, Prata, Ouro).
     */
    public function verifyNivelConfianca(string $nivel): bool
    {
        $normalized = strtolower(trim($nivel));
        return in_array($normalized, ['bronze', 'prata', 'silver', 'ouro', 'gold'], true);
    }

    /**
     * Rapid development/demonstration role switch.
     */
    public function simulateRoleLogin(string $roleSlug): User
    {
        $perfil = Perfil::where('slug', $roleSlug)->firstOrFail();
        $user = User::where('perfil_id', $perfil->id)->where('ativo', true)->first();

        if (!$user) {
            // Create a demo user for this role if none exists
            $demoCpf = match ($roleSlug) {
                'gestor' => '52998224725',
                'tecnico' => '70312384798',
                'familiar' => '42873194012',
                default => '84123569804',
            };

            $user = new User();
            $user->name = 'Usuário Demo ' . ucfirst($roleSlug);
            $user->email = "demo.{$roleSlug}@conectaegresso.es.gov.br";
            $user->password = Hash::make('password123');
            $user->perfil_id = $perfil->id;
            $user->cpf = $demoCpf;
            $user->ativo = true;
            $user->save();

            if ($roleSlug === 'egresso') {
                Egresso::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nome_completo' => $user->name,
                        'cpf' => $demoCpf,
                        'status_penal' => 'egresso',
                        'municipio_residencia_id' => 1,
                    ]
                );
            }
        }

        Auth::login($user);

        $this->audit->log(
            null,
            'AUTH_ROLE_SWITCH',
            [
                'target_role' => $roleSlug,
                'user_email' => $user->email,
            ],
            $user->id
        );

        return $user;
    }
}
