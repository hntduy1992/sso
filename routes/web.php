<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'validate'])->name('login.validate');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::prefix('users')->group(function () {
        Route::put('/{user}/reset-password', [UserController::class, 'reset-password'])->name('user.reset-password');
        Route::put('/{id}/update', [UserController::class, 'update'])->name('user.update');
        Route::delete('/{id}/delete', [UserController::class, 'destroy'])->name('user.destroy');
        Route::post('/create', [UserController::class, 'store'])->name('user.store');
        Route::delete('/deletes', [UserController::class, 'destroys'])->name('user.destroys');
        Route::get('/', [UserController::class, 'index'])->name('user.index')->can('user.list');
    });

    Route::prefix('departments')->group(function (){
        Route::post('/create', [DepartmentController::class, 'store'])->name('department.store')->can('department.create');
        Route::get('/', [DepartmentController::class, 'index'])->name('department.index')->can('department.list');
    });
    Route::get('/', function () {
        return redirect()->route('dashboard.index');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
});
