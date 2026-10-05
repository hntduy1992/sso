<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and attach enterprise-grade security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // 1. Prevent Clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // 2. Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 4. Content Security Policy (CSP)
        // Disallows framing ('frame-ancestors none') while permitting necessary Inertia/Vite assets and configured domains
        $extraOrigins = $this->resolveAllowedOrigins();

        $csp = "default-src 'self'{$extraOrigins}; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval'{$extraOrigins}; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$extraOrigins}; "
            ."font-src 'self' https://fonts.bunny.net data:{$extraOrigins}; "
            ."img-src 'self' data: https:{$extraOrigins}; "
            ."connect-src 'self' *; "
            ."frame-ancestors 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'{$extraOrigins} http://127.0.0.1:* http://localhost:*; ";

        $response->headers->set('Content-Security-Policy', $csp);

        // 5. Permissions Policy
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // 6. Modern XSS Protection header (disables legacy buggy auditor in favor of CSP)
        $response->headers->set('X-XSS-Protection', '0');

        // 7. Strict-Transport-Security (HSTS) only on HTTPS connections (RFC 6797)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }

    /**
     * Resolve allowed origins from APP_URL, ASSET_URL, and CSP_ALLOWED_HOSTS.
     */
    private function resolveAllowedOrigins(): string
    {
        $origins = [];

        // 1. Origin from APP_URL
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            $parsed = parse_url($appUrl);
            if (! empty($parsed['host'])) {
                $scheme = $parsed['scheme'] ?? 'https';
                $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                $origins[] = "{$scheme}://{$parsed['host']}{$port}";
            }
        }

        // 2. Origin from ASSET_URL
        $assetUrl = env('ASSET_URL');
        if (is_string($assetUrl) && $assetUrl !== '') {
            $parsedAsset = parse_url($assetUrl);
            if (! empty($parsedAsset['host'])) {
                $scheme = $parsedAsset['scheme'] ?? 'https';
                $port = isset($parsedAsset['port']) ? ':'.$parsedAsset['port'] : '';
                $origins[] = "{$scheme}://{$parsedAsset['host']}{$port}";
            }
        }

        // 3. Custom origins from CSP_ALLOWED_HOSTS (space or comma separated)
        $customHosts = env('CSP_ALLOWED_HOSTS');
        if (is_string($customHosts) && trim($customHosts) !== '') {
            $parts = preg_split('/[\s,]+/', trim($customHosts));
            if (is_array($parts)) {
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $origins[] = $part;
                    }
                }
            }
        }

        $unique = array_unique(array_filter($origins));

        return ! empty($unique) ? ' '.implode(' ', $unique) : '';
    }
}
