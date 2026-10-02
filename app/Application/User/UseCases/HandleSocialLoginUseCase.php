<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Handles OAuth2 Social Login via Laravel Socialite.
 *
 * Scenarios handled:
 *  A. New user, new social account    → Create user + social account, login
 *  B. Existing user (email match)     → Link social account to existing user, login
 *  C. Existing social account         → Login directly
 */
class HandleSocialLoginUseCase
{
    public function execute(string $provider, SocialiteUser $socialUser): User
    {
        // Case C: Social account already linked
        $existingSocial = UserSocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existingSocial) {
            $this->updateSocialTokens($existingSocial, $socialUser);
            Auth::login($existingSocial->user, remember: true);

            return $existingSocial->user;
        }

        // Case B: Email already exists → link to existing account
        $existingUser = User::where('email', $socialUser->getEmail())->first();

        if ($existingUser) {
            $this->attachSocialAccount($existingUser, $provider, $socialUser);
            Auth::login($existingUser, remember: true);

            return $existingUser;
        }

        // Case A: Brand new user
        $newUser = User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $socialUser->getEmail(),
            'password' => null, // Social-only account
            'avatar_url' => $socialUser->getAvatar(),
            'status' => 'active',
            'role' => 'user',
            'email_verified_at' => now(), // Social email is pre-verified
        ]);

        $this->attachSocialAccount($newUser, $provider, $socialUser);
        Auth::login($newUser, remember: true);

        return $newUser;
    }

    private function attachSocialAccount(User $user, string $provider, SocialiteUser $socialUser): void
    {
        $user->socialAccounts()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $socialUser->getId()],
            [
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken,
                'token_expires_at' => $socialUser->expiresIn
                    ? now()->addSeconds($socialUser->expiresIn)
                    : null,
            ]
        );
    }

    private function updateSocialTokens(UserSocialAccount $social, SocialiteUser $socialUser): void
    {
        $social->update([
            'provider_token' => $socialUser->token,
            'provider_refresh_token' => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);
    }
}
