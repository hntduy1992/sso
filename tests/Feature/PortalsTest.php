<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\OAuth\Jobs\SendBackchannelLogoutJob;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PortalsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'dev@sso.local',
            'name' => 'Developer User',
            'role' => 'user',
            'status' => 'active',
            'password' => bcrypt('OldPassword123!'),
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@sso.local',
            'name' => 'Admin User',
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('AdminPassword123!'),
        ]);
    }

    public function test_user_can_view_profile_page(): void
    {
        $response = $this->actingAs($this->user)->get('/profile');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Profile/Index'));
    }

    public function test_user_can_update_profile_name(): void
    {
        $response = $this->actingAs($this->user)->patch('/profile', [
            'name' => 'Updated Developer Name',
            'avatar_url' => 'https://example.com/avatar.png',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('Updated Developer Name', $this->user->fresh()->name);
        $this->assertEquals('https://example.com/avatar.png', $this->user->fresh()->avatar_url);
    }

    public function test_user_can_change_password_and_revoke_all_sessions(): void
    {
        // Set up active sessions
        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->put('/profile/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewSuperPassword123!',
            'password_confirmation' => 'NewSuperPassword123!',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewSuperPassword123!', (string) $this->user->fresh()->password));
        $this->assertFalse(UserSession::where('user_id', $this->user->id)->first()->is_active);
    }

    public function test_user_can_initiate_mfa_setup_and_receive_qr_svg(): void
    {
        $response = $this->actingAs($this->user)->postJson('/profile/mfa/setup');

        $response->assertStatus(200);
        $response->assertJsonStructure(['secret', 'qr_code_svg']);
        $this->assertNotEmpty($response->json('secret'));
        $this->assertStringContainsString('<svg', $response->json('qr_code_svg'));
        $this->assertEquals($response->json('secret'), session('mfa_setup_secret'));
    }

    public function test_user_can_confirm_mfa_setup_and_receive_recovery_codes(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $this->withSession(['mfa_setup_secret' => $secret]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($this->user)->postJson('/profile/mfa/confirm', [
            'code' => $validCode,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertCount(8, $response->json('recovery_codes'));

        $this->user->refresh();
        $this->assertTrue($this->user->two_factor_enabled);
        $this->assertNotNull($this->user->two_factor_confirmed_at);
        $this->assertNotNull($this->user->two_factor_secret);
    }

    public function test_user_can_disable_mfa_with_valid_password(): void
    {
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('SECRET'),
        ]);

        $response = $this->actingAs($this->user)->delete('/profile/mfa', [
            'password' => 'OldPassword123!',
        ]);

        $response->assertSessionHas('success');
        $this->user->refresh();
        $this->assertFalse($this->user->two_factor_enabled);
        $this->assertNull($this->user->two_factor_secret);
    }

    public function test_user_can_view_active_sessions(): void
    {
        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Chrome/120.0',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/profile/sessions');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Profile/Sessions'));
    }

    public function test_user_can_terminate_specific_session(): void
    {
        $sessionId = (string) Str::uuid();

        UserSession::create([
            'id' => $sessionId,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->delete("/profile/sessions/{$sessionId}");

        $response->assertSessionHas('success');
        $this->assertFalse(UserSession::find($sessionId)->is_active);
    }

    public function test_user_can_revoke_other_sessions(): void
    {
        $otherSessionId = (string) Str::uuid();

        UserSession::create([
            'id' => $otherSessionId,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post('/profile/sessions/revoke-others');

        $response->assertSessionHas('success');
        $this->assertFalse(UserSession::find($otherSessionId)->is_active);
    }

    public function test_user_can_view_and_revoke_authorized_app(): void
    {
        Queue::fake();

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Third-Party App',
            'redirect_uris' => 'https://thirdparty.com/callback',
            'grant_types' => 'authorization_code',
            'client_type' => 'PUBLIC',
            'revoked' => false,
            'backchannel_logout_uri' => 'https://thirdparty.com/logout',
        ]);

        $tokenId = (string) Str::uuid();
        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'scopes' => ['openid', 'profile'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/profile/authorized-apps');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Profile/AuthorizedApps'));

        // Revoke app
        $revokeResponse = $this->actingAs($this->user)->delete("/profile/authorized-apps/{$client->id}");
        $revokeResponse->assertSessionHas('success');

        $this->assertTrue(Passport::token()->find($tokenId)->revoked);
        $this->assertFalse(UserSession::where('client_id', $client->id)->first()->is_active);

        // Backchannel logout pushed
        Queue::assertPushed(SendBackchannelLogoutJob::class);
    }

    public function test_developer_can_create_oauth_client(): void
    {
        $response = $this->actingAs($this->user)->post('/developer/clients', [
            'name' => 'New Mobile Flutter App',
            'client_type' => 'PUBLIC',
            'redirect_uris' => "myapp://callback\nhttps://myapp.com/callback",
            'backchannel_logout_uri' => 'https://myapp.com/backchannel-logout',
            'description' => 'Mobile Flutter Client using PKCE',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('oauth_clients', [
            'name' => 'New Mobile Flutter App',
            'client_type' => 'PUBLIC',
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_developer_can_create_confidential_client_with_secret(): void
    {
        $response = $this->actingAs($this->user)->post('/developer/clients', [
            'name' => 'Backend Laravel Client',
            'client_type' => 'CONFIDENTIAL',
            'redirect_uris' => 'https://laravelapp.com/callback',
            'description' => 'Confidential backend client',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('plain_secret');

        $plainSecret = session('plain_secret');
        $this->assertNotEmpty($plainSecret);

        $client = Client::where('name', 'Backend Laravel Client')->first();
        $this->assertNotNull($client);
        $this->assertTrue(Hash::check($plainSecret, (string) $client->secret));
    }

    public function test_developer_can_regenerate_client_secret(): void
    {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Confidential Client',
            'owner_id' => $this->user->id,
            'secret' => 'old-secret',
            'redirect_uris' => 'https://app.com/callback',
            'grant_types' => 'authorization_code',
            'client_type' => 'CONFIDENTIAL',
            'revoked' => false,
        ]);

        $response = $this->actingAs($this->user)->post("/developer/clients/{$client->id}/secret");

        $response->assertSessionHas('success');
        $response->assertSessionHas('plain_secret');

        $newSecret = session('plain_secret');
        $this->assertTrue(Hash::check($newSecret, (string) $client->fresh()->secret));
    }

    public function test_developer_can_revoke_oauth_client(): void
    {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Client To Revoke',
            'owner_id' => $this->user->id,
            'redirect_uris' => 'https://app.com/callback',
            'grant_types' => 'authorization_code',
            'client_type' => 'PUBLIC',
            'revoked' => false,
        ]);

        $response = $this->actingAs($this->user)->delete("/developer/clients/{$client->id}");

        $response->assertSessionHas('success');
        $this->assertTrue($client->fresh()->revoked);
    }

    public function test_admin_can_force_logout_user(): void
    {
        Queue::fake();

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Target App',
            'redirect_uris' => 'https://target.com/callback',
            'grant_types' => 'authorization_code',
            'client_type' => 'PUBLIC',
            'revoked' => false,
            'backchannel_logout_uri' => 'https://target.com/logout',
        ]);

        $tokenId = (string) Str::uuid();
        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->user->id}/force-logout");

        $response->assertSessionHas('success');
        $this->assertTrue(Passport::token()->find($tokenId)->revoked);
        $this->assertFalse(UserSession::where('user_id', $this->user->id)->first()->is_active);

        Queue::assertPushed(SendBackchannelLogoutJob::class);
    }

    public function test_admin_can_view_and_filter_audit_logs(): void
    {
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'event' => 'REFRESH_TOKEN_REUSE_DETECTED',
            'ip_address' => '192.168.1.100',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'event' => 'TOKEN_ISSUED',
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/audit-logs?event=REFRESH_TOKEN_REUSE_DETECTED');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Admin/AuditLogs'));
    }

    public function test_developer_can_view_clients_page(): void
    {
        $response = $this->actingAs($this->user)->get('/developer/clients');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Developer/Clients'));
    }

    public function test_admin_can_view_dashboard_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }
}
