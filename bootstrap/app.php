<?php

use App\Http\Middleware\EnsureUserCanAccessApplication;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePkceForPublicClients;
use App\Infrastructure\Satellite\CheckTokenScope;
use App\Presentation\Http\Middleware\OAuthCorsMiddleware;
use App\Presentation\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global Enterprise Security Headers
        $middleware->append(SecurityHeadersMiddleware::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureUserCanAccessApplication::class,
        ]);

        // Register named middleware aliases
        $middleware->alias([
            'pkce' => RequirePkceForPublicClients::class,
            'oauth.cors' => OAuthCorsMiddleware::class,
            'scope' => CheckTokenScope::class,
            'security.headers' => SecurityHeadersMiddleware::class,
        ]);

        // OAuth, OIDC, and API endpoints accessible without CSRF
        $middleware->validateCsrfTokens(except: [
            '/.well-known/openid-configuration',
            '/oauth/jwks',
            '/oauth/token',
            '/oauth/revoke',
            '/oauth/introspect',
            '/oauth/userinfo',
            '/oauth/logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
