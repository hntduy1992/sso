<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInfoController extends Controller
{
    /**
     * OpenID Connect UserInfo Endpoint — RFC 6749 / OIDC Core 1.0 Section 5.3
     *
     * Returns identity claims for the authenticated user based on granted scopes.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('api');

        if (! $user) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'The access token is invalid, expired, or revoked.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'error_description' => 'The user account is locked or suspended.',
            ], 403);
        }

        $token = $user->token();

        // Security check: invalidate if password changed after token issuance
        if ($token && $token->created_at && $user->password_changed_at) {
            if ($user->isTokenIssuedBeforePasswordChange($token->created_at->timestamp)) {
                $token->update(['revoked' => true]);

                return response()->json([
                    'error' => 'invalid_token',
                    'error_description' => 'The token was issued before the user changed their password.',
                ], 401);
            }
        }

        $scopes = $token?->scopes ?? $token?->oauth_scopes ?? [];
        $hasScope = fn (string $scope) => in_array($scope, $scopes, true)
            || ($token && method_exists($token, 'can') && $token->can($scope))
            || (method_exists($user, 'tokenCan') && $user->tokenCan($scope));

        // Base claim: sub is always returned
        $claims = [
            'sub' => (string) $user->id,
        ];

        // Profile scope
        if ($hasScope('profile')) {
            $claims['name'] = $user->name;
            $claims['picture'] = $user->avatar_url;
            $claims['updated_at'] = $user->updated_at?->timestamp;
        }

        // Email scope
        if ($hasScope('email')) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        // Roles scope
        if ($hasScope('roles')) {
            $assignedRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
            $claims['roles'] = ! empty($assignedRoles) ? $assignedRoles : (array) ($user->role ?? 'user');
        }

        return response()->json($claims);
    }
}
