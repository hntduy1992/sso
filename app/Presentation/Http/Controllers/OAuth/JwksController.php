<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * JWKS (JSON Web Key Set) endpoint
 *
 * Exposes the public key so satellite apps can verify JWT signatures locally
 * without calling back to the IdP on every request.
 *
 * GET /oauth/jwks
 *
 * Cached for 1 hour — key rotation will need cache clearing.
 */
class JwksController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $jwks = Cache::remember('oauth:jwks', 3600, function () {
            return $this->buildJwks();
        });

        return response()
            ->json($jwks)
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Build JWKS from the Passport public key.
     *
     * @return array{keys: array<int, array<string, string>>}
     */
    private function buildJwks(): array
    {
        $publicKeyPath = config('passport.public_key')
            ?? storage_path('oauth-public.key');

        $publicKeyContent = file_get_contents($publicKeyPath);

        if (! $publicKeyContent) {
            abort(500, 'Public key not found. Run: php artisan passport:keys');
        }

        // Parse the modulus and exponent using OpenSSL
        $details = openssl_pkey_get_details(
            openssl_pkey_get_public($publicKeyContent)
        );

        if (! $details || ! isset($details['rsa'])) {
            abort(500, 'Unable to parse RSA key details.');
        }

        $rsa = $details['rsa'];

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => $this->generateKeyId($publicKeyContent),
                    'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
                    'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
                ],
            ],
        ];
    }

    /**
     * Generate a stable key ID from the public key content.
     * Used by JWT libraries to select the correct key when multiple keys exist.
     */
    private function generateKeyId(string $publicKeyContent): string
    {
        return substr(hash('sha256', $publicKeyContent), 0, 16);
    }
}
