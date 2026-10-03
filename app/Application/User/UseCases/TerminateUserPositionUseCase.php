<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\Exceptions\PositionConflictException;
use App\Domain\User\Repositories\UserPositionRepositoryInterface;
use App\Models\User;

class TerminateUserPositionUseCase
{
    public function __construct(
        private readonly UserPositionRepositoryInterface $userPositionRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Terminate an active user position.
     *
     * @throws PositionConflictException
     */
    public function execute(int $positionId, ?string $notes = null, ?User $adminUser = null): bool
    {
        $position = $this->userPositionRepository->findById($positionId);
        if (! $position) {
            throw new PositionConflictException('Chức vụ không tồn tại.');
        }

        if (! $position->isActive()) {
            throw new PositionConflictException('Chức vụ này đã được kết thúc trước đó.');
        }

        $success = $this->userPositionRepository->terminatePosition($positionId, $notes);

        if ($success) {
            $this->auditLogger->log(
                event: 'ADMIN_TERMINATE_POSITION',
                user: $adminUser,
                payload: [
                    'position_id' => $position->id,
                    'target_user_id' => $position->user_id,
                    'department_id' => $position->department_id,
                    'department_name' => $position->department?->name,
                    'position_type_id' => $position->position_type_id,
                    'position_type_name' => $position->positionType?->name,
                    'ended_at' => now()->toDateString(),
                    'notes' => $notes,
                ]
            );
        }

        return $success;
    }
}
