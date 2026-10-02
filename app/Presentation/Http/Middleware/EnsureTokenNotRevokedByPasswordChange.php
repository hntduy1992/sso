<?php

declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenNotRevokedByPasswordChange
{
    /**
     * Handle an incoming request.
     *
     * If the access token was issued prior to user's password_changed_at,
     * immediately reject the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user('api') ?? $request->user();

        if ($user && $user->password_changed_at) {
            $token = $request->user()?->token();

            if ($token && $token->created_at) {
                if ($user->isTokenIssuedBeforePasswordChange($token->created_at->timestamp)) {
                    $token->update(['revoked' => true]);

                    return response()->json([
                        'error' => 'invalid_token',
                        'error_description' => 'The token was issued before the password was changed.',
                    ], 401);
                }
            }
        }

        return $next($request);
    }
}
