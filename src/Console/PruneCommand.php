<?php

declare(strict_types=1);

namespace Webstat\Analytics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneCommand extends Command
{
    protected $signature = 'analytics:prune {--days= : override analytics.retention_days}';

    protected $description = 'Delete analytics data older than the retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('analytics.retention_days', 395));
        $cutoff = now()->subDays($days);
        $db = DB::connection(config('analytics.connection'));

        $events = 0;
        do { // chunked deletes keep locks short on big tables
            $ids = $db->table('analytics_events')->where('created_at', '<', $cutoff)->limit(5000)->pluck('id');
            $events += $ids->isEmpty() ? 0 : $db->table('analytics_events')->whereIn('id', $ids)->delete();
        } while ($ids->count() === 5000);

        $sessions = 0;
        do {
            $ids = $db->table('analytics_sessions')->where('last_seen_at', '<', $cutoff)->limit(5000)->pluck('id');
            $sessions += $ids->isEmpty() ? 0 : $db->table('analytics_sessions')->whereIn('id', $ids)->delete();
        } while ($ids->count() === 5000);

        $this->components->info("Pruned {$events} event(s), {$sessions} session(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
