<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Application\User\Services\SessionTracker;
use App\Domain\User\DTOs\LoginDTO;
use App\Domain\User\Exceptions\AccountSuspendedException;
use App\Domain\User\Exceptions\InvalidCredentialsException;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AuthenticateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SessionTracker $sessionTracker,
    ) {}

    /**
     * @throws InvalidCredentialsException
     * @throws AccountSuspendedException
     */
    public function execute(LoginDTO $dto): User
    {
        $user = $this->userRepository->findByEmailOrPhone($dto->login);

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        if ($user->isSuspended()) {
            throw new AccountSuspendedException;
        }

        Auth::login($user, $dto->remember);

        // Regenerate session to prevent session fixation attacks
        Session::regenerate();

        if (! $user->hasMfaEnabled()) {
            $this->sessionTracker->track($user);
        }

        return $user;
    }
}
