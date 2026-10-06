<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\OAuth\Services\ApplicationAccessService;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only users explicitly granted access may authorize (sign in to) an OAuth application.
 *
 * Applied to the Passport authorization endpoint:
 *   GET  /oauth/authorize  → starts the flow, client_id is in the query string
 *   POST /oauth/authorize  → approves consent, client_id remembered from the GET step
 *
 * Unauthenticated requests pass through so Passport can redirect to the login page;
 * the check runs again when the user returns.
 */
class EnsureUserCanAccessApplication
{
    private const SESSION_KEY = 'oauth_authorizing_client_id';

    public function __construct(
        private readonly ApplicationAccessService $accessService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('oauth/authorize') || (! $request->isMethod('GET') && ! $request->isMethod('POST'))) {
            return $next($request);
        }

        // If an Inertia-driven satellite app visited /oauth/authorize via an Inertia request (X-Inertia),
        // instruct the client Inertia router to perform a full-page external browser redirect (X-Inertia-Location)
        // instead of capturing the HTML and rendering it in a sandboxed modal iframe (about:srcdoc).
        if ($request->isMethod('GET') && $request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $clientId = $request->isMethod('GET')
            ? (string) $request->query('client_id')
            : (string) $request->session()->get(self::SESSION_KEY);

        if ($clientId === '') {
            return $next($request);
        }

        $client = Passport::client()->newQuery()->find($clientId);

        // Unknown client — let Passport produce its standard OAuth error.
        if (! $client) {
            return $next($request);
        }

        if (! $this->accessService->canAccess($user, (string) $client->getKey())) {
            $this->auditLogger->log(
                event: 'APPLICATION_ACCESS_DENIED',
                user: $user,
                clientId: (string) $client->getKey(),
                payload: ['client_name' => $client->name],
            );

            return Inertia::render('Auth/AccessDenied', [
                'applicationName' => $client->name,
            ])->toResponse($request)->setStatusCode(403);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put(self::SESSION_KEY, (string) $client->getKey());
        }

        return $next($request);
    }
}
