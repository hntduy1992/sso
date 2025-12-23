<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'validate'])->name('login.validate');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::prefix('users')->group(function () {
        Route::put('/{id}/reset-password', [UserController::class, 'reset-password'])->name('user.reset-password');
        Route::put('/{id}/update', [UserController::class, 'update'])->name('user.update');
        Route::get('/{id}', [UserController::class, 'view'])->name('user.view');
        Route::get('/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/create', [UserController::class, 'store'])->name('user.store');
        Route::delete('/delete', [UserController::class, 'destroy'])->name('user.destroy');
        Route::delete('/deletes', [UserController::class, 'destroys'])->name('user.destroys');
        Route::get('/', [UserController::class, 'index'])->name('user.index');
    });
    Route::prefix('user/')->group(function () {

    });
    Route::get('/', function () {
        return redirect()->route('dashboard.index');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
});
