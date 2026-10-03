<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Repositories;

use App\Domain\User\DTOs\AssignPositionDTO;
use App\Domain\User\Repositories\UserPositionRepositoryInterface;
use App\Models\PositionType;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentUserPositionRepository implements UserPositionRepositoryInterface
{
    public function findById(int $positionId): ?UserPosition
    {
        return UserPosition::with(['department', 'positionType', 'user'])->find($positionId);
    }

    public function assignPosition(AssignPositionDTO $dto): UserPosition
    {
        return DB::transaction(function () use ($dto): UserPosition {
            if ($dto->isPrimary) {
                // Enforce BR-03: Demote any existing active primary position to secondary
                UserPosition::where('user_id', $dto->userId)
                    ->whereNull('ended_at')
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            /** @var UserPosition $position */
            $position = UserPosition::create([
                'user_id' => $dto->userId,
                'department_id' => $dto->departmentId,
                'position_type_id' => $dto->positionTypeId,
                'started_at' => $dto->startedAt,
                'ended_at' => null,
                'is_primary' => $dto->isPrimary,
                'notes' => $dto->notes,
            ]);

            return $position->load(['department', 'positionType', 'user']);
        });
    }

    public function terminatePosition(int $positionId, ?string $notes = null): bool
    {
        $position = UserPosition::find($positionId);
        if (! $position) {
            return false;
        }

        return $position->terminate($notes);
    }

    /**
     * @return Collection<int, UserPosition>
     */
    public function getCurrentPositions(int $userId): Collection
    {
        return UserPosition::with(['department', 'positionType'])
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('is_primary')
            ->orderBy('started_at')
            ->get();
    }

    /**
     * @return Collection<int, UserPosition>
     */
    public function getPositionHistory(int $userId): Collection
    {
        return UserPosition::with(['department', 'positionType'])
            ->where('user_id', $userId)
            ->orderByDesc('started_at')
            ->get();
    }

    public function hasActiveTeamLead(int $departmentId, ?int $excludeUserId = null): bool
    {
        return UserPosition::query()
            ->where('department_id', $departmentId)
            ->whereNull('ended_at')
            ->whereHas('positionType', fn ($q) => $q->where('code', PositionType::TEAM_LEAD))
            ->when($excludeUserId, fn ($q) => $q->where('user_id', '!=', $excludeUserId))
            ->exists();
    }

    public function hasActiveDirector(int $departmentId, ?int $excludeUserId = null): bool
    {
        return UserPosition::query()
            ->where('department_id', $departmentId)
            ->whereNull('ended_at')
            ->whereHas('positionType', fn ($q) => $q->where('code', PositionType::DIRECTOR))
            ->when($excludeUserId, fn ($q) => $q->where('user_id', '!=', $excludeUserId))
            ->exists();
    }
}
