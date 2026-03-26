<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;

class AbsencePolicy
{
    /**
     * Determine whether the user can update the absence.
     * Only the absence owner may modify it.
     */
    public function update(User $user, Absence $absence): bool
    {
        return $user->id === $absence->user_id;
    }

    /**
     * Determine whether the user can delete the absence.
     * Only the absence owner may delete it.
     */
    public function delete(User $user, Absence $absence): bool
    {
        return $user->id === $absence->user_id;
    }
}
