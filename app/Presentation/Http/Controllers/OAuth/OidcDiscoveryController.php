<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OIDC Discovery endpoint — RFC 8414 / OpenID Connect Discovery 1.0
 *
 * Clients call this endpoint to auto-configure themselves:
 * GET /.well-known/openid-configuration
 */
class OidcDiscoveryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $baseUrl = rtrim(config('app.url'), '/');

        return response()->json([
            // REQUIRED — must match the "iss" claim in tokens
            'issuer' => $baseUrl,

            // Core OAuth2/OIDC endpoints
            'authorization_endpoint' => $baseUrl.'/oauth/authorize',
            'token_endpoint' => $baseUrl.'/oauth/token',
            'revocation_endpoint' => $baseUrl.'/oauth/revoke',
            'introspection_endpoint' => $baseUrl.'/oauth/introspect',
            'userinfo_endpoint' => $baseUrl.'/oauth/userinfo',

            // Public keys for JWT signature verification (RS256)
            'jwks_uri' => $baseUrl.'/oauth/jwks',

            // Scopes available in this server
            'scopes_supported' => [
                'openid', 'profile', 'email', 'offline_access', 'roles',
            ],

            // Supported response types
            'response_types_supported' => [
                'code',
            ],

            // Supported grant types
            'grant_types_supported' => [
                'authorization_code',
                'refresh_token',
                'client_credentials',
            ],

            // Subject identifier types (pairwise would be more private but complex)
            'subject_types_supported' => ['public'],

            // ID Token signing algorithms
            'id_token_signing_alg_values_supported' => ['RS256'],

            // Token endpoint authentication methods for Confidential clients
            'token_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
                'none', // for Public clients using PKCE
            ],

            // PKCE code challenge methods
            'code_challenge_methods_supported' => ['S256'],

            // Claims available in ID tokens / UserInfo
            'claims_supported' => [
                'sub', 'iss', 'aud', 'exp', 'iat', 'auth_time',
                'name', 'email', 'email_verified', 'picture',
                'roles', 'amr',
            ],

            // Backchannel logout supported (Phase 3)
            'backchannel_logout_supported' => true,
            'backchannel_logout_session_supported' => true,
        ]);
    }
}
