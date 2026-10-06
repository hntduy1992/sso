<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findByEmailOrPhone(string $identifier): ?User;

    public function getAllPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        ?string $role = null
    ): LengthAwarePaginator;

    public function updateStatus(int $userId, string $status): bool;

    public function attempt(string $email, string $password, bool $remember = false): bool;

    /**
     * @return array{total: int, active: int, suspended: int, trashed: int, admins: int}
     */
    public function getStats(): array;
}
