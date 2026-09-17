# Minecraft Version Changer — Documento de Planejamento Completo

> **Destino:** Entrega para execução em outro chat  
> **Status:** Planejamento 100% concluído, pronto para codificação  
> **Baseado em:** Análise do código-fonte real do Pelican Panel + modpack-manager (addon de referência funcional)

---

## 1. Visão Geral do Projeto

**Nome do plugin:** Minecraft Version Changer  
**ID do plugin:** `versions`  
**Pasta no panel:** `plugins/versions/`  
**Objetivo:** Permitir que o administrador de um servidor Minecraft troque o `server.jar` diretamente pela interface do Pelican Panel, sem acesso manual a arquivos — selecionando software (Paper, Vanilla, Fabric, Forge, etc.) e versão com liberdade total.

---

## 2. Stack e Compatibilidade

| Componente | Versão |
|-----------|--------|
| PHP | `^8.3 || ^8.4 || ^8.5` |
| Laravel | `^13.25` |
| FilamentPHP | `^5.7` |
| `panel_version` alvo | `^1.0.0-beta36` |
| Composer packages extras | Nenhum |

---

## 3. Como o Sistema de Plugins do Pelican Funciona

### 3.1 Descoberta e carregamento

- Plugins ficam em `plugins/<id>/plugin.json` (a pasta DEVE ter o mesmo nome que o campo `id`)
- O modelo `App\Models\Plugin` usa **Sushi** (SQLite em memória) — lê todos os `plugins/*/plugin.json` em runtime
- `Plugin::shouldLoad()` verifica: status `enabled` + contexto do painel (`panels` array)
- ServiceProviders são auto-descobertos de `src/Providers/`
- Migrations são auto-descobertas de `database/migrations/`
- Commands são auto-descobertos de `src/Console/Commands/`

### 3.2 Instalação

```bash
php artisan p:plugin:install versions
```

### 3.3 Estrutura obrigatória do `plugin.json`

```json
{
    "id": "versions",
    "name": "Minecraft Version Changer",
    "author": "yuri.j.cavalcante",
    "version": "1.0.0",
    "description": "Change the Minecraft server JAR directly from the panel UI.",
    "update_url": null,
    "category": "plugin",
    "namespace": "Pelican\Versions",
    "class": "VersionsPlugin",
    "panels": ["server"],
    "panel_version": "^1.0.0-beta36",
    "composer_packages": []
}
```

**Campos obrigatórios:** `id`, `name`, `author`, `version`, `description`, `category`, `namespace`, `class`, `panels`, `panel_version`

**`panels` possíveis:** `"admin"`, `"server"` — nosso plugin usa apenas `["server"]`

**`category` possíveis** (enum `PluginCategory`):
- `"plugin"` ← usamos este
- `"theme"`
- `"language"`

> ⚠️ **Remover o campo `meta` antes de publicar** — ele é gerenciado pelo panel em runtime

---

## 4. Estrutura de Arquivos do Plugin

```
plugins/versions/
├── plugin.json
├── src/
│   ├── VersionsPlugin.php
│   ├── Providers/
│   │   └── VersionsServiceProvider.php
│   ├── Filament/
│   │   └── Server/
│   │       └── Pages/
│   │           └── VersionsPage.php
│   ├── Jobs/
│   │   └── ChangeVersionJob.php
│   ├── Services/
│   │   ├── McJarsService.php
│   │   └── VersionChangeService.php
│   └── Models/
│       └── VersionChange.php
├── database/
│   └── migrations/
│       └── 2024_01_01_000000_create_version_changes_table.php
├── resources/
│   └── views/
│       └── filament/
│           └── server/
│               └── pages/
│                   └── versions-page.blade.php
└── config/
    └── versions.php
```

---

## 5. `plugin.json` final com namespace definido

```json
{
    "id": "versions",
    "name": "Minecraft Version Changer",
    "author": "yuri.j.cavalcante",
    "version": "1.0.0",
    "description": "Change the Minecraft server JAR directly from the panel UI. Supports all software types from MCJars (Vanilla, Paper, Purpur, Fabric, Forge, NeoForge, Folia, Spigot, Quilt, and more).",
    "update_url": null,
    "category": "plugin",
    "namespace": "Pelican\Versions",
    "class": "VersionsPlugin",
    "panels": ["server"],
    "panel_version": "^1.0.0-beta36",
    "composer_packages": []
}
```

---

## 6. Classe Principal do Plugin (`VersionsPlugin.php`)

```php
<?php

namespace Pelican\Versions;

use App\Contracts\Plugins\HasPluginSettings;
use App\Models\Subuser;
use Filament\Panel;
use Filament\Contracts\Plugin;
use Pelican\Versions\Filament\Server\Pages\VersionsPage;

class VersionsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'versions';
    }

    public function register(Panel $panel): void
    {
        // Registrar permissão customizada
        Subuser::registerCustomPermissions(
            'versions',            // grupo
            ['change'],            // permissões → gera "versions.change"
            null,                  // translation_prefix
            'tabler-arrows-exchange', // ícone Tabler
            false                  // hidden?
        );

        // Descobrir pages pelo contexto do panel
        if ($panel->getId() === 'server') {
            $panel->discoverPages(
                in: plugin_path('versions', 'src/Filament/Server/Pages'),
                for: 'Pelican\Versions\Filament\Server\Pages'
            );

            // Registrar view namespace
            $panel->discoverResources(
                in: plugin_path('versions', 'resources/views'),
                for: 'versions'
            );
        }
    }

    public function boot(Panel $panel): void {}
}
```

### Como `plugin_path()` funciona
```php
// Definida em app/helpers.php do Pelican
function plugin_path(string $pluginId, string $path = ''): string
{
    return base_path("plugins/{$pluginId}" . ($path ? "/{$path}" : ''));
}
```

---

## 7. ServiceProvider (`VersionsServiceProvider.php`)

```php
<?php

namespace Pelican\Versions\Providers;

use Illuminate\Support\ServiceProvider;

class VersionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Views namespace para Blade
        $this->loadViewsFrom(
            plugin_path('versions', 'resources/views'),
            'versions'
        );

        // Config
        $this->mergeConfigFrom(
            plugin_path('versions', 'config/versions.php'),
            'versions'
        );
    }
}
```

> Migrations e commands são auto-descobertos — não precisam ser registrados manualmente.

---

## 8. Sistema de Permissões

### 8.1 Registro da permissão custom

Feito dentro de `VersionsPlugin::register()`:

```php
Subuser::registerCustomPermissions(
    'versions',               // grupo (aparece na UI de permissões)
    ['change'],               // chaves → gera "versions.change"
    null,                     // translation_prefix (null = usa a chave diretamente)
    'tabler-arrows-exchange', // ícone Tabler Icons
    false                     // hidden from UI?
);
```

### 8.2 Como verificar permissão na Page

```php
// Estático (canAccess)
user()?->can('versions.change', $server)

// De instância
user()?->can('versions.change', $this->getServer())
```

---

## 9. Filament Page (`VersionsPage.php`)

### 9.1 Padrões críticos

```php
<?php

namespace Pelican\Versions\Filament\Server\Pages;

use App\Models\Server;
use Filament\Facades\Filament;
use Livewire\Component;

class VersionsPage extends \Filament\Pages\Page
{
    // View Blade
    protected static string $view = 'versions::filament.server.pages.versions-page';

    // Ícone da navegação (Tabler Icons)
    protected static ?string $navigationIcon = 'tabler-arrows-exchange';

    // Posição no menu lateral
    protected static ?int $navigationSort = 8;

    // Rota
    protected static string $routePath = 'versions';

    // ─── Visibilidade: só servidores Minecraft ───────────────────────────────

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return parent::canAccess()
            && $server instanceof Server
            && in_array('minecraft', array_map('strtolower', (array) $server->egg->tags), true)
            && user()?->can('versions.change', $server);
    }

    // ─── Helper para pegar o servidor atual ──────────────────────────────────

    private function getServer(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        return $server;
    }
}
```

### 9.2 Estado Livewire (propriedades reativas)

```php
// Seleção do usuário
public string $selectedSoftware   = '';
public string $selectedMcVersion  = '';
public int    $selectedBuild      = 0; // 0 = latest

// Estado da troca em andamento
public bool   $isChanging         = false;
public int    $changeId           = 0;
public string $changeStatus       = '';
public string $changeLog          = '';
public string $changeError        = '';

// Lista de softwares/builds (carregada via MCJars)
public array  $softwareTypes      = []; // do /api/v2/types
public array  $availableVersions  = []; // versões MC para o software selecionado
public array  $availableBuilds    = []; // builds para a versão selecionada

// Status atual do servidor (Wings)
public string $containerStatus    = 'offline';
```

### 9.3 Polling de progresso

```php
// Polling a cada 2 segundos enquanto está trocando
#[\Livewire\Attributes\On('refresh')]
public function pollProgress(): void
{
    if (!$this->isChanging || !$this->changeId) {
        return;
    }

    $record = VersionChange::find($this->changeId);
    if (!$record) return;

    $this->changeStatus = $record->status;
    $this->changeLog    = $record->log ?? '';
    $this->changeError  = $record->error_message ?? '';

    if (!$record->isActive()) {
        $this->isChanging = false;
    }
}
```

No blade, usar `wire:poll.2000ms="pollProgress"` ou `$this->dispatch` com interval.

---

## 10. MCJars API — Fonte de Dados

**Base URL:** `https://versions.mcjars.app/api/v2`

### 10.1 Listar todos os softwares disponíveis

```
GET https://versions.mcjars.app/api/v2/types
```

### 10.2 Listar builds por software e versão Minecraft

```
GET https://versions.mcjars.app/api/v2/builds/{TYPE}/{mc_version}
```

Exemplo: `GET /api/v2/builds/PAPER/1.21.4`

**Resposta (cada build):**
```json
{
  "id": 233760,
  "uuid": "e60f6fa0-...",
  "versionId": "1.21.4",
  "projectVersionId": null,
  "type": "PAPER",
  "experimental": false,
  "name": "#232",
  "buildNumber": 232,
  "jarUrl": "https://fill-data.papermc.io/.../paper-1.21.4-232.jar",
  "jarSize": 51437498,
  "zipUrl": null,
  "zipSize": null,
  "installation": [[
    {
      "type": "download",
      "url": "https://fill-data.papermc.io/.../paper-1.21.4-232.jar",
      "file": "server.jar",
      "size": 51437498
    }
  ]],
  "changes": ["Fix infinite loop in RegionFile IO"],
  "created": "2025-06-09T12:18:55.778"
}
```

### 10.3 `McJarsService.php`

```php
<?php

namespace Pelican\Versions\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class McJarsService
{
    private const BASE = 'https://versions.mcjars.app/api/v2';
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Retorna todos os tipos de software agrupados por categoria.
     * @return array<string, array<string, array>>
     */
    public function getTypes(): array
    {
        return Cache::remember('versions:mcjars:types', self::CACHE_TTL, function () {
            $response = Http::timeout(15)->get(self::BASE . '/types');
            return $response->successful() ? ($response->json('types') ?? []) : [];
        });
    }

    /**
     * Retorna os builds disponíveis para um software/versão MC.
     * @return array<int, array>  Lista de builds, do mais novo para o mais antigo
     */
    public function getBuilds(string $type, string $mcVersion): array
    {
        $key = "versions:mcjars:builds:{$type}:{$mcVersion}";
        return Cache::remember($key, self::CACHE_TTL, function () use ($type, $mcVersion) {
            $response = Http::timeout(15)->get(self::BASE . "/builds/{$type}/{$mcVersion}");
            return $response->successful() ? ($response->json('builds') ?? []) : [];
        });
    }

    /**
     * Retorna todas as versões Minecraft disponíveis para um tipo.
     */
    public function getMcVersionsForType(string $type): array
    {
        return [];
    }
}
```

---

## 11. Modelo de Dados — `version_changes`

### 11.1 Migration

```php
Schema::create('version_changes', function (Blueprint $table) {
    $table->id();
    $table->unsignedInteger('server_id');
    $table->string('software');              // PAPER, VANILLA, FABRIC, etc.
    $table->string('minecraft_version');     // 1.21.4
    $table->unsignedInteger('build_number')->nullable(); // 232
    $table->string('build_name')->nullable();            // "#232" ou "0.19.5"
    $table->text('jar_url');                 // URL usada para download
    $table->unsignedBigInteger('jar_size')->nullable();  // bytes esperados
    $table->string('status')->default('pending'); // pending|changing|done|failed
    $table->text('error_message')->nullable();
    $table->longText('log')->nullable();     // log de execução em tempo real
    $table->timestamps();

    $table->foreign('server_id')
        ->references('id')
        ->on('servers')
        ->cascadeOnDelete();

    $table->index(['server_id', 'status']);
});
```

### 11.2 Constantes de status

```php
const STATUS_PENDING  = 'pending';
const STATUS_CHANGING = 'changing';
const STATUS_DONE     = 'done';
const STATUS_FAILED   = 'failed';
```

---

## 12. Job (`ChangeVersionJob.php`)

```php
<?php

namespace Pelican\Versions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Pelican\Versions\Models\VersionChange;
use Pelican\Versions\Services\VersionChangeService;
use Throwable;

class ChangeVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hora
    public int $tries   = 1;    // Não retentar — operação não é idempotente

    public function __construct(
        public readonly int $changeRecordId
    ) {}

    public function handle(VersionChangeService $service): void
    {
        $record = VersionChange::find($this->changeRecordId);

        if (!$record || $record->status !== VersionChange::STATUS_PENDING) {
            return;
        }

        $record->markChanging();
        $record->appendLog('Job started.');

        $service->execute($record);
    }

    public function failed(Throwable $e): void
    {
        $record = VersionChange::find($this->changeRecordId);
        if ($record) {
            $record->markFailed($e->getMessage());
            $record->appendLog('JOB FAILED: ' . $e->getMessage());
        }
    }
}
```

---

## 13. Service (`VersionChangeService.php`)

```php
<?php

namespace Pelican\Versions\Services;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use RuntimeException;
use Throwable;
use Pelican\Versions\Models\VersionChange;

class VersionChangeService
{
    public function __construct(
        private DaemonFileRepository $fileRepo
    ) {}

    public function execute(VersionChange $record): void
    {
        $server = $record->server;

        // Vincula o repositório ao servidor correto
        $this->fileRepo->setServer($server);

        try {
            $this->stepBackup($record);
            $this->stepDownload($record);
            $this->stepVerify($record);

            $record->markDone();
            $record->appendLog('Version change complete. Restart the server to apply.');

        } catch (Throwable $e) {
            $record->markFailed($e->getMessage());
            $record->appendLog('ERROR: ' . $e->getMessage());
            throw $e;
        }
    }

    // ─── Steps ───────────────────────────────────────────────────────────────

    private function stepBackup(VersionChange $record): void
    {
        $record->appendLog('Checking for existing server.jar to backup...');

        try {
            $entries = $this->fileRepo->getDirectory('/');
            $hasJar  = collect($entries)->contains(
                fn ($e) => ($e['name'] ?? '') === 'server.jar' && !($e['directory'] ?? false)
            );

            if ($hasJar) {
                $this->fileRepo->renameFiles('/', [
                    ['from' => 'server.jar', 'to' => 'server.jar.bak']
                ]);
                $record->appendLog('  Renamed server.jar → server.jar.bak');
            } else {
                $record->appendLog('  No existing server.jar found, skipping backup.');
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Backup step failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function stepDownload(VersionChange $record): void
    {
        $record->appendLog('Telling Wings to download the new JAR...');
        $record->appendLog("  Source: {$record->jar_url}");

        try {
            $this->fileRepo->pull($record->jar_url, '/', [
                'filename'   => 'server.jar',
                'foreground' => false,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Download request failed: ' . $e->getMessage(), 0, $e);
        }

        // Aguarda o arquivo aparecer (polling de tamanho)
        $deadline = time() + 600; // 10 min máximo
        $lastSize = -1;
        $stable   = 0;

        while (time() < $deadline) {
            sleep(4);
            $size = $this->getRemoteFileSize('server.jar');

            if ($size === null) {
                $record->appendLog('  Waiting for download to start...');
                continue;
            }

            if ($size > 0 && $size === $lastSize) {
                if (++$stable >= 2) {
                    break;
                }
            } else {
                $stable = 0;
                $record->appendLog('  Downloaded ' . $this->humanBytes($size));
            }

            $lastSize = $size;
        }

        if (($lastSize ?? 0) <= 0) {
            throw new RuntimeException('Download did not complete — server.jar not found after waiting.');
        }

        $record->appendLog("  Download complete: " . $this->humanBytes($lastSize));
    }

    private function stepVerify(VersionChange $record): void
    {
        if (!$record->jar_size) {
            $record->appendLog('  Size verification skipped (no expected size provided).');
            return;
        }

        $actual = $this->getRemoteFileSize('server.jar');

        if ($actual === null) {
            throw new RuntimeException('Verification failed: server.jar not found after download.');
        }

        $tolerance = (int) ($record->jar_size * 0.01);
        if (abs($actual - $record->jar_size) > $tolerance) {
            throw new RuntimeException(
                "Size mismatch: expected {$record->jar_size} bytes, got {$actual} bytes."
            );
        }

        $record->appendLog('  Size verified: ' . $this->humanBytes($actual));
    }

    private function getRemoteFileSize(string $filename): ?int
    {
        try {
            $entries = $this->fileRepo->getDirectory('/');
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
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
```

---

## 14. APIs do Pelican usadas

### 14.1 `DaemonFileRepository` — operações de arquivo via Wings

```php
use App\Repositories\Daemon\DaemonFileRepository;

// Vincular ao servidor antes de usar:
$fileRepo->setServer($server);

// Métodos usados no plugin:
$fileRepo->getDirectory(string $path): array
$fileRepo->renameFiles(string $root, array $files): void
$fileRepo->pull(string $url, string $directory, array $params): void
$fileRepo->deleteFiles(string $root, array $files): void
$fileRepo->getContent(string $path, int $maxBytes): string
$fileRepo->putContent(string $path, string $content): void
```

### 14.2 `DaemonServerRepository` — estado do servidor

```php
use App\Repositories\Daemon\DaemonServerRepository;

$serverRepo->setServer($server)->getDetails();
$serverRepo->setServer($server)->power('stop');
```

---

## 15. Enums importantes do Pelican

- `ContainerStatus` (Running, Offline, Starting, Stopping, etc.)
- `ServerState` (Installing, Suspended, etc.)
- `SubuserPermission` (FileRead, FileCreate, etc.)

---

## 16. Padrões de código confirmados do addon de referência

### 16.1 `canAccess()` com verificação de tag

```php
public static function canAccess(): bool
{
    $server = Filament::getTenant();

    return parent::canAccess()
        && $server instanceof Server
        && in_array('minecraft', array_map('strtolower', (array) $server->egg->tags), true)
        && user()?->can('versions.change', $server);
}
```

### 16.2 `getServer()` helper dentro de uma Page

```php
private function getServer(): Server
{
    /** @var Server $server */
    $server = Filament::getTenant();
    return $server;
}
```

### 16.3 Dispatch do Job com fila

```php
ChangeVersionJob::dispatch($record->id)->onQueue('default');
```

---

## 17. Config (`config/versions.php`)

```php
<?php

return [
    'download_timeout' => env('VERSIONS_DOWNLOAD_TIMEOUT', 600),
    'api_cache_ttl' => env('VERSIONS_API_CACHE_TTL', 300),
    'keep_backup' => env('VERSIONS_KEEP_BACKUP', true),
    'default_mc_versions' => [
        '1.21.5', '1.21.4', '1.21.3', '1.21.1', '1.20.6', '1.20.4',
        '1.20.2', '1.20.1', '1.19.4', '1.18.2', '1.17.1', '1.16.5',
    ],
];
```

---

## 18. UX da Page — comportamento esperado

- Visualização de softwares agrupados por categoria (MCJars)
- Seleção de versão e build
- Banner de status do servidor Wings (aviso se estiver rodando)
- Polling em tempo real durante a execução do Job
- Histórico de trocas anteriores

---

## 19. Fluxo completo da troca

```
[Usuário clica "Change Version"]
         │
         ▼
[Page cria VersionChange (status=pending)]
         │
         ▼
[ChangeVersionJob::dispatch()->onQueue('default')]
         │
         ▼
[Job::handle()] → record->markChanging()
         │
         ▼
[VersionChangeService::execute()]
   ├── stepBackup()
   ├── stepDownload()
   ├── stepVerify()
   └── record->markDone()
         │
         ▼
[Page polling detecta status=done → exibe sucesso]
[Usuário reinicia o servidor]
```

---

## 20. Checklist de implementação

- [x] `plugin.json` (Já presente em `plugins/versions/plugin.json`)
- [x] `database/migrations/2024_01_01_000000_create_version_changes_table.php` (Já presente)
- [x] `src/Models/VersionChange.php` (Já presente)
- [ ] `config/versions.php`
- [ ] `src/Services/McJarsService.php`
- [ ] `src/Services/VersionChangeService.php`
- [ ] `src/Jobs/ChangeVersionJob.php`
- [ ] `src/VersionsPlugin.php`
- [ ] `src/Providers/VersionsServiceProvider.php`
- [ ] `src/Filament/Server/Pages/VersionsPage.php`
- [ ] `resources/views/filament/server/pages/versions-page.blade.php`

---

## 21. Notas finais

- **Namespace:** `Pelican\Versions`
- **Sem composer extras:** Apenas dependências nativas do Pelican
- **Wings é obrigatório:** Todas as operações passam por `DaemonFileRepository`
- **MCJars:** Endpoint `installation[0][0].url` unificado para todos os softwares
