<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ConfirmMfaUseCase
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Confirm TOTP code and finalize MFA activation, returning generated recovery codes.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    public function execute(User $user, string $code): array
    {
        $secret = session('mfa_setup_secret');

        if (! $secret || ! is_string($secret)) {
            throw ValidationException::withMessages([
                'code' => ['Phiên thiết lập đã hết hạn. Vui lòng tải lại và thử lại.'],
            ]);
        }

        try {
            $valid = $this->google2fa->verifyKey($secret, $code, 2);
        } catch (\Throwable) {
            $valid = false;
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['Mã xác thực 6 chữ số không chính xác.'],
            ]);
        }

        // Generate 8 recovery codes formatted as XXXXX-XXXXX
        $plainCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(Str::random(5).'-'.Str::random(5));
            $plainCodes[] = $plain;
            $hashedCodes[] = Hash::make($plain);
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($hashedCodes)),
        ]);

        session()->forget('mfa_setup_secret');

        $this->auditLogger->log(
            event: 'MFA_ENABLED',
            user: $user,
            payload: ['method' => 'totp']
        );

        return $plainCodes;
    }
}
