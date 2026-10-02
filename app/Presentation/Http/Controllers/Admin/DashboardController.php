<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Application\OAuth\Services\BackchannelLogoutService;
use App\Application\User\UseCases\UpdateUserStatusUseCase;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\User;
use App\Models\UserSession;
use App\Presentation\Http\Requests\Admin\UpdateUserStatusRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Passport;

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

    /**
     * Force logout target user from all devices, clients, and active sessions.
     */
    public function forceLogoutUser(
        int $id,
        BackchannelLogoutService $backchannelLogoutService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        /** @var User $targetUser */
        $targetUser = User::findOrFail($id);

        // 1. Trigger Backchannel Logout to all satellite apps before revoking
        $backchannelLogoutService->triggerLogoutForUser($targetUser);

        // 2. Revoke tokens
        $accessTokenIds = Passport::token()
            ->newQuery()
            ->where('user_id', $targetUser->id)
            ->pluck('id');

        if ($accessTokenIds->isNotEmpty()) {
            Passport::token()
                ->newQuery()
                ->whereIn('id', $accessTokenIds)
                ->update(['revoked' => true]);

            OAuthRefreshToken::whereIn('access_token_id', $accessTokenIds)
                ->update(['revoked' => true]);
        }

        // 3. Revoke families
        OAuthRefreshTokenFamily::where('user_id', $targetUser->id)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => 'admin_force_logout',
            ]);

        // 4. Deactivate sessions
        UserSession::where('user_id', $targetUser->id)
            ->update(['is_active' => false]);

        $auditLogger->log(
            event: 'ADMIN_FORCE_LOGOUT',
            user: auth()->user(),
            payload: [
                'target_user_id' => $targetUser->id,
                'target_user_email' => $targetUser->email,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã cưỡng chế đăng xuất {$targetUser->name} khỏi toàn bộ phiên và ứng dụng vệ tinh.");
    }
}
