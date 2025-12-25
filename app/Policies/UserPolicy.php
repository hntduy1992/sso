<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($model->hasRole('super-admin') && $user->hasRole('super-admin')) {
            return true;
        }
        if ($model->hasRole('admin') && !$user->hasRole('user')) {
            return true;
        }
        return false;
    }
}
