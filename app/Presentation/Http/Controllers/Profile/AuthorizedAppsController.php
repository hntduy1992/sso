<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Profile;

use App\Application\OAuth\Jobs\SendBackchannelLogoutJob;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class AuthorizedAppsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display all third-party and satellite apps authorized by the user.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $tokenClientIds = Passport::token()
            ->newQuery()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->pluck('client_id');

        $sessionClientIds = UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('client_id')
            ->pluck('client_id');

        $clientIds = $tokenClientIds->merge($sessionClientIds)->filter()->unique()->values();

        $apps = Passport::client()
            ->newQuery()
            ->whereIn('id', $clientIds)
            ->where('revoked', false)
            ->get()
            ->map(function (Client $client) use ($user) {
                $tokens = Passport::token()
                    ->newQuery()
                    ->where('user_id', $user->id)
                    ->where('client_id', $client->id)
                    ->where('revoked', false)
                    ->get();

                $scopes = $tokens->flatMap(fn ($t) => $t->scopes ?? [])->unique()->values();

                return [
                    'id' => (string) $client->id,
                    'name' => $client->name,
                    'description' => $client->description ?? 'Ứng dụng liên kết trong hệ sinh thái SSO',
                    'client_type' => $client->client_type,
                    'scopes' => $scopes,
                    'authorized_at' => $tokens->first()?->created_at?->diffForHumans() ?? 'Gần đây',
                ];
            });

        return Inertia::render('Profile/AuthorizedApps', [
            'apps' => $apps,
        ]);
    }

    /**
     * Revoke user's authorization for a specific client application.
     * Triggers token revocation, session termination, and Backchannel Logout.
     */
    public function destroy(Request $request, string $clientId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Client|null $client */
        $client = Passport::client()->newQuery()->find($clientId);

        if (! $client) {
            return back()->with('error', 'Không tìm thấy ứng dụng.');
        }

        // 1. Revoke all user's tokens for this client
        $tokens = Passport::token()
            ->newQuery()
            ->where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->get();

        $tokenIds = $tokens->pluck('id');

        if ($tokenIds->isNotEmpty()) {
            Passport::token()
                ->newQuery()
                ->whereIn('id', $tokenIds)
                ->update(['revoked' => true]);

            OAuthRefreshToken::whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        }

        // 2. Revoke families
        OAuthRefreshTokenFamily::where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => 'authorized_app_revoked_by_user',
            ]);

        // 3. Deactivate sessions
        UserSession::where('user_id', $user->id)
            ->where('client_id', $client->id)
            ->update(['is_active' => false]);

        // 4. Trigger Backchannel Logout to satellite app if configured
        if (! empty($client->backchannel_logout_uri)) {
            SendBackchannelLogoutJob::dispatch($client, (string) $user->id);
        }

        $this->auditLogger->log(
            event: 'AUTHORIZED_APP_REVOKED',
            user: $user,
            clientId: (string) $client->id,
            payload: [
                'client_name' => $client->name,
            ]
        );

        return back()->with('success', "Đã thu hồi toàn bộ quyền truy cập của ứng dụng \"{$client->name}\".");
    }
}
