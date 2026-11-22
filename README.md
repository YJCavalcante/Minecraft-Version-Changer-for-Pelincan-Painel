# Minecraft Version Changer for Pelican Panel (v1.2.0)

Effortlessly switch your Minecraft server's software and version directly from the Pelican Panel web interface. Featuring dynamic egg variable synchronization, universal hybrid & modpack detection, transactional rollback safety, and zero theme interference.

---

## Highlights

* **Dynamic Executable Variable Synchronization**: Inspects the server's Egg configuration (`SERVER_JARFILE`, `JARFILE`, `JAR_NAME`, `SERVER_JAR`) to identify the target executable filename, downloads into staging files, extracts `.zip` bundles cleanly, and syncs updated configuration with Wings (`$serverRepo->sync()`).
* **Universal Multi-Tier Software Detection**: Recognizes Vanilla, Paper, Purpur, Folia, Spigot, Fabric, Quilt, Forge, NeoForge, and hybrid servers (Youer, Mohist, Magma, Arclight, CatServer, Ketting, Banner, Thermos/Cauldron, Crucible, Cardboard) plus Modpack Manager records (`modpack_installs` / `/.modpack-manager.json` / `variables.txt` / `manifest.json` / `modrinth.index.json`).
* **Transactional Swap & Automatic Rollback**: Downloads to temporary `.versions-new` files, verifies size integrity, backs up existing JARs (`.bak`), `/libraries` (`libraries.versions-old`), and transient launch artifacts (`.versions-old`), and automatically rolls back and restarts on failure.
* **Livewire & SSRF Hardening**: Locks sensitive Livewire properties (`#[Locked]`), re-resolves builds server-side, restricts upstream URLs via `isAllowedDownloadUrl()` / `VERSIONS_ALLOWED_HOSTS`, and wraps queued version changes in a pessimistic row lock (`Server::lockForUpdate()`).
* **Scoped Modal & Clean UI**: Full-screen overlay (`inset: 0 !important; z-index: 99999 !important`) isolated under `.mvc` CSS custom properties with native light and dark mode support and no Vite/Tailwind rebuild dependency.
* **Strict Zero-Comment Codebase**: Clean production code across all PHP and Blade files.

---

## Automated Lifecycle Engine

When a version change is initiated, `VersionChangeService` orchestrates a 6-step workflow with rollback tracking:

* **Step 1 — Graceful Power Management (`stepPowerStopIfRunning`)**
  - Checks live container state via Wings (`getDetails()['state']`).
  - Sends `power('stop')` and polls up to `VERSIONS_STOP_WAIT` seconds (default `60`), aborting safely if `VERSIONS_ABORT_IF_NOT_STOPPED=true` and the server fails to stop in time.

* **Step 2 — Staged Download (`stepDownload`)**
  - Instructs Wings to pull asynchronously (`foreground => false`) into `*.versions-new` or `server.versions-new.zip`.
  - Enforces a startup grace timeout (`VERSIONS_DOWNLOAD_START_TIMEOUT`, default `90s`) if the file never appears, plus full `VERSIONS_DOWNLOAD_TIMEOUT` (default `600s`), requiring exact size match or 3 consecutive stable readings.

* **Step 3 — Integrity Verification (`stepVerify`)**
  - Confirms the staged artifact exists and meets minimum binary size requirements (`>= 4096` bytes) and matches upstream `jar_size` within 1% tolerance.

* **Step 4 — Reversible Swap & Extraction (`stepSwap`)**
  - Moves existing target JAR / `server.jar` to `.bak`, transient launch scripts/loaders to `*.versions-old`, and `/libraries` to `libraries.versions-old` (`VERSIONS_CLEAN_LIBRARIES=true`).
  - Optionally wipes root files when **Clean Install** is confirmed (preserving `.bak`, `libraries.versions-old`, and `eula.txt`), then extracts `.zip` bundles or moves the staged JAR into place.
  - Automatically rolls back previous files on any error (`rollback`).

* **Step 5 — Mojang EULA & Egg Variable Synchronization (`stepHandleEula` & `stepSyncEggVariable`)**
  - Preserves existing `eula=true` agreements without silently auto-accepting EULA terms.
  - Synchronizes `SERVER_JARFILE` / `JARFILE` / `JAR_NAME` / `SERVER_JAR` with Wings when needed.

* **Step 6 — Automatic Restart (`stepPowerRestart`)**
  - Restarts the server automatically if it was online before the change and `VERSIONS_AUTO_RESTART=true`.

---

## Scheduled Console Commands

* `php artisan versions:warm-cache` — Scheduled daily at `03:00`; refreshes MCJars catalog, versions, and latest release builds cleanly while aborting early if upstream is unreachable.
* `php artisan versions:prune` — Scheduled daily at `03:30`; marks stuck `pending`/`changing` records older than `VERSIONS_STALE_AFTER` minutes as `failed` and deletes finished records older than `VERSIONS_HISTORY_RETENTION` days while keeping the latest completed change per server.

---

## Configuration Reference (`.env`)

All behaviors can be customized through environment variables in your Pelican `.env` file:

| Variable | Default | Description |
| :--- | :--- | :--- |
| `VERSIONS_AUTO_STOP` | `true` | Gracefully shut down running servers before replacing files |
| `VERSIONS_ABORT_IF_NOT_STOPPED` | `true` | Abort safely before touching files if the server fails to stop |
| `VERSIONS_STOP_WAIT` | `60` | Maximum seconds to wait for clean container shutdown |
| `VERSIONS_AUTO_RESTART` | `true` | Automatically reboot the server once the update succeeds |
| `VERSIONS_CLEAN_LIBRARIES` | `true` | Move `/libraries/` aside to avoid cross-version classpath collisions |
| `VERSIONS_KEEP_BACKUP` | `true` | Retain previous executable as `*.bak` after completion |
| `VERSIONS_DOWNLOAD_START_TIMEOUT` | `90` | Maximum seconds to wait for Wings to begin creating the download file |
| `VERSIONS_DOWNLOAD_TIMEOUT` | `600` | Maximum seconds to wait for Wings download completion |
| `VERSIONS_API_CACHE_TTL` | `21600` | Cache duration in seconds (6h) for MCJars catalog/version API queries |
| `VERSIONS_BUILDS_CACHE_TTL` | `900` | Cache duration in seconds (15m) for MCJars build listings |
| `VERSIONS_BUILDS_LIMIT` | `60` | Maximum number of builds cached/displayed per version |
| `VERSIONS_DETECTION_CACHE_TTL` | `30` | Short cache duration in seconds for server software detection |
| `VERSIONS_STALE_AFTER` | `90` | Minutes without worker heartbeat before a job is marked failed |
| `VERSIONS_HISTORY_RETENTION` | `90` | Days to retain historical change logs (`versions:prune`) |
| `VERSIONS_WARM_BUILDS_VERSIONS` | `2` | Number of latest releases per software to pre-warm builds for |
| `VERSIONS_ALLOWED_HOSTS` | `""` | Optional comma-separated allow-list of permitted download host patterns |
| `VERSIONS_NAV_SORT` | `8` | Sidebar navigation order for the Version Changer page |
| `VERSIONS_QUEUE` | `null` | Custom queue name (`null` uses Pelican's default queue; configure `retry_after > 3600`) |

---

## Installation & Updating

### Web Interface

1. Download the plugin package (`versions.zip`).
2. In Pelican Panel go to **Admin → Plugins → Import from file** and upload the archive.
3. Click **Install** next to **Minecraft Version Changer**.

### Command Line (CLI)

```bash
unzip -o versions.zip -d /var/www/pelican/plugins/
chown -R www-data:www-data /var/www/pelican/plugins/versions
php /var/www/pelican/artisan p:plugin:install versions
php /var/www/pelican/artisan optimize:clear
php /var/www/pelican/artisan queue:restart
```

---

## Permissions

The **Version Changer** page appears in the server sidebar for Minecraft servers:

- **Panel Administrators & Server Owners**: Full access by default.
- **Subusers**: Controlled via `versions.change` or `settings.reinstall` server permissions.

---

## Author & License

- **Yuri J. Cavalcante** ([@YJCavalcante](https://github.com/YJCavalcante))
- Licensed under the [MIT License](LICENSE).
