<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display searchable, filterable security and token lifecycle audit logs.
     */
    public function index(Request $request): Response
    {
        $event = $request->query('event');
        $search = $request->query('search');

        $logs = AuditLog::with(['user', 'client'])
            ->when($event, fn ($q) => $q->where('event', $event))
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('ip_address', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'client' => $log->client ? [
                    'id' => (string) $log->client->id,
                    'name' => $log->client->name,
                ] : null,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'payload' => $log->payload,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'created_at_human' => $log->created_at->diffForHumans(),
            ]);

        // Distinct events for dropdown filter
        $availableEvents = AuditLog::select('event')
            ->distinct()
            ->pluck('event')
            ->sort()
            ->values();

        return Inertia::render('Admin/AuditLogs', [
            'logs' => $logs,
            'availableEvents' => $availableEvents,
            'filters' => [
                'event' => $event ?? '',
                'search' => $search ?? '',
            ],
        ]);
    }
}
