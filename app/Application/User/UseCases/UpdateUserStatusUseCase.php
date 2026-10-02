<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\DTOs\UpdateUserStatusDTO;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Auth;

class UpdateUserStatusUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * @throws UserNotFoundException
     * @throws DomainException
     */
    public function execute(UpdateUserStatusDTO $dto): User
    {
        $targetUser = $this->userRepository->findById($dto->userId);

        if (! $targetUser) {
            throw new UserNotFoundException("Người dùng với ID #{$dto->userId} không tồn tại.");
        }

        // Business rule: Do not allow current user to lock their own account
        $currentUser = Auth::user();
        if ($currentUser && (int) $currentUser->id === $dto->userId && $dto->status === 'suspended') {
            throw new DomainException('Bạn không thể tự khóa tài khoản của chính mình.');
        }

        $this->userRepository->updateStatus($dto->userId, $dto->status);

        return $this->userRepository->findById($dto->userId);
    }
}
