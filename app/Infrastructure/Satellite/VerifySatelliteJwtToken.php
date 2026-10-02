<?php

declare(strict_types=1);

namespace App\Infrastructure\Satellite;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Reusable Middleware for Satellite Applications:
 * Stateless RS256 JWT Verification via IdP JWKS / Public Key.
 *
 * Satellite apps verify JWT tokens LOCALLY without calling the SSO IdP on every request,
 * providing microsecond verification performance and zero load on the IdP.
 */
class VerifySatelliteJwtToken
{
    /**
     * Handle an incoming request with Bearer JWT token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with(strtolower($header), 'bearer ')) {
            return response()->json([
                'error' => 'unauthenticated',
                'error_description' => 'Missing or invalid Bearer token authorization header.',
            ], 401);
        }

        $jwtString = substr($header, 7);

        try {
            $publicKeyPem = $this->getIdpPublicKey();

            $config = Configuration::forAsymmetricSigner(
                new Sha256,
                InMemory::plainText('dummy'),
                InMemory::plainText($publicKeyPem)
            );

            $token = $config->parser()->parse($jwtString);

            // 1. Verify signature with IdP's Public Key
            $signer = new Sha256;
            if (! $config->validator()->validate($token, new SignedWith($signer, InMemory::plainText($publicKeyPem)))) {
                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'Token signature verification failed.',
                ], 401);
            }

            // 2. Verify token is not expired (with 1 minute clock skew tolerance)
            $clock = new SystemClock(new \DateTimeZone('UTC'));
            if (! $config->validator()->validate($token, new LooseValidAt($clock, new \DateInterval('PT60S')))) {
                return response()->json([
                    'error' => 'token_expired',
                    'error_description' => 'The access token has expired.',
                ], 401);
            }

            // Attach claims to request attributes for downstream controllers
            $claims = $token->claims()->all();
            $request->attributes->set('jwt_sub', $claims['sub'] ?? null);
            $request->attributes->set('jwt_scopes', $claims['scopes'] ?? []);
            $request->attributes->set('jwt_roles', $claims['roles'] ?? []);
            $request->attributes->set('jwt_claims', $claims);

        } catch (Throwable $e) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Malformed or invalid JWT: '.$e->getMessage(),
            ], 401);
        }

        return $next($request);
    }

    /**
     * Retrieve the SSO IdP's RSA Public Key from cache or fetch from JWKS endpoint.
     */
    protected function getIdpPublicKey(): string
    {
        return Cache::remember('sso_idp_public_key', now()->addHours(24), function () {
            // In a production satellite app, this URL points to https://idp.yourdomain.com/oauth/jwks
            $jwksUrl = rtrim(config('services.sso.url', config('app.url')), '/').'/oauth/jwks';

            $response = Http::timeout(5)->get($jwksUrl);

            if ($response->successful()) {
                $keys = $response->json('keys');
                if (! empty($keys[0]['x5c'][0])) {
                    // Reconstruct PEM from x5c certificate
                    return "-----BEGIN CERTIFICATE-----\n".
                        chunk_split($keys[0]['x5c'][0], 64, "\n").
                        "-----END CERTIFICATE-----\n";
                }
            }

            // Fallback to local storage if running in the same codebase
            $localKeyPath = storage_path('oauth-public.key');
            if (file_exists($localKeyPath)) {
                return file_get_contents($localKeyPath);
            }

            throw new \RuntimeException('Unable to obtain SSO IdP Public Key for JWT verification.');
        });
    }
}
