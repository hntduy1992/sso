<?php

declare(strict_types=1);

namespace App\Application\OAuth\Services;

class TokenIdentifierParser
{
    /**
     * Extract the raw token ID (jti) from a plain identifier or a signed JWT string.
     */
    public function extractTokenId(string $token): string
    {
        $parts = explode('.', $token);

        // If it's a JWT (header.payload.signature)
        if (count($parts) === 3) {
            $payload = $this->base64UrlDecode($parts[1]);

            if ($payload) {
                $decoded = json_decode($payload, true);
                if (is_array($decoded) && isset($decoded['jti'])) {
                    return (string) $decoded['jti'];
                }
            }
        }

        return $token;
    }

    /**
     * Decode a base64url string.
     */
    private function base64UrlDecode(string $input): ?string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        $decoded = base64_decode(strtr($input, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
