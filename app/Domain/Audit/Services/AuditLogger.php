<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Log a security or lifecycle audit event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function log(
        string $event,
        ?User $user = null,
        ?string $clientId = null,
        array $payload = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        /** @var Request|null $request */
        $request = request();

        $ip = $ipAddress ?? ($request?->ip());
        $ua = $userAgent ?? ($request?->userAgent());

        $userId = $user?->id ?? (auth()->check() ? auth()->id() : null);

        return AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => $clientId,
            'event' => $event,
            'ip_address' => $ip,
            'user_agent' => $ua ? Str::limit($ua, 500) : null,
            'payload' => empty($payload) ? null : $payload,
            'created_at' => now(),
        ]);
    }
}
