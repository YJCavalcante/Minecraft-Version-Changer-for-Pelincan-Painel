<?php

namespace Pelican\Versions\Console\Commands;

use Illuminate\Console\Command;
use Pelican\Versions\Models\VersionChange;

class PruneVersionChangesCommand extends Command
{
    protected $signature   = 'versions:prune {--days= : Delete finished records older than this many days}';
    protected $description = 'Fails stuck version changes and prunes old history records.';

    public function handle(): int
    {
        $stale = VersionChange::failStale();
        $this->info("Marked {$stale} stuck record(s) as failed.");

        $days = (int) ($this->option('days') ?: config('versions.history_retention_days', 90));

        if ($days <= 0) {
            return self::SUCCESS;
        }

        $keep = VersionChange::query()
            ->where('status', VersionChange::STATUS_DONE)
            ->selectRaw('MAX(id) as id')
            ->groupBy('server_id')
            ->pluck('id')
            ->all();

        $deleted = VersionChange::query()
            ->whereIn('status', [VersionChange::STATUS_DONE, VersionChange::STATUS_FAILED])
            ->where('created_at', '<', now()->subDays($days))
            ->whereNotIn('id', $keep)
            ->delete();

        $this->info("Deleted {$deleted} record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
