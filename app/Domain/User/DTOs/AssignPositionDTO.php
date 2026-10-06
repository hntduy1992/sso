<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class AssignPositionDTO
{
    public function __construct(
        public int $userId,
        public int $departmentId,
        public int $positionTypeId,
        public string $startedAt,
        public bool $isPrimary = false,
        public ?string $notes = null,
    ) {}
}
