<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Prontuario;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProntuarioPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any prontuarios.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can view the prontuario.
     */
    public function view(User $user, Prontuario $prontuario): bool
    {
        if ($user->isGestor() || $user->isTecnico()) {
            return true;
        }

        // Egresso can only view their own prontuario
        if ($user->isEgresso()) {
            return $user->egresso && $user->egresso->id === $prontuario->egresso_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create prontuarios.
     */
    public function create(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can update the prontuario.
     */
    public function update(User $user, Prontuario $prontuario): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can delete/archive the prontuario.
     */
    public function delete(User $user, Prontuario $prontuario): bool
    {
        return $user->isGestor();
    }

    /**
     * Determine whether the user can add evoluções / timeline entries.
     */
    public function addEvolucao(User $user, Prontuario $prontuario): bool
    {
        return $user->isTecnico() || $user->isGestor();
    }

    /**
     * Determine whether the user can view confidential technical notes.
     */
    public function viewConfidentialNotes(User $user, Prontuario $prontuario): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can audit prontuario logs.
     */
    public function audit(User $user, ?Prontuario $prontuario = null): bool
    {
        return $user->isGestor();
    }
}
