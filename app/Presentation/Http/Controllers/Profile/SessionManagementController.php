<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Profile;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SessionManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display list of active and recent sessions.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $sessions = UserSession::with('client')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (UserSession $session) => [
                'id' => $session->id,
                'client_name' => $session->client?->name ?? 'SSO Identity Provider (Trực tiếp)',
                'ip_address' => $session->ip_address ?? 'Không xác định',
                'user_agent' => $session->user_agent,
                'last_activity' => $session->last_activity?->diffForHumans() ?? 'Vừa xong',
                'is_current' => $session->id === $currentSessionId,
            ]);

        return Inertia::render('Profile/Sessions', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Terminate a specific session.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $session = UserSession::where('user_id', $user->id)->where('id', $id)->first();

        if ($session) {
            $session->update(['is_active' => false]);

            $this->auditLogger->log(
                event: 'SESSION_REVOKED',
                user: $user,
                payload: ['session_id' => $id]
            );
        }

        return back()->with('success', 'Phiên đăng nhập đã được chấm dứt.');
    }

    /**
     * Terminate all other sessions except the current one.
     */
    public function revokeOthers(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $count = UserSession::where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->auditLogger->log(
            event: 'OTHER_SESSIONS_REVOKED',
            user: $user,
            payload: ['count' => $count]
        );

        return back()->with('success', "Đã thu hồi thành công {$count} phiên đăng nhập khác.");
    }
}
