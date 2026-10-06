<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Models\UserSession;

class AdminResetMfaUseCase
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Admin disables / removes MFA (TOTP) and recovery codes for a target user.
     *
     * @throws UserNotFoundException
     */
    public function execute(int $targetUserId, ?string $reason = null, ?User $adminUser = null): void
    {
        $targetUser = User::find($targetUserId);
        if (! $targetUser) {
            throw new UserNotFoundException("Không tìm thấy người dùng với ID: {$targetUserId}");
        }

        $targetUser->update([
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        // Deactivate all web sessions to ensure fresh login
        UserSession::where('user_id', $targetUser->id)
            ->update(['is_active' => false]);

        $this->auditLogger->log(
            event: 'ADMIN_RESET_MFA',
            user: $adminUser,
            payload: [
                'target_user_id' => $targetUser->id,
                'target_user_email' => $targetUser->email,
                'reason' => $reason ?: 'Quản trị viên xóa xác thực 2 bước theo yêu cầu hỗ trợ.',
            ]
        );
    }
}
