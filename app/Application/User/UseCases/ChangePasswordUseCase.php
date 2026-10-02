<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;

class ChangePasswordUseCase
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Change user password and immediately revoke all existing sessions, tokens, and token families.
     *
     * @throws ValidationException
     */
    public function execute(User $user, string $newPassword, ?string $currentPassword = null): void
    {
        if ($currentPassword !== null) {
            if (! Hash::check($currentPassword, (string) $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Mật khẩu hiện tại không chính xác.'],
                ]);
            }
        }

        $now = now();

        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => $now,
        ]);

        // Revoke all access tokens owned by the user
        $accessTokenIds = Passport::token()
            ->newQuery()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->pluck('id');

        if ($accessTokenIds->isNotEmpty()) {
            Passport::token()
                ->newQuery()
                ->whereIn('id', $accessTokenIds)
                ->update(['revoked' => true]);

            // Revoke all associated refresh tokens
            OAuthRefreshToken::whereIn('access_token_id', $accessTokenIds)
                ->update(['revoked' => true]);
        }

        // Revoke all token families
        OAuthRefreshTokenFamily::where('user_id', $user->id)
            ->where('revoked', false)
            ->update([
                'revoked' => true,
                'revoked_at' => $now,
                'revoked_reason' => 'password_changed',
            ]);

        // Deactivate all user sessions
        UserSession::where('user_id', $user->id)
            ->update(['is_active' => false]);

        $this->auditLogger->log(
            event: 'PASSWORD_CHANGED',
            user: $user,
            payload: [
                'action' => 'all_tokens_and_sessions_revoked',
                'password_changed_at' => $now->toIso8601String(),
            ]
        );
    }
}
