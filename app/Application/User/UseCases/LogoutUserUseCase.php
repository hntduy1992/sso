<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Application\OAuth\Services\BackchannelLogoutService;
use App\Application\User\Services\SessionTracker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutUserUseCase
{
    public function __construct(
        private readonly SessionTracker $sessionTracker,
        private readonly BackchannelLogoutService $backchannelLogoutService,
    ) {}

    public function execute(Request $request): void
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user) {
            $sessionId = $request->session()->getId();
            $this->sessionTracker->terminate($user, $sessionId);

            // Dispatch Backchannel Logout jobs to connected satellite apps
            $this->backchannelLogoutService->triggerLogoutForUser($user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
