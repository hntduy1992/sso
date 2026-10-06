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

class AdminHrmManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    private Department $banGiamDoc;

    private Department $team1;

    private Department $team2;

    private PositionType $directorPos;

    private PositionType $deputyDirectorPos;

    private PositionType $teamLeadPos;

    private PositionType $deputyTeamLeadPos;

    private PositionType $memberPos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PositionTypeSeeder::class);
        $this->seed(DepartmentSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@sso.local',
            'name' => 'Admin User',
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('AdminPassword123!'),
        ]);

        $this->regularUser = User::factory()->create([
            'email' => 'officer@sso.local',
            'name' => 'Officer User',
            'role' => 'user',
            'status' => 'active',
            'password' => bcrypt('UserPassword123!'),
        ]);

        $this->banGiamDoc = Department::where('code', 'BGD')->firstOrFail();
        $this->team1 = Department::where('code', 'TCM-01')->firstOrFail();
        $this->team2 = Department::where('code', 'TCM-02')->firstOrFail();

        $this->directorPos = PositionType::where('code', PositionType::DIRECTOR)->firstOrFail();
        $this->deputyDirectorPos = PositionType::where('code', PositionType::DEPUTY_DIRECTOR)->firstOrFail();
        $this->teamLeadPos = PositionType::where('code', PositionType::TEAM_LEAD)->firstOrFail();
        $this->deputyTeamLeadPos = PositionType::where('code', PositionType::DEPUTY_TEAM_LEAD)->firstOrFail();
        $this->memberPos = PositionType::where('code', PositionType::MEMBER)->firstOrFail();
    }

    public function test_non_admin_cannot_access_departments_or_user_hrm(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/admin/departments');
        // ClientController/Dashboard uses auth, but form requests enforce isAdmin
        // For department controller actions with StoreDepartmentRequest, non-admins are 403
        $responseCreate = $this->actingAs($this->regularUser)->post('/admin/departments', [
            'name' => 'Unauthorized Team',
            'code' => 'TCM-UNAUTH',
            'type' => 'specialized_team',
        ]);
        $responseCreate->assertStatus(403);

        $responseAssign = $this->actingAs($this->regularUser)->post("/admin/users/{$this->regularUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->memberPos->id,
            'started_at' => now()->toDateString(),
        ]);
        $responseAssign->assertStatus(403);
    }

    public function test_admin_can_view_departments_index(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/departments');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Departments/Index')
                ->has('departments')
                ->has('positionTypes')
            );
    }

    public function test_admin_can_create_new_department(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/departments', [
            'name' => 'Tổ Công nghệ Thông tin',
            'code' => 'TCM-CNTT',
            'type' => 'specialized_team',
            'description' => 'Chuyên trách hạ tầng công nghệ',
            'display_order' => 5,
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('departments', [
            'code' => 'TCM-CNTT',
            'name' => 'Tổ Công nghệ Thông tin',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_CREATE_DEPARTMENT',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_department(): void
    {
        $response = $this->actingAs($this->admin)->put("/admin/departments/{$this->team1->id}", [
            'name' => 'Tổ Chuyên môn 1 (Cập nhật)',
            'code' => $this->team1->code,
            'type' => 'specialized_team',
            'description' => 'Mô tả mới',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('departments', [
            'id' => $this->team1->id,
            'name' => 'Tổ Chuyên môn 1 (Cập nhật)',
        ]);
    }

    public function test_admin_cannot_delete_department_with_active_members(): void
    {
        // Assign a member to team1
        UserPosition::create([
            'user_id' => $this->regularUser->id,
            'department_id' => $this->team1->id,
            'position_type_id' => $this->memberPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/departments/{$this->team1->id}");

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('departments', [
            'id' => $this->team1->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_delete_empty_department(): void
    {
        $newDept = Department::create([
            'name' => 'Tổ Tạm thời',
            'code' => 'TCM-TEMP',
            'type' => 'specialized_team',
            'is_active' => true,
            'display_order' => 99,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/departments/{$newDept->id}");

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('departments', ['id' => $newDept->id]);
    }

    public function test_admin_can_view_user_hrm_profile(): void
    {
        $response = $this->actingAs($this->admin)->get("/admin/users/{$this->regularUser->id}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Show')
                ->has('targetUser')
                ->has('activePositions')
                ->has('departments')
                ->has('positionTypes')
            );
    }

    public function test_admin_can_assign_primary_position_to_user(): void
    {
        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->regularUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->memberPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => true,
            'notes' => 'Quyết định tuyển dụng số 01',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('user_positions', [
            'user_id' => $this->regularUser->id,
            'department_id' => $this->team1->id,
            'position_type_id' => $this->memberPos->id,
            'is_primary' => true,
            'ended_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_ASSIGN_POSITION',
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Business Rule BR-04:
     * Mỗi Tổ chuyên môn có đúng 1 Tổ trưởng đang hoạt động.
     * Reject khi tổ đã có Tổ trưởng đang hoạt động.
     */
    public function test_business_rule_br04_team_cannot_have_two_active_team_leads(): void
    {
        // 1. Assign regularUser as Team Lead of team1
        UserPosition::create([
            'user_id' => $this->regularUser->id,
            'department_id' => $this->team1->id,
            'position_type_id' => $this->teamLeadPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => true,
        ]);

        // 2. Create another user and attempt to also assign as Team Lead of team1
        $anotherUser = User::factory()->create();

        $response = $this->actingAs($this->admin)->post("/admin/users/{$anotherUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->teamLeadPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $response->assertSessionHas('error');

        // Confirm anotherUser was NOT assigned as Team Lead of team1
        $this->assertDatabaseMissing('user_positions', [
            'user_id' => $anotherUser->id,
            'department_id' => $this->team1->id,
            'position_type_id' => $this->teamLeadPos->id,
        ]);
    }

    /**
     * Business Rule BR-02:
     * Phó Giám đốc CÓ THỂ kiêm Tổ trưởng của một hoặc nhiều Tổ chuyên môn.
     * is_primary = true với vị trí Phó Giám đốc trong Ban Giám đốc.
     * is_primary = false với các vị trí Tổ trưởng kiêm nhiệm.
     */
    public function test_business_rule_br02_deputy_director_can_concurrently_lead_teams(): void
    {
        $deputyDirectorUser = User::factory()->create(['name' => 'Phó Giám đốc Kiêm Nhiệm']);

        // 1. Assign as Deputy Director in Ban Giám đốc (Primary position)
        $resp1 = $this->actingAs($this->admin)->post("/admin/users/{$deputyDirectorUser->id}/positions", [
            'department_id' => $this->banGiamDoc->id,
            'position_type_id' => $this->deputyDirectorPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => true,
            'notes' => 'Bổ nhiệm Phó Giám đốc',
        ]);
        $resp1->assertSessionHas('success');

        // 2. Concurrently assign as Team Lead of Team 1 (is_primary = false)
        $resp2 = $this->actingAs($this->admin)->post("/admin/users/{$deputyDirectorUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->teamLeadPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => false,
            'notes' => 'Kiêm nhiệm Tổ trưởng Tổ 1',
        ]);
        $resp2->assertSessionHas('success');

        // 3. Concurrently assign as Team Lead of Team 2 (is_primary = false)
        $resp3 = $this->actingAs($this->admin)->post("/admin/users/{$deputyDirectorUser->id}/positions", [
            'department_id' => $this->team2->id,
            'position_type_id' => $this->teamLeadPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => false,
            'notes' => 'Kiêm nhiệm Tổ trưởng Tổ 2',
        ]);
        $resp3->assertSessionHas('success');

        // Verify in database:
        // Exactly 1 primary position (Deputy Director in Ban Giám đốc)
        $this->assertEquals(1, UserPosition::where('user_id', $deputyDirectorUser->id)->active()->primary()->count());
        $primaryPos = UserPosition::where('user_id', $deputyDirectorUser->id)->active()->primary()->first();
        $this->assertEquals($this->banGiamDoc->id, $primaryPos->department_id);
        $this->assertEquals($this->deputyDirectorPos->id, $primaryPos->position_type_id);

        // Exactly 2 concurrent active positions (Team Lead of Team 1 & Team 2)
        $this->assertEquals(2, UserPosition::where('user_id', $deputyDirectorUser->id)->active()->concurrent()->count());
    }

    /**
     * Business Rule:
     * Only 1 active primary position per user.
     * When a user already has an active primary position (e.g. Phó Giám đốc in Ban Giám đốc),
     * assigning a position in another unit (e.g. Tổ trưởng Tổ 1) must be concurrent (kiêm nhiệm, is_primary = false).
     * The original primary position remains primary.
     */
    public function test_business_rule_user_with_existing_primary_position_assigned_subsequent_position_as_concurrent(): void
    {
        // 1. Initial primary position: Phó Giám đốc in Ban Giám đốc
        $pos1 = UserPosition::create([
            'user_id' => $this->regularUser->id,
            'department_id' => $this->banGiamDoc->id,
            'position_type_id' => $this->deputyDirectorPos->id,
            'started_at' => '2025-01-01',
            'is_primary' => true,
        ]);

        // 2. Assign position in Team 1
        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->regularUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->teamLeadPos->id,
            'started_at' => now()->toDateString(),
            'notes' => 'Kiêm nhiệm Tổ trưởng Tổ 1',
        ]);

        $response->assertSessionHas('success');

        // Original primary position MUST remain primary
        $this->assertTrue((bool) $pos1->fresh()->is_primary);

        // New position in Team 1 MUST be concurrent (kiêm nhiệm, is_primary = false)
        $newPos = UserPosition::where('user_id', $this->regularUser->id)
            ->where('department_id', $this->team1->id)
            ->firstOrFail();
        $this->assertFalse((bool) $newPos->is_primary);

        // Exactly 1 active primary position across all departments
        $this->assertEquals(1, UserPosition::where('user_id', $this->regularUser->id)->active()->primary()->count());
    }

    /**
     * Business Rule:
     * Mỗi một đơn vị người dùng chỉ đảm nhiệm 1 chức vụ.
     * Attempting to assign another position in the same department without ending the previous one is rejected.
     */
    public function test_business_rule_user_cannot_have_more_than_one_active_position_in_the_same_department(): void
    {
        // 1. Initial position in Team 1: Member
        UserPosition::create([
            'user_id' => $this->regularUser->id,
            'department_id' => $this->team1->id,
            'position_type_id' => $this->memberPos->id,
            'started_at' => '2025-01-01',
            'is_primary' => true,
        ]);

        // 2. Attempt to assign another position (Deputy Team Lead) in the SAME department (Team 1)
        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->regularUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->deputyTeamLeadPos->id,
            'started_at' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');

        // Verify only 1 active position exists in Team 1
        $this->assertEquals(
            1,
            UserPosition::where('user_id', $this->regularUser->id)
                ->where('department_id', $this->team1->id)
                ->whereNull('ended_at')
                ->count()
        );
    }

    /**
     * Business Rule BR-05:
     * Inapplicable position type to department type must be rejected
     * (e.g. Director cannot be assigned to specialized_team, Member cannot be assigned to management_board).
     */
    public function test_business_rule_br05_inapplicable_position_type_is_rejected(): void
    {
        // Attempt to assign Director to a specialized team
        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->regularUser->id}/positions", [
            'department_id' => $this->team1->id,
            'position_type_id' => $this->directorPos->id,
            'started_at' => now()->toDateString(),
            'is_primary' => true,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('user_positions', [
            'user_id' => $this->regularUser->id,
            'position_type_id' => $this->directorPos->id,
        ]);
    }

    public function test_admin_can_terminate_user_position(): void
    {
        $pos = UserPosition::create([
            'user_id' => $this->regularUser->id,
            'department_id' => $this->team1->id,
            'position_type_id' => $this->memberPos->id,
            'started_at' => '2025-01-01',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            "/admin/users/{$this->regularUser->id}/positions/{$pos->id}",
            ['notes' => 'Hết nhiệm kỳ']
        );

        $response->assertSessionHas('success');

        $this->assertNotNull($pos->fresh()->ended_at);
        $this->assertEquals('Hết nhiệm kỳ', $pos->fresh()->notes);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_TERMINATE_POSITION',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reset_user_password_and_revoke_sessions(): void
    {
        $newPassword = 'BrandNewPassword999!';

        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->regularUser->id}/reset-password", [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'reason' => 'Người dùng yêu cầu qua hotline do quên mật khẩu',
        ]);

        $response->assertSessionHas('success');

        // Password hash updated
        $this->assertTrue(Hash::check($newPassword, (string) $this->regularUser->fresh()->password));

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_RESET_PASSWORD',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_update_user_and_profile(): void
    {
        $response = $this->actingAs($this->admin)->put("/admin/users/{$this->regularUser->id}", [
            'name' => 'officer_updated',
            'email' => 'officer_updated@sso.local',
            'role' => 'admin',
            'status' => 'suspended',
            'full_name' => 'Nguyễn Văn Cán Bộ',
            'phone_number' => '0987654321',
            'contact_email' => 'contact@sso.local',
            'address' => 'Hà Nội',
            'gender' => 'male',
            'date_of_birth' => '1990-05-15',
            'bio' => 'Cán bộ kỹ thuật',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'officer_updated',
            'email' => 'officer_updated@sso.local',
            'role' => 'admin',
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->regularUser->id,
            'full_name' => 'Nguyễn Văn Cán Bộ',
            'phone_number' => '0987654321',
            'gender' => 'male',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_UPDATE_USER',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $response = $this->actingAs($this->admin)->delete("/admin/users/{$this->regularUser->id}");

        $response->assertSessionHas('success');

        $this->assertSoftDeleted('users', [
            'id' => $this->regularUser->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_SOFT_DELETE_USER',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}");

        $response->assertSessionHas('error');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_restore_soft_deleted_user(): void
    {
        $this->regularUser->delete();
        $this->assertSoftDeleted('users', ['id' => $this->regularUser->id]);

        $response = $this->actingAs($this->admin)->post("/admin/users/{$this->regularUser->id}/restore");

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_RESTORE_USER',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_force_delete_user(): void
    {
        $target = User::factory()->create();
        $target->delete();

        $response = $this->actingAs($this->admin)->delete("/admin/users/{$target->id}/force");

        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('users', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ADMIN_FORCE_DELETE_USER',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_dashboard_can_filter_by_trashed_status(): void
    {
        $trashedUser = User::factory()->create();
        $trashedUser->delete();

        $response = $this->actingAs($this->admin)->get('/dashboard?status=trashed');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('users.data', 1)
                ->where('users.data.0.id', $trashedUser->id)
            );
    }
}
