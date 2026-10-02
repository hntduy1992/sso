<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Developer;

use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class ClientController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Display developer's OAuth clients.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = Passport::client()->newQuery()->where('revoked', false);

        if (! $user->isAdmin()) {
            $query->where('owner_id', $user->id);
        }

        $clients = $query->orderByDesc('created_at')
            ->get()
            ->map(fn (Client $client) => [
                'id' => (string) $client->id,
                'name' => $client->name,
                'client_type' => $client->client_type,
                'redirect_uris' => is_array($client->redirect_uris) ? $client->redirect_uris : [],
                'grant_types' => is_array($client->grant_types) ? $client->grant_types : [],
                'backchannel_logout_uri' => $client->backchannel_logout_uri,
                'description' => $client->description,
                'is_trusted' => (bool) $client->is_trusted,
                'created_at' => $client->created_at?->diffForHumans() ?? 'Vừa tạo',
            ]);

        return Inertia::render('Developer/Clients', [
            'clients' => $clients,
            'plainSecret' => session('plain_secret'),
            'newClientId' => session('new_client_id'),
        ]);
    }

    /**
     * Register a new OAuth Client.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_type' => ['required', 'in:PUBLIC,CONFIDENTIAL'],
            'redirect_uris' => ['required', 'string'],
            'backchannel_logout_uri' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $redirectUris = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $validated['redirect_uris']) ?: [])
        ));

        if (empty($redirectUris)) {
            return back()->withErrors(['redirect_uris' => 'Cần cung cấp ít nhất một Redirect URI hợp lệ.']);
        }

        $isConfidential = $validated['client_type'] === 'CONFIDENTIAL';
        $plainSecret = $isConfidential ? Str::random(40) : null;

        $grantTypes = $isConfidential
            ? ['authorization_code', 'refresh_token', 'client_credentials']
            : ['authorization_code', 'refresh_token'];

        /** @var Client $client */
        $client = Passport::client()->create([
            'id' => (string) Str::uuid(),
            'owner_id' => $user->id,
            'owner_type' => $user->getMorphClass(),
            'name' => $validated['name'],
            'secret' => $plainSecret,
            'client_type' => $validated['client_type'],
            'pkce_enforced' => true,
            'is_trusted' => false,
            'redirect_uris' => $redirectUris,
            'grant_types' => $grantTypes,
            'backchannel_logout_uri' => $validated['backchannel_logout_uri'] ?? null,
            'description' => $validated['description'] ?? null,
            'revoked' => false,
        ]);

        $this->auditLogger->log(
            event: 'CLIENT_CREATED',
            user: $user,
            clientId: (string) $client->id,
            payload: [
                'client_name' => $client->name,
                'client_type' => $client->client_type,
            ]
        );

        return back()
            ->with('success', 'Ứng dụng OAuth đã được đăng ký thành công.')
            ->with('plain_secret', $plainSecret)
            ->with('new_client_id', (string) $client->id);
    }

    /**
     * Update an existing OAuth Client.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Client|null $client */
        $client = Passport::client()->newQuery()->find($id);

        if (! $client || (! $user->isAdmin() && (string) $client->owner_id !== (string) $user->id)) {
            return back()->with('error', 'Bạn không có quyền chỉnh sửa ứng dụng này.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'string'],
            'backchannel_logout_uri' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $redirectUris = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $validated['redirect_uris']) ?: [])
        ));

        $client->update([
            'name' => $validated['name'],
            'redirect_uris' => $redirectUris,
            'backchannel_logout_uri' => $validated['backchannel_logout_uri'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Thông tin ứng dụng đã được cập nhật.');
    }

    /**
     * Regenerate client secret.
     */
    public function regenerateSecret(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Client|null $client */
        $client = Passport::client()->newQuery()->find($id);

        if (! $client || (! $user->isAdmin() && (string) $client->owner_id !== (string) $user->id)) {
            return back()->with('error', 'Bạn không có quyền thao tác trên ứng dụng này.');
        }

        if ($client->client_type !== 'CONFIDENTIAL') {
            return back()->with('error', 'Chỉ ứng dụng Confidential mới có Client Secret.');
        }

        $plainSecret = Str::random(40);
        $client->update(['secret' => $plainSecret]);

        $this->auditLogger->log(
            event: 'CLIENT_SECRET_REGENERATED',
            user: $user,
            clientId: (string) $client->id,
        );

        return back()
            ->with('success', 'Client Secret đã được làm mới thành công.')
            ->with('plain_secret', $plainSecret)
            ->with('new_client_id', (string) $client->id);
    }

    /**
     * Revoke an OAuth Client.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Client|null $client */
        $client = Passport::client()->newQuery()->find($id);

        if (! $client || (! $user->isAdmin() && (string) $client->owner_id !== (string) $user->id)) {
            return back()->with('error', 'Bạn không có quyền thao tác trên ứng dụng này.');
        }

        $client->update(['revoked' => true]);

        // Revoke all tokens issued for this client
        Passport::token()->newQuery()->where('client_id', $client->id)->update(['revoked' => true]);

        $this->auditLogger->log(
            event: 'CLIENT_REVOKED',
            user: $user,
            clientId: (string) $client->id,
        );

        return back()->with('success', 'Ứng dụng OAuth đã được thu hồi hoàn toàn.');
    }
}
