<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Auth;

use App\Application\User\UseCases\AuthenticateUserUseCase;
use App\Application\User\UseCases\LogoutUserUseCase;
use App\Domain\User\Exceptions\AccountSuspendedException;
use App\Domain\User\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the centralized SSO login page.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Login', [
            'redirect' => $request->query('redirect'),
        ]);
    }

    /**
     * Handle authentication request.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request, AuthenticateUserUseCase $authenticateUserUseCase): RedirectResponse
    {
        $dto = $request->toDTO();

        try {
            $user = $authenticateUserUseCase->execute($dto);
        } catch (InvalidCredentialsException|AccountSuspendedException $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }

        // MFA gate: if 2FA is confirmed, log out again and require TOTP challenge
        if ($user->hasMfaEnabled()) {
            Auth::logout();
            $request->session()->put('mfa_pending_user_id', $user->id);

            return redirect()->route('mfa.challenge');
        }

        $redirectUrl = $dto->redirect;
        if ($redirectUrl && filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($redirectUrl);
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', "Đăng nhập thành công! Chào mừng {$user->name}.");
    }

    /**
     * Terminate the session and log out.
     */
    public function destroy(Request $request, LogoutUserUseCase $logoutUserUseCase): RedirectResponse
    {
        $logoutUserUseCase->execute($request);

        return redirect()->route('login')
            ->with('success', 'Bạn đã đăng xuất an toàn khỏi hệ thống.');
    }
}
