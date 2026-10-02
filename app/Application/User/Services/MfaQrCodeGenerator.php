<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class MfaQrCodeGenerator
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * Generate an SVG QR code for TOTP authenticator setup.
     */
    public function generateSvg(string $email, string $secret): string
    {
        $company = (string) config('app.name', 'SSO Identity');
        $otpUrl = $this->google2fa->getQRCodeUrl($company, $email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(220),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($otpUrl);
    }
}
