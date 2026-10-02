<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Application\User\UseCases\UpdateUserStatusUseCase;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Admin\UpdateUserStatusRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the SSO Identity Provider Admin Dashboard.
     */
    public function index(Request $request, UserRepositoryInterface $userRepository): Response
    {
        $search = $request->query('search');
        $users = $userRepository->getAllPaginated(10, is_string($search) ? $search : null);
        $stats = $userRepository->getStats();

        return Inertia::render('Dashboard', [
            'users' => $users,
            'stats' => $stats,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Update user account status (active / suspended).
     */
    public function updateUserStatus(
        int $id,
        UpdateUserStatusRequest $request,
        UpdateUserStatusUseCase $updateUserStatusUseCase
    ): RedirectResponse {
        try {
            $user = $updateUserStatusUseCase->execute($request->toDTO($id));

            $statusText = $user->status === 'active' ? 'kích hoạt' : 'khóa';

            return redirect()->back()
                ->with('success', "Đã {$statusText} tài khoản của {$user->name} thành công.");
        } catch (UserNotFoundException|DomainException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }
}
