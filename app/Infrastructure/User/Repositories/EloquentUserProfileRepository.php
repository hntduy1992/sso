<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Repositories;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Domain\User\Repositories\UserProfileRepositoryInterface;
use App\Models\UserProfile;

class EloquentUserProfileRepository implements UserProfileRepositoryInterface
{
    public function findByUserId(int $userId): ?UserProfile
    {
        return UserProfile::where('user_id', $userId)->first();
    }

    public function upsert(UpdateProfileDTO $dto): UserProfile
    {
        /** @var UserProfile $profile */
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $dto->userId],
            [
                'full_name' => $dto->fullName,
                'date_of_birth' => $dto->dateOfBirth,
                'gender' => $dto->gender,
                'phone_number' => $dto->phoneNumber,
                'contact_email' => $dto->contactEmail,
                'address' => $dto->address,
                'bio' => $dto->bio,
            ]
        );

        return $profile;
    }

    public function updateAvatarPath(int $userId, string $path): UserProfile
    {
        /** @var UserProfile $profile */
        $profile = UserProfile::firstOrCreate(['user_id' => $userId]);
        $profile->avatar_path = $path;
        $profile->save();

        return $profile;
    }

    public function clearAvatarPath(int $userId): void
    {
        UserProfile::where('user_id', $userId)->update(['avatar_path' => null]);
    }
}
