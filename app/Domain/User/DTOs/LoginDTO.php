<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class LoginDTO
{
    public string $email;

    public function __construct(
        public string $login,
        public string $password,
        public bool $remember = false,
        public ?string $redirect = null,
    ) {
        $this->email = $this->login;
    }
}
