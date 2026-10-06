<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Profile;

use App\Application\User\UseCases\UnlinkSocialProviderUseCase;
use App\Domain\User\Exceptions\CannotUnlinkLastAuthMethodException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

/**
 * Manages social provider connections for already-authenticated users.
 *
 * Flow for linking:
 *   GET /profile/social-connections/{provider}/connect
 *     → Redirect to provider (with `state` bound to current session)
 *   GET /profile/social-connections/{provider}/callback
 *     → Provider returns here; link the social account to the current user
 *
 * Flow for unlinking:
 *   DELETE /profile/social-connections/{provider}
 *     → Removes the social account link (with safety check)
 */
class SocialConnectionController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'github'];

    /**
     * Redirect the authenticated user to the social provider for account linking.
     */
    public function connect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        return Socialite::driver($provider)
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle the provider callback and link the social account to the current user.
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        /** @var User $user */
        $user = $request->user();

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception) {
            return redirect()->route('profile.index')
                ->with('error', 'Không thể kết nối với '.ucfirst($provider).'. Vui lòng thử lại.');
        }

        // Check if this provider account is already linked to a different SSO account
        $existingLink = UserSocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($existingLink) {
            return redirect()->route('profile.index')
                ->with('error', 'Tài khoản '.ucfirst($provider).' này đã được liên kết với một tài khoản SSO khác.');
        }

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

        return redirect()->route('profile.index')
            ->with('success', 'Đã liên kết tài khoản '.ucfirst($provider).' thành công.');
    }

    /**
     * Unlink a social provider from the current user's account.
     */
    public function destroy(
        string $provider,
        Request $request,
        UnlinkSocialProviderUseCase $unlinkSocialProviderUseCase,
    ): RedirectResponse {
        $this->ensureProviderIsSupported($provider);

        /** @var User $user */
        $user = $request->user();

        try {
            $unlinkSocialProviderUseCase->execute($user, $provider);
        } catch (CannotUnlinkLastAuthMethodException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy liên kết tài khoản '.ucfirst($provider).'.');
    }

    private function ensureProviderIsSupported(string $provider): void
    {
        abort_unless(
            in_array($provider, self::SUPPORTED_PROVIDERS, strict: true),
            404,
            "Provider [{$provider}] không được hỗ trợ."
        );
    }
}
