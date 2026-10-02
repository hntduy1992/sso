<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sso:prune-logs 
                            {--days=90 : Number of days of audit logs to retain} 
                            {--dry-run : Simulate pruning without deleting records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old SSO audit logs older than the specified retention days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');

        if ($days < 1) {
            $this->error('The --days option must be at least 1.');

            return self::FAILURE;
        }

        $cutoffDate = now()->subDays($days);
        $query = AuditLog::where('created_at', '<', $cutoffDate);
        $count = $query->count();

        if ($dryRun) {
            $this->info("[DRY RUN] Found {$count} audit log(s) older than {$days} days (before {$cutoffDate->toDateTimeString()}) that would be pruned.");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info("No audit logs found older than {$days} days.");

            return self::SUCCESS;
        }

        $this->info("Pruning {$count} audit log(s) older than {$days} days...");

        // Delete in batches of 500 to avoid lock timeouts on high volume tables
        $deleted = 0;
        do {
            $batchDeleted = AuditLog::where('created_at', '<', $cutoffDate)
                ->limit(500)
                ->delete();

            $deleted += $batchDeleted;
        } while ($batchDeleted > 0);

        $this->info("Successfully pruned {$deleted} audit log(s).");

        return self::SUCCESS;
    }
}
