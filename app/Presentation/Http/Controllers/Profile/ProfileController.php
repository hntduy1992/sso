<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Profile;

use App\Application\User\UseCases\ChangePasswordUseCase;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show profile and security settings.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Profile/Index', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'role' => $user->role,
                'status' => $user->status,
                'password_changed_at' => $user->password_changed_at?->diffForHumans(),
                'two_factor_enabled' => $user->hasMfaEnabled(),
                'has_password' => ! empty($user->password),
            ],
        ]);
    }

    /**
     * Update user profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $user->update($validated);

        return back()->with('success', 'Thông tin cá nhân đã được cập nhật thành công.');
    }

    /**
     * Update the user password and revoke all previous sessions and tokens.
     */
    public function updatePassword(Request $request, ChangePasswordUseCase $changePasswordUseCase): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $hasCurrentPassword = ! empty($user->password);

        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];

        if ($hasCurrentPassword) {
            $rules['current_password'] = ['required', 'string'];
        }

        $validated = $request->validate($rules);

        $changePasswordUseCase->execute(
            user: $user,
            newPassword: $validated['password'],
            currentPassword: $validated['current_password'] ?? null,
        );

        return back()->with('success', 'Mật khẩu đã được thay đổi. Tất cả các phiên và token cũ đã được thu hồi an toàn.');
    }
}
