<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\ApplicationAccessGrant;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * Admin UI to decide who may sign in to each OAuth application:
 * individual users, or every active member of a department.
 */
class ApplicationAccessController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $this->ensureAdmin($request);

        $grantCounts = ApplicationAccessGrant::query()
            ->selectRaw('client_id, count(*) as total')
            ->groupBy('client_id')
            ->pluck('total', 'client_id');

        $clients = Passport::client()->newQuery()
            ->where('revoked', false)
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client) => [
                'id' => (string) $client->id,
                'name' => $client->name,
                'client_type' => $client->client_type,
                'description' => $client->description,
                'grants_count' => (int) ($grantCounts[(string) $client->id] ?? 0),
            ])
            ->values();

        $selectedId = (string) $request->query('client', (string) ($clients->first()['id'] ?? ''));
        $selected = $clients->firstWhere('id', $selectedId);

        $userGrants = [];
        $departmentGrants = [];
        $availableDepartments = [];
        $userResults = [];

        if ($selected) {
            $grants = ApplicationAccessGrant::query()
                ->with(['user.profile', 'department'])
                ->where('client_id', $selectedId)
                ->latest()
                ->get();

            $userGrants = $grants
                ->filter(fn (ApplicationAccessGrant $grant) => $grant->user !== null)
                ->map(fn (ApplicationAccessGrant $grant) => [
                    'id' => $grant->id,
                    'user_id' => $grant->user_id,
                    'name' => $grant->user->name,
                    'full_name' => $grant->user->profile?->full_name,
                    'email' => $grant->user->email,
                    'granted_at' => $grant->created_at?->format('d/m/Y'),
                ])
                ->values();

            $departmentGrants = $grants
                ->filter(fn (ApplicationAccessGrant $grant) => $grant->department !== null)
                ->map(fn (ApplicationAccessGrant $grant) => [
                    'id' => $grant->id,
                    'department_id' => $grant->department_id,
                    'name' => $grant->department->name,
                    'code' => $grant->department->code,
                    'type_label' => $grant->department->isManagementBoard() ? 'Ban Giám đốc' : 'Tổ chuyên môn',
                    'members_count' => $grant->department->activePositions()->distinct('user_id')->count('user_id'),
                    'granted_at' => $grant->created_at?->format('d/m/Y'),
                ])
                ->values();

            $grantedDepartmentIds = $grants->pluck('department_id')->filter()->all();
            $grantedUserIds = $grants->pluck('user_id')->filter()->all();

            $availableDepartments = Department::query()
                ->where('is_active', true)
                ->whereNotIn('id', $grantedDepartmentIds)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'type_label' => $department->isManagementBoard() ? 'Ban Giám đốc' : 'Tổ chuyên môn',
                ])
                ->values();

            $search = trim((string) $request->query('search', ''));
            if (mb_strlen($search) >= 2) {
                $term = '%'.$search.'%';

                $userResults = User::query()
                    ->with('profile')
                    ->where('status', 'active')
                    ->whereNotIn('id', $grantedUserIds)
                    ->where(function ($query) use ($term) {
                        $query->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhereHas('profile', function ($profileQuery) use ($term) {
                                $profileQuery->where('full_name', 'like', $term)
                                    ->orWhere('phone_number', 'like', $term);
                            });
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get()
                    ->map(fn (User $user) => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'full_name' => $user->profile?->full_name,
                        'email' => $user->email,
                    ])
                    ->values();
            }
        }

        return Inertia::render('Admin/ApplicationAccess/Index', [
            'clients' => $clients,
            'selectedClientId' => $selected ? $selectedId : null,
            'userGrants' => $userGrants,
            'departmentGrants' => $departmentGrants,
            'availableDepartments' => $availableDepartments,
            'userResults' => $userResults,
            'search' => $request->query('search', ''),
        ]);
    }

    public function grantUser(Request $request, string $clientId): RedirectResponse
    {
        $this->ensureAdmin($request);
        $client = $this->findClient($clientId);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);

        $grant = ApplicationAccessGrant::firstOrCreate(
            ['client_id' => (string) $client->id, 'user_id' => $validated['user_id']],
            ['granted_by' => $request->user()->id],
        );

        if ($grant->wasRecentlyCreated) {
            $this->auditLogger->log(
                event: 'APPLICATION_ACCESS_GRANTED',
                user: $request->user(),
                clientId: (string) $client->id,
                payload: ['target' => 'user', 'user_id' => $validated['user_id']],
            );
        }

        return back()->with('success', "Đã cấp quyền truy cập \"{$client->name}\" cho người dùng.");
    }

    public function grantDepartment(Request $request, string $clientId): RedirectResponse
    {
        $this->ensureAdmin($request);
        $client = $this->findClient($clientId);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
        ]);

        $grant = ApplicationAccessGrant::firstOrCreate(
            ['client_id' => (string) $client->id, 'department_id' => $validated['department_id']],
            ['granted_by' => $request->user()->id],
        );

        if ($grant->wasRecentlyCreated) {
            $this->auditLogger->log(
                event: 'APPLICATION_ACCESS_GRANTED',
                user: $request->user(),
                clientId: (string) $client->id,
                payload: ['target' => 'department', 'department_id' => $validated['department_id']],
            );
        }

        return back()->with('success', "Đã cấp quyền truy cập \"{$client->name}\" cho toàn bộ người dùng trong đơn vị.");
    }

    public function revoke(Request $request, string $clientId, int $grantId): RedirectResponse
    {
        $this->ensureAdmin($request);
        $client = $this->findClient($clientId);

        $grant = ApplicationAccessGrant::query()
            ->where('client_id', (string) $client->id)
            ->findOrFail($grantId);

        $payload = $grant->user_id
            ? ['target' => 'user', 'user_id' => $grant->user_id]
            : ['target' => 'department', 'department_id' => $grant->department_id];

        $grant->delete();

        $this->auditLogger->log(
            event: 'APPLICATION_ACCESS_REVOKED',
            user: $request->user(),
            clientId: (string) $client->id,
            payload: $payload,
        );

        return back()->with('success', "Đã thu hồi quyền truy cập \"{$client->name}\".");
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function findClient(string $clientId): Client
    {
        /** @var Client */
        return Passport::client()->newQuery()
            ->where('revoked', false)
            ->findOrFail($clientId);
    }
}
