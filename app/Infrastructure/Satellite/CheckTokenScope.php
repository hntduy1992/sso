<?php

declare(strict_types=1);

namespace App\Infrastructure\Satellite;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reusable Middleware for Satellite Laravel Applications.
 *
 * Verifies that the authenticated OAuth2 token possesses the required scope(s).
 *
 * Usage in satellite app:
 *   Route::get('/api/reports', ...)->middleware(CheckTokenScope::class.':roles');
 *   Route::post('/api/orders', ...)->middleware(CheckTokenScope::class.':orders.create,admin');
 */
class CheckTokenScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $user = $request->user();

        // Check if user has token instance (Passport or custom token)
        $token = ($user && method_exists($user, 'token')) ? $user->token() : null;
        $scopes = $token?->scopes ?? [];

        // Also support scopes embedded in JWT request attributes (from VerifySatelliteJwtToken)
        if (empty($scopes)) {
            $scopes = (array) ($request->attributes->get('oauth_scopes') ?? $request->attributes->get('jwt_scopes') ?? []);
        }

        if (! $user && ! $request->attributes->has('jwt_sub') && ! $request->attributes->has('oauth_scopes') && ! $request->attributes->has('jwt_scopes')) {
            return response()->json([
                'error' => 'unauthenticated',
                'error_description' => 'A valid Bearer token is required.',
            ], 401);
        }

        foreach ($requiredScopes as $scope) {
            if (! in_array($scope, $scopes, true)) {
                return response()->json([
                    'error' => 'insufficient_scope',
                    'error_description' => "This endpoint requires the '{$scope}' scope.",
                    'required_scope' => $scope,
                ], 403);
            }
        }

        return $next($request);
    }
}
