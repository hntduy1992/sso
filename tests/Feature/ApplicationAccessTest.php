<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApplicationAccessGrant;
use App\Models\Department;
use App\Models\PositionType;
use App\Models\User;
use App\Models\UserPosition;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PositionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

class ApplicationAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@sso.local',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'email' => 'regular@sso.local',
            'role' => 'user',
            'status' => 'active',
        ]);

        $this->client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'HRM Portal Satellite App',
            'redirect_uris' => 'https://hrm.example.com/callback',
            'grant_types' => 'authorization_code',
            'client_type' => 'CONFIDENTIAL',
            'revoked' => false,
            'pkce_enforced' => true,
        ]);
    }

    public function test_admin_can_view_application_access_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/application-access');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page->component('Admin/ApplicationAccess/Index'));
    }

    public function test_non_admin_cannot_view_application_access_page(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/application-access');

        $response->assertStatus(403);
    }

    public function test_admin_can_grant_and_revoke_user_access(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/admin/application-access/{$this->client->id}/users", [
                'user_id' => $this->user->id,
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('application_access_grants', [
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'department_id' => null,
        ]);

        $grant = ApplicationAccessGrant::where('client_id', $this->client->id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $revokeResponse = $this->actingAs($this->admin)
            ->delete("/admin/application-access/{$this->client->id}/grants/{$grant->id}");

        $revokeResponse->assertSessionHas('success');

        $this->assertDatabaseMissing('application_access_grants', [
            'id' => $grant->id,
        ]);
    }

    public function test_admin_can_grant_department_access(): void
    {
        $department = Department::create([
            'name' => 'Tổ Công nghệ thông tin',
            'code' => 'T-CNTT',
            'type' => 'specialized_team',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/application-access/{$this->client->id}/departments", [
                'department_id' => $department->id,
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('application_access_grants', [
            'client_id' => $this->client->id,
            'user_id' => null,
            'department_id' => $department->id,
        ]);
    }

    public function test_unauthorized_user_is_blocked_with_access_denied_page(): void
    {
        $response = $this->actingAs($this->user)->get(
            "/oauth/authorize?client_id={$this->client->id}&redirect_uri=https://hrm.example.com/callback&response_type=code&code_challenge=test_code_challenge_12345678901234567890&code_challenge_method=S256"
        );

        $response->assertStatus(403)
            ->assertInertia(fn ($page) => $page
                ->component('Auth/AccessDenied')
                ->where('applicationName', 'HRM Portal Satellite App')
            );
    }

    public function test_user_with_personal_grant_is_allowed(): void
    {
        ApplicationAccessGrant::create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'granted_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            "/oauth/authorize?client_id={$this->client->id}&redirect_uri=https://hrm.example.com/callback&response_type=code&code_challenge=test_code_challenge_12345678901234567890&code_challenge_method=S256"
        );

        // Not 403 Access Denied
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_user_in_granted_department_is_allowed(): void
    {
        $this->seed([PositionTypeSeeder::class, DepartmentSeeder::class]);

        $department = Department::where('code', 'TCM-01')->firstOrFail();
        $positionType = PositionType::where('code', PositionType::MEMBER)->firstOrFail();

        // Assign user to department
        UserPosition::create([
            'user_id' => $this->user->id,
            'department_id' => $department->id,
            'position_type_id' => $positionType->id,
            'started_at' => now()->subMonths(2)->toDateString(),
            'ended_at' => null,
            'is_primary' => true,
        ]);

        // Grant access to department
        ApplicationAccessGrant::create([
            'client_id' => $this->client->id,
            'department_id' => $department->id,
            'granted_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            "/oauth/authorize?client_id={$this->client->id}&redirect_uri=https://hrm.example.com/callback&response_type=code&code_challenge=test_code_challenge_12345678901234567890&code_challenge_method=S256"
        );

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_user_with_terminated_department_position_is_denied(): void
    {
        $this->seed([PositionTypeSeeder::class, DepartmentSeeder::class]);

        $department = Department::where('code', 'TCM-01')->firstOrFail();
        $positionType = PositionType::where('code', PositionType::MEMBER)->firstOrFail();

        // Assign user with ended position
        UserPosition::create([
            'user_id' => $this->user->id,
            'department_id' => $department->id,
            'position_type_id' => $positionType->id,
            'started_at' => now()->subMonths(5)->toDateString(),
            'ended_at' => now()->subMonth()->toDateString(), // ended!
            'is_primary' => true,
        ]);

        // Grant access to department
        ApplicationAccessGrant::create([
            'client_id' => $this->client->id,
            'department_id' => $department->id,
            'granted_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            "/oauth/authorize?client_id={$this->client->id}&redirect_uri=https://hrm.example.com/callback&response_type=code&code_challenge=test_code_challenge_12345678901234567890&code_challenge_method=S256"
        );

        $response->assertStatus(403)
            ->assertInertia(fn ($page) => $page->component('Auth/AccessDenied'));
    }

    public function test_system_admin_is_always_allowed(): void
    {
        // Admin has no explicit grant, but should never be locked out
        $response = $this->actingAs($this->admin)->get(
            "/oauth/authorize?client_id={$this->client->id}&redirect_uri=https://hrm.example.com/callback&response_type=code&code_challenge=test_code_challenge_12345678901234567890&code_challenge_method=S256"
        );

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_oauth_authorize_with_x_inertia_header_redirects_via_inertia_location(): void
    {
        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get("/oauth/authorize?client_id={$this->client->id}");

        $response->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url("/oauth/authorize?client_id={$this->client->id}"));
    }
}
