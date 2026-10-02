<?php

declare(strict_types=1);

namespace App\Application\User\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SessionTracker
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Record or update an active user session.
     */
    public function track(User $user, ?string $clientId = null, ?Request $request = null): UserSession
    {
        $req = $request ?? request();
        $sessionId = $req?->hasSession() ? $req->session()->getId() : (string) Str::uuid();

        /** @var UserSession $session */
        $session = UserSession::updateOrCreate(
            ['id' => $sessionId],
            [
                'user_id' => $user->id,
                'client_id' => $clientId,
                'ip_address' => $req?->ip(),
                'user_agent' => $req?->userAgent() ? Str::limit($req->userAgent(), 500) : null,
                'last_activity' => now(),
                'is_active' => true,
            ]
        );

        $this->auditLogger->log(
            event: 'USER_LOGIN',
            user: $user,
            clientId: $clientId,
            payload: [
                'session_id' => $sessionId,
            ]
        );

        return $session;
    }

    /**
     * Terminate user session upon logout.
     */
    public function terminate(User $user, ?string $sessionId = null): void
    {
        $query = UserSession::where('user_id', $user->id);

        if ($sessionId) {
            $query->where('id', $sessionId);
        }

        $query->update(['is_active' => false]);

        $this->auditLogger->log(
            event: 'USER_LOGOUT',
            user: $user,
            payload: [
                'session_id' => $sessionId,
            ]
        );
    }
}
