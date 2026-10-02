<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Repositories;

use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function getAllPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        $query = User::query()->latest();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function updateStatus(int $userId, string $status): bool
    {
        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        return $user->update(['status' => $status]);
    }

    public function attempt(string $email, string $password, bool $remember = false): bool
    {
        return Auth::attempt(
            ['email' => $email, 'password' => $password],
            $remember
        );
    }

    /**
     * @return array{total: int, active: int, suspended: int, admins: int}
     */
    public function getStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'admins' => User::where('role', 'admin')->count(),
        ];
    }
}
