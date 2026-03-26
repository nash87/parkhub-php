<?php

namespace App\Policies;

use App\Models\ParkingLot;
use App\Models\User;

class ParkingLotPolicy
{
    /**
     * Determine whether the user can create a parking lot.
     * Only admins may create lots.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update a parking lot.
     * Only admins may update lots.
     */
    public function update(User $user, ParkingLot $lot): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete a parking lot.
     * Only admins may delete lots.
     */
    public function delete(User $user, ParkingLot $lot): bool
    {
        return $user->isAdmin();
    }
}
