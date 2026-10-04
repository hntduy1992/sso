<?php

declare(strict_types=1);

namespace App\Application\OAuth\Services;

use App\Models\ApplicationAccessGrant;
use App\Models\User;
use App\Models\UserPosition;

/**
 * Decides whether a user may sign in to an OAuth application.
 *
 * Deny-by-default: a non-admin user needs either a personal grant, or an
 * active position (ended_at IS NULL) in a department that holds a grant.
 * System administrators are always allowed so they can never lock themselves out.
 */
class ApplicationAccessService
{
    public function canAccess(User $user, string $clientId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ApplicationAccessGrant::query()
            ->where('client_id', $clientId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereIn(
                        'department_id',
                        UserPosition::query()
                            ->active()
                            ->where('user_id', $user->id)
                            ->select('department_id')
                    );
            })
            ->exists();
    }
}
