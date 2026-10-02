<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutUserUseCase
{
    public function execute(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
