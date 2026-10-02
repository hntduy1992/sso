<?php

declare(strict_types=1);

namespace App\Application\OAuth\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class OAuthClientAuthenticator
{
    /**
     * Authenticate an OAuth client from HTTP Basic Auth headers or POST body.
     */
    public function authenticate(Request $request): ?Client
    {
        [$clientId, $clientSecret] = $this->extractCredentials($request);

        if (! $clientId) {
            return null;
        }

        /** @var Client|null $client */
        $client = Passport::client()->newQuery()->find($clientId);

        if (! $client || $client->revoked) {
            return null;
        }

        // Public clients do not have/require a client secret
        if ($client->client_type === 'PUBLIC' || (empty($client->secret) && $client->client_type === null)) {
            return $client;
        }

        // Confidential clients MUST provide a matching client secret
        if (empty($clientSecret)) {
            return null;
        }

        $secretMatches = Hash::check((string) $clientSecret, (string) $client->secret)
            || hash_equals((string) $client->secret, (string) $clientSecret);

        if (! $secretMatches) {
            return null;
        }

        return $client;
    }

    /**
     * Extract clientId and clientSecret from Basic auth or request body.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractCredentials(Request $request): array
    {
        // 1. Try HTTP Basic Authorization header
        if ($header = $request->header('Authorization')) {
            if (str_starts_with(strtolower($header), 'basic ')) {
                $decoded = base64_decode(substr($header, 6), true);
                if ($decoded && str_contains($decoded, ':')) {
                    [$id, $secret] = explode(':', $decoded, 2);

                    return [$id, $secret];
                }
            }
        }

        // 2. Fall back to POST body parameters
        $id = $request->input('client_id');
        $secret = $request->input('client_secret');

        return [$id ? (string) $id : null, $secret ? (string) $secret : null];
    }
}
