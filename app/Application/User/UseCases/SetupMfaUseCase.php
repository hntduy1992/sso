<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Application\User\Services\MfaQrCodeGenerator;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class SetupMfaUseCase
{
    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly MfaQrCodeGenerator $qrCodeGenerator,
    ) {}

    /**
     * Generate a new TOTP secret and QR code for user setup.
     *
     * @return array{secret: string, qr_code_svg: string}
     */
    public function execute(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();

        session(['mfa_setup_secret' => $secret]);

        $svg = $this->qrCodeGenerator->generateSvg($user->email, $secret);

        return [
            'secret' => $secret,
            'qr_code_svg' => $svg,
        ];
    }
}
