<?php

namespace Pelican\Versions\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Pelican\Versions\Models\VersionChange;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VersionChangeService
{
    public function __construct(
        private DaemonFileRepository $fileRepo
    ) {}

    /**
     * Execute the full version change workflow on the remote Wings daemon.
     *
     * @throws Throwable
     */
    public function execute(VersionChange $record): void
    {
        $server = $record->server;

        if (!$server) {
            throw new RuntimeException("Server not found for VersionChange #{$record->id}");
        }

        // Bind the daemon repository to this specific server
        $this->fileRepo->setServer($server);

        try {
            $this->stepBackup($record);
            $this->stepDownload($record);
            $this->stepVerify($record);
            $this->stepSyncEggVariable($record, $server);

            $record->markDone();
            $record->appendLog('Version change completed successfully! Please restart the server to apply.');
        } catch (Throwable $e) {
            $record->markFailed($e->getMessage());
            $record->appendLog('ERROR: ' . $e->getMessage());

            Log::error("[Versions] Version change failed for #{$record->id}: " . $e->getMessage(), [
                'server_id' => $server->id,
                'software'  => $record->software,
                'version'   => $record->minecraft_version,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Step 1: Backup existing server.jar if present.
     */
    private function stepBackup(VersionChange $record): void
    {
        if (!config('versions.keep_backup', true)) {
            $record->appendLog('Step 1/3: Backup skipped (disabled in configuration).');
            return;
        }

        $record->appendLog('Step 1/3: Checking existing server files for backup...');

        try {
            $entries = (array) $this->fileRepo->getDirectory('/');
            $hasJar = collect($entries)->contains(
                fn ($e) => ($e['name'] ?? '') === 'server.jar' && !($e['directory'] ?? false)
            );

            if ($hasJar) {
                // If a prior backup already exists, remove it first to avoid collision
                $hasOldBak = collect($entries)->contains(
                    fn ($e) => ($e['name'] ?? '') === 'server.jar.bak' && !($e['directory'] ?? false)
                );

                if ($hasOldBak) {
                    $this->fileRepo->deleteFiles('/', ['server.jar.bak']);
                    $record->appendLog('  Removed previous server.jar.bak backup.');
                }

                $this->fileRepo->renameFiles('/', [
                    ['from' => 'server.jar', 'to' => 'server.jar.bak']
                ]);

                $record->appendLog('  Renamed existing server.jar → server.jar.bak.');
            } else {
                $record->appendLog('  No existing server.jar found in root directory; skipping backup.');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Backup step failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Step 2: Instruct Wings to download the new server JAR.
     */
    private function stepDownload(VersionChange $record): void
    {
        $record->appendLog('Step 2/3: Telling Wings daemon to pull the new JAR...');
        $record->appendLog("  Source URL: {$record->jar_url}");

        try {
            $this->fileRepo->pull($record->jar_url, '/', [
                'filename'   => 'server.jar',
                'foreground' => false,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to send download command to Wings: ' . $e->getMessage(), 0, $e);
        }

        // Wait for download to finish by monitoring file presence and size stabilization
        $timeout = (int) config('versions.download_timeout', 600);
        $deadline = time() + $timeout;
        $lastSize = -1;
        $stableCount = 0;
        $size = null;

        while (time() < $deadline) {
            sleep(4);
            $size = $this->getRemoteFileSize('server.jar');

            if ($size === null || $size <= 0) {
                $record->appendLog('  Waiting for download to begin on node...');
                continue;
            }

            // If target size is known and exact match is reached
            if ($record->jar_size && $size === (int) $record->jar_size) {
                $record->appendLog('  Download complete: ' . $this->humanBytes($size) . ' (100%)');
                break;
            }

            if ($size === $lastSize) {
                $stableCount++;
                if ($stableCount >= 2) {
                    break;
                }
            } else {
                $stableCount = 0;
                $pct = $record->jar_size ? ' (' . round(($size / $record->jar_size) * 100, 1) . '%)' : '';
                $record->appendLog('  Downloaded ' . $this->humanBytes($size) . $pct);
            }

            $lastSize = $size;
        }

        if ($size === null || $size <= 0) {
            throw new RuntimeException('Download timed out or server.jar was not created by Wings.');
        }

        $record->appendLog('  Download completed successfully: ' . $this->humanBytes($size));
    }

    /**
     * Step 3: Verify the downloaded file integrity.
     */
    private function stepVerify(VersionChange $record): void
    {
        $record->appendLog('Step 3/3: Verifying file integrity...');

        $actual = $this->getRemoteFileSize('server.jar');

        if ($actual === null || $actual <= 0) {
            throw new RuntimeException('Verification failed: server.jar not found after download.');
        }

        if ($record->jar_size) {
            // Allow 1% tolerance for upstream compression or header differences
            $tolerance = (int) max(1024, $record->jar_size * 0.01);
            if (abs($actual - $record->jar_size) > $tolerance) {
                throw new RuntimeException(
                    "Size mismatch: expected {$record->jar_size} bytes, got {$actual} bytes."
                );
            }
            $record->appendLog('  File size verified: ' . $this->humanBytes($actual) . ' (matches expected)');
        } else {
            $record->appendLog('  File size verified: ' . $this->humanBytes($actual));
        }
    }

    /**
     * Optional Step: Ensure SERVER_JARFILE environment variable is aligned.
     */
    private function stepSyncEggVariable(VersionChange $record, Server $server): void
    {
        try {
            $serverVar = $server->serverVariables()
                ->whereHas('variable', fn ($q) => $q->where('env_variable', 'SERVER_JARFILE'))
                ->first();

            if ($serverVar && $serverVar->variable_value !== 'server.jar') {
                $serverVar->update(['variable_value' => 'server.jar']);
                $record->appendLog('  Updated SERVER_JARFILE egg variable to "server.jar".');
            }
        } catch (Throwable) {
            // Non-critical; ignore if egg doesn't define this variable
        }
    }

    /**
     * Helper to retrieve remote file size from the directory listing.
     */
    private function getRemoteFileSize(string $filename): ?int
    {
        try {
            $entries = (array) $this->fileRepo->getDirectory('/');
            foreach ($entries as $entry) {
                if (($entry['name'] ?? '') === $filename && !($entry['directory'] ?? false)) {
                    return (int) ($entry['size'] ?? 0);
                }
            }
        } catch (Throwable) {}

        return null;
    }

    /**
     * Helper to format bytes into human-readable size.
     */
    private function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
