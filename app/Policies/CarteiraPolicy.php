<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Egresso;
use Illuminate\Auth\Access\HandlesAuthorization;

class CarteiraPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the carteira digital.
     */
    public function view(User $user, ?Egresso $egresso = null): bool
    {
        if ($user->isGestor() || $user->isTecnico()) {
            return true;
        }

        if ($user->isEgresso()) {
            if ($egresso) {
                return $user->egresso && $user->egresso->id === $egresso->id;
            }
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can download the carteira PDF.
     */
    public function downloadPdf(User $user, ?Egresso $egresso = null): bool
    {
        return $this->view($user, $egresso);
    }

    /**
     * Determine whether the user can emit or reissue credentials.
     */
    public function emit(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }
}
