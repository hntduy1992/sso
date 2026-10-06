<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\Repositories\UserProfileRepositoryInterface;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Handles avatar file upload for a user's profile.
 *
 * - Validates that the file is a valid image (done at the Request layer).
 * - Stores the file on the `public` disk under `avatars/{userId}/`.
 * - Deletes the previous avatar file if one existed.
 * - Updates the `avatar_path` on UserProfile.
 */
class UploadAvatarUseCase
{
    private const DISK = 'public';

    private const DIRECTORY = 'avatars';

    public function __construct(
        private readonly UserProfileRepositoryInterface $profileRepository,
    ) {}

    public function execute(User $user, UploadedFile $file): string
    {
        $this->deletePreviousAvatar($user->id);

        $path = $file->store(
            self::DIRECTORY.'/'.$user->id,
            self::DISK,
        );

        $this->profileRepository->updateAvatarPath($user->id, (string) $path);

        return (string) $path;
    }

    private function deletePreviousAvatar(int $userId): void
    {
        $profile = $this->profileRepository->findByUserId($userId);

        if ($profile?->avatar_path) {
            Storage::disk(self::DISK)->delete($profile->avatar_path);
        }
    }
}
