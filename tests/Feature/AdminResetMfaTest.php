<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminResetMfaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_reset_mfa_for_user_with_mfa_enabled(): void
    {
        $targetUser = User::factory()->create([
            'password' => bcrypt('Password123'),
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('secret-key'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        ]);

        UserSession::create([
            'id' => 'session-123',
            'user_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser',
            'is_active' => true,
            'last_activity_at' => now(),
        ]);

        $this->assertTrue($targetUser->hasMfaEnabled());

        $response = $this->actingAs($this->admin)->post(
            route('admin.users.reset-mfa', $targetUser->id),
            ['reason' => 'Người dùng mất thiết bị xác thực OTP']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $targetUser->refresh();
        $this->assertFalse((bool) $targetUser->two_factor_enabled);
        $this->assertNull($targetUser->two_factor_confirmed_at);
        $this->assertNull($targetUser->two_factor_secret);
        $this->assertNull($targetUser->two_factor_recovery_codes);
        $this->assertFalse($targetUser->hasMfaEnabled());

        // Verify active sessions deactivated
        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $targetUser->id,
            'is_active' => false,
        ]);

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_RESET_MFA',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_non_admin_cannot_reset_mfa(): void
    {
        $targetUser = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->regularUser)->post(
            route('admin.users.reset-mfa', $targetUser->id),
            ['reason' => 'Hacker attempt']
        );

        $response->assertForbidden();
    }

    public function test_guest_cannot_reset_mfa(): void
    {
        $targetUser = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post(
            route('admin.users.reset-mfa', $targetUser->id),
            ['reason' => 'Guest attempt']
        );

        $response->assertRedirect(route('login'));
    }

    public function test_admin_reset_mfa_for_non_existent_user_returns_error(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.users.reset-mfa', 99999),
            ['reason' => 'Non-existent user']
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_user_can_login_directly_with_password_after_admin_resets_mfa(): void
    {
        $password = 'Secret@123456';
        $targetUser = User::factory()->create([
            'email' => 'mfa-user@example.com',
            'password' => bcrypt($password),
            'status' => 'active',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('secret-key'),
        ]);

        // 1. Before reset: login triggers MFA challenge redirect
        $initialLogin = $this->post('/login', [
            'email' => $targetUser->email,
            'password' => $password,
        ]);
        $initialLogin->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        // 2. Admin resets 2FA
        $this->actingAs($this->admin)->post(
            route('admin.users.reset-mfa', $targetUser->id),
            ['reason' => 'User lost authenticator']
        );

        // 3. Admin logs out, user logs in now: direct login succeeds!
        auth()->logout();
        $this->flushSession();
        $secondLogin = $this->post('/login', [
            'email' => $targetUser->email,
            'password' => $password,
        ]);

        $this->assertAuthenticatedAs($targetUser);
        $secondLogin->assertRedirect(route('dashboard'));
    }
}
