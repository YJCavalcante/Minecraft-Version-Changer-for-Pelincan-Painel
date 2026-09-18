<?php

namespace Pelican\Versions\Console\Commands;

use Illuminate\Console\Command;
use Pelican\Versions\Services\McJarsService;
use Throwable;

class WarmVersionCacheCommand extends Command
{
    protected $signature   = 'versions:warm-cache';
    protected $description = 'Pre-warms the MCJars API cache for all software types and their versions.';

    public function handle(McJarsService $mcJarsService): int
    {
        $this->info('[Versions] Warming MCJars cache...');

        $types = [];
        try {
            $types = $mcJarsService->getTypes();
            $this->info('  ✓ Types cached (' . array_sum(array_map('count', $types)) . ' software entries)');
        } catch (Throwable $e) {
            $this->error('  ✗ Failed to fetch types: ' . $e->getMessage());
            return self::FAILURE;
        }

        $total   = 0;
        $failed  = 0;

        foreach ($types as $category => $softwares) {
            foreach ($softwares as $key => $software) {
                try {
                    $versions = $mcJarsService->getVersions($key);
                    $count    = count($versions);
                    $total   += $count;
                    $this->line("  ✓ {$key}: {$count} versions cached");
                } catch (Throwable $e) {
                    $this->warn("  ✗ {$key}: failed — " . $e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("[Versions] Cache warm complete. {$total} version sets cached, {$failed} failed.");

        return self::SUCCESS;
    }
}
