<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VagaEmprego;
use Illuminate\Auth\Access\HandlesAuthorization;

class VagaEmpregoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any jobs.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the job.
     */
    public function view(?User $user, VagaEmprego $vaga): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create jobs.
     */
    public function create(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can update the job.
     */
    public function update(User $user, VagaEmprego $vaga): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can delete the job.
     */
    public function delete(User $user, VagaEmprego $vaga): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can apply for a job.
     */
    public function candidatar(User $user, VagaEmprego $vaga): bool
    {
        return $user->isEgresso() || $user->isTecnico() || $user->isGestor();
    }
}
