<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\OAuth\PassportClaimsEnricher;
use Laravel\Passport\Token as PassportToken;

/**
 * Custom Passport Token model that injects additional JWT claims.
 *
 * Passport uses this model when building the JWT access token payload.
 * We override getClaims() to add roles, amr, and OIDC standard claims.
 *
 * Registered in AppServiceProvider via Passport::useTokenModel().
 */
class OAuthAccessToken extends PassportToken
{
    /**
     * Get extra JWT claims to embed in the access token.
     *
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        $parentClaims = parent::getClaims();

        /** @var User|null $user */
        $user = $this->user;

        if (! $user) {
            return $parentClaims;
        }

        /** @var PassportClaimsEnricher $enricher */
        $enricher = app(PassportClaimsEnricher::class);

        $mfaUsed = (bool) session('mfa_verified_for_token', false);

        $customClaims = $enricher->buildClaims(
            user: $user,
            clientId: (string) $this->client_id,
            scopes: $this->scopes ?? [],
            mfaUsed: $mfaUsed,
        );

        return array_merge($parentClaims, $customClaims);
    }
}
