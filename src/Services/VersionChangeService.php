<?php

namespace Pelican\Versions\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pelican\Versions\Models\VersionChange;
use RuntimeException;
use Throwable;

class VersionChangeService
{
    private const LIBRARIES_OLD = 'libraries.versions-old';

    private const JAR_ENV_VARS = ['SERVER_JARFILE', 'JARFILE', 'JAR_NAME', 'SERVER_JAR'];

    private const MIN_ARTIFACT_BYTES = 4096;

    private DaemonServerRepository $serverRepo;

    private array $undo = [];

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
        $this->undo = [];

        $wasRunning = false;
        $temp       = null;
        $autoStart  = (bool) config('versions.auto_restart_server', true);

        try {
            $wasRunning = $this->stepPowerStopIfRunning($record);

            [$temp, $isZip] = $this->stepDownload($record);

            $this->stepVerify($record, $temp);

            $this->stepSwap($record, $temp, $isZip);

            $this->stepHandleEula($record);
            $this->stepSyncEggVariable($record, $server);

            $this->finalizeCleanup($record);

            if ($wasRunning && $autoStart) {
                $this->stepPowerRestart($record);
                $record->appendLog('Version change completed successfully! Server has been automatically restarted.');
            } else {
                $record->appendLog('Step 6/6: Server is offline and ready to start.');
                $record->appendLog('Version change completed successfully! You can now start your server.');
            }

            $record->markDone();

            Cache::forget("versions:current:{$server->id}");
        } catch (Throwable $e) {
            $record->appendLog('ERROR: ' . $e->getMessage());

            $rolledBack = $this->rollback($record);

            if ($temp !== null) {
                $this->deleteQuietly([$temp]);
            }

            if ($rolledBack && $wasRunning && $autoStart) {
                try {
                    $this->serverRepo->power('start');
                    $record->appendLog('Previous version restored and server started again.');
                } catch (Throwable $startError) {
                    $record->appendLog('Could not restart the server: ' . $startError->getMessage());
                }
            }

            $record->markFailed($e->getMessage());

            Cache::forget("versions:current:{$server->id}");

            Log::error("[Versions] Version change failed for #{$record->id}: " . $e->getMessage(), [
                'server_id' => $server->id,
                'software'  => $record->software,
                'version'   => $record->minecraft_version,
                'trace'     => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function getTargetServerJarFilename(Server $server): string
    {
        try {
            $match = $this->findJarServerVariable($server);
            $var   = $match['model'] ?? null;

            if ($var) {
                $val = trim((string) ($var->variable_value ?? ''));
                if ($val === '' && $var->variable) {
                    $val = trim((string) ($var->variable->default_value ?? ''));
                }

                if ($val !== '') {
                    $clean = basename(str_replace('\\', '/', $val));
                    if ($clean !== '' && $clean !== '.' && $clean !== '..') {
                        return $clean;
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] getTargetServerJarFilename error: ' . $e->getMessage());
        }

        return 'server.jar';
    }

    private function findJarServerVariable(Server $server): ?array
    {
        $rows = $server->serverVariables()
            ->whereHas('variable', fn ($q) => $q->whereIn('env_variable', self::JAR_ENV_VARS))
            ->with('variable')
            ->get();

        foreach (self::JAR_ENV_VARS as $envName) {
            foreach ($rows as $row) {
                if (strtoupper((string) ($row->variable?->env_variable ?? '')) === $envName) {
                    return ['env' => $envName, 'model' => $row];
                }
            }
        }

        return null;
    }

    private function stepPowerStopIfRunning(VersionChange $record): bool
    {
        if (!config('versions.auto_stop_server', true)) {
            $record->appendLog('Step 1/6: Server power automation disabled in config.');

            return false;
        }

        $abort = (bool) config('versions.abort_if_not_stopped', true);

        try {
            $state = strtolower((string) ($this->serverRepo->getDetails()['state'] ?? 'offline'));
        } catch (Throwable $e) {
            if ($abort) {
                throw new RuntimeException('Could not inspect the server power state: ' . $e->getMessage(), 0, $e);
            }

            $record->appendLog('  Notice: Could not inspect server power state: ' . $e->getMessage());

            return false;
        }

        if (in_array($state, ['offline', 'exited'], true)) {
            $record->appendLog('Step 1/6: Server is offline. Ready for file modifications.');

            return false;
        }

        if ($state === 'stopping') {
            $record->appendLog('Step 1/6: Server is shutting down. Waiting for clean stop...');
        } else {
            $record->appendLog("Step 1/6: Server is currently online ({$state}).");
            $record->appendLog('  Sending graceful stop signal to prevent file lock and corruption...');
            $this->serverRepo->power('stop');
        }

        $deadline  = time() + max(10, (int) config('versions.stop_wait_seconds', 60));
        $lastTouch = time();

        while (time() < $deadline) {
            sleep(3);

            if ((time() - $lastTouch) >= 15) {
                $record->touch();
                $lastTouch = time();
            }

            try {
                $current = strtolower((string) ($this->serverRepo->getDetails()['state'] ?? 'offline'));

                if (in_array($current, ['offline', 'exited'], true)) {
                    $record->appendLog('  Server stopped cleanly.');

                    return true;
                }
            } catch (Throwable $e) {
                Log::debug('[Versions] stop poll error: ' . $e->getMessage());
            }
        }

        if ($abort) {
            throw new RuntimeException('The server did not stop in time. No files were changed.');
        }

        $record->appendLog('  Shutdown wait timeout reached. Proceeding with file modifications.');

        return true;
    }

    private function stepDownload(VersionChange $record): array
    {
        $server    = $record->server;
        $targetJar = $this->getTargetServerJarFilename($server);
        $isZip     = SoftwareParser::isZipDownloadUrl((string) $record->jar_url);
        $filename  = $isZip ? 'server.versions-new.zip' : $targetJar . '.versions-new';

        $record->appendLog('Step 2/6: Telling Wings daemon to pull the new files...');
        $record->appendLog("  Target: {$record->software} {$record->minecraft_version} ({$record->build_name}) -> {$filename}");

        $this->deleteQuietly([$filename]);

        try {
            $this->fileRepo->pull($record->jar_url, '/', [
                'filename'   => $filename,
                'foreground' => false,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to send download command to Wings: {$e->getMessage()}", 0, $e);
        }

        $startTime  = time();
        $lastTouch  = $startTime;
        $startGrace = max(15, (int) config('versions.download_start_timeout', 90));
        $deadline   = $startTime + (int) config('versions.download_timeout', 600);
        $lastSize   = -1;
        $stable     = 0;
        $size       = null;
        $complete   = false;
        $announced  = false;

        while (time() < $deadline) {
            sleep(4);

            $now = time();
            if (($now - $lastTouch) >= 15) {
                $record->touch();
                $lastTouch = $now;
            }

            $size = $this->getRemoteFileSize($filename);

            if ($size === null && ($now - $startTime) >= $startGrace) {
                throw new RuntimeException("Download did not start on Wings within {$startGrace} seconds. Check the download URL and node connectivity.");
            }

            if ($size === null || $size <= 0) {
                if (!$announced) {
                    $record->appendLog('  Waiting for download to begin on node...');
                    $announced = true;
                }
                continue;
            }

            if ($record->jar_size && $size === (int) $record->jar_size) {
                $record->appendLog('  Download complete: ' . $this->humanBytes($size) . ' (100%)');
                $complete = true;
                break;
            }

            if ($size === $lastSize) {
                $stable++;
                if ($stable >= 3) {
                    $complete = true;
                    break;
                }
            } else {
                $stable = 0;
                $pct    = $record->jar_size ? ' (' . round(($size / $record->jar_size) * 100, 1) . '%)' : '';
                $record->appendLog('  Downloaded ' . $this->humanBytes($size) . $pct);
                $lastTouch = time();
            }

            $lastSize = $size;
        }

        if (!$complete) {
            $this->deleteQuietly([$filename]);

            throw new RuntimeException("Download timed out or {$filename} was not created by Wings.");
        }

        $record->appendLog('  Download completed successfully: ' . $this->humanBytes((int) $size));

        return [$filename, $isZip];
    }

    private function stepVerify(VersionChange $record, string $filename): void
    {
        $record->appendLog("Step 3/6: Verifying {$filename} integrity...");

        $actual = $this->getRemoteFileSize($filename);

        if ($actual === null || $actual < self::MIN_ARTIFACT_BYTES) {
            throw new RuntimeException("Verification failed: {$filename} is missing or too small (" . ($actual ?? 0) . ' bytes).');
        }

        if ($record->jar_size) {
            $tolerance = (int) max(1024, $record->jar_size * 0.01);

            if (abs($actual - $record->jar_size) > $tolerance) {
                throw new RuntimeException("Size mismatch: expected {$record->jar_size} bytes, got {$actual} bytes.");
            }
        }

        $record->appendLog('  File verified: ' . $this->humanBytes($actual));
    }

    private function stepSwap(VersionChange $record, string $temp, bool $isZip): void
    {
        $targetJar = $this->getTargetServerJarFilename($record->server);

        $record->appendLog('Step 4/6: Replacing server files (previous files are kept until the end)...');
        $record->appendLog("  Target server executable is: {$targetJar}");

        $entries   = $this->listRoot();
        $keepNames = array_unique(array_filter([$targetJar, 'server.jar', $temp]));

        foreach (array_unique(array_filter([$targetJar, 'server.jar'])) as $jar) {
            if (!$this->hasEntry($entries, $jar, false)) {
                continue;
            }

            $bak = $jar . '.bak';

            if ($this->hasEntry($entries, $bak, false)) {
                $this->fileRepo->deleteFiles('/', [$bak]);
            }

            $this->moveEntry($jar, $bak);
            $record->appendLog("  Moved existing {$jar} -> {$bak}.");

            $entries = $this->listRoot();
        }

        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if ($name === '' || !empty($entry['directory']) || !SoftwareParser::isTransientRootArtifact($name, $keepNames)) {
                continue;
            }

            $oldName = $name . '.versions-old';

            if ($this->hasEntry($entries, $oldName, false)) {
                $this->fileRepo->deleteFiles('/', [$oldName]);
            }

            $this->moveEntry($name, $oldName);
        }

        $entries = $this->listRoot();

        if (config('versions.clean_libraries', true) && $this->hasEntry($entries, 'libraries', true)) {
            if ($this->hasEntry($entries, self::LIBRARIES_OLD, true)) {
                $this->fileRepo->deleteFiles('/', [self::LIBRARIES_OLD]);
            }

            $this->moveEntry('libraries', self::LIBRARIES_OLD);
            $record->appendLog('  Moved old /libraries/ aside to prevent dependency collisions.');
        }

        if ($record->clean_install) {
            $this->wipeRoot($record, $temp, $targetJar);
        }

        if ($isZip) {
            $this->extractZip($record, $temp, $targetJar);

            return;
        }

        $this->fileRepo->renameFiles('/', [['from' => $temp, 'to' => $targetJar]]);
        $record->appendLog("  Installed new {$targetJar}.");
    }

    private function wipeRoot(VersionChange $record, string $temp, string $targetJar): void
    {
        $record->appendLog('  [CLEAN INSTALL] Wiping server root files...');

        $preserved = array_unique([$temp, $targetJar . '.bak', 'server.jar.bak', self::LIBRARIES_OLD, 'eula.txt']);
        $toDelete  = [];

        foreach ($this->listRoot() as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if ($name !== '' && !in_array($name, $preserved, true) && !str_ends_with($name, '.versions-old')) {
                $toDelete[] = $name;
            }
        }

        foreach (array_chunk($toDelete, 100) as $chunk) {
            $this->fileRepo->deleteFiles('/', $chunk);
        }

        $record->appendLog($toDelete === []
            ? '  [CLEAN INSTALL] Server root is already empty.'
            : '  [CLEAN INSTALL] Deleted ' . count($toDelete) . ' items from server root.');
    }

    private function extractZip(VersionChange $record, string $temp, string $targetJar): void
    {
        $record->appendLog("  Extracting {$temp} to root directory...");

        try {
            $this->fileRepo->decompressFile('/', $temp);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to decompress {$temp}: " . $e->getMessage(), 0, $e);
        }

        $record->appendLog('  Decompression finished successfully.');

        $this->deleteQuietly([$temp]);

        if ($targetJar !== 'server.jar') {
            $entries = $this->listRoot();

            if ($this->hasEntry($entries, 'server.jar', false)) {
                if ($this->hasEntry($entries, $targetJar, false)) {
                    $this->fileRepo->deleteFiles('/', [$targetJar]);
                }

                $this->fileRepo->renameFiles('/', [['from' => 'server.jar', 'to' => $targetJar]]);
                $record->appendLog("  Aligned extracted server.jar to configured {$targetJar}.");
            }
        }
    }

    private function moveEntry(string $from, string $to): void
    {
        $this->fileRepo->renameFiles('/', [['from' => $from, 'to' => $to]]);
        $this->undo[] = ['from' => $to, 'to' => $from];
    }

    private function rollback(VersionChange $record): bool
    {
        if ($this->undo === []) {
            return true;
        }

        $record->appendLog('Rolling back to the previous version...');

        try {
            foreach (array_reverse($this->undo) as $op) {
                $entries = $this->listRoot();

                if (!$this->hasEntry($entries, $op['from'])) {
                    continue;
                }

                if ($this->hasEntry($entries, $op['to'])) {
                    $this->fileRepo->deleteFiles('/', [$op['to']]);
                }

                $this->fileRepo->renameFiles('/', [['from' => $op['from'], 'to' => $op['to']]]);
            }

            $this->undo = [];
            $record->appendLog('  Previous files restored.');

            return true;
        } catch (Throwable $e) {
            $record->appendLog('  Rollback failed: ' . $e->getMessage() . '. Restore the .bak files manually.');
            Log::error('[Versions] Rollback failed: ' . $e->getMessage());

            return false;
        }
    }

    private function finalizeCleanup(VersionChange $record): void
    {
        $keep     = (bool) config('versions.keep_backup', true);
        $toDelete = [];

        foreach ($this->undo as $op) {
            if ($op['from'] === self::LIBRARIES_OLD || str_ends_with($op['from'], '.versions-old') || !$keep) {
                $toDelete[] = $op['from'];
            }
        }

        $this->undo = [];

        if ($toDelete !== []) {
            $this->deleteQuietly($toDelete);
            $record->appendLog('  Removed temporary backups.');
        }
    }

    private function stepHandleEula(VersionChange $record): void
    {
        $record->appendLog('Step 5/6: Verifying Minecraft EULA status...');

        try {
            $content = (string) $this->fileRepo->getContent('eula.txt', 2048);

            if (str_contains($content, 'eula=true')) {
                $record->appendLog('  Existing Minecraft EULA agreement detected and preserved.');

                return;
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] EULA check failed: ' . $e->getMessage());
        }

        $record->appendLog('  Notice: Minecraft EULA is handled via Pelican native prompt on startup.');
    }

    private function stepSyncEggVariable(VersionChange $record, Server $server): void
    {
        try {
            $match     = $this->findJarServerVariable($server);
            $serverVar = $match['model'] ?? null;

            if (!$serverVar) {
                return;
            }

            $envName    = $match['env'];
            $targetJar  = $this->getTargetServerJarFilename($server);
            $currentVal = trim((string) $serverVar->variable_value);

            if ($currentVal === $targetJar) {
                $record->appendLog("  {$envName} egg variable verified: \"{$targetJar}\".");

                return;
            }

            $serverVar->update(['variable_value' => $targetJar]);
            $record->appendLog("  Synchronized {$envName} egg variable to \"{$targetJar}\".");

            try {
                $this->serverRepo->sync();
                $record->appendLog('  Synced environment configuration with Wings node.');
            } catch (Throwable $e) {
                Log::debug('[Versions] sync environment failed: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] stepSyncEggVariable failed: ' . $e->getMessage());
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

    private function listRoot(): array
    {
        return (array) $this->fileRepo->getDirectory('/');
    }

    private function hasEntry(array $entries, string $name, ?bool $directory = null): bool
    {
        foreach ($entries as $entry) {
            if (($entry['name'] ?? '') !== $name) {
                continue;
            }

            if ($directory === null || (bool) ($entry['directory'] ?? false) === $directory) {
                return true;
            }
        }

        return false;
    }

    private function deleteQuietly(array $names): void
    {
        try {
            $entries = $this->listRoot();
            $present = array_values(array_filter($names, fn (string $n): bool => $this->hasEntry($entries, $n)));

            if ($present !== []) {
                $this->fileRepo->deleteFiles('/', $present);
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] deleteQuietly failed: ' . $e->getMessage());
        }
    }

    private function getRemoteFileSize(string $filename): ?int
    {
        try {
            foreach ($this->listRoot() as $entry) {
                if (($entry['name'] ?? '') === $filename && !($entry['directory'] ?? false)) {
                    return (int) ($entry['size'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] getRemoteFileSize failed: ' . $e->getMessage());
        }

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
