<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\DTOs\AssignPositionDTO;
use App\Domain\User\Exceptions\PositionConflictException;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Domain\User\Repositories\UserPositionRepositoryInterface;
use App\Models\Department;
use App\Models\PositionType;
use App\Models\User;
use App\Models\UserPosition;

class AssignUserPositionUseCase
{
    public function __construct(
        private readonly UserPositionRepositoryInterface $userPositionRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Assign a position to a user with strict HRM business rule validation.
     *
     * @throws UserNotFoundException
     * @throws PositionConflictException
     */
    public function execute(AssignPositionDTO $dto, ?User $adminUser = null): UserPosition
    {
        $user = User::find($dto->userId);
        if (! $user) {
            throw new UserNotFoundException("Không tìm thấy người dùng với ID: {$dto->userId}");
        }

        $department = Department::find($dto->departmentId);
        if (! $department || ! $department->is_active) {
            throw new PositionConflictException('Đơn vị/phòng ban không tồn tại hoặc đã bị vô hiệu hóa.');
        }

        $positionType = PositionType::find($dto->positionTypeId);
        if (! $positionType || ! $positionType->is_active) {
            throw new PositionConflictException('Loại chức danh không tồn tại hoặc đã bị vô hiệu hóa.');
        }

        // Validate applicability to department type
        if (! $positionType->isApplicableTo($department->type)) {
            $applicableLabel = $positionType->applicable_to === 'management_board' ? 'Ban Giám đốc' : 'Tổ chuyên môn';
            throw new PositionConflictException(
                "Chức danh '{$positionType->name}' chỉ áp dụng cho {$applicableLabel}, không thể áp dụng cho '{$department->name}'."
            );
        }

        // Enforce: Each user can only hold AT MOST 1 active position per department
        $existingDeptPosition = UserPosition::query()
            ->with('positionType')
            ->where('user_id', $user->id)
            ->where('department_id', $department->id)
            ->whereNull('ended_at')
            ->first();

        if ($existingDeptPosition) {
            throw new PositionConflictException(
                "Nhân sự {$user->name} hiện đang giữ chức danh '{$existingDeptPosition->positionType?->name}' tại đơn vị '{$department->name}'. Mỗi đơn vị người dùng chỉ được đảm nhiệm 1 chức vụ. Vui lòng kết thúc chức vụ hiện tại trước khi bổ nhiệm chức vụ mới."
            );
        }

        // Enforce BR-04: Specialized teams may only have ONE active Team Lead
        if ($positionType->code === PositionType::TEAM_LEAD) {
            if ($this->userPositionRepository->hasActiveTeamLead($department->id, $user->id)) {
                throw new PositionConflictException(
                    "Tổ chuyên môn '{$department->name}' đã có Tổ trưởng đang hoạt động. Vui lòng kết thúc chức vụ của Tổ trưởng hiện tại trước khi bổ nhiệm mới."
                );
            }
        }

        // Enforce Director uniqueness for Ban Giám đốc
        if ($positionType->code === PositionType::DIRECTOR) {
            if ($this->userPositionRepository->hasActiveDirector($department->id, $user->id)) {
                throw new PositionConflictException(
                    'Ban Giám đốc đã có Giám đốc đang đương nhiệm.'
                );
            }
        }

        // Enforce: If user already has an active primary position (e.g. Phó Giám đốc ở Ban Giám đốc),
        // any newly appointed position MUST be concurrent (kiêm nhiệm, is_primary = false).
        // If user does not have any active primary position, the new position becomes the primary position.
        $hasActivePrimary = UserPosition::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->where('is_primary', true)
            ->exists();

        $effectiveIsPrimary = ! $hasActivePrimary;

        $effectiveDto = new AssignPositionDTO(
            userId: $dto->userId,
            departmentId: $dto->departmentId,
            positionTypeId: $dto->positionTypeId,
            startedAt: $dto->startedAt,
            isPrimary: $effectiveIsPrimary,
            notes: $dto->notes,
        );

        $position = $this->userPositionRepository->assignPosition($effectiveDto);

        $this->auditLogger->log(
            event: 'ADMIN_ASSIGN_POSITION',
            user: $adminUser,
            payload: [
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'department_id' => $department->id,
                'department_name' => $department->name,
                'position_type_id' => $positionType->id,
                'position_type_name' => $positionType->name,
                'is_primary' => $effectiveIsPrimary,
                'started_at' => $dto->startedAt,
            ]
        );

        return $position;
    }
}
