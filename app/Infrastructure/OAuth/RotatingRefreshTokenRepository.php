<?php

declare(strict_types=1);

namespace App\Infrastructure\OAuth;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\RefreshToken;
use Laravel\Passport\Bridge\RefreshTokenRepository as PassportRefreshTokenRepository;
use Laravel\Passport\Events\RefreshTokenCreated;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * Custom RefreshTokenRepository implementing:
 * 1. Refresh Token Rotation (automatic rotation upon each grant)
 * 2. Revocation Family (detecting token reuse and invalidating the entire family)
 * 3. Audit logging of token issuance, rotation, and reuse events
 */
class RotatingRefreshTokenRepository extends PassportRefreshTokenRepository
{
    /**
     * ID of the token currently being rotated in this request lifecycle.
     */
    protected ?string $currentRotatingTokenId = null;

    public function __construct(
        Dispatcher $events,
        protected AuditLogger $auditLogger,
    ) {
        parent::__construct($events);
    }

    /**
     * {@inheritdoc}
     */
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshToken;
    }

    /**
     * {@inheritdoc}
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $id = $refreshTokenEntity->getIdentifier();
        $accessTokenId = $refreshTokenEntity->getAccessToken()->getIdentifier();
        $expiresAt = $refreshTokenEntity->getExpiryDateTime();

        $familyId = null;
        $isRotation = false;

        if ($this->currentRotatingTokenId !== null) {
            $oldToken = Passport::refreshToken()->newQuery()->whereKey($this->currentRotatingTokenId)->first();

            if ($oldToken && $oldToken->family_id) {
                $family = OAuthRefreshTokenFamily::find($oldToken->family_id);

                if ($family && ! $family->revoked) {
                    $family->update([
                        'current_refresh_token_id' => $id,
                        'expires_at' => $expiresAt,
                    ]);
                    $familyId = $family->id;
                    $isRotation = true;
                }
            }
        }

        // If not rotating an existing family, create a new family for this chain
        if ($familyId === null) {
            $accessToken = Passport::token()->newQuery()->find($accessTokenId);
            $userId = $accessToken?->user_id;
            $clientId = $accessToken?->client_id;

            if ($userId && $clientId) {
                $family = OAuthRefreshTokenFamily::create([
                    'id' => (string) Str::uuid(),
                    'root_access_token_id' => $accessTokenId,
                    'current_refresh_token_id' => $id,
                    'user_id' => $userId,
                    'client_id' => (string) $clientId,
                    'revoked' => false,
                    'expires_at' => $expiresAt,
                ]);

                $familyId = $family->id;
            }
        }

        // Persist refresh token with family_id
        Passport::refreshToken()->forceFill([
            'id' => $id,
            'access_token_id' => $accessTokenId,
            'family_id' => $familyId,
            'revoked' => false,
            'expires_at' => $expiresAt,
        ])->save();

        $this->events->dispatch(new RefreshTokenCreated($id, $accessTokenId));

        // Audit log
        $tokenRecord = Passport::token()->newQuery()->find($accessTokenId);
        $user = $tokenRecord?->user;
        $clientId = (string) ($tokenRecord?->client_id ?? '');

        $this->auditLogger->log(
            event: $isRotation ? 'TOKEN_ROTATED' : 'TOKEN_ISSUED',
            user: $user,
            clientId: $clientId,
            payload: [
                'refresh_token_id' => $id,
                'access_token_id' => $accessTokenId,
                'family_id' => $familyId,
                'is_rotation' => $isRotation,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function revokeRefreshToken(string $tokenId): void
    {
        $this->currentRotatingTokenId = $tokenId;

        Passport::refreshToken()->newQuery()->whereKey($tokenId)->update(['revoked' => true]);
    }

    /**
     * {@inheritdoc}
     */
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        /** @var OAuthRefreshToken|null $token */
        $token = Passport::refreshToken()->newQuery()->whereKey($tokenId)->first();

        if (! $token) {
            return true;
        }

        // If the token is already revoked, this indicates a TOKEN REUSE ATTEMPT!
        if ($token->revoked) {
            $familyId = $token->family_id;

            if ($familyId) {
                /** @var OAuthRefreshTokenFamily|null $family */
                $family = OAuthRefreshTokenFamily::find($familyId);

                if ($family && ! $family->revoked) {
                    // Revoke the entire family to protect the compromised user session
                    $family->revokeFamily('refresh_token_reuse_detected');

                    $this->auditLogger->log(
                        event: 'REFRESH_TOKEN_REUSE_DETECTED',
                        user: $family->user,
                        clientId: (string) $family->client_id,
                        payload: [
                            'attempted_token_id' => $tokenId,
                            'family_id' => $familyId,
                            'action' => 'all_family_tokens_revoked',
                        ]
                    );
                }
            }

            return true;
        }

        // Check if the family itself has been revoked
        if ($token->family_id) {
            $familyRevoked = OAuthRefreshTokenFamily::whereKey($token->family_id)
                ->where('revoked', true)
                ->exists();

            if ($familyRevoked) {
                return true;
            }
        }

        return false;
    }
}
