<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Auth;

use App\Application\User\UseCases\HandleSocialLoginUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

/**
 * Handles Social Login OAuth2 handshake via Laravel Socialite.
 *
 * Flow:
 *  GET /auth/{provider}/redirect  → Redirects user to provider (Google, GitHub...)
 *  GET /auth/{provider}/callback  → Handles callback, creates/links user, logs in
 */
class SocialAuthController extends Controller
{
    private const SUPPORTED_PROVIDERS = ['google', 'github'];

    /**
     * Redirect to the social provider's authorization page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        return Socialite::driver($provider)
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle the callback from the social provider.
     */
    public function callback(
        string $provider,
        HandleSocialLoginUseCase $handleSocialLoginUseCase,
    ): RedirectResponse {
        $this->ensureProviderIsSupported($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception) {
            return redirect()->route('login')
                ->with('error', 'Xác thực với '.ucfirst($provider).' thất bại. Vui lòng thử lại.');
        }

        $handleSocialLoginUseCase->execute($provider, $socialUser);

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Đăng nhập bằng '.ucfirst($provider).' thành công!');
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
