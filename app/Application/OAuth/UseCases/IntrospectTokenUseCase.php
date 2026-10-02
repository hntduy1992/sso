<?php

declare(strict_types=1);

namespace App\Application\OAuth\UseCases;

use App\Application\OAuth\Services\TokenIdentifierParser;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;

class IntrospectTokenUseCase
{
    public function __construct(
        private readonly TokenIdentifierParser $tokenParser,
    ) {}

    /**
     * Inspect a token according to RFC 7662.
     *
     * @return array<string, mixed>
     */
    public function execute(string $token, ?string $tokenTypeHint, Client $client): array
    {
        $tokenId = $this->tokenParser->extractTokenId($token);

        /** @var Token|null $accessToken */
        $accessToken = Passport::token()->newQuery()->find($tokenId);

        // If not found as access token, check if it's a refresh token
        if (! $accessToken) {
            /** @var OAuthRefreshToken|null $refreshToken */
            $refreshToken = OAuthRefreshToken::find($tokenId);

            if ($refreshToken && ! $refreshToken->revoked && $refreshToken->expires_at?->isFuture()) {
                $accessToken = Passport::token()->newQuery()->find($refreshToken->access_token_id);
            }
        }

        if (! $accessToken || $accessToken->revoked) {
            return ['active' => false];
        }

        // Check expiration
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return ['active' => false];
        }

        /** @var User|null $user */
        $user = User::find($accessToken->user_id);

        if (! $user || ! $user->isActive()) {
            return ['active' => false];
        }

        // Security check: If password was changed after token issuance, token is invalid
        if ($user->password_changed_at && $accessToken->created_at) {
            if ($accessToken->created_at->lt($user->password_changed_at)) {
                return ['active' => false];
            }
        }

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];

        return [
            'active' => true,
            'scope' => implode(' ', $accessToken->scopes ?? []),
            'client_id' => (string) $accessToken->client_id,
            'sub' => (string) $user->id,
            'username' => $user->email,
            'email' => $user->email,
            'token_type' => 'Bearer',
            'exp' => $accessToken->expires_at?->timestamp,
            'iat' => $accessToken->created_at?->timestamp,
            'nbf' => $accessToken->created_at?->timestamp,
            'roles' => $roles,
        ];
    }
}
