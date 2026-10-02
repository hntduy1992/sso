<?php

declare(strict_types=1);

namespace App\Application\OAuth\Services;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class BackchannelLogoutTokenGenerator
{
    /**
     * Generate an OIDC Back-Channel Logout Token (JWT) signed with RSA SHA256.
     *
     * Per OpenID Connect Back-Channel Logout 1.0 Section 2.4:
     * - iss: REQUIRED. Issuer URL.
     * - sub: REQUIRED (or sid). Subject identifier.
     * - aud: REQUIRED. Client ID of the relying party.
     * - iat: REQUIRED. Issued at time.
     * - jti: REQUIRED. Unique JWT ID.
     * - events: REQUIRED. JSON object containing "http://schemas.openid.net/event/backchannel-logout": {}.
     * - nonce: MUST NOT be present.
     */
    public function generate(string $userId, string $clientId): string
    {
        $privateKeyPath = Passport::keyPath('oauth-private.key');
        $publicKeyPath = Passport::keyPath('oauth-public.key');

        $config = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::file($privateKeyPath),
            InMemory::file($publicKeyPath)
        );

        $now = new DateTimeImmutable;

        $token = $config->builder()
            ->issuedBy(config('app.url'))
            ->permittedFor($clientId)
            ->relatedTo($userId)
            ->identifiedBy((string) Str::uuid())
            ->issuedAt($now)
            ->withClaim('events', [
                'http://schemas.openid.net/event/backchannel-logout' => new \stdClass,
            ])
            ->getToken($config->signer(), $config->signingKey());

        return $token->toString();
    }
}
