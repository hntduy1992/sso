<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\OAuth\Jobs\SendBackchannelLogoutJob;
use App\Infrastructure\Satellite\CheckTokenScope;
use App\Models\Department;
use App\Models\PositionType;
use App\Models\User;
use App\Models\UserPosition;
use App\Models\UserProfile;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SatelliteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@satellite.local',
            'status' => 'active',
            'role' => 'user',
        ]);

        $this->client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Satellite Web App',
            'secret' => 'satellite-secret-98765',
            'redirect_uris' => ['https://satellite.local/callback'],
            'grant_types' => 'authorization_code,refresh_token',
            'client_type' => 'CONFIDENTIAL',
            'revoked' => false,
            'backchannel_logout_uri' => 'https://satellite.local/backchannel-logout',
        ]);
    }

    // =========================================================================
    // 1. OIDC UserInfo Endpoint (/oauth/userinfo)
    // =========================================================================

    public function test_userinfo_returns_401_for_unauthenticated_request(): void
    {
        $response = $this->getJson('/oauth/userinfo');

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_token',
            ]);
    }

    public function test_userinfo_returns_claims_matching_token_scopes(): void
    {
        Passport::actingAs($this->user, ['openid', 'profile', 'email', 'roles']);

        $response = $this->getJson('/oauth/userinfo');

        $response->assertStatus(200)
            ->assertJson([
                'sub' => (string) $this->user->id,
                'name' => 'John Doe',
                'email' => 'john@satellite.local',
                'roles' => ['user'],
            ]);
    }

    public function test_userinfo_returns_department_and_hrm_profile_data(): void
    {
        UserProfile::create([
            'user_id' => $this->user->id,
            'full_name' => 'John Doe',
            'phone_number' => '0987654321',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'address' => '123 Main St, Hanoi',
        ]);

        $dept = Department::create([
            'name' => 'Tổ Kế toán',
            'code' => 'TO_KT',
            'type' => 'specialized_team',
            'is_active' => true,
            'display_order' => 1,
        ]);

        $posType = PositionType::create([
            'code' => 'TEAM_LEAD',
            'name' => 'Tổ trưởng',
            'level' => 3,
            'applicable_to' => 'specialized_team',
            'is_active' => true,
        ]);

        UserPosition::create([
            'user_id' => $this->user->id,
            'department_id' => $dept->id,
            'position_type_id' => $posType->id,
            'started_at' => '2023-01-01',
            'is_primary' => true,
        ]);

        Passport::actingAs($this->user, ['openid', 'profile', 'email']);

        $response = $this->getJson('/oauth/userinfo');

        $response->assertStatus(200)
            ->assertJson([
                'sub' => (string) $this->user->id,
                'phone_number' => '0987654321',
                'gender' => 'male',
                'date_of_birth' => '1990-01-15',
                'department' => [
                    'name' => 'Tổ Kế toán',
                    'code' => 'TO_KT',
                ],
                'position' => [
                    'name' => 'Tổ trưởng',
                    'code' => 'TEAM_LEAD',
                ],
            ]);
    }

    public function test_userinfo_filters_out_profile_and_email_if_scopes_not_granted(): void
    {
        Passport::actingAs($this->user, ['openid']);

        $response = $this->getJson('/oauth/userinfo');

        $response->assertStatus(200)
            ->assertJson([
                'sub' => (string) $this->user->id,
            ])
            ->assertJsonMissing(['name', 'email', 'roles']);
    }

    public function test_userinfo_returns_403_if_user_is_inactive_or_suspended(): void
    {
        $this->user->update(['status' => 'suspended']);

        Passport::actingAs($this->user, ['openid', 'profile']);

        $response = $this->getJson('/oauth/userinfo');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'account_inactive',
            ]);
    }

    // =========================================================================
    // 2. OIDC RP-Initiated Logout (/oauth/logout)
    // =========================================================================

    public function test_oidc_logout_terminates_session_and_redirects_to_whitelisted_uri(): void
    {
        Queue::fake();

        // Create an active user session in SSO
        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'session_id' => 'sso-test-session-123',
            'client_id' => $this->client->id,
            'last_active_at' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/oauth/logout?post_logout_redirect_uri=https://satellite.local/callback&state=xyz123');

        $response->assertRedirect('https://satellite.local/callback?state=xyz123');
        $this->assertGuest();

        // Backchannel logout job should be dispatched for the satellite app
        Queue::assertPushed(SendBackchannelLogoutJob::class);
    }

    public function test_oidc_logout_prevents_open_redirect_for_unregistered_domain(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->get('/oauth/logout?post_logout_redirect_uri=https://attacker.evil.com/login');

        // Should NOT redirect to attacker domain; falls back to SSO login page
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // =========================================================================
    // 3. Dynamic OAuth CORS Middleware
    // =========================================================================

    public function test_cors_options_preflight_succeeds_for_registered_client_origin(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/oauth/token',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://satellite.local',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ]
        );

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'https://satellite.local')
            ->assertHeader('Access-Control-Allow-Methods');
    }

    public function test_cors_options_preflight_is_forbidden_for_unwhitelisted_origin(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/oauth/token',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://malicious-site.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ]
        );

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'cors_origin_not_allowed',
            ]);
    }

    public function test_cors_allows_any_origin_for_public_discovery_and_jwks(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/oauth/jwks',
            [],
            [],
            [],
            [
                'HTTP_ORIGIN' => 'https://any-external-domain.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            ]
        );

        $response->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'https://any-external-domain.com');
    }

    // =========================================================================
    // 4. Satellite CheckTokenScope Middleware
    // =========================================================================

    public function test_scope_middleware_allows_request_when_all_scopes_are_present(): void
    {
        $middleware = new CheckTokenScope;

        $request = Request::create('/api/resource', 'GET');
        $request->attributes->set('oauth_scopes', ['openid', 'roles', 'profile']);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'openid', 'roles');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_scope_middleware_denies_request_when_required_scope_is_missing(): void
    {
        $middleware = new CheckTokenScope;

        $request = Request::create('/api/resource', 'GET');
        $request->attributes->set('oauth_scopes', ['openid', 'profile']);

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'roles');

        $this->assertEquals(403, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('insufficient_scope', $content['error']);
    }
}
