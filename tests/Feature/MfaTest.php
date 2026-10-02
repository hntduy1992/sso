<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_mfa_logs_in_directly(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'status' => 'active',
            'two_factor_enabled' => false,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_with_mfa_is_redirected_to_challenge(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'status' => 'active',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt($secret),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        // Should NOT be logged in yet
        $this->assertGuest();

        // Should redirect to MFA challenge
        $response->assertRedirect(route('mfa.challenge'));

        // Pending user ID should be in session
        $this->assertEquals($user->id, session('mfa_pending_user_id'));
    }

    public function test_mfa_challenge_screen_renders_inertia_component(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'two_factor_enabled' => true,
        ]);

        $response = $this->withSession(['mfa_pending_user_id' => $user->id])
            ->get('/mfa/challenge');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Auth/MfaChallenge'));
    }

    public function test_valid_totp_code_completes_login(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('Password123'),
            'status' => 'active',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt($secret),
        ]);

        // Simulate being in the MFA pending state
        $this->withSession(['mfa_pending_user_id' => $user->id]);

        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->post('/mfa/challenge', [
            'code' => $validCode,
            'is_recovery_code' => false,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_invalid_totp_code_fails_with_validation_error(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'status' => 'active',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt($secret),
        ]);

        $this->withSession(['mfa_pending_user_id' => $user->id]);

        $response = $this->post('/mfa/challenge', [
            'code' => '000000', // Invalid code
            'is_recovery_code' => false,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_valid_recovery_code_completes_login(): void
    {
        $plainCode = 'ABCD-EFGH-IJKL-MNOP';

        $user = User::factory()->create([
            'status' => 'active',
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode([
                Hash::make($plainCode),
            ])),
        ]);

        $this->withSession(['mfa_pending_user_id' => $user->id]);

        $response = $this->post('/mfa/challenge', [
            'code' => $plainCode,
            'is_recovery_code' => true,
        ]);

        $this->assertAuthenticatedAs($user);

        // Recovery code should be consumed (removed)
        $remainingCodes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
        $this->assertCount(0, $remainingCodes);
    }

    public function test_mfa_challenge_without_pending_session_redirects_to_login(): void
    {
        $response = $this->get('/mfa/challenge');

        $response->assertRedirect(route('login'));
    }
}
