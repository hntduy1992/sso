<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\Exceptions\CannotUnlinkLastAuthMethodException;
use App\Models\User;
use App\Models\UserSocialAccount;

/**
 * Unlinks a social provider from the authenticated user's account.
 *
 * Safety check (BR): Before unlinking, verifies that the user retains
 * at least one remaining authentication method (either a password or
 * another linked social provider). This prevents account lockout.
 */
class UnlinkSocialProviderUseCase
{
    /**
     * @throws CannotUnlinkLastAuthMethodException
     */
    public function execute(User $user, string $provider): void
    {
        $this->ensureUserRetainsAuthMethod($user, $provider);

        UserSocialAccount::where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * @throws CannotUnlinkLastAuthMethodException
     */
    private function ensureUserRetainsAuthMethod(User $user, string $providerToRemove): void
    {
        $hasPassword = ! empty($user->password);

        $remainingProviders = $user->socialAccounts()
            ->where('provider', '!=', $providerToRemove)
            ->exists();

        if (! $hasPassword && ! $remainingProviders) {
            throw new CannotUnlinkLastAuthMethodException(
                'Không thể hủy liên kết "'.ucfirst($providerToRemove).'": '
                .'đây là phương thức đăng nhập duy nhất của tài khoản. '
                .'Hãy đặt mật khẩu trước khi hủy liên kết.'
            );
        }
    }
}
