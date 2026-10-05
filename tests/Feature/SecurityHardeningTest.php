<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');
        RateLimiter::clear('mfa.challenge');
        RateLimiter::clear('oauth.token');

        $this->user = User::factory()->create([
            'email' => 'victim@security.local',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
        ]);

        $this->client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Security Test Client',
            'secret' => 'security-secret-12345',
            'redirect_uris' => ['https://satellite.local/callback'],
            'grant_types' => 'authorization_code,refresh_token',
            'client_type' => 'CONFIDENTIAL',
            'revoked' => false,
        ]);
    }

    // =========================================================================
    // 1. Enterprise Security Headers
    // =========================================================================

    public function test_responses_include_enterprise_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            ->assertHeader('X-XSS-Protection', '0');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_csp_includes_configured_app_and_custom_origins(): void
    {
        config(['app.url' => 'https://ttcudvcsadec.dongthap.gov.vn/sso']);
        putenv('CSP_ALLOWED_HOSTS=http://192.168.1.100:8000');

        $response = $this->get('/login');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://ttcudvcsadec.dongthap.gov.vn', $csp);
        $this->assertStringContainsString('http://192.168.1.100:8000', $csp);

        // Reset
        putenv('CSP_ALLOWED_HOSTS');
    }

    public function test_api_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // =========================================================================
    // 2. Health Check Endpoint
    // =========================================================================

    public function test_health_check_returns_healthy_status_and_subsystem_reports(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'database' => 'ok',
                    'cache' => 'ok',
                ],
            ]);
    }

    // =========================================================================
    // 3. Login Rate Limiting (Brute-Force Protection)
    // =========================================================================

    public function test_login_rate_limiter_blocks_after_5_failed_attempts(): void
    {
        $loginData = [
            'email' => 'victim@security.local',
            'password' => 'WrongPassword!',
        ];

        // 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', $loginData);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th attempt must be throttled with 429 Too Many Requests
        $throttledResponse = $this->post('/login', $loginData);
        $throttledResponse->assertStatus(429);
    }

    // =========================================================================
    // 4. MFA Challenge Rate Limiting
    // =========================================================================

    public function test_mfa_challenge_rate_limiter_blocks_after_5_attempts(): void
    {
        $challengeData = ['code' => '000000'];

        // Simulate session with pending user id
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withSession(['mfa_pending_user_id' => $this->user->id])
                ->post('/mfa/challenge', $challengeData);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th attempt must be throttled
        $throttledResponse = $this->withSession(['mfa_pending_user_id' => $this->user->id])
            ->post('/mfa/challenge', $challengeData);
        $throttledResponse->assertStatus(429);
    }

    // =========================================================================
    // 5. Audit Log Retention & Cleanup Command (sso:prune-logs)
    // =========================================================================

    public function test_sso_prune_logs_dry_run_does_not_delete_records(): void
    {
        // Old log (120 days ago)
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'event' => 'OLD_EVENT',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subDays(120),
        ]);

        $this->artisan('sso:prune-logs --days=90 --dry-run')
            ->expectsOutputToContain('[DRY RUN]')
            ->assertExitCode(0);

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_sso_prune_logs_deletes_records_older_than_retention_days(): void
    {
        // Old log (100 days ago)
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'event' => 'OLD_EVENT_PRUNE',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subDays(100),
        ]);

        // Recent log (10 days ago)
        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'event' => 'RECENT_EVENT_KEEP',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subDays(10),
        ]);

        $this->assertDatabaseCount('audit_logs', 2);

        $this->artisan('sso:prune-logs --days=90')
            ->expectsOutputToContain('Successfully pruned 1 audit log(s)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'RECENT_EVENT_KEEP']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'OLD_EVENT_PRUNE']);
    }
}
