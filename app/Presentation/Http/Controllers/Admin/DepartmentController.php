<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PositionType;
use App\Presentation\Http\Requests\Admin\StoreDepartmentRequest;
use App\Presentation\Http\Requests\Admin\UpdateDepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display the organizational departments and specialized teams hierarchy.
     */
    public function index(Request $request): Response
    {
        $departments = Department::with([
            'activePositions.user.profile',
            'activePositions.positionType',
        ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(function (Department $dept) {
                // Group active positions by position type
                $active = $dept->activePositions;

                $director = $active->first(fn ($p) => $p->positionType?->code === PositionType::DIRECTOR);
                $deputyDirectors = $active->filter(fn ($p) => $p->positionType?->code === PositionType::DEPUTY_DIRECTOR);
                $teamLead = $active->first(fn ($p) => $p->positionType?->code === PositionType::TEAM_LEAD);
                $deputyTeamLeads = $active->filter(fn ($p) => $p->positionType?->code === PositionType::DEPUTY_TEAM_LEAD);
                $members = $active->filter(fn ($p) => $p->positionType?->code === PositionType::MEMBER);

                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'type' => $dept->type,
                    'type_label' => $dept->type === 'management_board' ? 'Ban Giám đốc' : 'Tổ chuyên môn',
                    'description' => $dept->description,
                    'is_active' => (bool) $dept->is_active,
                    'display_order' => $dept->display_order,
                    'members_count' => $active->count(),
                    'leadership' => [
                        'director' => $director ? [
                            'user_id' => $director->user_id,
                            'name' => $director->user?->name,
                            'avatar' => $director->user?->profile?->getEffectiveAvatarUrl($director->user->avatar_url),
                            'is_primary' => (bool) $director->is_primary,
                        ] : null,
                        'deputy_directors' => $deputyDirectors->map(fn ($p) => [
                            'user_id' => $p->user_id,
                            'name' => $p->user?->name,
                            'avatar' => $p->user?->profile?->getEffectiveAvatarUrl($p->user->avatar_url),
                            'is_primary' => (bool) $p->is_primary,
                        ])->values(),
                        'team_lead' => $teamLead ? [
                            'user_id' => $teamLead->user_id,
                            'name' => $teamLead->user?->name,
                            'avatar' => $teamLead->user?->profile?->getEffectiveAvatarUrl($teamLead->user->avatar_url),
                            'is_primary' => (bool) $teamLead->is_primary,
                            'is_concurrent' => ! $teamLead->is_primary,
                        ] : null,
                        'deputy_team_leads' => $deputyTeamLeads->map(fn ($p) => [
                            'user_id' => $p->user_id,
                            'name' => $p->user?->name,
                            'avatar' => $p->user?->profile?->getEffectiveAvatarUrl($p->user->avatar_url),
                        ])->values(),
                    ],
                    'members_list' => $members->map(fn ($p) => [
                        'id' => $p->id,
                        'user_id' => $p->user_id,
                        'name' => $p->user?->name,
                        'email' => $p->user?->email,
                        'avatar' => $p->user?->profile?->getEffectiveAvatarUrl($p->user->avatar_url),
                        'started_at' => (string) $p->started_at,
                    ])->values(),
                ];
            });

        $positionTypes = PositionType::where('is_active', true)
            ->orderBy('level')
            ->get();

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'positionTypes' => $positionTypes,
        ]);
    }

    /**
     * Store a newly created department / specialized team.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        $this->auditLogger->log(
            event: 'ADMIN_CREATE_DEPARTMENT',
            user: $request->user(),
            payload: [
                'department_id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'type' => $department->type,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã tạo đơn vị \"{$department->name}\" thành công.");
    }

    /**
     * Update an existing department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        $this->auditLogger->log(
            event: 'ADMIN_UPDATE_DEPARTMENT',
            user: $request->user(),
            payload: [
                'department_id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã cập nhật thông tin đơn vị \"{$department->name}\" thành công.");
    }

    /**
     * Delete a department (prevent deletion if active positions exist).
     */
    public function destroy(Department $department, Request $request): RedirectResponse
    {
        if ($department->activePositions()->exists()) {
            return redirect()->back()
                ->with('error', "Không thể xóa đơn vị \"{$department->name}\" vì vẫn còn nhân sự đang giữ chức vụ. Vui lòng kết thúc chức vụ của các nhân sự trước.");
        }

        $departmentName = $department->name;
        $department->delete();

        $this->auditLogger->log(
            event: 'ADMIN_DELETE_DEPARTMENT',
            user: $request->user(),
            payload: [
                'department_id' => $department->id,
                'name' => $departmentName,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã xóa đơn vị \"{$departmentName}\" thành công.");
    }
}
