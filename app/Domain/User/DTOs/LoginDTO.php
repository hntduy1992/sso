<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
        public ?string $redirect = null,
    ) {}
}
