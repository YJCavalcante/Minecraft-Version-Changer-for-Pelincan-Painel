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

    /**
     * Sidebar position configuration fallback.
     */
    public static function getNavigationSort(): ?int
    {
        return (int) config('versions.navigation_sort', static::$navigationSort);
    }

    /**
     * Access control: server must have 'minecraft' tag and user must have permission.
     */
    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        if (!parent::canAccess() || !$server instanceof Server) {
            return false;
        }

        $tags = array_map('strtolower', (array) ($server->egg?->tags ?? []));
        $isMinecraft = in_array('minecraft', $tags, true)
            || str_contains(strtolower($server->egg?->name ?? ''), 'minecraft');
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

    /**
     * Helper to get the current tenant Server model.
     */
    public function getServer(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        return $server;
    }

    // ─── Component State ──────────────────────────────────────────────────────

    // Browsing and filter state
    public string $search           = '';
    public string $selectedCategory = 'all'; // 'all', 'recommended', 'established', 'experimental', etc.
    public array  $types            = [];
    public ?string $selectedSoftware = null;
    public ?array $softwareDetails  = null;

    // Versions and builds state
    public array  $availableVersions   = [];
    public ?string $selectedVersion    = null;
    public ?array $versionDetails      = null;
    public array  $availableBuilds     = [];
    public ?int   $selectedBuildNumber = null; // null = latest
    public ?array $selectedBuild       = null;

    public bool $isLoadingVersions = false;
    public bool $isLoadingBuilds   = false;

    // Server daemon state
    public string $containerStatus = 'offline';

    // Active change state (Livewire polling)
    public bool   $isChanging   = false;
    public int    $changeId     = 0;
    public string $changeStatus = '';
    public string $changeLog    = '';
    public string $changeError  = '';

    // Modals
    public bool   $showConfirmModal = false;
    public bool   $showLogModal     = false;
    public ?array $viewingRecord    = null;

    // History
    public array $recentChanges = [];

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function mount(?McJarsService $mcJarsService = null, ?DaemonServerRepository $serverRepo = null): void
    {
        $mcJarsService = $mcJarsService ?? app(McJarsService::class);
        $serverRepo    = $serverRepo ?? app(DaemonServerRepository::class);

        // Fetch container status safely
        try {
            $details = $serverRepo->setServer($this->getServer())->getDetails();
            $this->containerStatus = (string) ($details['state'] ?? 'offline');
        } catch (Throwable) {
            $this->containerStatus = 'offline';
        }

        // Fetch software types
        $this->types = $mcJarsService->getTypes();

        // Check for an ongoing version change
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

        // Default to PAPER if available, or first recommended software
        $defaultSoftware = 'PAPER';
        if (!isset($this->types['recommended'][$defaultSoftware])) {
            $recommended = array_keys($this->types['recommended'] ?? []);
            $defaultSoftware = $recommended[0] ?? null;
        }

        if ($defaultSoftware) {
            $this->selectSoftware($defaultSoftware, $mcJarsService);
        }

        $this->loadHistory();
    }

    // ─── Software Selection ───────────────────────────────────────────────────

    public function selectCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function selectSoftware(string $softwareKey, ?McJarsService $mcJarsService = null): void
    {
        $mcJarsService = $mcJarsService ?? app(McJarsService::class);
        $this->selectedSoftware = strtoupper($softwareKey);

        // Find software details in types list
        $this->softwareDetails = null;
        foreach ($this->types as $group) {
            if (isset($group[$this->selectedSoftware])) {
                $this->softwareDetails = $group[$this->selectedSoftware];
                break;
            }
        }

        $this->isLoadingVersions = true;
        $this->availableVersions = $mcJarsService->getVersions($this->selectedSoftware);
        $this->isLoadingVersions = false;

        // Auto-select latest version
        if (!empty($this->availableVersions)) {
            $firstVersion = array_key_first($this->availableVersions);
            $this->selectVersion($firstVersion, $mcJarsService);
        } else {
            $this->selectedVersion = null;
            $this->versionDetails  = null;
            $this->availableBuilds = [];
            $this->selectedBuild   = null;
        }
    }

    public function selectVersion(string $version, ?McJarsService $mcJarsService = null): void
    {
        $mcJarsService = $mcJarsService ?? app(McJarsService::class);
        $this->selectedVersion = $version;
        $this->versionDetails  = $this->availableVersions[$version] ?? null;

        $this->isLoadingBuilds = true;
        $this->availableBuilds = $mcJarsService->getBuilds($this->selectedSoftware, $version);
        $this->isLoadingBuilds = false;

        // Default to latest build
        $this->selectBuild(null);
    }

    public function selectBuild(?int $buildNumber): void
    {
        $this->selectedBuildNumber = $buildNumber;

        if ($buildNumber === null || $buildNumber === 0) {
            $this->selectedBuild = !empty($this->availableBuilds)
                ? $this->availableBuilds[0]
                : ($this->versionDetails['latest'] ?? null);
        } else {
            $this->selectedBuild = collect($this->availableBuilds)
                ->firstWhere('buildNumber', $buildNumber);
        }
    }

    // ─── Execution ────────────────────────────────────────────────────────────

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

        // Refresh container status
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
            'status'            => VersionChange::STATUS_PENDING,
            'log'               => "[" . now()->format('H:i:s') . "] Version change queued for {$this->selectedSoftware} {$this->selectedVersion} ({$jarDetails['name']})\n",
        ]);

        ChangeVersionJob::dispatch($record->id);

        $this->changeId         = $record->id;
        $this->isChanging       = true;
        $this->changeStatus     = VersionChange::STATUS_PENDING;
        $this->changeLog        = $record->log;
        $this->changeError      = '';
        $this->showConfirmModal = false;

        Notification::make()
            ->title('Version Change Queued')
            ->body("Installing {$this->selectedSoftware} {$this->selectedVersion}...")
            ->info()
            ->send();

        $this->loadHistory();
    }

    /**
     * Polls active version change progress (invoked via Livewire).
     */
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
                Notification::make()
                    ->title('Version Changed Successfully!')
                    ->body('The server.jar has been updated. Please restart your server to apply.')
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

    // ─── History & Logs ───────────────────────────────────────────────────────

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

    // ─── Helpers ──────────────────────────────────────────────────────────────

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
