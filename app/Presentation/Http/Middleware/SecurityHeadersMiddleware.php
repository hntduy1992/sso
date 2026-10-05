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
        // Disallows framing ('frame-ancestors none') while permitting necessary Inertia/Vite assets
        $csp = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net; "
            ."font-src 'self' https://fonts.bunny.net data:; "
            ."img-src 'self' data: https:; "
            ."connect-src 'self' *; "
            ."frame-ancestors 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self' http://127.0.0.1:* http://localhost:*; ";

        $response->headers->set('Content-Security-Policy', $csp);

        // 5. Permissions Policy
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // 6. Modern XSS Protection header (disables legacy buggy auditor in favor of CSP)
        $response->headers->set('X-XSS-Protection', '0');

        // 7. Strict-Transport-Security (HSTS) on HTTPS or production
        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
