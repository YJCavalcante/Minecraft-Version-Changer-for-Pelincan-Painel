<?php

namespace Pelican\Versions\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Pelican\Versions\Models\VersionChange;
use Throwable;

class ServerSoftwareDetector
{
    private const LAUNCH_FILES = ['unix_args.txt', 'run.sh', 'startserver.sh', 'start.sh', 'user_jvm_args.txt'];

    private const PACK_FILES = ['.modpack-manager.json', 'variables.txt', 'manifest.json', 'modrinth.index.json'];

    private const WEAK_VERSIONS = ['', 'Minecraft', 'Latest', 'Default'];

    private const YML_MARKERS = [
        'purpur.yml' => ['Purpur', 'PURPUR_VERSION'],
        'folia.yml'  => ['Folia', 'FOLIA_VERSION'],
        'paper.yml'  => ['Paper', 'PAPER_VERSION'],
        'spigot.yml' => ['Spigot', null],
    ];

    private const EGG_JAR_NEEDLES = [
        'youer'     => 'Youer',
        'catserver' => 'CatServer',
        'ketting'   => 'Ketting',
        'banner'    => 'Banner',
        'mohist'    => 'Mohist',
        'purpur'    => 'Purpur',
        'magma'     => 'Magma',
        'arclight'  => 'Arclight',
        'folia'     => 'Folia',
        'paper'     => 'Paper',
        'fabric'    => 'Fabric',
        'neoforge'  => 'NeoForge',
        'forge'     => 'Forge',
    ];

    private const EGG_VERSION_VARS = [
        'YOUER_VERSION'     => ['Youer', false],
        'CATSERVER_VERSION' => ['CatServer', false],
        'KETTING_VERSION'   => ['Ketting', false],
        'BANNER_VERSION'    => ['Banner', false],
        'MOHIST_VERSION'    => ['Mohist', false],
        'PURPUR_VERSION'    => ['Purpur', false],
        'MAGMA_VERSION'     => ['Magma', false],
        'ARCLIGHT_VERSION'  => ['Arclight', false],
        'NEOFORGE_VERSION'  => ['NeoForge', true],
        'FORGE_VERSION'     => ['Forge', true],
        'FABRIC_VERSION'    => ['Fabric', true],
        'QUILT_VERSION'     => ['Quilt', true],
        'PAPER_VERSION'     => ['Paper', false],
    ];

    private const EGG_NAME_NEEDLES = [
        'youer'     => 'Youer',
        'catserver' => 'CatServer',
        'ketting'   => 'Ketting',
        'banner'    => 'Banner',
        'mohist'    => 'Mohist',
        'purpur'    => 'Purpur',
        'magma'     => 'Magma',
        'arclight'  => 'Arclight',
        'thermos'   => 'Thermos',
        'cauldron'  => 'Thermos',
        'crucible'  => 'Crucible',
        'paper'     => 'Paper',
        'spigot'    => 'Spigot',
        'neoforge'  => 'NeoForge',
        'forge'     => 'Forge',
        'fabric'    => 'Fabric',
        'bungee'    => 'BungeeCord',
        'velocity'  => 'Velocity',
        'vanilla'   => 'Vanilla',
    ];

    public function detect(Server $server): ?array
    {
        $variables  = $this->variables($server);
        $lastChange = VersionChange::query()
            ->where('server_id', $server->id)
            ->where('status', VersionChange::STATUS_DONE)
            ->latest('id')
            ->first();

        $repo  = null;
        $files = [];

        try {
            $repo  = $this->repo($server);
            $files = $this->listRoot($repo);
        } catch (Throwable $e) {
            Log::debug('[Versions] root listing error: ' . $e->getMessage());
        }

        $modpack   = $this->modpack($server, $repo, $files);
        $changeTs  = $lastChange?->updated_at?->getTimestamp();
        $modpackTs = $modpack['installed_at'];

        if ($lastChange && $modpack['name'] !== null && ($modpackTs === null || ($changeTs !== null && $modpackTs > $changeTs))) {
            $lastChange = null;
            $changeTs   = null;
        } elseif ($lastChange && $changeTs !== null && $modpackTs !== null && $changeTs > ($modpackTs + 60) && ($lastChange->clean_install || !isset($files['mods']))) {
            $modpack   = ['name' => null, 'installed_at' => null];
            $modpackTs = null;
        }

        $baselineTs    = max((int) ($changeTs ?? 0), (int) ($modpackTs ?? 0)) ?: null;
        $zipInstall    = $lastChange !== null && SoftwareParser::isZipDownloadUrl((string) $lastChange->jar_url);
        $modpackActive = $modpack['name'] !== null && $lastChange === null;
        $newestCodeTs  = $this->newestCodeTimestamp($files);

        $result = null;
        $disk   = $repo !== null
            ? $this->detectFromDisk($repo, $files, $variables, $baselineTs, $changeTs, $zipInstall, $modpackActive, $lastChange, $newestCodeTs)
            : null;

        if ($disk) {
            $result = $this->mergeWithHistory($disk, $lastChange, $newestCodeTs);
        } elseif ($lastChange) {
            $result = $this->fromHistory($lastChange, $server, $variables);
        }

        if (!$result) {
            $result = $this->detectFromEgg($server, $variables);
        }

        if (!$result && $modpack['name']) {
            $result = [
                'software'     => 'Minecraft',
                'version'      => SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null) ?? 'Custom',
                'build'        => null,
                'installed_at' => null,
                'source'       => 'modpack',
                'icon_hint'    => 'curseforge',
            ];
        }

        if ($result && $modpack['name']) {
            $result['modpack'] = $modpack['name'];
        }

        return $result;
    }

    public static function displayName(string $software): string
    {
        foreach (array_keys(SoftwareParser::HYBRIDS) as $name) {
            if (strcasecmp($name, $software) === 0) {
                return $name;
            }
        }

        return SoftwareParser::normalizeLoader($software);
    }

    private function variables(Server $server): array
    {
        try {
            return $server->serverVariables()
                ->with('variable')
                ->get()
                ->pluck('variable_value', 'variable.env_variable')
                ->toArray();
        } catch (Throwable $e) {
            Log::debug('[Versions] variables error: ' . $e->getMessage());

            return [];
        }
    }

    private function modpack(Server $server, ?DaemonFileRepository $repo, array $files): array
    {
        $name = null;
        $at   = null;

        try {
            if (Schema::hasTable('modpack_installs')) {
                $row = DB::table('modpack_installs')
                    ->where('server_id', $server->id)
                    ->where('status', 'installed')
                    ->latest('updated_at')
                    ->first();

                if ($row) {
                    $name = !empty($row->modpack_name) ? (string) $row->modpack_name : null;
                    $at   = !empty($row->updated_at) ? (strtotime((string) $row->updated_at) ?: null) : null;
                }
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] modpack db error: ' . $e->getMessage());
        }

        if ($repo !== null && isset($files['.modpack-manager.json'])) {
            $fileAt = $files['.modpack-manager.json']['modified'] ?? null;

            if ($fileAt !== null && ($at === null || $fileAt > $at)) {
                $at = $fileAt;
            }

            if ($name === null) {
                try {
                    $meta = json_decode($this->read($repo, '/.modpack-manager.json', 32 * 1024), true);

                    if (is_array($meta)) {
                        if (!empty($meta['name'])) {
                            $name = (string) $meta['name'];
                        }

                        $metaAt = $meta['installed_at'] ?? ($meta['updated_at'] ?? ($meta['installedAt'] ?? null));
                        $parsed = $metaAt ? (is_numeric($metaAt) ? (int) $metaAt : (strtotime((string) $metaAt) ?: null)) : null;

                        if ($parsed !== null && ($at === null || $parsed > $at)) {
                            $at = $parsed;
                        }
                    }
                } catch (Throwable $e) {
                    Log::debug('[Versions] modpack meta error: ' . $e->getMessage());
                }
            }
        }

        if ($name !== null && $at === null) {
            foreach (self::PACK_FILES as $packFile) {
                $mod = $files[$packFile]['modified'] ?? null;
                if ($mod !== null && ($at === null || $mod > $at)) {
                    $at = $mod;
                }
            }
        }

        return ['name' => $name, 'installed_at' => $at];
    }

    private function mergeWithHistory(array $disk, ?VersionChange $lastChange, ?int $newestDiskTs): array
    {
        $disk['version'] = $disk['version'] ?? 'Minecraft';

        if (!$lastChange || strtolower($lastChange->software) !== strtolower($disk['software'])) {
            return $disk;
        }

        $weakVersion      = in_array((string) $disk['version'], self::WEAK_VERSIONS, true);
        $historyBuild     = SoftwareParser::formatHistoryBuild($lastChange->minecraft_version, $lastChange->build_name, $lastChange->build_number);
        $diskBuild        = $disk['build'] ?? null;
        $changeTs         = $lastChange->updated_at?->getTimestamp();
        $historyIsCurrent = $changeTs !== null && ($newestDiskTs === null || $changeTs >= ($newestDiskTs - 120));

        if ($historyIsCurrent) {
            if (!empty($lastChange->minecraft_version)) {
                $disk['version'] = $lastChange->minecraft_version;
            }

            $disk['build'] = !empty($historyBuild) ? $historyBuild : $diskBuild;
        } else {
            if ($weakVersion && !empty($lastChange->minecraft_version)) {
                $disk['version'] = $lastChange->minecraft_version;
            }

            $disk['build'] = !empty($diskBuild) ? $diskBuild : $historyBuild;
        }

        $disk['installed_at'] = $lastChange->updated_at?->diffForHumans();
        $disk['source']       = (!empty($diskBuild) || !$weakVersion) ? 'disk' : 'history';

        return $disk;
    }

    private function fromHistory(VersionChange $lastChange, Server $server, array $variables): ?array
    {
        $eggName      = strtolower($server->egg?->name ?? '');
        $lastSoftware = strtolower($lastChange->software);

        $contradicted = ($lastSoftware === 'vanilla' && (
            str_contains($eggName, 'forge')
            || str_contains($eggName, 'fabric')
            || str_contains($eggName, 'paper')
            || !empty($variables['NEOFORGE_VERSION'])
            || !empty($variables['FORGE_VERSION'])
            || !empty($variables['FABRIC_VERSION'])
        )) || ($lastSoftware === 'forge' && (
            str_contains($eggName, 'neoforge')
            || !empty($variables['NEOFORGE_VERSION'])
        ));

        if ($contradicted) {
            return null;
        }

        return [
            'software'     => self::displayName($lastChange->software),
            'version'      => $lastChange->minecraft_version,
            'build'        => SoftwareParser::formatHistoryBuild($lastChange->minecraft_version, $lastChange->build_name, $lastChange->build_number),
            'installed_at' => $lastChange->updated_at?->diffForHumans(),
            'source'       => 'history',
        ];
    }

    private function detectFromDisk(
        DaemonFileRepository $repo,
        array $files,
        array $variables,
        ?int $baselineTs,
        ?int $changeTs,
        bool $zipInstall,
        bool $modpackActive,
        ?VersionChange $lastChange,
        ?int $newestCodeTs
    ): ?array {
        try {
            $isStaleConfig = function (string $key) use ($files, $baselineTs, $newestCodeTs): bool {
                if (!isset($files[$key])) {
                    return false;
                }

                $fileTs = $files[$key]['modified'];

                if ($fileTs === null) {
                    return false;
                }

                if ($baselineTs && $baselineTs > ($fileTs + 60)) {
                    return true;
                }

                return !$baselineTs && $newestCodeTs !== null && ($newestCodeTs - $fileTs) > 120;
            };

            $isStalePack = function (string $key) use ($files, $changeTs, $modpackActive): bool {
                if ($modpackActive) {
                    return false;
                }

                if (!$changeTs || !isset($files[$key])) {
                    return false;
                }

                $fileTs = $files[$key]['modified'];

                return $fileTs !== null && $changeTs > ($fileTs + 60);
            };

            $isStaleFile = function (string $key) use ($files, $baselineTs, $zipInstall, $modpackActive): bool {
                if ($zipInstall || $modpackActive || !$baselineTs || !isset($files[$key])) {
                    return false;
                }

                $fileTs = $files[$key]['modified'];

                return $fileTs !== null && $baselineTs > ($fileTs + 60);
            };

            if ($modpackActive) {
                $fromPack = $this->detectFromPackFiles($repo, $files, $variables, $isStalePack);
                if ($fromPack) {
                    return $fromPack;
                }
            }

            $launch = '';
            foreach (self::LAUNCH_FILES as $launchFile) {
                if (isset($files[$launchFile]) && !$files[$launchFile]['isDir'] && !$isStaleFile($launchFile)) {
                    $launch .= PHP_EOL . $this->read($repo, $files[$launchFile]['name'], 16384);
                }
            }

            if (trim($launch) !== '') {
                $launchHybrid = SoftwareParser::matchHybridMarker($launch);
                $launchLoader = SoftwareParser::parseLaunchContent($launch, $variables);

                if ($launchHybrid) {
                    $under = $launchLoader ?? $this->detectFromLibraries($repo, $files, $variables, $lastChange);

                    return SoftwareParser::result(
                        $launchHybrid,
                        $under['version'] ?? SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null) ?? 'Latest',
                        $under['build'] ?? null
                    );
                }

                if ($launchLoader) {
                    $under = $this->detectFromLibraries($repo, $files, $variables, $lastChange);

                    if ($under && isset(SoftwareParser::HYBRIDS[$under['software']])) {
                        return $under;
                    }

                    return $launchLoader;
                }
            }

            if (!$modpackActive) {
                $fromPack = $this->detectFromPackFiles($repo, $files, $variables, $isStalePack);
                if ($fromPack) {
                    return $fromPack;
                }
            }

            $sortedFiles = $files;
            uasort($sortedFiles, fn (array $a, array $b): int => ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0));

            foreach ($sortedFiles as $key => $meta) {
                if ($meta['isDir'] || !str_ends_with($key, '.jar') || $isStaleFile($key)) {
                    continue;
                }

                $hybridJar = SoftwareParser::hybridFromJarName($meta['name']);
                if ($hybridJar) {
                    $under   = $this->detectFromLibraries($repo, $files, $variables, $lastChange);
                    $mc      = SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null);
                    $own     = SoftwareParser::cleanMc($variables[strtoupper($hybridJar['software']) . '_VERSION'] ?? null);
                    $version = $hybridJar['version'] ?? $under['version'] ?? $own ?? $mc ?? 'Latest';
                    $build   = $hybridJar['build'] ?? $under['build'] ?? null;

                    return SoftwareParser::result($hybridJar['software'], $version, $build);
                }

                $byName = SoftwareParser::parseJarName($meta['name'], $variables);
                if ($byName) {
                    return $byName;
                }
            }

            $mc = SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null) ?? 'Minecraft';

            if ((isset($files['fabric-server-launch.jar']) && !$isStaleFile('fabric-server-launch.jar'))
                || (isset($files['fabric-server-launcher.properties']) && !$isStaleFile('fabric-server-launcher.properties'))) {
                $loader = $variables['FABRIC_VERSION'] ?? null;

                return SoftwareParser::result('Fabric', $mc, $loader ? '#loader-' . $loader : null);
            }

            if ((isset($files['quilt-server-launch.jar']) && !$isStaleFile('quilt-server-launch.jar'))
                || (isset($files['quilt-server-launcher.properties']) && !$isStaleFile('quilt-server-launcher.properties'))) {
                return SoftwareParser::result('Quilt', $mc, !empty($variables['QUILT_VERSION']) ? '#' . $variables['QUILT_VERSION'] : null);
            }

            $fromLibraries = $this->detectFromLibraries($repo, $files, $variables, $lastChange);

            if ($fromLibraries && isset(SoftwareParser::HYBRIDS[$fromLibraries['software']])) {
                return $fromLibraries;
            }

            if (!$modpackActive) {
                $hybridConfig = $this->detectHybridFromConfig($sortedFiles, $isStaleConfig, $lastChange);

                if ($hybridConfig !== null) {
                    $mc      = SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null);
                    $own     = SoftwareParser::cleanMc($variables[strtoupper($hybridConfig) . '_VERSION'] ?? null);
                    $version = $fromLibraries['version'] ?? $own ?? $mc ?? 'Latest';
                    $build   = $fromLibraries['build'] ?? ($lastChange === null && !empty($variables['BUILD_NUMBER']) ? '#' . $variables['BUILD_NUMBER'] : null);

                    return SoftwareParser::result($hybridConfig, $version, $build);
                }
            }

            if ($fromLibraries) {
                return $fromLibraries;
            }

            foreach (self::YML_MARKERS as $ymlFile => [$name, $versionVar]) {
                if (!isset($files[$ymlFile]) || $isStaleConfig($ymlFile)) {
                    continue;
                }

                if ($lastChange !== null && strcasecmp($lastChange->software, $name) !== 0) {
                    continue;
                }

                $version = SoftwareParser::cleanMc($versionVar ? ($variables[$versionVar] ?? null) : null)
                    ?? SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null)
                    ?? 'Latest';

                $build = $lastChange === null && !empty($variables['BUILD_NUMBER']) ? '#' . $variables['BUILD_NUMBER'] : null;

                return SoftwareParser::result($name, $version, $build);
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] detectFromDisk error: ' . $e->getMessage());
        }

        return null;
    }

    private function detectFromPackFiles(DaemonFileRepository $repo, array $files, array $variables, callable $isStalePack): ?array
    {
        return $this->fromVariablesFile($repo, $files, $variables, $isStalePack)
            ?? $this->fromManifest($repo, $files, $variables, $isStalePack)
            ?? $this->fromModrinth($repo, $files, $variables, $isStalePack);
    }

    private function detectHybridFromConfig(array $files, callable $isStaleConfig, ?VersionChange $lastChange): ?string
    {
        foreach ($files as $key => $meta) {
            if ($meta['isDir'] || $isStaleConfig($key)) {
                continue;
            }

            $name = SoftwareParser::hybridFromConfigName($key);
            if ($name === null) {
                continue;
            }

            if ($lastChange !== null && strcasecmp($lastChange->software, $name) !== 0) {
                continue;
            }

            return $name;
        }

        return null;
    }

    private function detectFromLibraries(DaemonFileRepository $repo, array $files, array $variables, ?VersionChange $lastChange): ?array
    {
        if (!isset($files['libraries']) || !$files['libraries']['isDir']) {
            return null;
        }

        try {
            $net   = array_map('strtolower', $this->listNames($repo, '/libraries/net'));
            $mc    = SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null);
            $isNeo = in_array('neoforged', $net, true);

            if ($isNeo) {
                foreach ($this->listNames($repo, '/libraries/net/neoforged') as $sub) {
                    if (!in_array(strtolower($sub), ['neoforge', 'forge'], true)) {
                        continue;
                    }

                    $dirPath = '/libraries/net/neoforged/' . $sub;
                    $best    = $this->pickVersionDir($repo, $dirPath, $lastChange);

                    if ($best) {
                        $parsed  = SoftwareParser::parseNeoForgeVersion($best);
                        $libArgs = $this->read($repo, $dirPath . '/' . $best . '/unix_args.txt', 16384);
                        $hybrid  = $libArgs !== '' ? SoftwareParser::matchHybridMarker($libArgs) : null;

                        return SoftwareParser::result($hybrid ?? 'NeoForge', $parsed['version'] ?? $mc ?? 'Minecraft', $parsed['build']);
                    }
                }
            }

            if (in_array('minecraftforge', $net, true)) {
                $dirPath = '/libraries/net/minecraftforge/forge';
                $best    = $this->pickVersionDir($repo, $dirPath, $lastChange);

                if ($best) {
                    $parsed  = SoftwareParser::parseForgeVersion($best);
                    $libArgs = $this->read($repo, $dirPath . '/' . $best . '/unix_args.txt', 16384);
                    $hybrid  = $libArgs !== '' ? SoftwareParser::matchHybridMarker($libArgs) : null;

                    return SoftwareParser::result($hybrid ?? 'Forge', $parsed['version'] ?? $mc ?? 'Minecraft', $parsed['build'] ?? '#' . $best);
                }
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] libraries scan error: ' . $e->getMessage());
        }

        return null;
    }

    private function pickVersionDir(DaemonFileRepository $repo, string $path, ?VersionChange $lastChange): ?string
    {
        $entries = [];
        $maxMod  = null;

        foreach ((array) $repo->getDirectory($path) as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if ($name === '' || !preg_match('~^[0-9]~', $name)) {
                continue;
            }

            $raw = $entry['modified'] ?? ($entry['modified_at'] ?? null);
            $mod = $raw ? (strtotime((string) $raw) ?: null) : null;

            if ($mod !== null && ($maxMod === null || $mod > $maxMod)) {
                $maxMod = $mod;
            }

            $entries[$name] = $mod;
        }

        if ($entries === []) {
            return null;
        }

        if ($lastChange !== null) {
            $wantedBuild = ltrim((string) ($lastChange->build_name ?: $lastChange->build_number), '#');

            if ($wantedBuild !== '') {
                foreach (array_keys($entries) as $dirName) {
                    if ($dirName === $wantedBuild || str_ends_with($dirName, '-' . $wantedBuild)) {
                        return $dirName;
                    }
                }
            }
        }

        $candidates = array_keys($entries);

        if ($maxMod !== null) {
            $recent = array_keys(array_filter($entries, fn (?int $mod): bool => $mod !== null && $mod >= ($maxMod - 10)));
            if ($recent !== []) {
                $candidates = $recent;
            }
        }

        return SoftwareParser::highestVersion($candidates);
    }

    private function newestCodeTimestamp(array $files): ?int
    {
        $max = null;

        foreach ($files as $key => $meta) {
            if ($meta['isDir'] ?? false) {
                continue;
            }

            $isCode = in_array($key, self::LAUNCH_FILES, true)
                || in_array($key, self::PACK_FILES, true)
                || str_ends_with($key, '.jar');

            if (!$isCode) {
                continue;
            }

            $mod = $meta['modified'] ?? null;
            if ($mod !== null && ($max === null || $mod > $max)) {
                $max = $mod;
            }
        }

        return $max;
    }

    private function fromVariablesFile(DaemonFileRepository $repo, array $files, array $variables, callable $isStale): ?array
    {
        if (!isset($files['variables.txt']) || $isStale('variables.txt')) {
            return null;
        }

        $vars = [];
        foreach (explode(PHP_EOL, $this->read($repo, 'variables.txt', 8192)) as $line) {
            $parts = explode('=', trim($line), 2);
            if (count($parts) === 2) {
                $vars[trim($parts[0])] = trim($parts[1]);
            }
        }

        if (empty($vars['MODLOADER'])) {
            return null;
        }

        $mc = SoftwareParser::cleanMc($vars['MINECRAFT_VERSION'] ?? null) ?? SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null) ?? 'Minecraft';

        return SoftwareParser::result(
            SoftwareParser::normalizeLoader($vars['MODLOADER']),
            $mc,
            !empty($vars['MODLOADER_VERSION']) ? '#' . $vars['MODLOADER_VERSION'] : null
        );
    }

    private function fromManifest(DaemonFileRepository $repo, array $files, array $variables, callable $isStale): ?array
    {
        if (!isset($files['manifest.json']) || $isStale('manifest.json')) {
            return null;
        }

        $json = json_decode($this->read($repo, 'manifest.json', 8192), true);
        $id   = is_array($json) ? ($json['minecraft']['modLoaders'][0]['id'] ?? null) : null;

        if (!$id) {
            return null;
        }

        $parsed = SoftwareParser::parseModLoaderId((string) $id);
        if (!$parsed) {
            return null;
        }

        $mc = SoftwareParser::cleanMc($json['minecraft']['version'] ?? null) ?? SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null) ?? 'Minecraft';

        return SoftwareParser::result($parsed['software'], $mc, $parsed['build']);
    }

    private function fromModrinth(DaemonFileRepository $repo, array $files, array $variables, callable $isStale): ?array
    {
        if (!isset($files['modrinth.index.json']) || $isStale('modrinth.index.json')) {
            return null;
        }

        $json = json_decode($this->read($repo, 'modrinth.index.json', 8192), true);
        $deps = is_array($json) ? ($json['dependencies'] ?? []) : [];

        if (!is_array($deps) || $deps === []) {
            return null;
        }

        $mc = SoftwareParser::cleanMc($deps['minecraft'] ?? null) ?? SoftwareParser::cleanMc($variables['MINECRAFT_VERSION'] ?? null) ?? 'Minecraft';

        foreach (['neoforge', 'forge', 'fabric-loader', 'quilt-loader'] as $key) {
            if (!empty($deps[$key])) {
                return SoftwareParser::result(SoftwareParser::normalizeLoader($key), $mc, '#' . $deps[$key]);
            }
        }

        return null;
    }

    private function detectFromEgg(Server $server, array $variables): ?array
    {
        try {
            $eggName = strtolower((string) ($server->egg?->name ?? ''));
            $filled  = fn (string $key): ?string => (isset($variables[$key]) && trim((string) $variables[$key]) !== '') ? trim((string) $variables[$key]) : null;

            $version = $filled('MINECRAFT_VERSION') ?? $filled('VANILLA_VERSION') ?? $filled('PAPER_VERSION') ?? $filled('PURPUR_VERSION') ?? $filled('VERSION');
            $build   = null;
            $name    = null;
            $jar     = strtolower((string) ($filled('SERVER_JARFILE') ?? $filled('JARFILE') ?? $filled('JAR_NAME') ?? $filled('SERVER_JAR')));

            foreach (self::EGG_JAR_NEEDLES as $needle => $label) {
                if ($jar !== '' && str_contains($jar, $needle)) {
                    $name    = $label;
                    $version = $filled(strtoupper($needle) . '_VERSION') ?? $version;
                    break;
                }
            }

            if (!$name) {
                foreach (self::EGG_VERSION_VARS as $var => [$label, $isBuild]) {
                    if ($filled($var) !== null) {
                        $name = $label;
                        if ($isBuild) {
                            $build = '#' . $filled($var);
                        } else {
                            $version = $filled($var);
                        }
                        break;
                    }
                }
            }

            if (!$name) {
                foreach (self::EGG_NAME_NEEDLES as $needle => $label) {
                    if (str_contains($eggName, $needle)) {
                        $name = $label;
                        break;
                    }
                }
            }

            if (!$name && $eggName !== '') {
                $name = (string) $server->egg->name;
            }

            if (!$name && !$version) {
                return null;
            }

            $build ??= $filled('BUILD_NUMBER') !== null ? '#' . $filled('BUILD_NUMBER') : null;

            return [
                'software'     => $name ?: 'Minecraft',
                'version'      => ($version && strtolower($version) !== 'latest') ? $version : ($version ? 'Latest' : 'Default'),
                'build'        => $build,
                'installed_at' => null,
                'source'       => 'egg',
            ];
        } catch (Throwable $e) {
            Log::debug('[Versions] detectFromEgg error: ' . $e->getMessage());
        }

        return null;
    }

    private function repo(Server $server): DaemonFileRepository
    {
        $repo = app(DaemonFileRepository::class);
        $repo->setServer($server);

        return $repo;
    }

    private function listRoot(DaemonFileRepository $repo): array
    {
        $files = [];

        foreach ((array) $repo->getDirectory('/') as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $raw = $entry['modified'] ?? ($entry['modified_at'] ?? null);

            $files[strtolower($name)] = [
                'name'     => $name,
                'isDir'    => (bool) ($entry['directory'] ?? false),
                'modified' => $raw ? (strtotime((string) $raw) ?: null) : null,
            ];
        }

        return $files;
    }

    private function listNames(DaemonFileRepository $repo, string $path): array
    {
        $names = [];

        foreach ((array) $repo->getDirectory($path) as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function read(DaemonFileRepository $repo, string $path, int $limit): string
    {
        try {
            return (string) $repo->getContent($path, $limit);
        } catch (Throwable) {
            return '';
        }
    }
}
