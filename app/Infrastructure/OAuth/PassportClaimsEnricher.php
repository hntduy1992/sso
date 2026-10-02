<?php

declare(strict_types=1);

namespace App\Infrastructure\OAuth;

use App\Models\User;

/**
 * Passport token finalizer — called after token issuance to inject custom JWT claims.
 *
 * This service is bound via AppServiceProvider and listens to Passport's
 * token creation event to enrich the access_token and id_token with:
 *  - roles: array of role names assigned to the user for the requesting client
 *  - amr:   Authentication Method References (pwd, otp) per OIDC spec
 */
class PassportClaimsEnricher
{
    /**
     * Build the extra claims to embed in the access token JWT.
     *
     * Called from a Passport token event listener or a custom AccessToken model.
     *
     * @param  User  $user  The authenticated user
     * @param  string  $clientId  The OAuth client requesting the token
     * @param  array<string>  $scopes  Requested scopes
     * @param  bool  $mfaUsed  Whether MFA was used during this session
     * @return array<string, mixed>
     */
    public function buildClaims(User $user, string $clientId, array $scopes, bool $mfaUsed = false): array
    {
        $claims = [];

        // Only embed roles if 'roles' scope was requested
        if (in_array('roles', $scopes, strict: true)) {
            $claims['roles'] = $user->getRoleNames()->values()->all();
        }

        // Authentication Method References (RFC 8176)
        // 'pwd' = password auth, 'otp' = TOTP/one-time password
        $amr = ['pwd'];
        if ($mfaUsed) {
            $amr[] = 'otp';
        }
        $claims['amr'] = $amr;

        // Standard OIDC claims
        if (in_array('profile', $scopes, strict: true)) {
            $claims['name'] = $user->name;
            $claims['picture'] = $user->avatar_url;
        }

        if (in_array('email', $scopes, strict: true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return $claims;
    }
}
