<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoRoom;
use Illuminate\Auth\Access\HandlesAuthorization;

class VideoRoomPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view video rooms.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can view the specific video room.
     */
    public function view(User $user, VideoRoom $room): bool
    {
        if ($user->isGestor()) {
            return true;
        }

        if ($user->isTecnico() && $room->tecnico_id === $user->id) {
            return true;
        }

        if ($user->isEgresso() && $user->egresso && $room->egresso_id === $user->egresso->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create a video room.
     */
    public function create(User $user): bool
    {
        return $user->isGestor() || $user->isTecnico();
    }

    /**
     * Determine whether the user can join the video room.
     */
    public function join(User $user, VideoRoom $room): bool
    {
        if ($user->isGestor()) {
            return true; // Gestor can join as supervisor/observer
        }

        if ($user->isTecnico()) {
            return true;
        }

        if ($user->isEgresso() && $user->egresso) {
            return $room->egresso_id === null || $room->egresso_id === $user->egresso->id;
        }

        return false;
    }

    /**
     * Determine whether the user can terminate/end the video room.
     */
    public function end(User $user, VideoRoom $room): bool
    {
        return $user->isGestor() || ($user->isTecnico() && $room->tecnico_id === $user->id);
    }
}
