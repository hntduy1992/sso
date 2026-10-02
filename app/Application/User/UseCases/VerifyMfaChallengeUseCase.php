<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\DTOs\MfaChallengeDTO;
use App\Domain\User\Exceptions\InvalidMfaCodeException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Google2FA;

/**
 * Verifies a TOTP challenge (6-digit code) or a recovery code.
 *
 * Flow:
 *  1. User logs in with valid email/password
 *  2. Session stores 'mfa_pending_user_id' instead of logging in
 *  3. This use case verifies the TOTP/recovery code
 *  4. On success, the user is fully authenticated
 */
class VerifyMfaChallengeUseCase
{
    public function __construct(
        private readonly Google2FA $google2fa
    ) {}

    /**
     * @throws InvalidMfaCodeException
     */
    public function execute(User $user, MfaChallengeDTO $dto): void
    {
        if ($dto->isRecoveryCode) {
            $this->verifyRecoveryCode($user, $dto->code);

            return;
        }

        $this->verifyTotpCode($user, $dto->code);
    }

    /**
     * @throws InvalidMfaCodeException
     */
    private function verifyTotpCode(User $user, string $code): void
    {
        try {
            $valid = $this->google2fa->verifyKey(
                decrypt($user->two_factor_secret),
                $code,
                2 // Window: allow ±2 periods (±60 seconds) for clock skew
            );
        } catch (\Throwable) {
            // Library throws on invalid base32 characters or malformed input
            throw new InvalidMfaCodeException;
        }

        if (! $valid) {
            throw new InvalidMfaCodeException;
        }
    }

    /**
     * @throws InvalidMfaCodeException
     */
    private function verifyRecoveryCode(User $user, string $code): void
    {
        /** @var array<string> $recoveryCodes */
        $recoveryCodes = json_decode(
            decrypt($user->two_factor_recovery_codes),
            true
        ) ?? [];

        $matchedIndex = null;
        foreach ($recoveryCodes as $index => $storedCode) {
            if (Hash::check($code, $storedCode)) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            throw new InvalidMfaCodeException;
        }

        // Invalidate the used recovery code (each can only be used once)
        unset($recoveryCodes[$matchedIndex]);

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
        ]);
    }
}
