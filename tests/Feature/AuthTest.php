<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_user_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@sso.local',
            'password' => bcrypt('Password123'),
            'status' => 'active',
            'role' => 'user',
        ]);

        $response = $this->post('/login', [
            'email' => 'test@sso.local',
            'password' => 'Password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_suspended_user_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => 'locked@sso.local',
            'password' => bcrypt('Password123'),
            'status' => 'suspended',
            'role' => 'user',
        ]);

        $response = $this->post('/login', [
            'email' => 'locked@sso.local',
            'password' => 'Password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'test@sso.local',
            'password' => bcrypt('Password123'),
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'test@sso.local',
            'password' => 'WrongPassword',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_update_user_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $targetUser = User::factory()->create([
            'role' => 'user',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('suspended', $targetUser->fresh()->status);
    }

    public function test_admin_cannot_lock_themselves(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$admin->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('active', $admin->fresh()->status);
    }
}
