<?php

namespace Pelican\Versions\Console\Commands;

use Illuminate\Console\Command;
use Pelican\Versions\Services\McJarsService;
use Throwable;

class WarmVersionCacheCommand extends Command
{
    protected $signature   = 'versions:warm-cache';
    protected $description = 'Refreshes the MCJars API cache for all software types, their versions and the latest builds.';

    public function handle(McJarsService $mcJarsService): int
    {
        $this->info('[Versions] Warming MCJars cache...');

        $types = $mcJarsService->getTypes(true);

        if ($types === [] || $mcJarsService->lastFetchFailed('types')) {
            $this->error('  Failed to fetch fresh types from MCJars; keeping existing cache and aborting warm-up.');

            return self::FAILURE;
        }

        $this->info('  Types cached (' . array_sum(array_map('count', $types)) . ' software entries)');

        $buildsPerSoftware   = max(0, (int) config('versions.warm_builds_versions', 2));
        $total               = 0;
        $failed              = 0;
        $consecutiveFailures = 0;
        $seen                = [];

        foreach ($types as $softwares) {
            foreach (array_keys((array) $softwares) as $key) {
                $key = strtoupper((string) $key);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;

                try {
                    $versions = $mcJarsService->getVersions($key, true);
                    usleep(150000);

                    if ($mcJarsService->lastFetchFailed("versions:{$key}")) {
                        $this->warn("  {$key}: upstream fetch failed");
                        $failed++;
                        $consecutiveFailures++;

                        if ($consecutiveFailures >= 3) {
                            $this->error('  Upstream API appears unreachable; stopping warm-up early.');

                            return self::FAILURE;
                        }

                        continue;
                    }

                    $consecutiveFailures = 0;

                    if ($versions === []) {
                        $this->warn("  {$key}: no versions returned");
                        $failed++;
                        continue;
                    }

                    $total += count($versions);
                    $this->line("  {$key}: " . count($versions) . ' versions cached');

                    $releases = array_keys(array_filter($versions, fn (array $v): bool => ($v['type'] ?? 'RELEASE') === 'RELEASE'));

                    foreach (array_slice($releases, 0, $buildsPerSoftware) as $version) {
                        $mcJarsService->getBuilds($key, (string) $version, true);
                        usleep(150000);
                    }
                } catch (Throwable $e) {
                    $this->warn("  {$key}: failed - " . $e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("[Versions] Cache warm complete. {$total} versions cached, {$failed} failed.");

        return self::SUCCESS;
    }
}
