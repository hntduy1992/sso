<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Profile;

use App\Application\User\UseCases\ConfirmMfaUseCase;
use App\Application\User\UseCases\DisableMfaUseCase;
use App\Application\User\UseCases\SetupMfaUseCase;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    /**
     * Start MFA setup: generates TOTP secret and QR code.
     */
    public function setup(Request $request, SetupMfaUseCase $setupMfaUseCase): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $setupMfaUseCase->execute($user);

        return response()->json($data);
    }

    /**
     * Confirm MFA code and activate 2FA.
     */
    public function confirm(Request $request, ConfirmMfaUseCase $confirmMfaUseCase): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $recoveryCodes = $confirmMfaUseCase->execute($user, $request->string('code')->value());

        return response()->json([
            'success' => true,
            'message' => 'Xác thực hai yếu tố đã được kích hoạt thành công.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable MFA.
     */
    public function destroy(Request $request, DisableMfaUseCase $disableMfaUseCase): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $disableMfaUseCase->execute($user, $request->string('password')->value());

        return back()->with('success', 'Đã tắt xác thực hai yếu tố.');
    }
}
