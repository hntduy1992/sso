<?php

declare(strict_types=1);

namespace App\Application\OAuth\Services;

use App\Application\OAuth\Jobs\SendBackchannelLogoutJob;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class BackchannelLogoutService
{
    /**
     * Trigger OIDC Backchannel Logout jobs for all satellite apps that the user has sessions or active tokens with.
     */
    public function triggerLogoutForUser(User $user, ?string $initiatorClientId = null): void
    {
        // 1. Collect client IDs from active sessions
        $sessionClientIds = UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('client_id')
            ->pluck('client_id');

        // 2. Collect client IDs from active access tokens
        $tokenClientIds = Passport::token()
            ->newQuery()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->pluck('client_id');

        $allClientIds = $sessionClientIds->merge($tokenClientIds)
            ->filter()
            ->unique()
            ->values();

        if ($initiatorClientId !== null) {
            $allClientIds = $allClientIds->reject(fn ($id) => (string) $id === $initiatorClientId);
        }

        if ($allClientIds->isEmpty()) {
            return;
        }

        /** @var Collection<int, Client> $clients */
        $clients = Passport::client()
            ->newQuery()
            ->whereIn('id', $allClientIds)
            ->whereNotNull('backchannel_logout_uri')
            ->where('revoked', false)
            ->get();

        foreach ($clients as $client) {
            if (! empty($client->backchannel_logout_uri)) {
                SendBackchannelLogoutJob::dispatch($client, (string) $user->id);
            }
        }
    }
}
