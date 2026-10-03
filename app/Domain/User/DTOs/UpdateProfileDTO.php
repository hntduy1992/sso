<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

/**
 * Input DTO carrying validated data for updating a user's personal profile.
 */
readonly class UpdateProfileDTO
{
    public function __construct(
        public int $userId,
        public ?string $fullName,
        public ?string $dateOfBirth,
        public ?string $gender,
        public ?string $phoneNumber,
        public ?string $contactEmail,
        public ?string $address,
        public ?string $bio,
    ) {}
}
