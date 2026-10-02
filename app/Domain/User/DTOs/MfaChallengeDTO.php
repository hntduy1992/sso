<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class MfaChallengeDTO
{
    public function __construct(
        public string $code,
        public bool $isRecoveryCode = false,
    ) {}
}
