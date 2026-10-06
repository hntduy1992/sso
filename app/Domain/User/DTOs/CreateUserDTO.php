<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $fullName = null,
        public ?string $phoneNumber = null,
        public ?string $contactEmail = null,
        public ?string $gender = null,
        public ?string $dateOfBirth = null,
        public ?string $address = null,
        public ?int $departmentId = null,
        public string $role = 'user',
        public string $status = 'active',
    ) {}

    /**
     * Build a DTO from a validated attribute array (single form or import row).
     *
     * @param  array{name: string, email: string, full_name?: string|null, phone_number?: string|null, contact_email?: string|null, gender?: string|null, date_of_birth?: string|null, address?: string|null, role?: string|null, status?: string|null}  $attributes
     */
    public static function fromArray(array $attributes, string $password, ?int $departmentId = null): self
    {
        return new self(
            name: trim($attributes['name']),
            email: mb_strtolower(trim($attributes['email'])),
            password: $password,
            fullName: self::nullableString($attributes['full_name'] ?? null),
            phoneNumber: self::nullableString($attributes['phone_number'] ?? null),
            contactEmail: self::nullableString($attributes['contact_email'] ?? null),
            gender: self::nullableString($attributes['gender'] ?? null),
            dateOfBirth: self::nullableString($attributes['date_of_birth'] ?? null),
            address: self::nullableString($attributes['address'] ?? null),
            departmentId: $departmentId,
            role: self::nullableString($attributes['role'] ?? null) ?? 'user',
            status: self::nullableString($attributes['status'] ?? null) ?? 'active',
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
