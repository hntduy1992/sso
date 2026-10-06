<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

use App\Models\UserProfile;

/**
 * Read-only DTO representing a user's personal profile data.
 * Used to pass profile information across layer boundaries.
 *
 * @phpstan-type LinkedProvider array{provider: string, connected: bool, linked_at: string|null}
 */
readonly class UserProfileDTO
{
    /**
     * @param  array<int, LinkedProvider>  $linkedProviders
     */
    public function __construct(
        public int $userId,
        public ?string $fullName,
        public ?string $dateOfBirth,
        public ?string $gender,
        public ?string $phoneNumber,
        public ?string $contactEmail,
        public ?string $address,
        public ?string $bio,
        public ?string $avatarUrl,
        public array $linkedProviders = [],
    ) {}

    public static function fromModel(UserProfile $profile, array $linkedProviders = []): self
    {
        return new self(
            userId: $profile->user_id,
            fullName: $profile->full_name,
            dateOfBirth: $profile->date_of_birth?->format('Y-m-d'),
            gender: $profile->gender,
            phoneNumber: $profile->phone_number,
            contactEmail: $profile->contact_email,
            address: $profile->address,
            bio: $profile->bio,
            avatarUrl: $profile->getEffectiveAvatarUrl(),
            linkedProviders: $linkedProviders,
        );
    }
}
