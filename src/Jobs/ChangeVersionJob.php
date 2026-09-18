<?php

namespace Pelican\Versions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pelican\Versions\Models\VersionChange;
use Pelican\Versions\Services\VersionChangeService;
use Throwable;

class ChangeVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries   = 1;

    public function __construct(
        public readonly int $changeRecordId
    ) {
        $queue = config('versions.queue', null);
        if (!empty($queue)) {
            $this->onQueue($queue);
        }
    }

    public function handle(VersionChangeService $service): void
    {
        $record = VersionChange::find($this->changeRecordId);

        if (!$record) {
            Log::warning("[Versions] ChangeVersionJob: Record #{$this->changeRecordId} not found, skipping.");
            return;
        }

        if ($record->status !== VersionChange::STATUS_PENDING) {
            Log::info("[Versions] ChangeVersionJob: Record #{$this->changeRecordId} is {$record->status}, not pending; skipping.");
            return;
        }

        $record->markChanging();
        $record->appendLog('Job started on queue worker.');

        $service->execute($record);
    }

    public function failed(Throwable $e): void
    {
        $record = VersionChange::find($this->changeRecordId);

        if ($record) {
            $record->markFailed($e->getMessage());
            $record->appendLog('JOB FAILED: ' . $e->getMessage());
        }

        Log::error("[Versions] ChangeVersionJob failed for record #{$this->changeRecordId}", [
            'error' => $e->getMessage(),
        ]);
    }
}
