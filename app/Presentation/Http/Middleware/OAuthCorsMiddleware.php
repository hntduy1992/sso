<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class OAuthCorsMiddleware
{
    /**
     * Handle incoming OAuth and OIDC requests with dynamic client origin whitelisting.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        // Handle preflight OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            if ($this->isOriginAllowed($origin, $request)) {
                return response('', 204)->withHeaders($this->getCorsHeaders($origin));
            }

            return response()->json([
                'error' => 'cors_origin_not_allowed',
                'error_description' => 'The origin is not whitelisted by any registered OAuth client.',
            ], 403);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($origin && $this->isOriginAllowed($origin, $request)) {
            foreach ($this->getCorsHeaders($origin) as $header => $value) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    /**
     * Determine if an origin is permitted to access the requested OAuth resource.
     */
    private function isOriginAllowed(?string $origin, Request $request): bool
    {
        // Public metadata and key discovery endpoints are open to all origins
        if ($request->is('.well-known/*') || $request->is('oauth/jwks')) {
            return true;
        }

        if (! $origin) {
            return true; // Non-browser / cURL / server-to-server requests
        }

        $parsedOrigin = parse_url($origin);
        $originHost = $parsedOrigin['host'] ?? null;
        $originPort = $parsedOrigin['port'] ?? null;

        if (! $originHost) {
            return false;
        }

        // Whitelist check against all registered OAuth clients
        return Passport::client()
            ->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(function (Client $client) use ($originHost, $originPort) {
                $uris = is_array($client->redirect_uris) ? $client->redirect_uris : [];
                foreach ($uris as $uri) {
                    $clientHost = parse_url($uri, PHP_URL_HOST);
                    $clientPort = parse_url($uri, PHP_URL_PORT);

                    if ($clientHost === $originHost) {
                        // If port is specified in redirect URI, it must match
                        if ($clientPort && $originPort && $clientPort !== $originPort) {
                            continue;
                        }

                        return true;
                    }
                }

                return false;
            });
    }

    /**
     * Get headers for CORS response.
     *
     * @return array<string, string>
     */
    private function getCorsHeaders(?string $origin): array
    {
        return [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept',
            'Access-Control-Max-Age' => '86400',
        ];
    }
}
