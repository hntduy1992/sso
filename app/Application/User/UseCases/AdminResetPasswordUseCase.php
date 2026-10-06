<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;

class AdminResetPasswordUseCase
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Admin forces password reset for a target user and revokes all active sessions/tokens.
     *
     * @throws UserNotFoundException
     */
    public function execute(int $targetUserId, string $newPassword, string $reason, ?User $adminUser = null): void
    {
        $targetUser = User::find($targetUserId);
        if (! $targetUser) {
            throw new UserNotFoundException("Không tìm thấy người dùng với ID: {$targetUserId}");
        }

        $targetUser->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        // Revoke all access tokens
        $accessTokenIds = Passport::token()
            ->newQuery()
            ->where('user_id', $targetUser->id)
            ->where('revoked', false)
            ->pluck('id');

        if ($accessTokenIds->isNotEmpty()) {
            Passport::token()
                ->newQuery()
                ->whereIn('id', $accessTokenIds)
                ->update(['revoked' => true]);

            OAuthRefreshToken::whereIn('access_token_id', $accessTokenIds)
                ->update(['revoked' => true]);
        }

        // Revoke all refresh token families
        OAuthRefreshTokenFamily::where('user_id', $targetUser->id)
            ->where('revoked', false)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => 'admin_reset_password',
            ]);

        // Deactivate all web sessions
        UserSession::where('user_id', $targetUser->id)
            ->update(['is_active' => false]);

        $this->auditLogger->log(
            event: 'ADMIN_RESET_PASSWORD',
            user: $adminUser,
            payload: [
                'target_user_id' => $targetUser->id,
                'target_user_email' => $targetUser->email,
                'reason' => $reason,
            ]
        );
    }
}
