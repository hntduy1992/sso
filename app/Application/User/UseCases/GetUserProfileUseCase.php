<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\DTOs\UserProfileDTO;
use App\Domain\User\Repositories\UserProfileRepositoryInterface;
use App\Models\User;
use App\Models\UserProfile;

/**
 * Retrieves a user's profile alongside their linked social providers.
 *
 * If no UserProfile record exists yet (new account), returns a DTO
 * with null personal fields but still includes provider connection status.
 */
class GetUserProfileUseCase
{
    /** @var list<string> All providers the SSO system supports */
    private const SUPPORTED_PROVIDERS = ['google', 'github'];

    public function __construct(
        private readonly UserProfileRepositoryInterface $profileRepository,
    ) {}

    public function execute(User $user): UserProfileDTO
    {
        $profile = $this->profileRepository->findByUserId($user->id);

        $linkedProviders = $this->buildLinkedProviders($user);

        if ($profile === null) {
            return $this->buildEmptyProfileDTO($user, $linkedProviders);
        }

        return UserProfileDTO::fromModel($profile, $linkedProviders);
    }

    /**
     * @return list<array{provider: string, connected: bool, linked_at: string|null}>
     */
    private function buildLinkedProviders(User $user): array
    {
        $existingProviders = $user->socialAccounts
            ->keyBy('provider');

        return array_map(function (string $provider) use ($existingProviders) {
            $linked = $existingProviders->get($provider);

            return [
                'provider' => $provider,
                'connected' => $linked !== null,
                'linked_at' => $linked?->created_at?->toIso8601String(),
            ];
        }, self::SUPPORTED_PROVIDERS);
    }

    /**
     * @param  list<array{provider: string, connected: bool, linked_at: string|null}>  $linkedProviders
     */
    private function buildEmptyProfileDTO(User $user, array $linkedProviders): UserProfileDTO
    {
        return new UserProfileDTO(
            userId: $user->id,
            fullName: $user->name,
            dateOfBirth: null,
            gender: null,
            phoneNumber: null,
            contactEmail: null,
            address: null,
            bio: null,
            avatarUrl: $user->avatar_url,
            linkedProviders: $linkedProviders,
        );
    }
}
