<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Application\User\UseCases\AdminResetPasswordUseCase;
use App\Application\User\UseCases\AssignUserPositionUseCase;
use App\Application\User\UseCases\TerminateUserPositionUseCase;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\User\Exceptions\PositionConflictException;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OAuthRefreshToken;
use App\Models\OAuthRefreshTokenFamily;
use App\Models\PositionType;
use App\Models\User;
use App\Models\UserPosition;
use App\Models\UserProfile;
use App\Models\UserSession;
use App\Presentation\Http\Requests\Admin\AdminResetPasswordRequest;
use App\Presentation\Http\Requests\Admin\AssignPositionRequest;
use App\Presentation\Http\Requests\Admin\UpdateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Passport;

class UserManagementController extends Controller
{
    /**
     * Display detailed user HRM profile, current positions, and assignment history.
     */
    public function show(int $id): Response
    {
        /** @var User $targetUser */
        $targetUser = User::with([
            'profile',
            'socialAccounts',
            'positions.department',
            'positions.positionType',
        ])->findOrFail($id);

        $profile = $targetUser->profile;

        // Separate active vs historical positions
        $allPositions = $targetUser->positions->sortByDesc('started_at');

        $activePositions = $allPositions->filter(fn (UserPosition $p) => $p->isActive())->values();
        $historyPositions = $allPositions->filter(fn (UserPosition $p) => ! $p->isActive())->values();

        // Available departments and position types for assignment modal
        $departments = Department::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $positionTypes = PositionType::where('is_active', true)
            ->orderBy('level')
            ->get(['id', 'name', 'code', 'level', 'applicable_to']);

        $formatPosition = fn (UserPosition $p) => [
            'id' => $p->id,
            'department_id' => $p->department_id,
            'department_name' => $p->department?->name ?? 'N/A',
            'department_code' => $p->department?->code ?? '',
            'department_type' => $p->department?->type ?? '',
            'position_type_id' => $p->position_type_id,
            'position_type_name' => $p->positionType?->name ?? 'N/A',
            'position_type_code' => $p->positionType?->code ?? '',
            'position_level' => $p->positionType?->level ?? 1,
            'is_primary' => (bool) $p->is_primary,
            'started_at' => (string) $p->started_at,
            'ended_at' => $p->ended_at ? (string) $p->ended_at : null,
            'is_active' => $p->isActive(),
            'notes' => $p->notes,
        ];

        return Inertia::render('Admin/Users/Show', [
            'targetUser' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'status' => $targetUser->status,
                'avatar_url' => $profile?->getEffectiveAvatarUrl($targetUser->avatar_url),
                'has_password' => ! empty($targetUser->password),
                'mfa_enabled' => (bool) $targetUser->mfa_enabled,
                'created_at' => $targetUser->created_at->format('Y-m-d H:i:s'),
                'is_deputy_director' => $targetUser->isDeputyDirector(),
            ],
            'profile' => $profile ? [
                'full_name' => $profile->full_name,
                'date_of_birth' => $profile->date_of_birth?->format('Y-m-d'),
                'gender' => $profile->gender,
                'phone_number' => $profile->phone_number,
                'contact_email' => $profile->contact_email,
                'address' => $profile->address,
                'bio' => $profile->bio,
            ] : null,
            'socialAccounts' => $targetUser->socialAccounts->map(fn ($sa) => [
                'provider' => $sa->provider,
                'created_at' => $sa->created_at->format('Y-m-d'),
            ]),
            'activePositions' => $activePositions->map($formatPosition),
            'historyPositions' => $historyPositions->map($formatPosition),
            'departments' => $departments,
            'positionTypes' => $positionTypes,
        ]);
    }

    /**
     * Assign a new organizational position to the user.
     */
    public function assignPosition(
        int $id,
        AssignPositionRequest $request,
        AssignUserPositionUseCase $useCase
    ): RedirectResponse {
        try {
            $useCase->execute($request->toDTO($id), $request->user());

            return redirect()->back()
                ->with('success', 'Bổ nhiệm chức vụ thành công.');
        } catch (PositionConflictException|UserNotFoundException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Terminate an active position.
     */
    public function terminatePosition(
        int $id,
        int $positionId,
        Request $request,
        TerminateUserPositionUseCase $useCase
    ): RedirectResponse {
        $notes = $request->input('notes') ? (string) $request->input('notes') : null;

        try {
            $useCase->execute($positionId, $notes, $request->user());

            return redirect()->back()
                ->with('success', 'Đã kết thúc nhiệm kỳ chức vụ thành công.');
        } catch (PositionConflictException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Admin forces password reset for a target user.
     */
    public function resetPassword(
        int $id,
        AdminResetPasswordRequest $request,
        AdminResetPasswordUseCase $useCase
    ): RedirectResponse {
        try {
            $useCase->execute(
                targetUserId: $id,
                newPassword: $request->string('password')->toString(),
                reason: $request->string('reason')->toString(),
                adminUser: $request->user(),
            );

            return redirect()->back()
                ->with('success', 'Đã đặt lại mật khẩu thành công và thu hồi tất cả phiên đăng nhập của người dùng.');
        } catch (UserNotFoundException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Update user details and HRM profile.
     */
    public function update(int $id, UpdateUserRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $user */
        $user = User::withTrashed()->findOrFail($id);

        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        // Update or create UserProfile
        $profile = $user->profile ?? new UserProfile(['user_id' => $user->id]);
        $profile->fill([
            'full_name' => $validated['full_name'] ?? $validated['name'],
            'phone_number' => $validated['phone_number'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'address' => $validated['address'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'bio' => $validated['bio'] ?? null,
        ]);
        $profile->save();

        $auditLogger->log(
            event: 'ADMIN_UPDATE_USER',
            user: $request->user(),
            payload: [
                'target_user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã cập nhật thông tin người dùng {$user->name} thành công.");
    }

    /**
     * Soft delete user account and revoke all sessions/tokens.
     */
    public function destroy(int $id, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        if ($id === (int) $request->user()?->id) {
            return redirect()->back()
                ->with('error', 'Bạn không thể tự xóa tài khoản của chính mình.');
        }

        /** @var User $user */
        $user = User::findOrFail($id);

        // Revoke active sessions and tokens
        $accessTokenIds = Passport::token()
            ->newQuery()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->pluck('id');

        if ($accessTokenIds->isNotEmpty()) {
            Passport::token()
                ->newQuery()
                ->whereIn('id', $accessTokenIds)
                ->update(['revoked' => true]);

            OAuthRefreshToken::whereIn('access_token_id', $accessTokenIds)
                ->update(['revoked' => true]);
        }

        OAuthRefreshTokenFamily::where('user_id', $user->id)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => 'user_soft_deleted',
            ]);

        UserSession::where('user_id', $user->id)
            ->update(['is_active' => false]);

        $userName = $user->name;
        $user->delete();

        $auditLogger->log(
            event: 'ADMIN_SOFT_DELETE_USER',
            user: $request->user(),
            payload: [
                'target_user_id' => $id,
                'name' => $userName,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã chuyển tài khoản {$userName} vào thùng rác.");
    }

    /**
     * Restore soft-deleted user.
     */
    public function restore(int $id, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $user */
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        $auditLogger->log(
            event: 'ADMIN_RESTORE_USER',
            user: $request->user(),
            payload: [
                'target_user_id' => $user->id,
                'name' => $user->name,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã khôi phục tài khoản {$user->name} thành công.");
    }

    /**
     * Permanently delete user account and clean up dependencies.
     */
    public function forceDelete(int $id, Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        if ($id === (int) $request->user()?->id) {
            return redirect()->back()
                ->with('error', 'Bạn không thể tự xóa tài khoản của chính mình.');
        }

        /** @var User $user */
        $user = User::onlyTrashed()->findOrFail($id);
        $userName = $user->name;

        // Cascade cleanup
        $user->profile()?->forceDelete();
        $user->positions()->delete();
        $user->socialAccounts()->delete();

        $user->forceDelete();

        $auditLogger->log(
            event: 'ADMIN_FORCE_DELETE_USER',
            user: $request->user(),
            payload: [
                'target_user_id' => $id,
                'name' => $userName,
            ]
        );

        return redirect()->back()
            ->with('success', "Đã xóa vĩnh viễn tài khoản {$userName}.");
    }
}
