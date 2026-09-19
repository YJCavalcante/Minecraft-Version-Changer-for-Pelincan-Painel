<?php

namespace Pelican\Versions\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Pelican\Versions\Jobs\ChangeVersionJob;
use Pelican\Versions\Models\VersionChange;
use Pelican\Versions\Services\McJarsService;
use Throwable;

class VersionsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-arrows-exchange';
    protected static ?string $navigationLabel = 'Version Changer';
    protected static ?string $slug            = 'versions';
    protected static ?int    $navigationSort  = 8;

    protected string $view = 'versions::filament.server.pages.versions-page';

    public static function getNavigationSort(): ?int
    {
        return (int) config('versions.navigation_sort', static::$navigationSort);
    }

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        if (!parent::canAccess() || !$server instanceof Server) {
            return false;
        }

        $tags = array_map('strtolower', (array) ($server->egg?->tags ?? []));
        $eggName = strtolower($server->egg?->name ?? '');
        $startup = strtolower($server->startup ?? '');

        $isMinecraft = in_array('minecraft', $tags, true)
            || str_contains($eggName, 'minecraft')
            || str_contains($eggName, 'paper')
            || str_contains($eggName, 'purpur')
            || str_contains($eggName, 'forge')
            || str_contains($eggName, 'fabric')
            || str_contains($eggName, 'spigot')
            || str_contains($eggName, 'bungee')
            || str_contains($eggName, 'velocity')
            || str_contains($startup, 'server.jar')
            || str_contains($startup, 'minecraft');

        if (!$isMinecraft) {
            return false;
        }

        $user = user();
        if (!$user) {
            return false;
        }

        if ($user->isRootAdmin() || $user->isAdmin() || $server->owner_id === $user->id) {
            return true;
        }

        return $user->can('versions.change', $server)
            || $user->can('settings.reinstall', $server);
    }

    public function getServer(): Server
    {
        $server = Filament::getTenant();
        return $server;
    }

    public string $search           = '';
    public string $selectedCategory = 'recommended';
    public array  $types            = [];
    public ?string $selectedSoftware = null;
    public ?array $softwareDetails  = null;

    public array   $availableVersions   = [];
    public string  $versionSearch       = '';
    public ?string $selectedVersion     = null;
    public ?array  $versionDetails      = null;
    public array   $availableBuilds     = [];
    public string|int|null $selectedBuildNumber = 'latest';
    public ?array $selectedBuild       = null;

    public bool $isLoadingVersions = false;
    public bool $isLoadingBuilds   = false;

    public string $containerStatus = 'offline';

    public bool   $isChanging   = false;
    public int    $changeId     = 0;
    public string $changeStatus = '';
    public string $changeLog    = '';
    public string $changeError  = '';

    public bool   $showInstallModal = false;
    public bool   $showConfirmModal = false;
    public bool   $showLogModal     = false;
    public ?array $viewingRecord    = null;

    public bool   $keepBackup    = true;
    public bool   $cleanInstall  = false;

    public ?array $javaWarning   = null;

    public array  $recentChanges      = [];
    public ?array $currentVersionInfo = null;

    public function mount(?McJarsService $mcJarsService = null, ?DaemonServerRepository $serverRepo = null): void
    {
        $mcJarsService = $mcJarsService ?? app(McJarsService::class);
        $serverRepo    = $serverRepo ?? app(DaemonServerRepository::class);

        try {
            $details = $serverRepo->setServer($this->getServer())->getDetails();
            $this->containerStatus = (string) ($details['state'] ?? 'offline');
        } catch (Throwable $e) {
            \Log::debug('[Versions] mount getDetails error: ' . $e->getMessage());
            $this->containerStatus = 'offline';
        }

        $this->types = $mcJarsService->getTypes();

        $activeChange = VersionChange::query()
            ->where('server_id', $this->getServer()->id)
            ->whereIn('status', [VersionChange::STATUS_PENDING, VersionChange::STATUS_CHANGING])
            ->latest()
            ->first();

        if ($activeChange) {
            $this->isChanging   = true;
            $this->changeId     = $activeChange->id;
            $this->changeStatus = $activeChange->status;
            $this->changeLog    = $activeChange->log ?? '';
            $this->changeError  = $activeChange->error_message ?? '';
        }

        $this->loadHistory();
        $this->loadCurrentVersion();
    }

    public function selectCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function openSoftwareModal(string $softwareKey): void
    {
        $this->selectSoftware($softwareKey, openModal: true);
    }

    public function closeInstallModal(): void
    {
        $this->showInstallModal  = false;
        $this->availableVersions = [];
        $this->availableBuilds   = [];
        $this->selectedBuild     = null;
        $this->versionSearch     = '';
    }

    public function selectSoftware(string $softwareKey, bool $openModal = true, ?McJarsService $mcJarsService = null): void
    {
        $key = strtoupper(trim($softwareKey));
        $mcJarsService = $mcJarsService ?? app(McJarsService::class);

        if ($this->selectedSoftware === $key && !empty($this->availableVersions)) {
            if ($openModal) {
                $this->showInstallModal = true;
            }
            return;
        }

        $this->selectedSoftware = $key;
        $this->versionSearch    = '';

        $this->softwareDetails = null;
        foreach ($this->types as $group) {
            if (isset($group[$this->selectedSoftware])) {
                $this->softwareDetails = $group[$this->selectedSoftware];
                break;
            }
        }

        if (!$this->softwareDetails) {
            $this->softwareDetails = [
                'name'        => ucfirst(strtolower($this->selectedSoftware)),
                'description' => 'Minecraft server software.',
                'icon'        => null,
            ];
        }

        $this->isLoadingVersions = true;
        try {
            $this->availableVersions = $mcJarsService->getVersions($this->selectedSoftware);
        } catch (Throwable $e) {
            $this->availableVersions = [];
        }
        $this->isLoadingVersions = false;

        if (!empty($this->availableVersions)) {
            $firstVersion = array_key_first($this->availableVersions);
            $this->selectVersion($firstVersion, $mcJarsService);
        } else {
            $this->selectedVersion = null;
            $this->versionDetails  = null;
            $this->availableBuilds = [];
            $this->selectedBuild   = null;
        }

        if ($openModal) {
            $this->showInstallModal = true;
        }
    }

    public function selectVersion(string $version, ?McJarsService $mcJarsService = null): void
    {
        if (empty($version)) {
            return;
        }

        $mcJarsService = $mcJarsService ?? app(McJarsService::class);
        $this->selectedVersion = $version;
        $this->versionDetails  = $this->availableVersions[$version] ?? null;

        $this->isLoadingBuilds = true;
        try {
            $this->availableBuilds = $mcJarsService->getBuilds($this->selectedSoftware, $version);
        } catch (Throwable $e) {
            $this->availableBuilds = [];
        }
        $this->isLoadingBuilds = false;

        $this->selectBuild('latest');

        $this->checkJavaCompatibility($version);
    }

    public function selectBuild(string|int|null $buildNumber): void
    {
        $this->selectedBuildNumber = $buildNumber !== null ? (string) $buildNumber : 'latest';

        if (empty($this->availableBuilds)) {
            $this->selectedBuild = $this->versionDetails['latest'] ?? null;
            return;
        }

        if ($buildNumber === null || $buildNumber === '' || $buildNumber === '0' || $buildNumber === 0 || $buildNumber === 'latest') {
            $this->selectedBuild = $this->availableBuilds[0];
            $this->selectedBuildNumber = 'latest';
            return;
        }

        $specialTypes = ['FABRIC', 'FORGE', 'NEOFORGE', 'SPONGE', 'LEGACYFABRIC', 'QUILT'];
        $isSpecial = in_array(strtoupper($this->selectedSoftware ?? ''), $specialTypes, true);

        $found = collect($this->availableBuilds)->first(function ($b) use ($buildNumber, $isSpecial) {
            if ($isSpecial && isset($b['name']) && (string) $b['name'] === (string) $buildNumber) {
                return true;
            }
            if (isset($b['buildNumber']) && (string) $b['buildNumber'] === (string) $buildNumber) {
                return true;
            }
            return (string) ($b['name'] ?? '') === (string) $buildNumber;
        });

        $this->selectedBuild = $found ?? $this->availableBuilds[0];
    }

    public function canManageVersions(): bool
    {
        $server = $this->getServer();
        $user = user();

        if (!$user) {
            return false;
        }

        if ($user->isRootAdmin() || $user->isAdmin() || $server->owner_id === $user->id) {
            return true;
        }

        return $user->can('versions.change', $server)
            || $user->can('settings.reinstall', $server);
    }

    public function openConfirmModal(?DaemonServerRepository $serverRepo = null): void
    {
        if (!$this->canManageVersions()) {
            Notification::make()->title('Unauthorized')->danger()->send();
            return;
        }

        if (empty($this->selectedSoftware) || empty($this->selectedVersion)) {
            Notification::make()->title('Please select a software and version first.')->warning()->send();
            return;
        }

        try {
            $serverRepo = $serverRepo ?? app(DaemonServerRepository::class);
            $details = $serverRepo->setServer($this->getServer())->getDetails();
            $this->containerStatus = (string) ($details['state'] ?? 'offline');
        } catch (Throwable $e) {
            \Log::debug('[Versions] openConfirmModal getDetails error: ' . $e->getMessage());
        }

        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
    }

    public function startVersionChange(?McJarsService $mcJarsService = null): void
    {
        $mcJarsService = $mcJarsService ?? app(McJarsService::class);

        if (!$this->canManageVersions()) {
            Notification::make()->title('Unauthorized action.')->danger()->send();
            return;
        }

        if ($this->isChanging) {
            Notification::make()->title('A version change is already in progress.')->warning()->send();
            return;
        }

        if (empty($this->selectedBuild) && !empty($this->selectedSoftware) && !empty($this->selectedVersion)) {
            $builds = $mcJarsService->getBuilds($this->selectedSoftware, $this->selectedVersion);
            if (!empty($builds)) {
                $this->availableBuilds = $builds;
                $this->selectBuild($this->selectedBuildNumber);
            }
        }

        $build = $this->selectedBuild ?? [];
        $jarDetails = $mcJarsService->resolveJarDetails($build);

        if (empty($jarDetails['url'])) {
            Notification::make()
                ->title('Download URL Unavailable')
                ->body('MCJars did not provide a download URL for this build. Please try another build or version.')
                ->danger()
                ->send();
            return;
        }

        $record = VersionChange::create([
            'server_id'         => $this->getServer()->id,
            'software'          => $this->selectedSoftware,
            'minecraft_version' => $this->selectedVersion,
            'build_number'      => $jarDetails['build_number'],
            'build_name'        => $jarDetails['name'],
            'jar_url'           => $jarDetails['url'],
            'jar_size'          => $jarDetails['size'],
            'clean_install'     => $this->cleanInstall,
            'status'            => VersionChange::STATUS_PENDING,
            'log'               => "[" . now()->format('H:i:s') . "] Version change queued for {$this->selectedSoftware} {$this->selectedVersion} ({$jarDetails['name']})" . ($this->cleanInstall ? ' [CLEAN INSTALL]' : '') . "\n",
        ]);

        ChangeVersionJob::dispatch($record->id);

        $this->changeId         = $record->id;
        $this->isChanging       = true;
        $this->changeStatus     = VersionChange::STATUS_PENDING;
        $this->changeLog        = $record->log;
        $this->changeError      = '';
        $this->showInstallModal = false;
        $this->showConfirmModal = false;
        $this->cleanInstall     = false;

        Notification::make()
            ->title('Version Change Queued')
            ->body("Installing {$this->selectedSoftware} {$this->selectedVersion}...")
            ->info()
            ->send();

        $this->loadHistory();
    }

    public function pollProgress(): void
    {
        if (!$this->isChanging || !$this->changeId) {
            return;
        }

        $record = VersionChange::find($this->changeId);

        if (!$record) {
            $this->isChanging = false;
            return;
        }

        $this->changeStatus = $record->status;
        $this->changeLog    = $record->log ?? '';
        $this->changeError  = $record->error_message ?? '';

        if (!$record->isActive()) {
            $this->isChanging = false;
            $this->loadHistory();

            if ($record->status === VersionChange::STATUS_DONE) {
                $this->loadCurrentVersion();
                $restarted = str_contains($record->log ?? '', 'automatically restarted');
                $body = $restarted
                    ? 'The server jar has been updated and your server was automatically restarted.'
                    : 'The server jar has been updated. You can now start your server from the console.';

                Notification::make()
                    ->title('Version Changed Successfully!')
                    ->body($body)
                    ->success()
                    ->send();
            } elseif ($record->status === VersionChange::STATUS_FAILED) {
                Notification::make()
                    ->title('Version Change Failed')
                    ->body($record->error_message ?: 'An error occurred while updating the server jar.')
                    ->danger()
                    ->send();
            }
        }
    }

    public function openLogModal(int $recordId): void
    {
        $record = VersionChange::where('server_id', $this->getServer()->id)->find($recordId);
        if ($record) {
            $this->viewingRecord = $record->toArray();
            $this->showLogModal  = true;
        }
    }

    public function closeLogModal(): void
    {
        $this->showLogModal  = false;
        $this->viewingRecord = null;
    }

    public function loadHistory(): void
    {
        $this->recentChanges = VersionChange::query()
            ->where('server_id', $this->getServer()->id)
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }

    public function loadCurrentVersion(): void
    {
        $server = $this->getServer();
        $this->currentVersionInfo = $this->detectActiveServerVersion($server);
    }

    public function detectActiveServerVersion(Server $server): ?array
    {
        $variables = [];
        try {
            $variables = $server->serverVariables()
                ->with('variable')
                ->get()
                ->pluck('variable_value', 'variable.env_variable')
                ->toArray();
        } catch (Throwable $e) {
            Log::debug('[Versions] detectActiveServerVersion variables error: ' . $e->getMessage());
        }

        $lastChange = VersionChange::query()
            ->where('server_id', $server->id)
            ->where('status', VersionChange::STATUS_DONE)
            ->latest('id')
            ->first();

        $modpack = $this->detectModpack($server, $lastChange, $variables);
        if ($modpack) {
            return $modpack;
        }

        $disk = $this->detectFromDisk($server, $variables);
        if ($disk) {
            if ($lastChange && strtolower($disk['software']) === strtolower($lastChange->software)) {
                $software = ucfirst(strtolower($lastChange->software));
                return [
                    'software'     => $software,
                    'version'      => $lastChange->minecraft_version ?: $disk['version'],
                    'build'        => $lastChange->build_name ?: ($lastChange->build_number ? "#{$lastChange->build_number}" : $disk['build']),
                    'installed_at' => $lastChange->updated_at?->diffForHumans(),
                    'source'       => 'history',
                    'icon'         => $this->findSoftwareIcon($software),
                ];
            }

            $disk['icon'] = $this->findSoftwareIcon($disk['software']);
            return $disk;
        }

        if ($lastChange) {
            $eggName = strtolower($server->egg?->name ?? '');
            $lastSoftware = strtolower($lastChange->software);

            $isContradicted = ($lastSoftware === 'vanilla' && (
                str_contains($eggName, 'forge') ||
                str_contains($eggName, 'fabric') ||
                str_contains($eggName, 'neoforge') ||
                str_contains($eggName, 'paper') ||
                !empty($variables['FORGE_VERSION']) ||
                !empty($variables['NEOFORGE_VERSION']) ||
                !empty($variables['FABRIC_VERSION'])
            ));

            if (!$isContradicted) {
                $software = ucfirst(strtolower($lastChange->software));
                return [
                    'software'     => $software,
                    'version'      => $lastChange->minecraft_version,
                    'build'        => $lastChange->build_name ?: ($lastChange->build_number ? "#{$lastChange->build_number}" : null),
                    'installed_at' => $lastChange->updated_at?->diffForHumans(),
                    'source'       => 'history',
                    'icon'         => $this->findSoftwareIcon($software),
                ];
            }
        }

        $eggInfo = $this->detectFromEgg($server, $variables);
        if ($eggInfo) {
            $eggInfo['icon'] = $this->findSoftwareIcon($eggInfo['software']);
            return $eggInfo;
        }

        return null;
    }

    private function detectModpack(Server $server, ?VersionChange $lastChange, array $variables): ?array
    {
        try {
            if (Schema::hasTable('modpack_installs')) {
                $latestModpack = DB::table('modpack_installs')
                    ->where('server_id', $server->id)
                    ->where('status', 'installed')
                    ->latest('updated_at')
                    ->first();

                if ($latestModpack) {
                    $modpackTime = Carbon::parse($latestModpack->updated_at);
                    $lastChangeTime = $lastChange?->updated_at;

                    if (!$lastChangeTime || $modpackTime->gte($lastChangeTime) || strtolower($lastChange->software) === 'vanilla') {
                        $provider = !empty($latestModpack->provider) ? ucfirst($latestModpack->provider) : 'Modpack';
                        $version = $latestModpack->modpack_version ?: ($variables['MINECRAFT_VERSION'] ?? 'Installed');

                        return [
                            'software'     => $latestModpack->modpack_name,
                            'version'      => $version,
                            'build'        => $provider,
                            'installed_at' => $modpackTime->diffForHumans(),
                            'source'       => 'modpack',
                            'icon'         => $latestModpack->modpack_icon_url ?: $this->findSoftwareIcon('curseforge'),
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] detectModpack DB error: ' . $e->getMessage());
        }

        try {
            $fileRepo = app(DaemonFileRepository::class);
            $fileRepo->setServer($server);
            $metaJson = (string) $fileRepo->getContent('/.modpack-manager.json', 32 * 1024);

            if (!empty($metaJson)) {
                $meta = json_decode($metaJson, true);
                if (is_array($meta) && !empty($meta['name'])) {
                    $installedAt = !empty($meta['installed_at']) ? Carbon::parse($meta['installed_at']) : null;
                    $lastChangeTime = $lastChange?->updated_at;

                    if (!$lastChangeTime || !$installedAt || $installedAt->gte($lastChangeTime) || strtolower($lastChange->software) === 'vanilla') {
                        return [
                            'software'     => (string) $meta['name'],
                            'version'      => (string) ($meta['version'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Installed')),
                            'build'        => !empty($meta['provider']) ? ucfirst((string) $meta['provider']) : 'Modpack',
                            'installed_at' => $installedAt?->diffForHumans(),
                            'source'       => 'modpack',
                            'icon'         => $meta['icon_url'] ?? $this->findSoftwareIcon('curseforge'),
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] detectModpack metadata error: ' . $e->getMessage());
        }

        return null;
    }

    private function detectFromDisk(Server $server, array $variables): ?array
    {
        try {
            $fileRepo = app(DaemonFileRepository::class);
            $fileRepo->setServer($server);

            $entries = (array) $fileRepo->getDirectory('/');
            $fileNames = [];
            foreach ($entries as $entry) {
                $name = (string) ($entry['name'] ?? '');
                $isDir = (bool) ($entry['directory'] ?? false);
                if ($name !== '') {
                    $fileNames[strtolower($name)] = [
                        'name'  => $name,
                        'isDir' => $isDir,
                    ];
                }
            }

            if (isset($fileNames['unix_args.txt'])) {
                $content = (string) $fileRepo->getContent('unix_args.txt', 8192);
                $detected = $this->parseArgsTxt($content, $variables);
                if ($detected) {
                    return $detected;
                }
            }

            foreach (['run.sh', 'startserver.sh', 'start.sh'] as $sh) {
                if (isset($fileNames[strtolower($sh)])) {
                    $content = (string) $fileRepo->getContent($sh, 8192);
                    $detected = $this->parseArgsTxt($content, $variables);
                    if ($detected) {
                        return $detected;
                    }
                }
            }

            foreach ($fileNames as $lower => $meta) {
                if ($meta['isDir']) {
                    continue;
                }

                if (preg_match('/neoforge-([0-9a-zA-Z\.\-]+).*?\.jar/i', $meta['name'], $m)) {
                    return [
                        'software'     => 'NeoForge',
                        'version'      => $variables['MINECRAFT_VERSION'] ?? 'Latest',
                        'build'        => "#{$m[1]}",
                        'installed_at' => null,
                        'source'       => 'disk',
                    ];
                }

                if (preg_match('/forge-([0-9]+\.[0-9]+(?:\.[0-9]+)?)-([0-9a-zA-Z\.\-]+).*?\.jar/i', $meta['name'], $m)) {
                    return [
                        'software'     => 'Forge',
                        'version'      => $m[1],
                        'build'        => "#{$m[2]}",
                        'installed_at' => null,
                        'source'       => 'disk',
                    ];
                }

                if (preg_match('/fabric-server-mc\.([0-9\.]+)-loader\.([0-9\.]+)/i', $meta['name'], $m)) {
                    return [
                        'software'     => 'Fabric',
                        'version'      => $m[1],
                        'build'        => "#loader-{$m[2]}",
                        'installed_at' => null,
                        'source'       => 'disk',
                    ];
                }
            }

            if (isset($fileNames['fabric-server-launch.jar']) || isset($fileNames['fabric-server-launcher.properties'])) {
                $mc = $variables['MINECRAFT_VERSION'] ?? null;
                $loader = $variables['FABRIC_VERSION'] ?? null;
                return [
                    'software'     => 'Fabric',
                    'version'      => $mc ?: 'Minecraft',
                    'build'        => $loader ? "#loader-{$loader}" : null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['quilt-server-launch.jar']) || isset($fileNames['quilt-server-launcher.properties'])) {
                return [
                    'software'     => 'Quilt',
                    'version'      => $variables['MINECRAFT_VERSION'] ?? 'Minecraft',
                    'build'        => !empty($variables['QUILT_VERSION']) ? "#{$variables['QUILT_VERSION']}" : null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['mohist.yml']) || isset($fileNames['mohist'])) {
                return [
                    'software'     => 'Mohist',
                    'version'      => $variables['MOHIST_VERSION'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Latest'),
                    'build'        => !empty($variables['BUILD_NUMBER']) ? "#{$variables['BUILD_NUMBER']}" : null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['magma.yml'])) {
                return [
                    'software'     => 'Magma',
                    'version'      => $variables['MAGMA_VERSION'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Latest'),
                    'build'        => null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['arclight.conf'])) {
                return [
                    'software'     => 'Arclight',
                    'version'      => $variables['ARCLIGHT_VERSION'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Latest'),
                    'build'        => null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['purpur.yml'])) {
                return [
                    'software'     => 'Purpur',
                    'version'      => $variables['PURPUR_VERSION'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Latest'),
                    'build'        => !empty($variables['BUILD_NUMBER']) ? "#{$variables['BUILD_NUMBER']}" : null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['folia.yml'])) {
                return [
                    'software'     => 'Folia',
                    'version'      => $variables['FOLIA_VERSION'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Latest'),
                    'build'        => !empty($variables['BUILD_NUMBER']) ? "#{$variables['BUILD_NUMBER']}" : null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['paper.yml'])) {
                return [
                    'software'     => 'Paper',
                    'version'      => $variables['PAPER_VERSION'] ?? ($variables['MINECRAFT_VERSION'] ?? 'Latest'),
                    'build'        => !empty($variables['BUILD_NUMBER']) ? "#{$variables['BUILD_NUMBER']}" : null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }

            if (isset($fileNames['spigot.yml'])) {
                return [
                    'software'     => 'Spigot',
                    'version'      => $variables['MINECRAFT_VERSION'] ?? 'Latest',
                    'build'        => null,
                    'installed_at' => null,
                    'source'       => 'disk',
                ];
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] detectFromDisk error: ' . $e->getMessage());
        }

        return null;
    }

    private function parseArgsTxt(string $txt, array $variables): ?array
    {
        if (preg_match('#libraries/net/neoforged/neoforge/([0-9a-zA-Z\.\-]+)/#', $txt, $m)) {
            $ver = $m[1];
            $mc = $variables['MINECRAFT_VERSION'] ?? null;
            if (!$mc && preg_match('/^(\d+)\.(\d+)/', $ver, $vParts)) {
                $mc = '1.' . $vParts[1] . ($vParts[2] !== '0' ? '.' . $vParts[2] : '');
            }

            return [
                'software'     => 'NeoForge',
                'version'      => $mc ?: $ver,
                'build'        => "#{$ver}",
                'installed_at' => null,
                'source'       => 'disk',
            ];
        }

        if (preg_match('#libraries/net/minecraftforge/forge/([0-9]+\.[0-9]+(?:\.[0-9]+)?)-([0-9a-zA-Z\.\-]+)/#', $txt, $m)) {
            return [
                'software'     => 'Forge',
                'version'      => $m[1],
                'build'        => "#{$m[2]}",
                'installed_at' => null,
                'source'       => 'disk',
            ];
        }

        if (preg_match('/FORGE_VERSION\s*=\s*["\']?([0-9]+\.[0-9]+(?:\.[0-9]+)?)-([0-9a-zA-Z\.\-]+)["\']?/i', $txt, $m)) {
            return [
                'software'     => 'Forge',
                'version'      => $m[1],
                'build'        => "#{$m[2]}",
                'installed_at' => null,
                'source'       => 'disk',
            ];
        }

        return null;
    }

    public function findSoftwareIcon(?string $softwareName): ?string
    {
        if (empty($softwareName)) {
            return null;
        }

        $searchKey = strtoupper(trim($softwareName));

        if (!empty($this->types) && is_array($this->types)) {
            foreach ($this->types as $group) {
                if (is_array($group)) {
                    foreach ($group as $key => $data) {
                        if (strtoupper((string) $key) === $searchKey || strtoupper((string) ($data['name'] ?? '')) === $searchKey) {
                            if (!empty($data['icon'])) {
                                return (string) $data['icon'];
                            }
                        }
                    }
                }
            }
        }

        $lower = strtolower($softwareName);
        if (str_contains($lower, 'mohist')) {
            return 'https://s3.mcjars.app/icons/mohist.png';
        }
        if (str_contains($lower, 'magma')) {
            return 'https://s3.mcjars.app/icons/magma.png';
        }
        if (str_contains($lower, 'arclight')) {
            return 'https://s3.mcjars.app/icons/arclight.png';
        }
        if (str_contains($lower, 'folia')) {
            return 'https://s3.mcjars.app/icons/folia.png';
        }
        if (str_contains($lower, 'forge')) {
            return 'https://s3.mcjars.app/icons/forge.png';
        }
        if (str_contains($lower, 'fabric')) {
            return 'https://s3.mcjars.app/icons/fabric.png';
        }
        if (str_contains($lower, 'neoforge')) {
            return 'https://s3.mcjars.app/icons/neoforge.png';
        }
        if (str_contains($lower, 'quilt')) {
            return 'https://s3.mcjars.app/icons/quilt.png';
        }
        if (str_contains($lower, 'paper')) {
            return 'https://s3.mcjars.app/icons/paper.png';
        }
        if (str_contains($lower, 'purpur')) {
            return 'https://s3.mcjars.app/icons/purpur.png';
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $softwareName));
        if ($slug) {
            return "https://s3.mcjars.app/icons/{$slug}.png";
        }

        return null;
    }

    private function detectFromEgg(Server $server, array $variables): ?array
    {
        try {
            $eggName = $server->egg?->name ?? '';

            $version = $variables['MINECRAFT_VERSION']
                ?? $variables['VANILLA_VERSION']
                ?? $variables['PAPER_VERSION']
                ?? $variables['PURPUR_VERSION']
                ?? $variables['VERSION']
                ?? null;

            $software = null;
            $build = null;
            $serverJar = strtolower(trim((string) ($variables['SERVER_JARFILE'] ?? '')));

            if (str_contains($serverJar, 'mohist')) {
                $software = 'Mohist';
                $version = $variables['MOHIST_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'purpur')) {
                $software = 'Purpur';
                $version = $variables['PURPUR_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'magma')) {
                $software = 'Magma';
                $version = $variables['MAGMA_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'arclight')) {
                $software = 'Arclight';
                $version = $variables['ARCLIGHT_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'folia')) {
                $software = 'Folia';
                $version = $variables['FOLIA_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'paper')) {
                $software = 'Paper';
                $version = $variables['PAPER_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'fabric')) {
                $software = 'Fabric';
                $version = $variables['FABRIC_VERSION'] ?? $version;
            } elseif (str_contains($serverJar, 'forge')) {
                $software = 'Forge';
                $version = $variables['FORGE_VERSION'] ?? $version;
            } elseif (!empty($variables['MOHIST_VERSION'])) {
                $software = 'Mohist';
                $version = $variables['MOHIST_VERSION'];
            } elseif (!empty($variables['PURPUR_VERSION'])) {
                $software = 'Purpur';
                $version = $variables['PURPUR_VERSION'];
            } elseif (!empty($variables['FORGE_VERSION'])) {
                $software = 'Forge';
                $build = "#{$variables['FORGE_VERSION']}";
            } elseif (!empty($variables['NEOFORGE_VERSION'])) {
                $software = 'NeoForge';
                $build = "#{$variables['NEOFORGE_VERSION']}";
            } elseif (!empty($variables['FABRIC_VERSION'])) {
                $software = 'Fabric';
                $build = "#{$variables['FABRIC_VERSION']}";
            } elseif (!empty($variables['QUILT_VERSION'])) {
                $software = 'Quilt';
                $build = "#{$variables['QUILT_VERSION']}";
            } elseif (!empty($variables['PAPER_VERSION'])) {
                $software = 'Paper';
                $version = $variables['PAPER_VERSION'];
            } elseif (stripos($eggName, 'mohist') !== false) {
                $software = 'Mohist';
            } elseif (stripos($eggName, 'purpur') !== false) {
                $software = 'Purpur';
            } elseif (stripos($eggName, 'paper') !== false) {
                $software = 'Paper';
            } elseif (stripos($eggName, 'spigot') !== false) {
                $software = 'Spigot';
            } elseif (stripos($eggName, 'forge') !== false) {
                $software = 'Forge';
            } elseif (stripos($eggName, 'fabric') !== false) {
                $software = 'Fabric';
            } elseif (stripos($eggName, 'bungee') !== false) {
                $software = 'BungeeCord';
            } elseif (stripos($eggName, 'velocity') !== false) {
                $software = 'Velocity';
            } elseif (stripos($eggName, 'vanilla') !== false) {
                $software = 'Vanilla';
            } elseif (!empty($eggName)) {
                $software = $eggName;
            }

            $build = $build ?? (!empty($variables['BUILD_NUMBER']) ? "#{$variables['BUILD_NUMBER']}" : null);

            if ($software || $version) {
                return [
                    'software'     => $software ?: 'Minecraft',
                    'version'      => ($version && $version !== 'latest') ? $version : ($version === 'latest' ? 'Latest' : 'Default'),
                    'build'        => $build,
                    'installed_at' => null,
                    'source'       => 'egg',
                ];
            }
        } catch (Throwable $e) {
            Log::debug('[Versions] detectFromEgg error: ' . $e->getMessage());
        }

        return null;
    }

    private function checkJavaCompatibility(string $version): void
    {
        $this->javaWarning = null;

        try {
            $requiredJava = isset($this->versionDetails['java']) ? (int) $this->versionDetails['java'] : null;

            if ($requiredJava === null) {
                return;
            }

            $image = $this->getServer()->image ?? '';
            if (empty($image)) {
                return;
            }

            if (!preg_match('/java[_-]?(\d+)/i', $image, $matches)) {
                return;
            }

            $detectedJava = (int) $matches[1];

            if ($detectedJava < $requiredJava) {
                $this->javaWarning = [
                    'required' => $requiredJava,
                    'detected' => $detectedJava,
                    'image'    => $image,
                    'version'  => $version,
                ];
            }
        } catch (Throwable $e) {
            \Log::debug('[Versions] checkJavaCompatibility error: ' . $e->getMessage());
        }
    }

    public function getFilteredTypesProperty(): array
    {
        $result = [];
        $search = strtolower(trim($this->search));

        foreach ($this->types as $category => $softwares) {
            if ($this->selectedCategory !== 'all' && $this->selectedCategory !== $category) {
                continue;
            }

            foreach ($softwares as $key => $software) {
                if ($search !== '') {
                    $name = strtolower($software['name'] ?? '');
                    $desc = strtolower($software['description'] ?? '');
                    $keyLower = strtolower($key);

                    if (!str_contains($name, $search) && !str_contains($desc, $search) && !str_contains($keyLower, $search)) {
                        continue;
                    }
                }

                $result[$category][$key] = $software;
            }
        }

        return $result;
    }

    public function isServerRunning(): bool
    {
        return in_array(strtolower($this->containerStatus), ['running', 'starting', 'restarting'], true);
    }
}
