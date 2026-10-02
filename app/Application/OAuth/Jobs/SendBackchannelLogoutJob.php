<?php

declare(strict_types=1);

namespace App\Application\OAuth\Jobs;

use App\Application\OAuth\Services\BackchannelLogoutTokenGenerator;
use App\Domain\Audit\Services\AuditLogger;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;
use Throwable;

class SendBackchannelLogoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>|int
     */
    public int $backoff = 5;

    public function __construct(
        public Client $client,
        public string $userId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        BackchannelLogoutTokenGenerator $tokenGenerator,
        AuditLogger $auditLogger,
    ): void {
        $logoutUri = $this->client->backchannel_logout_uri;

        if (empty($logoutUri)) {
            return;
        }

        $logoutToken = $tokenGenerator->generate(
            userId: $this->userId,
            clientId: (string) $this->client->id,
        );

        $response = Http::asForm()
            ->timeout(5)
            ->post($logoutUri, [
                'logout_token' => $logoutToken,
            ]);

        $user = User::find($this->userId);

        if ($response->successful()) {
            $auditLogger->log(
                event: 'BACKCHANNEL_LOGOUT_SENT',
                user: $user,
                clientId: (string) $this->client->id,
                payload: [
                    'uri' => $logoutUri,
                    'status_code' => $response->status(),
                ]
            );
        } else {
            $auditLogger->log(
                event: 'BACKCHANNEL_LOGOUT_FAILED',
                user: $user,
                clientId: (string) $this->client->id,
                payload: [
                    'uri' => $logoutUri,
                    'status_code' => $response->status(),
                    'error' => $response->body(),
                ]
            );

            $response->throw();
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        /** @var AuditLogger $auditLogger */
        $auditLogger = app(AuditLogger::class);

        $auditLogger->log(
            event: 'BACKCHANNEL_LOGOUT_EXHAUSTED',
            user: User::find($this->userId),
            clientId: (string) $this->client->id,
            payload: [
                'uri' => $this->client->backchannel_logout_uri,
                'error' => $exception?->getMessage(),
            ]
        );
    }
}
