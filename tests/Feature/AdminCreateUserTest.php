<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PositionType;
use App\Models\User;
use App\Models\UserPosition;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PositionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCreateUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PositionTypeSeeder::class);
        $this->seed(DepartmentSeeder::class);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->regularUser = User::factory()->create(['role' => 'user', 'status' => 'active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'newhire',
            'email' => 'NewHire@Example.com',
            'password' => 'Welcome123',
            'role' => 'user',
            'status' => 'active',
            'full_name' => 'Nguyễn Văn Mới',
            'phone_number' => '0901234567',
            'gender' => 'male',
            'date_of_birth' => '1990-12-25',
        ], $overrides);
    }

    public function test_admin_creates_user_with_profile_and_hashed_password(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', $this->validPayload());

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user = User::where('email', 'newhire@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Welcome123', $user->password));
        $this->assertSame('Nguyễn Văn Mới', $user->profile->full_name);
        $this->assertDatabaseCount('user_positions', 0);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ADMIN_CREATE_USER']);
    }

    public function test_admin_can_view_profile_of_created_user_with_date_of_birth(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', $this->validPayload());

        $user = User::where('email', 'newhire@example.com')->firstOrFail();

        $this->actingAs($this->admin)
            ->get('/admin/users/'.$user->id)
            ->assertOk();
    }

    public function test_admin_creates_user_assigned_as_member_of_selected_team(): void
    {
        $team = Department::where('code', 'TCM-01')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/users', $this->validPayload(['department_id' => $team->id]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'newhire@example.com')->firstOrFail();
        $position = UserPosition::where('user_id', $user->id)->firstOrFail();

        $this->assertSame($team->id, $position->department_id);
        $this->assertSame(PositionType::MEMBER, $position->positionType->code);
        $this->assertTrue((bool) $position->is_primary);
        $this->assertTrue($position->isActive());
    }

    public function test_management_board_cannot_be_used_as_department(): void
    {
        $board = Department::where('code', 'BGD')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/users', $this->validPayload(['department_id' => $board->id]))
            ->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('users', ['email' => 'newhire@example.com']);
    }

    public function test_duplicate_email_and_weak_password_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', $this->validPayload(['email' => $this->regularUser->email, 'password' => 'short']))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $this->actingAs($this->regularUser)
            ->post('/admin/users', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'newhire@example.com']);
    }
}
