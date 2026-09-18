<?php

namespace Pelican\Versions\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Pelican\Versions\Models\VersionChange;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class VersionChangeService
{
    private DaemonServerRepository $serverRepo;

    public function __construct(
        private DaemonFileRepository $fileRepo,
        ?DaemonServerRepository $serverRepo = null
    ) {
        $this->serverRepo = $serverRepo ?? app(DaemonServerRepository::class);
    }

    public function execute(VersionChange $record): void
    {
        $server = $record->server;

        if (!$server) {
            throw new RuntimeException("Server not found for VersionChange #{$record->id}");
        }

        $this->fileRepo->setServer($server);
        $this->serverRepo->setServer($server);

        try {
            $wasRunning = $this->stepPowerStopIfRunning($record);

            $this->stepBackupAndClean($record);

            $isZip = $this->stepDownload($record);

            $this->stepVerifyAndExtract($record, $isZip);

            $this->stepHandleEula($record);
            $this->stepSyncEggVariable($record, $server);

            if ($wasRunning && config('versions.auto_restart_server', true)) {
                $this->stepPowerRestart($record);
            } else {
                $record->appendLog('Step 6/6: Server is offline and ready to start.');
            }

            $record->markDone();

            if ($wasRunning && config('versions.auto_restart_server', true)) {
                $record->appendLog('Version change completed successfully! Server has been automatically restarted.');
            } else {
                $record->appendLog('Version change completed successfully! You can now start your server.');
            }
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

    private function stepPowerStopIfRunning(VersionChange $record): bool
    {
        if (!config('versions.auto_stop_server', true)) {
            $record->appendLog('Step 1/6: Server power automation disabled in config.');
            return false;
        }

        try {
            $details = $this->serverRepo->getDetails();
            $state = strtolower($details['state'] ?? 'offline');
            $isRunning = in_array($state, ['running', 'starting', 'restarting'], true);
            $isStopping = ($state === 'stopping');

            if ($isRunning || $isStopping) {
                if ($isRunning) {
                    $record->appendLog("Step 1/6: Server is currently online ({$state}).");
                    $record->appendLog('  Sending graceful stop signal to prevent file lock and corruption...');
                    $this->serverRepo->power('stop');
                } else {
                    $record->appendLog("Step 1/6: Server is currently shutting down ({$state}). Waiting for clean stop...");
                }

                for ($i = 0; $i < 7; $i++) {
                    sleep(3);
                    try {
                        $check = $this->serverRepo->getDetails();
                        $currentState = strtolower($check['state'] ?? 'offline');
                        if (in_array($currentState, ['offline', 'exited'], true)) {
                            $record->appendLog('  Server stopped cleanly.');
                            return true;
                        }
                    } catch (Throwable) {}
                }

                $record->appendLog('  Shutdown wait timeout reached. Proceeding with file modifications.');
                return true;
            } else {
                $record->appendLog('Step 1/6: Server is offline. Ready for file modifications.');
                return false;
            }
        } catch (Throwable $e) {
            $record->appendLog('  Notice: Could not inspect server power state: ' . $e->getMessage());
            return false;
        }
    }

    private function stepBackupAndClean(VersionChange $record): void
    {
        $record->appendLog('Step 2/6: Checking existing files and cleaning legacy libraries...');

        try {
            $entries = (array) $this->fileRepo->getDirectory('/');

            $hasJar = collect($entries)->contains(
                fn ($e) => ($e['name'] ?? '') === 'server.jar' && !($e['directory'] ?? false)
            );

            if ($hasJar) {
                if (config('versions.keep_backup', true)) {
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
                    $this->fileRepo->deleteFiles('/', ['server.jar']);
                    $record->appendLog('  Deleted previous server.jar (backup disabled).');
                }
            } else {
                $record->appendLog('  No existing server.jar found; skipping backup.');
            }

            if (config('versions.clean_libraries', true)) {
                $hasLibraries = collect($entries)->contains(
                    fn ($e) => ($e['name'] ?? '') === 'libraries' && ($e['directory'] ?? false)
                );

                if ($hasLibraries) {
                    $this->fileRepo->deleteFiles('/', ['libraries']);
                    $record->appendLog('  Removed old /libraries/ directory to prevent dependency collisions.');
                }
            }

            if ($record->clean_install) {
                $record->appendLog('  [CLEAN INSTALL] Wiping server root files...');

                $currentEntries = (array) $this->fileRepo->getDirectory('/');

                $preserved = ['server.jar.bak', 'eula.txt'];
                $toDelete   = [];

                foreach ($currentEntries as $entry) {
                    $name = $entry['name'] ?? '';
                    if (empty($name) || in_array($name, $preserved, true)) {
                        continue;
                    }
                    $toDelete[] = $name;
                }

                if (!empty($toDelete)) {
                    foreach (array_chunk($toDelete, 100) as $chunk) {
                        $this->fileRepo->deleteFiles('/', $chunk);
                    }
                    $record->appendLog('  [CLEAN INSTALL] Deleted ' . count($toDelete) . ' items from server root.');
                } else {
                    $record->appendLog('  [CLEAN INSTALL] Server root is already empty.');
                }
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Backup/Clean step failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function stepDownload(VersionChange $record): bool
    {
        $urlPath = parse_url($record->jar_url, PHP_URL_PATH) ?? '';
        $isZip = str_ends_with(strtolower($urlPath), '.zip')
            || str_contains(strtolower($record->jar_url), '.zip');

        $filename = $isZip ? 'server.zip' : 'server.jar';

        $record->appendLog('Step 3/6: Telling Wings daemon to pull the new files...');
        $record->appendLog("  Target: {$record->software} {$record->minecraft_version} ({$record->build_name})");

        try {
            $this->fileRepo->pull($record->jar_url, '/', [
                'filename'   => $filename,
                'foreground' => false,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to send download command to Wings: {$e->getMessage()}", 0, $e);
        }

        $timeout = (int) config('versions.download_timeout', 600);
        $deadline = time() + $timeout;
        $lastSize = -1;
        $stableCount = 0;
        $size = null;
        $loggedWaiting = false;

        while (time() < $deadline) {
            sleep(4);
            $size = $this->getRemoteFileSize($filename);

            if ($size === null || $size <= 0) {
                if (!$loggedWaiting) {
                    $record->appendLog('  Waiting for download to begin on node...');
                    $loggedWaiting = true;
                }
                continue;
            }

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
            throw new RuntimeException("Download timed out or {$filename} was not created by Wings.");
        }

        $record->appendLog("  Download completed successfully: " . $this->humanBytes($size));

        return $isZip;
    }

    private function stepVerifyAndExtract(VersionChange $record, bool $isZip): void
    {
        $filename = $isZip ? 'server.zip' : 'server.jar';
        $record->appendLog("Step 4/6: Verifying {$filename} integrity...");

        $actual = $this->getRemoteFileSize($filename);

        if ($actual === null || $actual <= 0) {
            throw new RuntimeException("Verification failed: {$filename} not found after download.");
        }

        if ($record->jar_size) {
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

        if ($isZip) {
            $record->appendLog('  Extracting server.zip archive to root directory...');
            try {
                $this->fileRepo->decompressFile('/', 'server.zip');
                $record->appendLog('  Decompression finished successfully.');

                try {
                    $this->fileRepo->deleteFiles('/', ['server.zip']);
                    $record->appendLog('  Cleaned up temporary server.zip.');
                } catch (Throwable) {}
            } catch (Throwable $e) {
                throw new RuntimeException("Failed to decompress {$filename}: " . $e->getMessage(), 0, $e);
            }
        }
    }

    private function stepHandleEula(VersionChange $record): void
    {
        $record->appendLog('Step 5/6: Verifying Minecraft EULA status...');

        try {
            $content = $this->fileRepo->getContent('eula.txt', 2048);
            if (str_contains($content, 'eula=true')) {
                $record->appendLog('  Existing Minecraft EULA agreement detected and preserved.');
                return;
            }
        } catch (Throwable) {
        }

        $record->appendLog('  Notice: Minecraft EULA is handled via Pelican native prompt on startup.');
    }

    private function stepSyncEggVariable(VersionChange $record, Server $server): void
    {
        try {
            $serverVar = $server->serverVariables()
                ->whereHas('variable', fn ($q) => $q->where('env_variable', 'SERVER_JARFILE'))
                ->first();

            if ($serverVar && $serverVar->variable_value !== 'server.jar') {
                $serverVar->update(['variable_value' => 'server.jar']);
                $record->appendLog('  Updated SERVER_JARFILE egg variable to "server.jar".');
                try {
                    $this->serverRepo->sync();
                    $record->appendLog('  Synced environment configuration with Wings node.');
                } catch (Throwable) {}
            }
        } catch (Throwable) {
        }
    }

    private function stepPowerRestart(VersionChange $record): void
    {
        $record->appendLog('Step 6/6: Automatically restarting server with the new version...');

        try {
            $this->serverRepo->power('start');
            $record->appendLog('  Sent start signal to Wings. Server is booting up!');
        } catch (Throwable $e) {
            $record->appendLog('  Notice: Could not send start signal: ' . $e->getMessage() . '. Please boot manually from the console.');
        }
    }

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
