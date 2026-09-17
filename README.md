# Minecraft Version Changer

[![Pelican Panel](https://img.shields.io/badge/Pelican-Plugin-blue.svg)](https://pelican.dev)
[![Status](https://img.shields.io/badge/Status-Beta-orange.svg)](#)
[![Version](https://img.shields.io/badge/Version-1.0.0--beta-green.svg)](#)
[![License](https://img.shields.io/badge/License-MIT-purple.svg)](LICENSE)

Effortlessly switch your Minecraft server's software and version directly from the Pelican Panel web interface. No manual SFTP uploads or JAR file renaming required.

> [!WARNING]
> **Beta Release**: This plugin is currently in early beta and actively under development. Features and workflows are still being refined, and you may encounter bugs. Please report any issues or suggestions on GitHub!

---

## ✨ Features

- **Extensive Software Catalog**: Powered by the MCJars API v2. Supports over 25 server software distributions including **Paper**, **Purpur**, **Vanilla**, **Fabric**, **Forge**, **NeoForge**, **Folia**, **Spigot**, **Quilt**, and more.
- **Dynamic Version Discovery**: Automatically lists all available Minecraft versions for each software, showing required Java runtime versions (Java 8, 11, 17, 21).
- **Build Selection**: Choose between the latest recommended build or inspect and install specific previous builds with commit messages.
- **Daemon-Direct Download**: Downloads are handled directly by the Wings daemon via HTTP `pull`, eliminating overhead on the web panel and avoiding timeout bottlenecks.
- **Safety First**:
  - Automatically creates a backup of the current `server.jar` as `server.jar.bak` before writing the new version.
  - Checks if the server is running and warns the administrator before proceeding.
  - Verifies file size and existence on the node post-download.
- **Live Execution Console**: Real-time progress terminal with step-by-step status updates directly inside the panel using Livewire polling.
- **Change History**: Audit log recording all past version changes per server, including execution times, users, and detailed logs.
- **Granular Permissions**: Restrict version changes to server owners or users with the custom `versions.change` permission.

---

## 📋 Requirements

- **Pelican Panel**: `^1.0.0-beta36`
- **PHP**: `^8.2` (including `php-curl`, `php-zip`)
- **Node Daemon**: Wings with pull download support enabled
- **Server Egg**: Minecraft egg (tagged with `minecraft` or named with Minecraft)

---

## 📦 Installation

### Method 1: Web Interface (Recommended)

1. Download the latest `versions.zip` release from the [Releases](https://github.com/YJCavalcante/Minecraft-Version-Changer-for-Pelincan-Painel/releases) page.
2. Log into your Pelican Panel as an administrator and navigate to **Admin → Plugins**.
3. Click the **Import from file** button in the upper right corner.
4. Select `versions.zip` and upload.
5. In the plugins list, find **Minecraft Version Changer** and click **Install**.

### Method 2: Command Line (CLI)

1. Extract the release archive into your panel's plugins directory:
   ```bash
   unzip versions.zip -d /var/www/pelican/plugins/versions/
   ```
2. Set appropriate ownership and permissions:
   ```bash
   chown -R www-data:www-data /var/www/pelican/plugins/versions
   chmod -R 755 /var/www/pelican/plugins/versions
   ```
3. Run the installer:
   ```bash
   php /var/www/pelican/artisan p:plugin:install versions
   ```

> [!TIP]
> **Queue Worker Reminder**: Plugin installations and version changes are processed asynchronously via Laravel queues. Ensure your `pelican.service` queue worker is running and configured to listen to all queues:
> ```ini
> ExecStart=/usr/bin/php /var/www/pelican/artisan queue:work --queue=high,standard,low,default --sleep=3 --tries=3
> ```

---

## ⚙️ Configuration

You can customize default behavior via environment variables in your Pelican `.env` file:

| Variable | Default | Description |
| :--- | :--- | :--- |
| `VERSIONS_DOWNLOAD_TIMEOUT` | `600` | Max seconds to wait for Wings to pull and verify the JAR file |
| `VERSIONS_API_CACHE_TTL` | `300` | Cache time (seconds) for MCJars API catalog queries |
| `VERSIONS_KEEP_BACKUP` | `true` | Retain previous `server.jar` as `server.jar.bak` |
| `VERSIONS_NAV_SORT` | `8` | Position of Version Changer in the server sidebar navigation |
| `VERSIONS_QUEUE` | `standard` | Queue name to dispatch version change jobs to |

---

## 🔒 Permissions

The **Version Changer** navigation item appears in the server navigation for Minecraft servers.

- **Panel Administrators & Server Owners**: Full access by default.
- **Subusers**: Access can be granted via the `versions.change` permission or standard `settings.reinstall` permission in server subuser settings.

---

## 👤 Author

- **Yuri J. Cavalcante** ([@YJCavalcante](https://github.com/YJCavalcante))

## 📄 License

This project is licensed under the [MIT License](LICENSE).
