<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Domain\User\Repositories\UserProfileRepositoryInterface;
use App\Models\UserProfile;

/**
 * Updates a user's personal profile (non-auth fields).
 * Creates the profile record if it doesn't exist yet (upsert semantics).
 */
class UpdateUserProfileUseCase
{
    public function __construct(
        private readonly UserProfileRepositoryInterface $profileRepository,
    ) {}

    public function execute(UpdateProfileDTO $dto): UserProfile
    {
        return $this->profileRepository->upsert($dto);
    }
}
