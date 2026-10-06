<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\DTOs\AssignPositionDTO;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Collection;

interface UserPositionRepositoryInterface
{
    /**
     * Find a position by ID.
     */
    public function findById(int $positionId): ?UserPosition;

    /**
     * Assign a position to a user.
     */
    public function assignPosition(AssignPositionDTO $dto): UserPosition;

    /**
     * Terminate an active position.
     */
    public function terminatePosition(int $positionId, ?string $notes = null): bool;

    /**
     * Get all active positions for a user.
     *
     * @return Collection<int, UserPosition>
     */
    public function getCurrentPositions(int $userId): Collection;

    /**
     * Get all positions (including historical) for a user, latest first.
     *
     * @return Collection<int, UserPosition>
     */
    public function getPositionHistory(int $userId): Collection;

    /**
     * Check if a department already has an active Team Lead (Tổ trưởng).
     */
    public function hasActiveTeamLead(int $departmentId, ?int $excludeUserId = null): bool;

    /**
     * Check if a department already has an active Director (Giám đốc).
     */
    public function hasActiveDirector(int $departmentId, ?int $excludeUserId = null): bool;
}
