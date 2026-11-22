<?php

namespace Pelican\Versions\Filament\Server\Pages;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Pelican\Versions\Jobs\ChangeVersionJob;
use Pelican\Versions\Models\VersionChange;
use Pelican\Versions\Services\McJarsService;
use Pelican\Versions\Services\ServerSoftwareDetector;
use Pelican\Versions\Services\SoftwareParser;
use Pelican\Versions\Services\VersionChangeService;
use Throwable;

class VersionsPage extends Page
{
    private const EGG_HINTS = [
        'minecraft', 'paper', 'purpur', 'folia', 'neoforge', 'forge', 'fabric', 'quilt', 'spigot', 'bungee', 'velocity',
        'youer', 'catserver', 'ketting', 'banner', 'mohist', 'magma', 'arclight', 'thermos', 'cauldron', 'crucible', 'cardboard',
    ];

    private const ICON_NEEDLES = [
        'youer'     => 'youer',
        'catserver' => 'catserver',
        'ketting'   => 'ketting',
        'banner'    => 'banner',
        'mohist'    => 'mohist',
        'magma'     => 'magma',
        'arclight'  => 'arclight',
        'thermos'   => 'thermos',
        'cauldron'  => 'thermos',
        'crucible'  => 'crucible',
        'cardboard' => 'cardboard',
        'folia'     => 'folia',
        'neoforge'  => 'neoforge',
        'forge'     => 'forge',
        'fabric'    => 'fabric',
        'quilt'     => 'quilt',
        'paper'     => 'paper',
        'purpur'    => 'purpur',
    ];

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-arrows-exchange';
    protected static ?string $navigationLabel = 'Version Changer';
    protected static ?string $slug            = 'versions';
    protected static ?int    $navigationSort  = 8;

    protected string $view = 'versions::filament.server.pages.versions-page';

    public string $search           = '';
    public string $selectedCategory = 'recommended';
    public string $versionSearch    = '';
    public bool   $showSnapshots    = false;
    public bool   $cleanInstall     = false;

    #[Locked]
    public bool $showInstallModal = false;

    #[Locked]
    public bool $showConfirmModal = false;

    #[Locked]
    public bool $showLogModal = false;

    #[Locked]
    public string $targetJarFilename = 'server.jar';

    #[Locked]
    public array $types = [];

    #[Locked]
    public ?string $selectedSoftware = null;

    #[Locked]
    public ?array $softwareDetails = null;

    #[Locked]
    public array $availableVersions = [];

    #[Locked]
    public ?string $selectedVersion = null;

    #[Locked]
    public ?array $versionDetails = null;

    #[Locked]
    public array $availableBuilds = [];

    #[Locked]
    public string|int|null $selectedBuildNumber = 'latest';

    #[Locked]
    public ?array $selectedBuild = null;

    #[Locked]
    public bool $isLoadingVersions = false;

    #[Locked]
    public bool $isLoadingBuilds = false;

    #[Locked]
    public string $containerStatus = 'offline';

    #[Locked]
    public bool $isChanging = false;

    #[Locked]
    public int $changeId = 0;

    #[Locked]
    public string $changeStatus = '';

    #[Locked]
    public string $changeLog = '';

    #[Locked]
    public string $changeError = '';

    #[Locked]
    public ?array $viewingRecord = null;

    #[Locked]
    public ?array $javaWarning = null;

    #[Locked]
    public array $recentChanges = [];

    #[Locked]
    public ?array $currentVersionInfo = null;

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

        $tags    = array_map('strtolower', (array) ($server->egg?->tags ?? []));
        $eggName = strtolower($server->egg?->name ?? '');
        $startup = strtolower($server->startup ?? '');

        $isMinecraft = in_array('minecraft', $tags, true)
            || str_contains($startup, 'server.jar')
            || str_contains($startup, 'minecraft');

        foreach (self::EGG_HINTS as $hint) {
            if ($isMinecraft) {
                break;
            }

            $isMinecraft = str_contains($eggName, $hint);
        }

        return $isMinecraft && static::userCanManage($server);
    }

    protected static function userCanManage(Server $server): bool
    {
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
        return Filament::getTenant();
    }

    public function canManageVersions(): bool
    {
        return static::userCanManage($this->getServer());
    }

    public function mount(): void
    {
        $server   = $this->getServer();
        $serverId = $server->id;

        $this->refreshContainerStatus();
        $this->targetJarFilename = app(VersionChangeService::class)->getTargetServerJarFilename($server);
        $this->types             = app(McJarsService::class)->getTypes();

        $cachedVersion = Cache::get("versions:current:{$serverId}");
        if (is_array($cachedVersion) && $cachedVersion !== []) {
            $this->currentVersionInfo = $cachedVersion;
        }

        VersionChange::failStale($serverId);

        $activeChange = VersionChange::query()
            ->where('server_id', $serverId)
            ->active()
            ->latest('id')
            ->first();

        if ($activeChange) {
            $this->isChanging   = true;
            $this->changeId     = $activeChange->id;
            $this->changeStatus = $activeChange->status;
            $this->changeLog    = $activeChange->log ?? '';
            $this->changeError  = $activeChange->error_message ?? '';
        }

        $this->loadHistory();
    }

    public function selectCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function openSoftwareModal(string $softwareKey): void
    {
        $this->selectSoftware($softwareKey, true);
    }

    public function closeInstallModal(): void
    {
        $this->showInstallModal  = false;
        $this->showConfirmModal  = false;
        $this->availableVersions = [];
        $this->availableBuilds   = [];
        $this->selectedBuild     = null;
        $this->versionSearch     = '';
        $this->showSnapshots     = false;
        $this->cleanInstall      = false;
    }

    public function selectSoftware(string $softwareKey, bool $openModal = true): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        $key = strtoupper(trim($softwareKey));
        $svc = app(McJarsService::class);

        $details = null;
        foreach ($this->types as $group) {
            if (isset($group[$key])) {
                $details = $group[$key];
                break;
            }
        }

        if ($details === null && $this->types !== []) {
            Notification::make()->title('Unknown software type.')->warning()->send();

            return;
        }

        if ($this->selectedSoftware === $key && $this->availableVersions !== []) {
            if ($openModal) {
                $this->showConfirmModal = false;
                $this->showInstallModal = true;
            }

            return;
        }

        $this->selectedSoftware = $key;
        $this->versionSearch    = '';
        $this->cleanInstall     = false;
        $this->softwareDetails  = $details ?? [
            'name'        => ucfirst(strtolower($key)),
            'description' => 'Minecraft server software.',
            'icon'        => null,
        ];

        $this->isLoadingVersions = true;
        try {
            $this->availableVersions = $svc->getVersions($key);
        } catch (Throwable $e) {
            $this->availableVersions = [];
        }
        $this->isLoadingVersions = false;

        if ($this->availableVersions !== []) {
            $first = $this->firstRelease() ?? array_key_first($this->availableVersions);
            $this->selectVersion((string) $first);
        } else {
            $this->selectedVersion = null;
            $this->versionDetails  = null;
            $this->availableBuilds = [];
            $this->selectedBuild   = null;
            $this->javaWarning     = null;
        }

        if ($openModal) {
            $this->showConfirmModal = false;
            $this->showInstallModal = true;
        }
    }

    public function selectVersion(string $version): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        if ($version === '' || !isset($this->availableVersions[$version])) {
            return;
        }

        $this->selectedVersion = $version;
        $this->versionDetails  = $this->availableVersions[$version];

        $this->isLoadingBuilds = true;
        try {
            $this->availableBuilds = app(McJarsService::class)->getBuilds((string) $this->selectedSoftware, $version);
        } catch (Throwable $e) {
            $this->availableBuilds = [];
        }
        $this->isLoadingBuilds = false;

        $this->selectBuild('latest');
        $this->checkJavaCompatibility($version);
    }

    public function updatedVersionSearch(): void
    {
        $this->syncSelectedVersionWithFilter();
    }

    public function updatedShowSnapshots(): void
    {
        $this->syncSelectedVersionWithFilter();
    }

    public function selectBuild(string|int|null $buildNumber): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        $wanted = ($buildNumber === null || $buildNumber === '' || $buildNumber === 0 || $buildNumber === '0') ? 'latest' : (string) $buildNumber;

        if ($this->availableBuilds === []) {
            $this->selectedBuildNumber = $wanted;
            $this->selectedBuild       = $this->versionDetails['latest'] ?? null;

            return;
        }

        $found = $this->findBuild($this->availableBuilds, $wanted);

        if ($found === null) {
            $found  = $this->availableBuilds[0];
            $wanted = 'latest';
        }

        $this->selectedBuildNumber = $wanted;
        $this->selectedBuild       = $found;
    }

    public function openConfirmModal(): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        if ($this->isChanging) {
            Notification::make()->title('A version change is already in progress.')->warning()->send();

            return;
        }

        if (empty($this->selectedSoftware) || empty($this->selectedVersion)) {
            Notification::make()->title('Please select a software and version first.')->warning()->send();

            return;
        }

        if ($this->filteredVersions === []) {
            Notification::make()->title('No matching version is selected.')->warning()->send();

            return;
        }

        $this->syncSelectedVersionWithFilter();
        $this->refreshContainerStatus();
        $this->targetJarFilename = app(VersionChangeService::class)->getTargetServerJarFilename($this->getServer());
        $this->showInstallModal  = false;
        $this->showConfirmModal  = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;

        if ($this->selectedSoftware && $this->softwareDetails) {
            $this->showInstallModal = true;
        }
    }

    public function startVersionChange(): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        if ($this->isChanging) {
            Notification::make()->title('A version change is already in progress.')->warning()->send();

            return;
        }

        if (empty($this->selectedSoftware) || empty($this->selectedVersion)) {
            Notification::make()->title('Please select a software and version first.')->warning()->send();

            return;
        }

        $svc    = app(McJarsService::class);
        $server = $this->getServer();

        $builds = $svc->getBuilds($this->selectedSoftware, $this->selectedVersion);

        if ($builds === []) {
            Notification::make()
                ->title('Builds unavailable')
                ->body('Could not load the build list from MCJars. Please try again in a moment.')
                ->danger()
                ->send();

            return;
        }

        $this->availableBuilds = $builds;
        $build                 = $this->findBuild($builds, (string) ($this->selectedBuildNumber ?? 'latest'));

        if ($build === null) {
            $this->selectBuild('latest');

            Notification::make()
                ->title('Selected build is no longer available')
                ->body('The build list changed. Review the selection and try again.')
                ->warning()
                ->send();

            return;
        }

        $this->selectedBuild = $build;
        $jar                 = $svc->resolveJarDetails($build);

        if (empty($jar['url']) || !$svc->isAllowedDownloadUrl($jar['url'])) {
            Notification::make()
                ->title('Download URL Unavailable')
                ->body('MCJars did not provide a valid download URL for this build. Please try another build or version.')
                ->danger()
                ->send();

            return;
        }

        $software     = $this->selectedSoftware;
        $mcVersion    = $this->selectedVersion;
        $cleanInstall = $this->cleanInstall;

        $record = DB::transaction(function () use ($server, $software, $mcVersion, $jar, $cleanInstall) {
            Server::query()->whereKey($server->id)->lockForUpdate()->first();

            VersionChange::failStale($server->id);

            if (VersionChange::query()->active()->where('server_id', $server->id)->exists()) {
                return null;
            }

            return VersionChange::create([
                'server_id'         => $server->id,
                'software'          => $software,
                'minecraft_version' => $mcVersion,
                'build_number'      => $jar['build_number'],
                'build_name'        => $jar['name'],
                'jar_url'           => $jar['url'],
                'jar_size'          => $jar['size'],
                'clean_install'     => $cleanInstall,
                'status'            => VersionChange::STATUS_PENDING,
                'log'               => '[' . now()->format('H:i:s') . "] Version change queued for {$software} {$mcVersion} ({$jar['name']})" . ($cleanInstall ? ' [CLEAN INSTALL]' : '') . PHP_EOL,
            ]);
        });

        if (!$record) {
            Notification::make()->title('A version change is already in progress for this server.')->warning()->send();
            $this->adoptActiveChange();

            return;
        }

        $this->changeId         = $record->id;
        $this->isChanging       = true;
        $this->changeStatus     = VersionChange::STATUS_PENDING;
        $this->changeLog        = $record->log;
        $this->changeError      = '';
        $this->showInstallModal = false;
        $this->showConfirmModal = false;
        $this->cleanInstall     = false;

        try {
            ChangeVersionJob::dispatch($record->id);
        } catch (Throwable $e) {
            $fresh = $record->fresh();
            if (!$fresh || $fresh->isActive()) {
                $record->markFailed('Could not queue the job: ' . $e->getMessage());
            }
            Log::error('[Versions] dispatch failed: ' . $e->getMessage());
            $this->pollProgress();

            return;
        }

        if (config('queue.default') === 'sync') {
            $this->pollProgress();

            return;
        }

        Notification::make()
            ->title('Version Change Queued')
            ->body("Installing {$software} {$mcVersion}...")
            ->info()
            ->send();

        $this->loadHistory();
    }

    public function pollProgress(): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        if (!$this->isChanging || !$this->changeId) {
            return;
        }

        $server   = $this->getServer();
        $serverId = $server->id;

        $record = VersionChange::query()->where('server_id', $serverId)->find($this->changeId);

        if (!$record) {
            $this->isChanging = false;

            return;
        }

        if ($record->isStale()) {
            VersionChange::failStale($serverId);
            $record = $record->fresh() ?? $record;
        }

        $this->changeStatus = $record->status;
        $this->changeLog    = $record->log ?? '';
        $this->changeError  = $record->error_message ?? '';

        if ($record->isActive()) {
            return;
        }

        $this->isChanging        = false;
        $this->targetJarFilename = app(VersionChangeService::class)->getTargetServerJarFilename($server);
        $this->loadHistory();
        $this->refreshContainerStatus();

        if ($record->status === VersionChange::STATUS_DONE) {
            $this->loadCurrentVersion(true);

            $restarted = str_contains($record->log ?? '', 'automatically restarted');

            Notification::make()
                ->title('Version Changed Successfully!')
                ->body($restarted
                    ? "{$this->targetJarFilename} has been updated and your server was automatically restarted."
                    : "{$this->targetJarFilename} has been updated. You can now start your server from the console.")
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

    public function dismissChangeResult(): void
    {
        if (!$this->isChanging) {
            $this->changeStatus = '';
            $this->changeError  = '';
        }
    }

    public function openLogModal(int $recordId): void
    {
        if (!$this->ensureAuthorized()) {
            return;
        }

        $record = VersionChange::query()->where('server_id', $this->getServer()->id)->find($recordId);

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
            ->select(['id', 'server_id', 'software', 'minecraft_version', 'build_number', 'build_name', 'status', 'created_at'])
            ->where('server_id', $this->getServer()->id)
            ->latest()
            ->take(10)
            ->get()
            ->toArray();
    }

    public function loadCurrentVersion(bool $fresh = false): void
    {
        if (!$this->canManageVersions()) {
            return;
        }

        $server = $this->getServer();
        $key    = "versions:current:{$server->id}";
        $ttl    = max(0, (int) config('versions.detection_cache_ttl', 30));

        if ($fresh) {
            Cache::forget($key);
        } elseif ($this->currentVersionInfo !== null) {
            return;
        } elseif ($ttl > 0) {
            $cached = Cache::get($key);
            if (is_array($cached) && $cached !== []) {
                $this->currentVersionInfo = $cached;

                return;
            }
        }

        $info = $this->detectActiveServerVersion($server);

        if (is_array($info) && $info !== [] && $ttl > 0) {
            Cache::put($key, $info, $ttl);
        }

        $this->currentVersionInfo = $info;
    }

    public function detectActiveServerVersion(Server $server): ?array
    {
        $info = app(ServerSoftwareDetector::class)->detect($server);

        if (!$info) {
            return null;
        }

        $info['icon'] = $this->findSoftwareIcon($info['icon_hint'] ?? $info['software'] ?? null);
        unset($info['icon_hint']);

        return $info;
    }

    public function findSoftwareIcon(?string $softwareName): ?string
    {
        if (empty($softwareName)) {
            return null;
        }

        $searchKey = strtoupper(trim($softwareName));

        foreach ($this->types as $group) {
            if (!is_array($group)) {
                continue;
            }

            foreach ($group as $key => $data) {
                $matches = strtoupper((string) $key) === $searchKey || strtoupper((string) ($data['name'] ?? '')) === $searchKey;

                if ($matches && !empty($data['icon'])) {
                    return (string) $data['icon'];
                }
            }
        }

        $lower = strtolower($softwareName);

        foreach (self::ICON_NEEDLES as $needle => $slug) {
            if (str_contains($lower, $needle)) {
                return "https://s3.mcjars.app/icons/{$slug}.png";
            }
        }

        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $softwareName));

        return $slug !== '' ? "https://s3.mcjars.app/icons/{$slug}.png" : null;
    }

    public function getFilteredTypesProperty(): array
    {
        $result   = [];
        $search   = strtolower(trim($this->search));
        $seenKeys = [];

        foreach ($this->types as $category => $softwares) {
            if ($search === '' && $this->selectedCategory !== 'all' && $this->selectedCategory !== $category) {
                continue;
            }

            foreach ($softwares as $key => $software) {
                if ($search !== '') {
                    $upperKey = strtoupper((string) $key);

                    if (isset($seenKeys[$upperKey])) {
                        continue;
                    }

                    $haystack = ($software['name'] ?? '') . ' ' . ($software['description'] ?? '') . ' ' . $key;

                    if (!SoftwareParser::matchesSearch($haystack, $search)) {
                        continue;
                    }

                    $seenKeys[$upperKey] = true;
                }

                $result[$category][$key] = $software;
            }
        }

        return $result;
    }

    public function getFilteredVersionsProperty(): array
    {
        $search = strtolower(trim($this->versionSearch));

        $filtered = array_filter(
            $this->availableVersions,
            function ($vData, $k) use ($search): bool {
                if (!$this->showSnapshots && ($vData['type'] ?? 'RELEASE') !== 'RELEASE') {
                    return false;
                }

                if ($search !== '' && !str_contains(strtolower((string) $k), $search)) {
                    return false;
                }

                return true;
            },
            ARRAY_FILTER_USE_BOTH
        );

        if ($filtered === [] && !$this->showSnapshots && $search === '') {
            return $this->availableVersions;
        }

        return $filtered;
    }

    public function isServerRunning(): bool
    {
        return in_array(strtolower($this->containerStatus), ['running', 'starting', 'restarting'], true);
    }

    private function ensureAuthorized(): bool
    {
        if ($this->canManageVersions()) {
            return true;
        }

        Notification::make()->title('Unauthorized action.')->danger()->send();

        return false;
    }

    private function syncSelectedVersionWithFilter(): void
    {
        $filtered = $this->filteredVersions;

        if ($filtered === []) {
            return;
        }

        if ($this->selectedVersion === null || !array_key_exists((string) $this->selectedVersion, $filtered)) {
            $first = array_key_first($filtered);
            if ($first !== null) {
                $this->selectVersion((string) $first);
            }
        }
    }

    private function firstRelease(): string|int|null
    {
        if (!$this->showSnapshots) {
            foreach ($this->availableVersions as $id => $meta) {
                if (($meta['type'] ?? 'RELEASE') === 'RELEASE') {
                    return $id;
                }
            }
        }

        return array_key_first($this->availableVersions);
    }

    private function findBuild(array $builds, string $wanted): ?array
    {
        if ($wanted === 'latest' || $wanted === '') {
            return $builds[0] ?? null;
        }

        foreach ($builds as $b) {
            if ((string) ($b['name'] ?? '') === $wanted || (string) ($b['buildNumber'] ?? '') === $wanted) {
                return $b;
            }
        }

        return null;
    }

    private function refreshContainerStatus(): void
    {
        try {
            $repo = app(DaemonServerRepository::class);
            $this->containerStatus = (string) ($repo->setServer($this->getServer())->getDetails()['state'] ?? 'offline');
        } catch (Throwable $e) {
            Log::debug('[Versions] container status error: ' . $e->getMessage());
            $this->containerStatus = 'offline';
        }
    }

    private function adoptActiveChange(): void
    {
        $active = VersionChange::query()
            ->where('server_id', $this->getServer()->id)
            ->active()
            ->latest('id')
            ->first();

        if ($active) {
            $this->isChanging   = true;
            $this->changeId     = $active->id;
            $this->changeStatus = $active->status;
            $this->changeLog    = $active->log ?? '';
        }
    }

    private function checkJavaCompatibility(string $version): void
    {
        $this->javaWarning = null;

        try {
            $requiredJava = isset($this->versionDetails['java']) ? (int) $this->versionDetails['java'] : null;
            $image        = $this->getServer()->image ?? '';

            if ($requiredJava === null || $image === '' || !preg_match('/java[_-]?(\\d+)/i', $image, $matches)) {
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
            Log::debug('[Versions] checkJavaCompatibility error: ' . $e->getMessage());
        }
    }
}
