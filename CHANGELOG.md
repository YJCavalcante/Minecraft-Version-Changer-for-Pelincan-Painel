# Changelog

All notable changes to the **Minecraft Version Changer for Pelican Panel** (`versions`) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0-beta] - 2026-09-19

### Added
- **Dynamic `SERVER_JARFILE` Synchronization**:
  - Automatically queries the server's Egg configuration to identify the target executable variable (e.g. `SERVER_JARFILE`, `JARFILE`, `JAR_NAME`).
  - Downloads the selected software directly to the target filename required by the Egg.
  - For bundled distributions (`.zip`), extracts and automatically renames the launch JAR to the egg's expected executable name, followed by an immediate Wings daemon synchronization (`$serverRepo->sync()`).
- **Multi-Tier Real-Time Software & Version Detection**:
  - **Modpack Manager Integration**: Inspects the `modpack_installs` database table and `/.modpack-manager.json` file. If a modpack (Forge, Fabric, NeoForge, etc.) was installed via Modpack Manager or manual upload, the plugin detects and displays the exact software and version instead of defaulting to "Vanilla".
  - **Server Root Disk Fingerprints**: Recognizes software signatures from files such as `unix_args.txt`, `run.sh`, `mohist.yml`, `purpur.yml`, `magma.yml`, `arclight.conf`, `paper.yml`, and `fabric-server-launch.jar`.
  - **Startup & Environment Variables**: Inspects software-specific environment variables (`MOHIST_VERSION`, `PURPUR_VERSION`, `PAPER_VERSION`, etc.) and startup flags.
  - **Historical Database Fallback**: Retains fallback to the `version_changes` audit table.
- **Network & Livewire Hardening**:
  - Configured `"update_url": null` in `plugin.json` to eliminate synchronous cURL timeouts (cURL error 28 / 5002ms) commonly triggered by IPv6 resolution issues on Linux VPS servers.
  - Eliminated any potential circular `$this->dispatch()` loops, preventing Livewire `TooManyCallsException` (199 calls exceeding the 50-call limit).
  - Enforced input debouncing (`debounce.250ms`) on software and version searches.
- **Enhanced Telemetry & Logging**:
  - Non-intrusive `Log::debug` diagnostics across all 6 lifecycle stages for rapid troubleshooting.

### Fixed
- **Modal Overlay Isolation**:
  - Guaranteed full viewport modal overlay (`inset: 0 !important; z-index: 99999 !important; backdrop-filter: blur(4px)`) preventing clipping or displacement under custom panel layouts or third-party themes.

---

## [1.0.4-beta] - 2026-09-17

### Added
- **Scoped CSS Isolation**:
  - Consolidated all styling under the `.mvc` CSS namespace using CSS custom properties (`--mvc-primary`, `--mvc-surface`, `--mvc-border`, etc.).
  - Full native dark mode support (`.dark .mvc`).
- **Zero Asset Build Dependency**:
  - Eliminated external CSS files and Vite compilation requirements; 100% self-contained in the blade template without theme conflicts.

### Changed
- Standardized codebase to strict zero-comment policy across all production code files (PHP, Blade, JS, CSS).

---

## [1.0.3-beta] - 2026-09-15

### Added
- **Active Version Detection**: Initial version status bar displaying currently installed software and version.
- **Clean Install Toggle**: Optional wipe of existing server files before installing new software (preserves `server.jar.bak` and `eula.txt`).
- **Java Compatibility Matrix**: Inline warnings when the selected Minecraft version requires a Java runtime different from the server's configured Docker image.
- **Background Cache Warming**: Daily scheduled command (`WarmVersionCacheCommand`) to prefetch MCJars catalogs.

---

## [1.0.2-beta] - 2026-09-12

### Added
- **6-Step Automated Lifecycle Engine**:
  - Step 1: Graceful power shutdown (`power('stop')`) with container polling.
  - Step 2: Safety backup (`server.jar.bak`) and dependency purge (`/libraries/`).
  - Step 3: Daemon-direct asynchronous HTTP pull via Wings.
  - Step 4: File integrity verification and automatic `.zip` decompression for Forge/NeoForge.
  - Step 5: Strict Mojang EULA preservation and egg variable synchronization.
  - Step 6: Automatic reboot if server was running prior to the change.
- **Audit Table**: Database migration for `version_changes` tracking change logs, initiator, and status.

---

## [1.0.1-beta] - 2026-09-10

### Added
- Interactive build selection modal for software types with multiple builds.
- Livewire state and payload optimizations.

---

## [1.0.0-beta] - 2026-09-08

### Added
- Initial beta release of Minecraft Version Changer for Pelican Panel.
- Integration with the MCJars public API for Vanilla, Paper, Purpur, Fabric, Forge, NeoForge, Spigot, and more.
