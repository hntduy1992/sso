<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce PKCE (Proof Key for Code Exchange) for Public OAuth clients.
 *
 * Per OAuth 2.1 spec, all authorization code flows MUST use PKCE.
 * For Public clients (SPA, Mobile) this is especially critical as
 * they cannot store a client secret securely.
 *
 * Applied to: GET /oauth/authorize
 */
class RequirePkceForPublicClients
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->query('client_id');

        // Only validate during authorization code requests
        if (! $clientId || $request->query('response_type') !== 'code') {
            return $next($request);
        }

        $client = Client::find($clientId);

        // Unknown client — let Passport handle this error
        if (! $client) {
            return $next($request);
        }

        // If client is PUBLIC or has pkce_enforced=true, code_challenge is mandatory
        $requiresPkce = $client->client_type === 'PUBLIC' || $client->pkce_enforced;

        if ($requiresPkce && ! $request->filled('code_challenge')) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'PKCE is required for this client. '
                    .'Please include code_challenge and code_challenge_method=S256.',
            ], 400);
        }

        if ($requiresPkce && $request->query('code_challenge_method') !== 'S256') {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Only S256 code_challenge_method is supported.',
            ], 400);
        }

        return $next($request);
    }
}
