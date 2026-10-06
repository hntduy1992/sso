<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

use App\Models\UserPosition;

readonly class OrganizationPositionDTO
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $departmentId,
        public string $departmentName,
        public string $departmentCode,
        public string $departmentType,
        public int $positionTypeId,
        public string $positionTypeCode,
        public string $positionTypeName,
        public int $positionLevel,
        public string $startedAt,
        public ?string $endedAt,
        public bool $isPrimary,
        public bool $isActive,
        public ?string $notes = null,
    ) {}

    public static function fromModel(UserPosition $position): self
    {
        return new self(
            id: $position->id,
            userId: $position->user_id,
            departmentId: $position->department_id,
            departmentName: $position->department->name,
            departmentCode: $position->department->code,
            departmentType: $position->department->type,
            positionTypeId: $position->position_type_id,
            positionTypeCode: $position->positionType->code,
            positionTypeName: $position->positionType->name,
            positionLevel: $position->positionType->level,
            startedAt: (string) $position->started_at,
            endedAt: $position->ended_at ? (string) $position->ended_at : null,
            isPrimary: (bool) $position->is_primary,
            isActive: $position->ended_at === null,
            notes: $position->notes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'department_id' => $this->departmentId,
            'department_name' => $this->departmentName,
            'department_code' => $this->departmentCode,
            'department_type' => $this->departmentType,
            'position_type_id' => $this->positionTypeId,
            'position_type_code' => $this->positionTypeCode,
            'position_type_name' => $this->positionTypeName,
            'position_level' => $this->positionLevel,
            'started_at' => $this->startedAt,
            'ended_at' => $this->endedAt,
            'is_primary' => $this->isPrimary,
            'is_active' => $this->isActive,
            'notes' => $this->notes,
        ];
    }
}
