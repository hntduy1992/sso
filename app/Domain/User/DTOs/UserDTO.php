<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class UserDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public string $status,
        public ?string $createdAt = null,
    ) {}

    public static function fromModel(mixed $user): self
    {
        return new self(
            id: (int) $user->id,
            name: (string) $user->name,
            email: (string) $user->email,
            role: (string) ($user->role ?? 'user'),
            status: (string) ($user->status ?? 'active'),
            createdAt: $user->created_at ? $user->created_at->toIso8601String() : null,
        );
    }
}
