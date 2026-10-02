<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\OAuth;

use App\Application\OAuth\Services\BackchannelLogoutService;
use App\Application\OAuth\Services\TokenIdentifierParser;
use App\Application\User\Services\SessionTracker;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class OidcLogoutController extends Controller
{
    public function __construct(
        private readonly SessionTracker $sessionTracker,
        private readonly BackchannelLogoutService $backchannelLogoutService,
        private readonly TokenIdentifierParser $tokenParser,
    ) {}

    /**
     * OpenID Connect RP-Initiated Logout 1.0 Endpoint.
     *
     * Handles single sign-out initiated by satellite apps.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        $idTokenHint = $request->input('id_token_hint');
        $postLogoutRedirectUri = $request->input('post_logout_redirect_uri');
        $state = $request->input('state');

        // If no active web session, attempt to identify user from id_token_hint
        if (! $user && $idTokenHint && is_string($idTokenHint)) {
            $parts = explode('.', $idTokenHint);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (isset($payload['sub'])) {
                    $user = User::find($payload['sub']);
                }
            }
        }

        if ($user) {
            // Single Sign-Out: notify connected satellite apps via Backchannel Logout before invalidating
            $this->backchannelLogoutService->triggerLogoutForUser($user);

            $sessionId = $request->hasSession() ? $request->session()->getId() : null;
            $this->sessionTracker->terminate($user, $sessionId);
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Validate post_logout_redirect_uri against registered clients to avoid Open Redirects
        if ($postLogoutRedirectUri && is_string($postLogoutRedirectUri) && filter_var($postLogoutRedirectUri, FILTER_VALIDATE_URL)) {
            $parsedTarget = parse_url($postLogoutRedirectUri);
            $targetHost = $parsedTarget['host'] ?? '';

            // Check if target host is whitelisted in any client's redirect_uris
            $isWhitelisted = Passport::client()
                ->newQuery()
                ->where('revoked', false)
                ->get()
                ->contains(function (Client $client) use ($targetHost) {
                    $uris = is_array($client->redirect_uris) ? $client->redirect_uris : [];
                    foreach ($uris as $uri) {
                        $clientHost = parse_url($uri, PHP_URL_HOST);
                        if ($clientHost === $targetHost) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($isWhitelisted) {
                $redirectUrl = $postLogoutRedirectUri;
                if ($state) {
                    $separator = str_contains($redirectUrl, '?') ? '&' : '?';
                    $redirectUrl .= "{$separator}state=".urlencode((string) $state);
                }

                return redirect()->away($redirectUrl);
            }
        }

        return redirect()->route('login')
            ->with('success', 'Bạn đã đăng xuất an toàn khỏi toàn bộ hệ thống SSO.');
    }
}
