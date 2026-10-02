<?php

declare(strict_types=1);

namespace App\Application\OAuth\UseCases;

use App\Application\OAuth\Services\TokenIdentifierParser;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\OAuthRefreshToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

class RevokeTokenUseCase
{
    public function __construct(
        private readonly TokenIdentifierParser $tokenParser,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Revoke a token according to RFC 7009.
     */
    public function execute(string $token, ?string $tokenTypeHint, Client $client): void
    {
        $tokenId = $this->tokenParser->extractTokenId($token);

        // If hint is refresh_token, check refresh tokens first
        if ($tokenTypeHint === 'refresh_token') {
            if ($this->revokeRefreshToken($tokenId, $client)) {
                return;
            }

            $this->revokeAccessToken($tokenId, $client);

            return;
        }

        // Default or access_token hint: check access tokens first
        if ($this->revokeAccessToken($tokenId, $client)) {
            return;
        }

        $this->revokeRefreshToken($tokenId, $client);
    }

    /**
     * Attempt to revoke an access token and its paired refresh token.
     */
    private function revokeAccessToken(string $tokenId, Client $client): bool
    {
        /** @var Token|null $accessToken */
        $accessToken = Passport::token()->newQuery()->find($tokenId);

        if (! $accessToken) {
            return false;
        }

        $accessToken->update(['revoked' => true]);

        // Revoke linked refresh token
        OAuthRefreshToken::where('access_token_id', $accessToken->id)->update(['revoked' => true]);

        $this->auditLogger->log(
            event: 'TOKEN_REVOKED',
            user: $accessToken->user,
            clientId: (string) $client->id,
            payload: [
                'token_id' => $tokenId,
                'token_type' => 'access_token',
            ]
        );

        return true;
    }

    /**
     * Attempt to revoke a refresh token and its paired access token.
     */
    private function revokeRefreshToken(string $tokenId, Client $client): bool
    {
        /** @var OAuthRefreshToken|null $refreshToken */
        $refreshToken = OAuthRefreshToken::find($tokenId);

        if (! $refreshToken) {
            return false;
        }

        $refreshToken->update(['revoked' => true]);

        // Revoke linked access token
        Passport::token()->where('id', $refreshToken->access_token_id)->update(['revoked' => true]);

        $this->auditLogger->log(
            event: 'TOKEN_REVOKED',
            user: $refreshToken->accessToken?->user,
            clientId: (string) $client->id,
            payload: [
                'token_id' => $tokenId,
                'token_type' => 'refresh_token',
            ]
        );

        return true;
    }
}
