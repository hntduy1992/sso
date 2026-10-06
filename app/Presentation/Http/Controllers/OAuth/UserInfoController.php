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

        // Profile scope (personal & organizational HRM info)
        if ($hasScope('profile')) {
            $user->loadMissing(['profile', 'activePositions.department', 'activePositions.positionType']);

            $claims['name'] = $user->name;
            $claims['picture'] = $user->avatar_url;
            $claims['updated_at'] = $user->updated_at?->timestamp;

            // HRM Personal Profile
            $profile = $user->profile;
            $claims['phone_number'] = $profile?->phone_number;
            $claims['gender'] = $profile?->gender;
            $claims['date_of_birth'] = $profile?->date_of_birth?->format('Y-m-d');
            $claims['address'] = $profile?->address;

            // Organizational Department & Positions
            $activePositions = $user->activePositions;
            $primaryPosition = $activePositions->firstWhere('is_primary', true) ?? $activePositions->first();

            $claims['department'] = $primaryPosition?->department ? [
                'id' => $primaryPosition->department->id,
                'name' => $primaryPosition->department->name,
                'code' => $primaryPosition->department->code,
                'type' => $primaryPosition->department->type,
            ] : null;

            $claims['position'] = $primaryPosition?->positionType ? [
                'id' => $primaryPosition->positionType->id,
                'name' => $primaryPosition->positionType->name,
                'code' => $primaryPosition->positionType->code,
                'level' => $primaryPosition->positionType->level,
            ] : null;

            $claims['positions'] = $activePositions->map(fn ($pos) => [
                'department' => $pos->department ? [
                    'id' => $pos->department->id,
                    'name' => $pos->department->name,
                    'code' => $pos->department->code,
                ] : null,
                'position_type' => $pos->positionType ? [
                    'id' => $pos->positionType->id,
                    'name' => $pos->positionType->name,
                    'code' => $pos->positionType->code,
                ] : null,
                'is_primary' => (bool) $pos->is_primary,
                'started_at' => $pos->started_at,
            ])->values()->all();
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
