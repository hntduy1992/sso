<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Auth;

use App\Application\User\Services\SessionTracker;
use App\Application\User\UseCases\VerifyMfaChallengeUseCase;
use App\Domain\User\DTOs\MfaChallengeDTO;
use App\Domain\User\Exceptions\InvalidMfaCodeException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the MFA TOTP challenge step in the login flow.
 *
 * After successful email/password login, if user has MFA enabled:
 *  1. AuthController stores user_id in session as 'mfa_pending_user_id'
 *  2. User is redirected here to complete MFA challenge
 *  3. On success, user is fully authenticated
 */
class MfaChallengeController extends Controller
{
    /**
     * Show the TOTP challenge screen.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('mfa_pending_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    /**
     * Verify the submitted TOTP code or recovery code.
     *
     * @throws ValidationException
     */
    public function store(Request $request, VerifyMfaChallengeUseCase $verifyMfaUseCase): RedirectResponse
    {
        $pendingUserId = $request->session()->get('mfa_pending_user_id');

        if (! $pendingUserId) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        // Rate limit: 5 attempts per minute per user
        $rateLimitKey = "mfa:{$pendingUserId}:".$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'code' => ["Quá nhiều lần thử. Vui lòng thử lại sau {$seconds} giây."],
            ]);
        }

        /** @var User|null $user */
        $user = User::find($pendingUserId);

        if (! $user) {
            Session::forget('mfa_pending_user_id');

            return redirect()->route('login');
        }

        $isRecovery = $request->boolean('is_recovery_code');

        try {
            $verifyMfaUseCase->execute($user, new MfaChallengeDTO(
                code: $request->string('code')->value(),
                isRecoveryCode: $isRecovery,
            ));
        } catch (InvalidMfaCodeException $e) {
            RateLimiter::hit($rateLimitKey, decaySeconds: 60);

            throw ValidationException::withMessages([
                'code' => [$e->getMessage()],
            ]);
        }

        // MFA passed — clear pending state and fully authenticate
        RateLimiter::clear($rateLimitKey);
        Session::forget('mfa_pending_user_id');
        Auth::login($user, remember: true);
        Session::regenerate();

        app(SessionTracker::class)->track($user);

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Xác thực hai yếu tố thành công!');
    }
}
