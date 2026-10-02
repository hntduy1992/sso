<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DisableMfaUseCase
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Disable MFA after password confirmation.
     *
     * @throws ValidationException
     */
    public function execute(User $user, string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Mật khẩu xác nhận không chính xác.'],
            ]);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $this->auditLogger->log(
            event: 'MFA_DISABLED',
            user: $user,
        );
    }
}
