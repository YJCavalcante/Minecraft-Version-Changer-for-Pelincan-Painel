<?php

namespace Pelican\Versions\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
        } catch (Throwable) {
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
        } catch (Throwable) {}

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

        $lastChange = VersionChange::query()
            ->where('server_id', $server->id)
            ->where('status', VersionChange::STATUS_DONE)
            ->latest('id')
            ->first();

        if ($lastChange) {
            $this->currentVersionInfo = [
                'software'     => ucfirst(strtolower($lastChange->software)),
                'version'      => $lastChange->minecraft_version,
                'build'        => $lastChange->build_name ?: ($lastChange->build_number ? "#{$lastChange->build_number}" : null),
                'installed_at' => $lastChange->updated_at?->diffForHumans(),
                'source'       => 'history',
            ];
            return;
        }

        $this->currentVersionInfo = $this->detectServerVersionFallback($server);
    }

    private function detectServerVersionFallback(Server $server): ?array
    {
        try {
            $variables = $server->serverVariables()
                ->with('variable')
                ->get()
                ->pluck('variable_value', 'variable.env_variable')
                ->toArray();

            $eggName = $server->egg?->name ?? '';

            $version = $variables['MINECRAFT_VERSION']
                ?? $variables['VANILLA_VERSION']
                ?? $variables['PAPER_VERSION']
                ?? $variables['PURPUR_VERSION']
                ?? $variables['VERSION']
                ?? null;

            $software = null;
            if (stripos($eggName, 'paper') !== false) {
                $software = 'Paper';
            } elseif (stripos($eggName, 'purpur') !== false) {
                $software = 'Purpur';
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

            $build = !empty($variables['BUILD_NUMBER']) ? "#{$variables['BUILD_NUMBER']}" : null;

            if ($software || $version) {
                return [
                    'software'     => $software ?: 'Minecraft',
                    'version'      => ($version && $version !== 'latest') ? $version : ($version === 'latest' ? 'Latest' : 'Default'),
                    'build'        => $build,
                    'installed_at' => null,
                    'source'       => 'egg',
                ];
            }
        } catch (Throwable) {
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
        } catch (Throwable) {
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
