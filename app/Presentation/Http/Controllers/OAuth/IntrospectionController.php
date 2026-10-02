<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\OAuth;

use App\Application\OAuth\Services\OAuthClientAuthenticator;
use App\Application\OAuth\UseCases\IntrospectTokenUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntrospectionController extends Controller
{
    public function __construct(
        private readonly OAuthClientAuthenticator $clientAuthenticator,
        private readonly IntrospectTokenUseCase $introspectTokenUseCase,
    ) {}

    /**
     * Handle RFC 7662 Token Introspection request.
     */
    public function introspect(Request $request): JsonResponse
    {
        $client = $this->clientAuthenticator->authenticate($request);

        if (! $client) {
            return response()->json([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401);
        }

        $token = $request->input('token');

        if (empty($token) || ! is_string($token)) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'The token parameter is required',
            ], 400);
        }

        $tokenTypeHint = $request->input('token_type_hint');

        $result = $this->introspectTokenUseCase->execute(
            token: $token,
            tokenTypeHint: is_string($tokenTypeHint) ? $tokenTypeHint : null,
            client: $client,
        );

        return response()->json($result, 200);
    }
}
