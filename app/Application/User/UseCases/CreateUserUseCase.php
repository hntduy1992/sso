<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\DTOs\AssignPositionDTO;
use App\Domain\User\DTOs\CreateUserDTO;
use App\Domain\User\Exceptions\PositionConflictException;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Models\PositionType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class CreateUserUseCase
{
    public function __construct(
        private readonly AssignUserPositionUseCase $assignUserPositionUseCase,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Create a new account with its HRM profile and, when a department is given,
     * attach the user to that department as a MEMBER (Tổ viên) primary position.
     *
     * The whole operation is atomic: if the position assignment violates a
     * business rule, the account is not created.
     *
     * @throws PositionConflictException
     * @throws UserNotFoundException
     */
    public function execute(CreateUserDTO $dto, ?User $adminUser = null): User
    {
        return DB::transaction(function () use ($dto, $adminUser): User {
            /** @var User $user */
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password,
                'role' => $dto->role,
                'status' => $dto->status,
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'full_name' => $dto->fullName ?? $dto->name,
                'phone_number' => $dto->phoneNumber,
                'contact_email' => $dto->contactEmail,
                'gender' => $dto->gender,
                'date_of_birth' => $dto->dateOfBirth,
                'address' => $dto->address,
            ]);

            if ($dto->departmentId !== null) {
                $this->assignUserPositionUseCase->execute(
                    new AssignPositionDTO(
                        userId: $user->id,
                        departmentId: $dto->departmentId,
                        positionTypeId: $this->memberPositionTypeId(),
                        startedAt: now()->toDateString(),
                        isPrimary: true,
                    ),
                    $adminUser,
                );
            }

            $this->auditLogger->log(
                event: 'ADMIN_CREATE_USER',
                user: $adminUser,
                payload: [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'department_id' => $dto->departmentId,
                ]
            );

            return $user;
        });
    }

    /**
     * @throws PositionConflictException
     */
    private function memberPositionTypeId(): int
    {
        $memberPositionTypeId = PositionType::where('code', PositionType::MEMBER)
            ->where('is_active', true)
            ->value('id');

        if ($memberPositionTypeId === null) {
            throw new PositionConflictException('Chưa cấu hình chức danh Tổ viên (MEMBER) trong hệ thống.');
        }

        return (int) $memberPositionTypeId;
    }
}
