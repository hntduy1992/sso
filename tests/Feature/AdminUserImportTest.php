<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PositionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserImportTest extends TestCase
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
     * @return array<string, string>
     */
    private function row(string $key, string $email, array $overrides = []): array
    {
        return array_merge([
            'key' => $key,
            'name' => explode('@', $email)[0],
            'email' => $email,
            'full_name' => 'Người Dùng '.$key,
            'phone_number' => '',
            'contact_email' => '',
            'gender' => 'female',
            'date_of_birth' => '1995-05-20',
            'address' => '',
        ], $overrides);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function payload(array $rows, array $overrides = []): array
    {
        return array_merge(['password' => 'Welcome123', 'department_id' => null, 'rows' => $rows], $overrides);
    }

    public function test_import_page_is_only_for_admins(): void
    {
        $this->actingAs($this->admin)->get('/admin/users/import')->assertOk();
        $this->actingAs($this->regularUser)->get('/admin/users/import')->assertForbidden();
    }

    public function test_dry_run_reports_per_row_errors_without_creating_users(): void
    {
        $rows = [
            $this->row('a', 'ok@example.com'),
            $this->row('b', 'bad-email'),
            $this->row('c', $this->regularUser->email),
            $this->row('d', 'ok@example.com'),
            $this->row('e', 'dob@example.com', ['date_of_birth' => '2999-01-01', 'gender' => 'robot']),
        ];

        $response = $this->actingAs($this->admin)->postJson('/admin/users/import/validate', $this->payload($rows));

        $response->assertOk();
        $results = collect($response->json('results'))->keyBy('key');

        $this->assertSame('valid', $results['a']['status']);
        $this->assertSame('invalid', $results['b']['status']);
        $this->assertArrayHasKey('email', $results['b']['errors']);
        $this->assertSame('Email này đã tồn tại trong hệ thống.', $results['c']['errors']['email']);
        $this->assertArrayHasKey('email', $results['d']['errors']);
        $this->assertArrayHasKey('date_of_birth', $results['e']['errors']);
        $this->assertArrayHasKey('gender', $results['e']['errors']);

        $this->assertDatabaseMissing('users', ['email' => 'ok@example.com']);
    }

    public function test_import_creates_only_valid_rows_and_returns_errors_for_the_rest(): void
    {
        $rows = [
            $this->row('a', 'first@example.com'),
            $this->row('b', 'broken'),
            $this->row('c', 'third@example.com'),
        ];

        $response = $this->actingAs($this->admin)->postJson('/admin/users/import', $this->payload($rows));

        $results = collect($response->json('results'))->keyBy('key');
        $this->assertSame('created', $results['a']['status']);
        $this->assertSame('invalid', $results['b']['status']);
        $this->assertSame('created', $results['c']['status']);

        $this->assertDatabaseHas('users', ['email' => 'first@example.com', 'role' => 'user']);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $results['a']['user_id'], 'full_name' => 'Người Dùng a']);
        $this->assertDatabaseMissing('users', ['email' => 'broken']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ADMIN_IMPORT_USERS']);
    }

    public function test_import_with_department_assigns_users_as_team_members(): void
    {
        $team = Department::where('code', 'TCM-02')->firstOrFail();

        $this->actingAs($this->admin)->postJson('/admin/users/import', $this->payload(
            [$this->row('a', 'member@example.com')],
            ['department_id' => $team->id],
        ))->assertOk();

        $user = User::where('email', 'member@example.com')->firstOrFail();
        $this->assertDatabaseHas('user_positions', [
            'user_id' => $user->id,
            'department_id' => $team->id,
            'is_primary' => true,
            'ended_at' => null,
        ]);
    }

    public function test_resubmitting_a_fixed_row_succeeds_after_a_failed_attempt(): void
    {
        $first = $this->actingAs($this->admin)->postJson('/admin/users/import', $this->payload(
            [$this->row('a', 'retry@example.com', ['phone_number' => 'abc'])]
        ));
        $this->assertSame('invalid', $first->json('results.0.status'));

        $second = $this->actingAs($this->admin)->postJson('/admin/users/import', $this->payload(
            [$this->row('a', 'retry@example.com', ['phone_number' => '0901234567'])]
        ));

        $this->assertSame('created', $second->json('results.0.status'));
    }

    public function test_weak_password_and_oversized_chunks_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/users/import', $this->payload([$this->row('a', 'weak@example.com')], ['password' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $tooMany = array_map(fn (int $i): array => $this->row("r{$i}", "bulk{$i}@example.com"), range(1, 201));

        $this->actingAs($this->admin)
            ->postJson('/admin/users/import', $this->payload($tooMany))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rows');
    }

    public function test_non_admin_cannot_import_users(): void
    {
        $payload = $this->payload([$this->row('a', 'blocked@example.com')]);

        $this->actingAs($this->regularUser)->postJson('/admin/users/import', $payload)->assertForbidden();
        $this->actingAs($this->regularUser)->postJson('/admin/users/import/validate', $payload)->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }
}
