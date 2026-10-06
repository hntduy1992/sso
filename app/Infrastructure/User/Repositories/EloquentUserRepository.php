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

    public function findByEmailOrPhone(string $identifier): ?User
    {
        $trimmed = trim($identifier);

        if (str_contains($trimmed, '@')) {
            return User::where('email', strtolower($trimmed))->first();
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed);
        $variations = array_values(array_filter(array_unique([
            $trimmed,
            $digits,
            str_starts_with($digits, '0') ? substr($digits, 1) : null,
            str_starts_with($digits, '0') ? '+84'.substr($digits, 1) : null,
            str_starts_with($digits, '+84') ? '0'.substr($digits, 3) : null,
            str_starts_with($digits, '+84') ? substr($digits, 3) : null,
            (! str_starts_with($digits, '0') && ! str_starts_with($digits, '+')) ? '0'.$digits : null,
        ])));

        return User::where('email', $trimmed)
            ->orWhereHas('profile', function ($q) use ($variations) {
                $q->whereIn('phone_number', $variations);
            })
            ->first();
    }

    public function getAllPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null,
        ?string $role = null
    ): LengthAwarePaginator {
        $query = User::query()
            ->with([
                'profile',
                'activePositions.department',
                'activePositions.positionType',
            ])
            ->latest();

        if ($status === 'trashed') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        } elseif ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($role !== null && $role !== '' && $role !== 'all') {
            $query->where('role', $role);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhereHas('profile', function ($pq) use ($term) {
                        $pq->where('full_name', 'like', $term)
                            ->orWhere('phone_number', 'like', $term);
                    });
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
     * @return array{total: int, active: int, suspended: int, trashed: int, admins: int}
     */
    public function getStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'trashed' => User::onlyTrashed()->count(),
            'admins' => User::where('role', 'admin')->count(),
        ];
    }
}
