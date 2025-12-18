<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function login(): \Inertia\Response
    {
        return Inertia::render('Auth/LoginPage', []);
    }

    public function validate(LoginRequest $request): \Illuminate\Http\RedirectResponse
    {
        $request->validated();
        $credentials = $request->safe(['username', 'password']);
        if (Auth::attempt($credentials)) {
            $request->session()->regenerateToken();

            return redirect()->intended('/')->with('success', 'Đăng nhập thành công!');
        }
        return back()->withErrors([
            'message' => 'Thông tin đăng nhập không chính xác.',
        ]);
    }
}
