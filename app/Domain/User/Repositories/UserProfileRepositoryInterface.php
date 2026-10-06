<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Models\UserProfile;

interface UserProfileRepositoryInterface
{
    /**
     * Find the profile for a given user, or return null if not yet created.
     */
    public function findByUserId(int $userId): ?UserProfile;

    /**
     * Create or update a user's profile from the given DTO.
     * Safe to call even when no profile record exists yet (upsert).
     */
    public function upsert(UpdateProfileDTO $dto): UserProfile;

    /**
     * Persist a new avatar path and clear any previous one.
     * Returns the updated profile.
     */
    public function updateAvatarPath(int $userId, string $path): UserProfile;

    /**
     * Remove the stored avatar file path (soft-clear, does not delete the file).
     */
    public function clearAvatarPath(int $userId): void;
}
