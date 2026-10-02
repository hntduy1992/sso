<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Enterprise SSO Health Check Endpoint for Kubernetes & Docker Probes.
     */
    public function __invoke(): JsonResponse
    {
        $services = [];
        $healthy = true;

        // 1. Check Database Connectivity
        try {
            DB::connection()->getPdo();
            $services['database'] = 'ok';
        } catch (Throwable $e) {
            $services['database'] = 'error: '.$e->getMessage();
            $healthy = false;
        }

        // 2. Check Cache Store
        try {
            $testKey = 'health_check_ping';
            Cache::put($testKey, 'pong', 5);
            $services['cache'] = Cache::get($testKey) === 'pong' ? 'ok' : 'error';
            Cache::forget($testKey);
        } catch (Throwable $e) {
            $services['cache'] = 'error: '.$e->getMessage();
            $healthy = false;
        }

        // 3. Check OAuth Key Pairs
        $privateKeyConfigured = ! empty(config('passport.private_key'))
            || file_exists(storage_path('oauth-private.key'));
        $publicKeyConfigured = ! empty(config('passport.public_key'))
            || file_exists(storage_path('oauth-public.key'));

        if ($privateKeyConfigured && $publicKeyConfigured) {
            $services['oauth_keys'] = 'ok';
        } else {
            $services['oauth_keys'] = 'warning: missing private or public key';
        }

        $statusCode = $healthy ? 200 : 503;

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => config('app.env'),
            'services' => $services,
        ], $statusCode);
    }
}
