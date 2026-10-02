<?php

use App\Presentation\Http\Controllers\Health\HealthCheckController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;

// Enterprise Health check for satellite apps and container probes
Route::get('/health', HealthCheckController::class)->name('health');
