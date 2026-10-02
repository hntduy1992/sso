<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class UpdateUserStatusDTO
{
    public function __construct(
        public int $userId,
        public string $status,
    ) {}
}
