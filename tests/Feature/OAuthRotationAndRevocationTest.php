<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\OAuth\Jobs\SendBackchannelLogoutJob;
use App\Application\OAuth\Services\BackchannelLogoutTokenGenerator;
use App\Application\User\UseCases\ChangePasswordUseCase;
use App\Infrastructure\OAuth\RotatingRefreshTokenRepository;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class OAuthRotationAndRevocationTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'user@sso.local',
            'status' => 'active',
        ]);

        $this->client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Satellite App Client',
            'secret' => 'super-secret-key-12345',
            'redirect_uris' => 'https://satellite.local/callback',
            'grant_types' => 'authorization_code,refresh_token',
            'client_type' => 'CONFIDENTIAL',
            'revoked' => false,
            'backchannel_logout_uri' => 'https://satellite.local/backchannel-logout',
        ]);
    }

    public function test_oauth_client_can_revoke_access_token_via_rfc7009(): void
    {
        $tokenId = (string) Str::uuid();

        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid', 'profile'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        OAuthRefreshToken::create([
            'id' => (string) Str::uuid(),
            'access_token_id' => $tokenId,
            'revoked' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode("{$this->client->id}:super-secret-key-12345"),
        ])->postJson('/oauth/revoke', [
            'token' => $tokenId,
            'token_type_hint' => 'access_token',
        ]);

        $response->assertStatus(200);

        $this->assertTrue(Passport::token()->find($tokenId)->revoked);
        $this->assertTrue(OAuthRefreshToken::where('access_token_id', $tokenId)->first()->revoked);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'TOKEN_REVOKED',
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_oauth_client_can_revoke_refresh_token_via_rfc7009(): void
    {
        $tokenId = (string) Str::uuid();
        $refreshTokenId = (string) Str::uuid();

        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid', 'profile'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        OAuthRefreshToken::create([
            'id' => $refreshTokenId,
            'access_token_id' => $tokenId,
            'revoked' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $response = $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => $refreshTokenId,
            'token_type_hint' => 'refresh_token',
        ]);

        $response->assertStatus(200);

        $this->assertTrue(OAuthRefreshToken::find($refreshTokenId)->revoked);
        $this->assertTrue(Passport::token()->find($tokenId)->revoked);
    }

    public function test_revoke_unknown_token_still_returns_200_per_rfc7009(): void
    {
        $response = $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => 'non-existent-token-id',
        ]);

        $response->assertStatus(200);
    }

    public function test_revoke_fails_with_invalid_client(): void
    {
        $response = $this->postJson('/oauth/revoke', [
            'client_id' => $this->client->id,
            'client_secret' => 'wrong-secret',
            'token' => 'some-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'invalid_client']);
    }

    public function test_introspection_returns_active_metadata_for_valid_token(): void
    {
        $tokenId = (string) Str::uuid();

        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid', 'profile', 'email'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => $tokenId,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'active' => true,
            'client_id' => $this->client->id,
            'sub' => (string) $this->user->id,
            'username' => $this->user->email,
            'token_type' => 'Bearer',
        ]);
    }

    public function test_introspection_returns_inactive_for_revoked_or_expired_tokens(): void
    {
        $revokedTokenId = (string) Str::uuid();
        Passport::token()->create([
            'id' => $revokedTokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => true,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => $revokedTokenId,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['active' => false]);

        $expiredTokenId = (string) Str::uuid();
        Passport::token()->create([
            'id' => $expiredTokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => $expiredTokenId,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['active' => false]);
    }

    public function test_introspection_returns_inactive_for_suspended_user(): void
    {
        $this->user->update(['status' => 'suspended']);

        $tokenId = (string) Str::uuid();
        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => $tokenId,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['active' => false]);
    }

    public function test_password_change_invalidates_earlier_tokens_in_introspection(): void
    {
        $tokenId = (string) Str::uuid();

        Passport::token()->create([
            'id' => $tokenId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'created_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
        ]);

        // User changes password later
        $this->user->update(['password_changed_at' => now()->subMinutes(10)]);

        $response = $this->postJson('/oauth/introspect', [
            'client_id' => $this->client->id,
            'client_secret' => 'super-secret-key-12345',
            'token' => $tokenId,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['active' => false]);
    }

    public function test_change_password_use_case_revokes_all_user_tokens_families_and_sessions(): void
    {
        $atId1 = (string) Str::uuid();
        $atId2 = (string) Str::uuid();

        Passport::token()->create([
            'id' => $atId1,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        Passport::token()->create([
            'id' => $atId2,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        OAuthRefreshToken::create([
            'id' => (string) Str::uuid(),
            'access_token_id' => $atId1,
            'revoked' => false,
            'expires_at' => now()->addDays(14),
        ]);

        $family = OAuthRefreshTokenFamily::create([
            'id' => (string) Str::uuid(),
            'root_access_token_id' => $atId1,
            'current_refresh_token_id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'revoked' => false,
            'expires_at' => now()->addDays(14),
        ]);

        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'is_active' => true,
        ]);

        $useCase = app(ChangePasswordUseCase::class);
        $useCase->execute($this->user, 'NewPassword123!');

        $this->user->refresh();
        $this->assertNotNull($this->user->password_changed_at);

        $this->assertTrue(Passport::token()->find($atId1)->revoked);
        $this->assertTrue(Passport::token()->find($atId2)->revoked);
        $this->assertTrue($family->fresh()->revoked);
        $this->assertEquals('password_changed', $family->fresh()->revoked_reason);
        $this->assertFalse(UserSession::where('user_id', $this->user->id)->first()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'PASSWORD_CHANGED',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_refresh_token_reuse_detection_revokes_entire_family(): void
    {
        $repo = app(RotatingRefreshTokenRepository::class);

        $familyId = (string) Str::uuid();
        $oldRtId = 'rotated-rt-1';
        $newRtId = 'active-rt-2';
        $atId1 = (string) Str::uuid();
        $atId2 = (string) Str::uuid();

        Passport::token()->create([
            'id' => $atId1,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        Passport::token()->create([
            'id' => $atId2,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'scopes' => ['openid'],
            'revoked' => false,
            'expires_at' => now()->addMinutes(30),
        ]);

        $family = OAuthRefreshTokenFamily::create([
            'id' => $familyId,
            'root_access_token_id' => $atId1,
            'current_refresh_token_id' => $newRtId,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'revoked' => false,
            'expires_at' => now()->addDays(14),
        ]);

        // Old token: already revoked during rotation
        OAuthRefreshToken::create([
            'id' => $oldRtId,
            'access_token_id' => $atId1,
            'family_id' => $familyId,
            'revoked' => true,
            'expires_at' => now()->addDays(14),
        ]);

        // New token: currently active
        OAuthRefreshToken::create([
            'id' => $newRtId,
            'access_token_id' => $atId2,
            'family_id' => $familyId,
            'revoked' => false,
            'expires_at' => now()->addDays(14),
        ]);

        // Attacker attempts to reuse the already-revoked oldRtId
        $isRevoked = $repo->isRefreshTokenRevoked($oldRtId);

        $this->assertTrue($isRevoked);

        // Entire family MUST be revoked!
        $this->assertTrue($family->fresh()->revoked);
        $this->assertEquals('refresh_token_reuse_detected', $family->fresh()->revoked_reason);

        // Active token in family must also be revoked!
        $this->assertTrue(OAuthRefreshToken::find($newRtId)->revoked);

        // All access tokens in family must also be revoked!
        $this->assertTrue(Passport::token()->find($atId2)->revoked);

        // Audit log of reuse event must be recorded!
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'REFRESH_TOKEN_REUSE_DETECTED',
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
    }

    public function test_backchannel_logout_job_is_dispatched_when_user_logs_out(): void
    {
        Queue::fake();

        // Create an active session with client
        UserSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        Queue::assertPushed(SendBackchannelLogoutJob::class, function ($job) {
            return (string) $job->client->id === (string) $this->client->id &&
                   $job->userId === (string) $this->user->id;
        });
    }

    public function test_backchannel_logout_token_generator_creates_valid_signed_jwt(): void
    {
        $generator = app(BackchannelLogoutTokenGenerator::class);
        $jwtString = $generator->generate((string) $this->user->id, (string) $this->client->id);

        $this->assertNotEmpty($jwtString);
        $parts = explode('.', $jwtString);
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals(config('app.url'), $payload['iss']);
        $this->assertEquals((string) $this->user->id, $payload['sub']);

        $aud = is_array($payload['aud']) ? $payload['aud'][0] : $payload['aud'];
        $this->assertEquals((string) $this->client->id, $aud);

        $this->assertArrayHasKey('events', $payload);
        $this->assertArrayHasKey('http://schemas.openid.net/event/backchannel-logout', $payload['events']);
        $this->assertArrayNotHasKey('nonce', $payload); // Nonce must NOT be present per OIDC spec
    }

    public function test_login_creates_user_session_and_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'login-test@sso.local',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
            'two_factor_enabled' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'login-test@sso.local',
            'password' => 'Secret123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'USER_LOGIN',
            'user_id' => $user->id,
        ]);
    }
}
