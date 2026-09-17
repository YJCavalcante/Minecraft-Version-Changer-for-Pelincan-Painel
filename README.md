# Minecraft Version Changer for Pelican Panel (v1.0.2-beta)

[![Pelican Panel](https://img.shields.io/badge/Pelican-Plugin-blue.svg)](https://pelican.dev)
[![Status](https://img.shields.io/badge/Status-Beta-orange.svg)](#)
[![Version](https://img.shields.io/badge/Version-1.0.2--beta-green.svg)](#)
[![License](https://img.shields.io/badge/License-MIT-purple.svg)](LICENSE)

Effortlessly switch your Minecraft server's software and version directly from the Pelican Panel web interface. Fully automated lifecycle management with zero manual SFTP uploads or console commands.

---

## 🚀 Overview of Version 1.0.2-beta

Version **1.0.2-beta** introduces a complete **automated lifecycle engine** and an in-depth **resilience overhaul**, ensuring seamless version transitions, safe server restarts, cross-version dependency cleanup, and native support for both `.jar` and `.zip` distributions.

---

## ⚡ 6-Step Automated Lifecycle Engine

When a version change is initiated, the backend execution service (`VersionChangeService`) orchestrates a 6-stage automated workflow:

### 1. 🛑 Graceful Power Management (`stepPowerStopIfRunning`)
- Inspects the live container state via the Wings daemon.
- If the server is `running`, `starting`, or `restarting`, it sends a graceful `power('stop')` signal to save world data and flush chunks.
- Actively polls the container state up to 21 seconds (handling Docker `stopping`, `offline`, and `exited` states).
- If the server is already in the process of `stopping`, it waits for completion without sending redundant signals.

### 2. 💾 Safety Backup & Dependency Cleaning (`stepBackupAndClean`)
- **Safety Backup**: Renames the existing `server.jar` to `server.jar.bak` (configurable via `VERSIONS_KEEP_BACKUP`).
- **Clean Removal Fallback**: If backups are disabled, it deletes the previous `server.jar` so size monitoring never reads obsolete file data.
- **Dependency Purge**: Automatically wipes the legacy `/libraries/` folder to prevent classpath collisions and fatal crashes caused by incompatible Java libraries between Minecraft versions.

### 3. 📦 Daemon-Direct Download & ZIP Handling (`stepDownload`)
- Triggers an asynchronous HTTP pull directly on the Wings daemon (`foreground => false`), bypassing PHP web server timeouts.
- **Archive Detection**: Automatically detects whether the upstream distribution from MCJars is a standard `.jar` or a `.zip` archive (e.g., Forge and NeoForge server bundles `server.jar.zip`).
- **Stabilization Polling**: Monitors download progress by reading remote file size increments without log spamming.

### 4. 🔍 Integrity Verification & Auto-Extraction (`stepVerifyAndExtract`)
- Verifies downloaded file existence and checks file size against expected upstream bytes (with 1% tolerance for compression and header differences).
- **Automatic Decompression**: For `.zip` distributions (Forge/NeoForge), it invokes native Wings decompression (`DaemonFileRepository::decompressFile('/', 'server.zip')`) to extract the server bundle into the server root.
- Automatically removes the temporary `.zip` archive once extraction succeeds.

### 5. 📜 EULA Agreement & Daemon Synchronization (`stepAcceptEula` & `stepSyncEggVariable`)
- **Auto-Accept EULA**: Writes `eula=true` to `eula.txt` automatically, eliminating first-boot EULA crashes.
- **Egg Variable Synchronization**: Ensures the `SERVER_JARFILE` egg variable is set to `server.jar` and calls `$serverRepo->sync()` to refresh container startup arguments on Wings immediately.

### 6. 🚀 Automatic Server Reboot (`stepPowerRestart`)
- If the server was running before the change began, the addon automatically sends `power('start')` to Wings.
- The server boots up immediately on the newly installed version with zero manual intervention required.
- If the server was offline, it remains offline and ready to start.

---

## 🛠️ Audit & Resilience Improvements

- **Standard Laravel Queue Compatibility**: Configured `ChangeVersionJob` to use Pelican's default queue worker (`config('versions.queue', null)`), ensuring background jobs process immediately on standard installations without requiring dedicated queue worker flags.
- **Comprehensive Minecraft Server Detection**: Expanded `canAccess()` to recognize community eggs named `Paper`, `Purpur`, `Forge`, `Fabric`, `Spigot`, `Bungee`, `Velocity`, and eggs using `server.jar` in their startup command.
- **On-Demand Build Resolution**: Added a backend safety fallback in `startVersionChange` to fetch builds directly from the API if rapid user interaction causes state loss during Livewire hydration.
- **Forge & NeoForge Size Fallback**: `resolveJarDetails()` automatically falls back to `zipSize` when `jarSize` is null, preventing false size mismatch errors.
- **Zero Frontend Interference**: 100% scoped inline CSS (`.mvc`). Operates independently of Vite or Tailwind asset compilation, guaranteeing zero conflicts with existing panel themes.

---

## ⚙️ Configuration Reference (`.env`)

All behaviors can be customized through environment variables in your Pelican `.env` file:

| Variable | Default | Description |
| :--- | :--- | :--- |
| `VERSIONS_AUTO_STOP` | `true` | Gracefully shut down running servers before replacing files |
| `VERSIONS_AUTO_RESTART` | `true` | Automatically reboot the server once the update succeeds |
| `VERSIONS_AUTO_ACCEPT_EULA` | `true` | Automatically write `eula=true` to `eula.txt` |
| `VERSIONS_CLEAN_LIBRARIES` | `true` | Wipe `/libraries/` folder to avoid cross-version classpath collisions |
| `VERSIONS_KEEP_BACKUP` | `true` | Retain previous `server.jar` as `server.jar.bak` |
| `VERSIONS_DOWNLOAD_TIMEOUT` | `600` | Maximum seconds to wait for Wings to pull and verify files |
| `VERSIONS_API_CACHE_TTL` | `300` | Cache duration in seconds for MCJars catalog API queries |
| `VERSIONS_NAV_SORT` | `8` | Sidebar navigation order for the Version Changer page |
| `VERSIONS_QUEUE` | `null` | Custom queue name (`null` uses Pelican's default queue) |

---

## 📦 Installation

### Method 1: Web Interface (Recommended)

1. Download the latest `versions_v1.0.2-beta.zip` (or `versions.zip`) from the [Releases](https://github.com/YJCavalcante/Minecraft-Version-Changer-for-Pelincan-Painel/releases) page.
2. Log into your Pelican Panel as an administrator and go to **Admin → Plugins**.
3. Click **Import from file** in the top right corner.
4. Upload the zip file.
5. Find **Minecraft Version Changer** in the list and click **Install**.

### Method 2: Command Line (CLI)

1. Extract the release archive into your panel plugins directory:
   ```bash
   unzip versions_v1.0.2-beta.zip -d /var/www/pelican/plugins/versions/
   ```
2. Set ownership and permissions:
   ```bash
   chown -R www-data:www-data /var/www/pelican/plugins/versions
   chmod -R 755 /var/www/pelican/plugins/versions
   ```
3. Run the installer:
   ```bash
   php /var/www/pelican/artisan p:plugin:install versions
   ```

---

## 💡 Panel Permissions & Best Practices

To ensure Pelican Panel background queues, plugins, and theme compilers work without permissions issues, ensure the panel directory belongs to the web server user (`www-data`):

```bash
# Ensure correct ownership across the panel
sudo chown -R www-data:www-data /var/www/pelican

# Ensure cache directories are writable for theme compilers (yarn/vite)
sudo mkdir -p /var/www/.cache /var/www/.yarn
sudo chown -R www-data:www-data /var/www/.cache /var/www/.yarn
```

> [!NOTE]
> **Theme Independence**: Minecraft Version Changer is an independent plugin that does **not** modify or trigger Vite asset builds. It will never interfere with, break, or overwrite your panel themes.

---

## 🔒 Permissions

The **Version Changer** navigation item appears in the sidebar for Minecraft servers:

- **Panel Administrators & Server Owners**: Full access by default.
- **Subusers**: Access can be granted via the custom `versions.change` permission or standard `settings.reinstall` permission in server subuser settings.

---

## 👤 Author

- **Yuri J. Cavalcante** ([@YJCavalcante](https://github.com/YJCavalcante))

## 📄 License

This project is licensed under the [MIT License](LICENSE).
