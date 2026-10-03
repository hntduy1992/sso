<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Profile;

use App\Application\User\UseCases\ChangePasswordUseCase;
use App\Application\User\UseCases\GetUserProfileUseCase;
use App\Application\User\UseCases\UpdateUserProfileUseCase;
use App\Application\User\UseCases\UploadAvatarUseCase;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Presentation\Http\Requests\Profile\UpdateProfileRequest;
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
    public function index(Request $request, GetUserProfileUseCase $getUserProfileUseCase): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Eager-load social accounts to avoid N+1 in GetUserProfileUseCase
        $user->load('socialAccounts');
        $profileDTO = $getUserProfileUseCase->execute($user);

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
            'userProfile' => [
                'full_name' => $profileDTO->fullName,
                'date_of_birth' => $profileDTO->dateOfBirth,
                'gender' => $profileDTO->gender,
                'phone_number' => $profileDTO->phoneNumber,
                'contact_email' => $profileDTO->contactEmail,
                'address' => $profileDTO->address,
                'bio' => $profileDTO->bio,
                'avatar_url' => $profileDTO->avatarUrl,
            ],
            'linkedProviders' => $profileDTO->linkedProviders,
        ]);
    }

    /**
     * Update personal profile information (name, dob, phone, etc.).
     */
    public function update(
        UpdateProfileRequest $request,
        UpdateUserProfileUseCase $updateUserProfileUseCase,
    ): RedirectResponse {
        $updateUserProfileUseCase->execute($request->toDTO());

        // Also sync the User.name from full_name for backward compatibility
        /** @var User $user */
        $user = $request->user();
        $userUpdates = ['name' => $request->string('full_name')->toString()];
        if ($request->filled('avatar_url')) {
            $userUpdates['avatar_url'] = $request->string('avatar_url')->toString();
        }
        $user->update($userUpdates);

        return back()->with('success', 'Thông tin cá nhân đã được cập nhật thành công.');
    }

    /**
     * Upload a new avatar image.
     */
    public function uploadAvatar(
        Request $request,
        UploadAvatarUseCase $uploadAvatarUseCase,
    ): RedirectResponse {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $uploadAvatarUseCase->execute($user, $request->file('avatar'));

        return back()->with('success', 'Ảnh đại diện đã được cập nhật thành công.');
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
