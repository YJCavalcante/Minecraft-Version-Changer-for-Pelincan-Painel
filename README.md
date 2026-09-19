# Minecraft Version Changer for Pelican Panel (v1.1.0-beta)

Effortlessly switch your Minecraft server's software and version directly from the Pelican Panel web interface. Featuring dynamic egg variable synchronization, multi-tier real-time software detection, automated lifecycle orchestration, and zero theme interference.

---

## 🚀 Highlights of Version 1.1.0-beta

Version **1.1.0-beta** brings major stability and intelligence upgrades over previous versions:

* **🔄 Dynamic `SERVER_JARFILE` Synchronization**: Automatically queries the server's Egg configuration to identify the target executable variable (`SERVER_JARFILE`, `JARFILE`, etc.). Downloads directly to the required filename and, for zipped distributions (Forge/NeoForge), extracts and renames the launch JAR automatically, instantly synchronizing container startup arguments with Wings.
* **🔍 Multi-Tier Real-Time Detection**: Automatically recognizes server software changes made outside the plugin — including **Modpack Manager** installations (`modpack_installs` and `/.modpack-manager.json`), server root disk fingerprints (`unix_args.txt`, `run.sh`, `mohist.yml`, `purpur.yml`, `magma.yml`, `arclight.conf`, `paper.yml`, `fabric-server-launch.jar`), and startup environment variables (`MOHIST_VERSION`, etc.). No more displaying "Vanilla" when running Forge or Fabric!
* **⚡ Network & Livewire Hardening**:
  - Removed synchronous remote update checks (`"update_url": null`), completely eliminating the 5-second cURL timeout freezes (`cURL error 28: Operation timed out after 5002ms`) common on VPS environments with unrouted IPv6.
  - Eliminated circular `$this->dispatch()` loops and isolated polling to prevent Livewire `TooManyCallsException` (199 calls exceeding the 50-call limit).
  - Enforced input debouncing (`debounce.250ms`) across all search filters.
* **🎨 Scoped Full-Viewport Modal**: Guaranteed full-screen modal overlay (`inset: 0 !important; z-index: 99999 !important; backdrop-filter: blur(4px)`) completely isolated under the `.mvc` CSS namespace, preventing clipping under custom panel layouts or third-party themes.
* **🧹 Strict Zero-Comment Codebase**: 100% clean production code across all PHP, Blade, and CSS files.

---

## Automated Lifecycle Engine

When a version change is initiated, the backend execution service (`VersionChangeService`) orchestrates an automated workflow:

* **Graceful Power Management (`stepPowerStopIfRunning`)**
  - Inspects live container state via the Wings daemon.
  - If the server is `running`, `starting`, or `restarting`, it sends a graceful `power('stop')` signal to save world data and flush chunks.
  - Actively polls the container state up to 21 seconds (handling Docker `stopping`, `offline`, and `exited` states).
  - If the server is already in the process of `stopping`, it waits for completion without sending redundant signals.

* **Safety Backup & Dependency Cleaning (`stepBackupAndClean`)**
  - **Safety Backup**: Renames the existing executable to `server.jar.bak` (configurable via `VERSIONS_KEEP_BACKUP`).
  - **Clean Removal Fallback**: If backups are disabled, it deletes the previous JAR so size monitoring never reads obsolete file data.
  - **Dependency Purge**: Automatically wipes the legacy `/libraries/` folder to prevent classpath collisions and fatal crashes caused by incompatible Java libraries between Minecraft versions.

* **Daemon-Direct Download & ZIP Handling (`stepDownload`)**
  - Triggers an asynchronous HTTP pull directly on the Wings daemon (`foreground => false`), bypassing PHP web server timeouts.
  - **Dynamic File Naming**: Uses the exact filename configured in the server's egg variables (e.g. `server.jar`, `custom.jar`).
  - **Archive Detection**: Automatically detects whether the upstream distribution from MCJars is a standard `.jar` or a `.zip` archive (e.g., Forge and NeoForge server bundles `server.jar.zip`).
  - **Stabilization Polling**: Monitors download progress by reading remote file size increments without log spamming.

* **Integrity Verification & Auto-Extraction (`stepVerifyAndExtract`)**
  - Verifies downloaded file existence and checks file size against expected upstream bytes (with 1% tolerance for compression and header differences).
  - **Automatic Decompression**: For `.zip` distributions (Forge/NeoForge), it invokes native Wings decompression (`DaemonFileRepository::decompressFile('/', 'server.zip')`) to extract the server bundle into the server root.
  - **Launch JAR Alignment**: Automatically renames the extracted loader JAR to the egg's expected executable name and purges the temporary `.zip` archive.

* **EULA Agreement & Daemon Synchronization (`stepHandleEula` & `stepSyncEggVariable`)**
  - **Strict Mojang EULA Compliance**: In strict compliance with Mojang's Commercial Usage Guidelines, automatic EULA acceptance is completely removed. The addon detects and preserves any existing `eula=true` agreements on the server, while deferring new agreements to Pelican's native console prompt (`MinecraftEulaSchema`) where server owners explicitly accept the terms on startup.
  - **Egg Variable Synchronization**: Ensures the egg executable variable is set to the target filename and calls `$serverRepo->sync()` to refresh container startup arguments on Wings immediately.

* **Automatic Server Reboot (`stepPowerRestart`)**
  - If the server was running before the change began, the addon automatically sends `power('start')` to Wings.
  - The server boots up immediately on the newly installed version with zero manual intervention required.
  - If the server was offline, it remains offline and ready to start.

---

## 🛠️ Built-in Safety Features

- **Active Version Detection**: Multi-level detection pipeline (Modpack Manager, disk signatures, egg startup variables, and database history) displaying an active status card at the top of the interface.
- **Clean Install Toggle**: Optional toggle in the install modal that wipes all existing server files before installing the new version — ideal for ecosystem migrations (e.g. Vanilla/Paper → Forge/Fabric). Only `server.jar.bak` and `eula.txt` are preserved. Unchecked and safe by default.
- **Java Compatibility Matrix**: Automatically detects the Java version required by the selected Minecraft version (via MCJars API) and compares it against the server's configured Docker image. Displays a clear inline warning in the install modal when a mismatch is detected (e.g. the selected version requires Java 21 but the server image uses Java 17), preventing failed server starts.
- **Standard Laravel Queue Compatibility**: Configured `ChangeVersionJob` to use Pelican's default queue worker (`config('versions.queue', null)`), ensuring background jobs process immediately on standard installations without requiring dedicated queue worker flags.
- **Comprehensive Minecraft Server Detection**: Expanded `canAccess()` to recognize community eggs named `Paper`, `Purpur`, `Forge`, `Fabric`, `Spigot`, `Bungee`, `Velocity`, and eggs using `server.jar` in their startup command.
- **Zero Frontend Interference**: 100% scoped inline CSS (`.mvc`). Operates independently of Vite or Tailwind asset compilation, guaranteeing zero conflicts with existing panel themes.

---

## ⚙️ Configuration Reference (`.env`)

All behaviors can be customized through environment variables in your Pelican `.env` file:

| Variable | Default | Description |
| :--- | :--- | :--- |
| `VERSIONS_AUTO_STOP` | `true` | Gracefully shut down running servers before replacing files |
| `VERSIONS_AUTO_RESTART` | `true` | Automatically reboot the server once the update succeeds |
| `VERSIONS_CLEAN_LIBRARIES` | `true` | Wipe `/libraries/` folder to avoid cross-version classpath collisions |
| `VERSIONS_KEEP_BACKUP` | `true` | Retain previous `server.jar` as `server.jar.bak` |
| `VERSIONS_DOWNLOAD_TIMEOUT` | `600` | Maximum seconds to wait for Wings to pull and verify files |
| `VERSIONS_API_CACHE_TTL` | `300` | Cache duration in seconds for MCJars catalog API queries |
| `VERSIONS_NAV_SORT` | `8` | Sidebar navigation order for the Version Changer page |
| `VERSIONS_QUEUE` | `null` | Custom queue name (`null` uses Pelican's default queue) |

---

## 📦 Installation

### Method 1: Web Interface (Recommended)

1. Download the latest `versions_v1.1.0-beta.zip` (or `versions.zip`) from the [Releases](https://github.com/YJCavalcante/Minecraft-Version-Changer-for-Pelincan-Painel/releases) page.
2. Log into your Pelican Panel as an administrator and go to **Admin → Plugins**.
3. Click **Import from file** in the top right corner.
4. Upload the zip file.
5. Find **Minecraft Version Changer** in the list and click **Install**.

### Method 2: Command Line (CLI)

1. Extract the release archive into your panel plugins directory:
   ```bash
   unzip versions_v1.1.0-beta.zip -d /var/www/pelican/plugins/versions/
   ```
2. Set ownership and permissions:
   ```bash
   chown -R www-data:www-data /var/www/pelican/plugins/versions
   chmod -R 755 /var/www/pelican/plugins/versions
   ```
3. Run the installer:
   ```bash
   php /var/www/pelican/artisan p:plugin:install versions
   php /var/www/pelican/artisan optimize:clear
   ```

---

## 🔄 Updating to v1.1.0-beta

To update an existing installation to **v1.1.0-beta**:

```bash
# 1. Download and extract over the existing plugin folder
unzip -o versions_v1.1.0-beta.zip -d /var/www/pelican/plugins/

# 2. Fix permissions
chown -R www-data:www-data /var/www/pelican/plugins/versions

# 3. Clear Pelican caches
cd /var/www/pelican
php artisan optimize:clear
```

---

## 🔒 Permissions

The **Version Changer** navigation item appears in the sidebar for Minecraft servers:

- **Panel Administrators & Server Owners**: Full access by default.
- **Subusers**: Access can be granted via the custom `versions.change` permission or standard `settings.reinstall` permission in server subuser settings.

---

## 💡 Best Practices

To ensure Pelican Panel background queues, plugins, and web workers operate smoothly, ensure the panel directory belongs to the web server user (`www-data`):

```bash
# Ensure correct ownership across the panel
sudo chown -R www-data:www-data /var/www/pelican

# Ensure cache directories are writable
sudo mkdir -p /var/www/.cache /var/www/.yarn
sudo chown -R www-data:www-data /var/www/.cache /var/www/.yarn
```

---

## ❓ Frequently Asked Questions (FAQ)

### Does this plugin conflict with Vite or custom panel themes?
**No.** Version Changer uses 100% scoped CSS (`.mvc`) embedded directly in its Blade component. It does not alter global styles, does not invoke Vite compilers, and works seamlessly with all Pelican themes in both light and dark modes.

### What if I install a modpack via Modpack Manager or FTP?
**It detects it automatically.** In v1.1.0-beta, the multi-tier detection engine inspects Modpack Manager records and server root disk files (such as `unix_args.txt`, `run.sh`, `mohist.yml`, etc.). It will immediately identify the active software and version instead of showing "Vanilla".

### How does dynamic `SERVER_JARFILE` synchronization work?
The plugin detects the specific startup variable defined in the server's Egg (such as `SERVER_JARFILE=server.jar` or `JARFILE=custom.jar`). It downloads directly to that file and, if a zip bundle is extracted, renames the launch JAR to match the variable and notifies Wings (`$serverRepo->sync()`) so container restart arguments are always synchronized.

---

## 👤 Author

- **Yuri J. Cavalcante** ([@YJCavalcante](https://github.com/YJCavalcante))

## 📄 License

This project is licensed under the [MIT License](LICENSE).
