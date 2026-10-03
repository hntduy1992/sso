<?php

namespace App\Providers;

use App\Domain\User\Repositories\UserPositionRepositoryInterface;
use App\Domain\User\Repositories\UserProfileRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Infrastructure\OAuth\RotatingRefreshTokenRepository;
use App\Infrastructure\User\Repositories\EloquentUserPositionRepository;
use App\Infrastructure\User\Repositories\EloquentUserProfileRepository;
use App\Infrastructure\User\Repositories\EloquentUserRepository;
use App\Models\OAuthAccessToken;
use App\Models\OAuthRefreshToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\RefreshTokenRepository as PassportRefreshTokenRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            UserProfileRepositoryInterface::class,
            EloquentUserProfileRepository::class
        );

        $this->app->bind(
            UserPositionRepositoryInterface::class,
            EloquentUserPositionRepository::class
        );

        // Bind custom RefreshTokenRepository for rotation and revocation family support
        $this->app->singleton(
            PassportRefreshTokenRepository::class,
            RotatingRefreshTokenRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->configureRateLimiting();
    }

    /**
     * Configure enterprise rate limiters for security hardening.
     */
    private function configureRateLimiting(): void
    {
        // 1. Login Brute-force: 5 req/min per [email + IP]
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                        return response()->json([
                            'message' => 'Quá nhiều lần thử đăng nhập. Vui lòng thử lại sau 1 phút.',
                        ], 429, $headers);
                    }

                    return back()->withErrors([
                        'email' => 'Quá nhiều lần thử đăng nhập. Vui lòng thử lại sau 1 phút.',
                    ])->setStatusCode(429)->withHeaders($headers);
                });
        });

        // 2. TOTP MFA Challenge: 5 attempts/min per user session
        RateLimiter::for('mfa.challenge', function (Request $request) {
            $userId = (string) ($request->session()->get('mfa_pending_user_id') ?? $request->ip());

            return Limit::perMinute(5)
                ->by($userId.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                        return response()->json([
                            'message' => 'Quá nhiều lần thử mã OTP không chính xác. Vui lòng chờ 1 phút.',
                        ], 429, $headers);
                    }

                    return back()->withErrors([
                        'code' => 'Quá nhiều lần thử mã OTP không chính xác. Vui lòng chờ 1 phút.',
                    ])->setStatusCode(429)->withHeaders($headers);
                });
        });

        // 3. OAuth Token exchange: 30 req/min per [client_id + IP]
        RateLimiter::for('oauth.token', function (Request $request) {
            $clientId = (string) ($request->input('client_id') ?? 'unknown');

            return Limit::perMinute(30)->by($clientId.'|'.$request->ip());
        });

        // 4. Token Introspection: 60 req/min per IP
        RateLimiter::for('oauth.introspect', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // 5. UserInfo: 120 req/min per user / IP
        RateLimiter::for('oauth.userinfo', function (Request $request) {
            $user = $request->user('api');

            return Limit::perMinute(120)->by(($user?->id ?? 'guest').'|'.$request->ip());
        });
    }

    /**
     * Configure Laravel Passport for the SSO Identity Provider.
     */
    private function configurePassport(): void
    {
        // Register custom authorization view for Passport v13
        Passport::authorizationView('passport.authorize');
        // Use custom token models to inject claims and support revocation families
        Passport::useTokenModel(OAuthAccessToken::class);
        Passport::useRefreshTokenModel(OAuthRefreshToken::class);

        // Token lifetimes
        Passport::tokensExpireIn(now()->addMinutes(30));
        Passport::refreshTokensExpireIn(now()->addDays(14));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        // Enable Authorization Code Grant with PKCE enforcement
        Passport::enablePasswordGrant();

        // Define all available OAuth2 scopes
        // Format: 'scope-name' => 'Human-readable description shown on Consent screen'
        Passport::tokensCan([
            // OIDC standard scopes
            'openid' => 'Xác định danh tính OpenID Connect',
            'profile' => 'Thông tin cá nhân: tên, avatar',
            'email' => 'Địa chỉ email đã xác minh',
            'offline_access' => 'Duy trì quyền truy cập khi bạn offline (Refresh Token)',

            // Extended scopes for satellite apps
            'roles' => 'Danh sách vai trò và quyền hạn trong ứng dụng',
        ]);

        // Default scope when client requests no specific scope
        Passport::setDefaultScope([
            'openid',
            'profile',
            'email',
        ]);
    }
}
