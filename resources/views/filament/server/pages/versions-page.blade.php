<x-filament-panels::page>
{{-- ═══════════════════════════════════════════════════════════════════════════
     Minecraft Version Changer — Server Panel Page
     ═══════════════════════════════════════════════════════════════════════════ --}}

<style>
    .mvc {
        --mvc-primary: #3b82f6;
        --mvc-primary-hover: #2563eb;
        --mvc-surface: #ffffff;
        --mvc-surface-subtle: #f8fafc;
        --mvc-surface-elevated: #ffffff;
        --mvc-border: #e2e8f0;
        --mvc-border-hover: #cbd5e1;
        --mvc-text: #0f172a;
        --mvc-text-muted: #64748b;
        --mvc-badge-bg: #f1f5f9;
        --mvc-badge-text: #475569;
        --mvc-terminal-bg: #090d16;
        --mvc-terminal-text: #38bdf8;
    }

    .dark .mvc {
        --mvc-primary: #3b82f6;
        --mvc-primary-hover: #60a5fa;
        --mvc-surface: #111827;
        --mvc-surface-subtle: #1f2937;
        --mvc-surface-elevated: #1e293b;
        --mvc-border: #374151;
        --mvc-border-hover: #4b5563;
        --mvc-text: #f9fafb;
        --mvc-text-muted: #9ca3af;
        --mvc-badge-bg: #374151;
        --mvc-badge-text: #d1d5db;
        --mvc-terminal-bg: #030712;
        --mvc-terminal-text: #38bdf8;
    }

    .mvc-card {
        background-color: var(--mvc-surface);
        border: 1px solid var(--mvc-border);
        transition: all 0.15s ease-in-out;
    }
    .mvc-card:hover {
        border-color: var(--mvc-border-hover);
    }
    .mvc-card-active {
        border-color: var(--mvc-primary) !important;
        background-color: var(--mvc-surface-subtle) !important;
        box-shadow: 0 0 0 1px var(--mvc-primary);
    }

    .mvc-terminal {
        background-color: var(--mvc-terminal-bg);
        color: #e2e8f0;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .mvc-pulse {
        animation: mvc-pulse-animation 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes mvc-pulse-animation {
        0%, 100% { opacity: 1; }
        50% { opacity: .5; }
    }
</style>

<div class="mvc space-y-6" @if($isChanging) wire:poll.2000ms="pollProgress" @endif>

    {{-- ── 1. SERVER RUNNING ALERT ───────────────────────────────────────────── --}}
    @if($this->isServerRunning())
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200">
            <div class="flex items-center gap-3">
                <svg class="h-6 w-6 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div class="text-sm">
                    <span class="font-semibold">Server is currently online ({{ ucfirst($containerStatus) }}):</span>
                    For best results and to prevent file lock issues or save corruption, stop your Minecraft server before switching JAR versions.
                </div>
            </div>
        </div>
    @endif

    {{-- ── 2. ACTIVE PROGRESS PANEL (WHEN CHANGING) ────────────────────────── --}}
    @if($isChanging || ($changeId && $changeStatus === 'changing') || ($changeId && $changeStatus === 'pending'))
        <div class="mvc-card rounded-2xl p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            Switching Server Version...
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Status: <span class="font-medium text-blue-500 uppercase">{{ $changeStatus }}</span> — Node is pulling the selected JAR file.
                        </p>
                    </div>
                </div>
                <div class="text-xs text-gray-400">
                    Operation ID #{{ $changeId }}
                </div>
            </div>

            {{-- Terminal execution log --}}
            <div class="mt-4">
                <div class="mvc-terminal rounded-xl p-4 text-xs h-48 overflow-y-auto whitespace-pre-wrap leading-relaxed border border-gray-800 shadow-inner">
{{ $changeLog ?: 'Connecting to Wings node daemon...' }}
                </div>
            </div>
        </div>
    @elseif(!empty($changeStatus) && in_array($changeStatus, ['done', 'failed']))
        <div class="mvc-card rounded-2xl p-4 shadow-sm border-l-4 {{ $changeStatus === 'done' ? 'border-l-emerald-500' : 'border-l-rose-500' }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    @if($changeStatus === 'done')
                        <div class="h-8 w-8 rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Version Change Complete</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">The server.jar has been updated. Restart your server to run the new version.</p>
                        </div>
                    @else
                        <div class="h-8 w-8 rounded-full bg-rose-500/20 text-rose-500 flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Version Change Failed</h4>
                            <p class="text-xs text-rose-500">{{ $changeError ?: 'An unexpected error occurred.' }}</p>
                        </div>
                    @endif
                </div>
                <button type="button" wire:click="$set('changeStatus', '')" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    Dismiss
                </button>
            </div>
        </div>
    @endif

    {{-- ── 3. SOFTWARE CATALOG (FULL WIDTH) ─────────────────────────────────── --}}
    <div class="space-y-4">
        {{-- Category Filter Pills & Search --}}
        <div class="mvc-card rounded-2xl p-4 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                    Software Catalog
                </h2>

                {{-- Search Input --}}
                <div class="relative w-full sm:w-64">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="search"
                        placeholder="Filter software (e.g. Paper, Fabric)..."
                        class="w-full text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2 pl-9 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    />
                    <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
            </div>

            {{-- Categories tabs --}}
            <div class="flex flex-wrap gap-1.5 border-t border-gray-200 dark:border-gray-800 pt-3">
                @php
                    $categories = [
                        'recommended'   => 'Recommended',
                        'all'           => 'All Softwares',
                        'established'   => 'Established',
                        'experimental'  => 'Experimental',
                        'miscellaneous' => 'Miscellaneous',
                        'limbos'        => 'Limbos',
                    ];
                @endphp
                @foreach($categories as $catKey => $catLabel)
                    <button
                        type="button"
                        wire:click="selectCategory('{{ $catKey }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all {{ $selectedCategory === $catKey ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}"
                    >
                        {{ $catLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Softwares List / Cards Grid (2 Columns) --}}
        <div class="space-y-6">
            @php $filteredGroup = $this->filteredTypes; @endphp

            @if(empty($filteredGroup))
                <div class="mvc-card rounded-2xl p-8 text-center text-gray-500 dark:text-gray-400">
                    <p>No software types found matching your query.</p>
                </div>
            @else
                @foreach($filteredGroup as $catName => $softwares)
                    <div class="space-y-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-400 px-1">
                            {{ ucfirst($catName) }} Softwares
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                            @foreach($softwares as $key => $software)
                                @php $isActive = ($selectedSoftware === $key); @endphp
                                <button
                                    type="button"
                                    wire:click="openSoftwareModal('{{ $key }}')"
                                    wire:loading.attr="disabled"
                                    class="mvc-card text-left p-4 rounded-2xl flex items-start gap-3.5 transition-all hover:border-blue-500/50 hover:bg-gray-50 dark:hover:bg-gray-800/60 {{ $isActive ? 'mvc-card-active ring-1 ring-blue-500' : '' }}"
                                >
                                    {{-- Software Icon --}}
                                    @if(!empty($software['icon']))
                                        <img
                                            src="{{ $software['icon'] }}"
                                            alt="{{ $software['name'] }}"
                                            class="h-10 w-10 rounded-xl object-contain bg-black/5 dark:bg-white/5 p-1 shrink-0"
                                            loading="lazy"
                                            onerror="this.style.display='none'"
                                        />
                                    @else
                                        <div class="h-10 w-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold text-sm shrink-0">
                                            {{ substr($software['name'] ?? $key, 0, 2) }}
                                        </div>
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-sm text-gray-900 dark:text-white truncate">
                                                {{ $software['name'] }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-blue-500/10 border border-blue-500/20 px-2 py-0.5 text-[10px] font-semibold text-blue-500 shrink-0">
                                                Install →
                                            </span>
                                        </div>

                                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mt-0.5">
                                            {{ $software['description'] ?? 'Minecraft server software.' }}
                                        </p>

                                        @if(!empty($software['builds']))
                                            <div class="mt-2 text-[11px] text-gray-400">
                                                {{ number_format($software['builds']) }} builds available
                                            </div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ── 4. INSTALL MODAL (LIKE PTERODACTYL VERSIONMODAL) ─────────────────── --}}
    @if($showInstallModal && $selectedSoftware && $softwareDetails)
        <div
            x-data="{ closing: false }"
            x-show="!closing"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
            @click.self="closing = true; $wire.closeInstallModal()"
        >
            <div class="mvc-card rounded-3xl p-6 sm:p-7 max-w-lg w-full shadow-2xl border border-gray-200 dark:border-gray-700 space-y-5 bg-white dark:bg-gray-900 animate-in fade-in zoom-in-95">
                {{-- Modal Header --}}
                <div class="flex items-start justify-between border-b border-gray-200 dark:border-gray-800 pb-4">
                    <div class="flex items-center gap-3.5">
                        @if(!empty($softwareDetails['icon']))
                            <img src="{{ $softwareDetails['icon'] }}" class="h-11 w-11 rounded-xl object-contain bg-black/5 dark:bg-white/5 p-1.5 border border-gray-200 dark:border-gray-700 shrink-0" alt="{{ $softwareDetails['name'] ?? $selectedSoftware }}">
                        @endif
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                Install {{ $softwareDetails['name'] ?? $selectedSoftware }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $softwareDetails['description'] ?? 'Configure version and build for your server.' }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closing = true; $wire.closeInstallModal()"
                        wire:loading.attr="disabled"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 text-lg leading-none transition"
                    >
                        ✕
                    </button>
                </div>

                {{-- Modal Form Content --}}
                <div class="space-y-4 text-xs">
                    {{-- Step 1: Minecraft Version --}}
                    <div class="space-y-1.5">
                        <label class="block font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            1. Minecraft Version
                        </label>

                        @if($isLoadingVersions)
                            <div class="text-xs text-gray-400 flex items-center gap-2 py-2.5">
                                <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Loading available versions...
                            </div>
                        @elseif(empty($availableVersions))
                            <div class="text-xs text-gray-400 py-2">
                                No versions available for this software.
                            </div>
                        @else
                            <select
                                wire:change="selectVersion($event.target.value)"
                                class="w-full text-xs font-semibold rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white p-3 focus:ring-2 focus:ring-blue-500 outline-none transition"
                            >
                                @foreach($availableVersions as $vKey => $vData)
                                    <option value="{{ $vKey }}" @selected($selectedVersion === $vKey)>
                                        Minecraft {{ $vKey }} @if(!empty($vData['type']) && $vData['type'] !== 'RELEASE') ({{ $vData['type'] }}) @endif
                                    </option>
                                @endforeach
                            </select>

                            {{-- Version Metadata Tags --}}
                            @if($versionDetails)
                                <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px]">
                                    @if(isset($versionDetails['supported']))
                                        <span class="inline-flex items-center rounded-md {{ $versionDetails['supported'] ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/10 text-gray-500' }} px-2 py-0.5 font-semibold">
                                            {{ $versionDetails['supported'] ? 'Stable Release' : 'Snapshot / Legacy' }}
                                        </span>
                                    @endif
                                    @if(isset($versionDetails['java']))
                                        <span class="inline-flex items-center rounded-md bg-purple-500/10 px-2 py-0.5 font-semibold text-purple-600 dark:text-purple-400">
                                            Java {{ $versionDetails['java'] }}
                                        </span>
                                    @endif
                                    @if(isset($versionDetails['builds']))
                                        <span class="text-gray-400">
                                            {{ number_format($versionDetails['builds']) }} builds available
                                        </span>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Step 2: JAR Build --}}
                    <div class="space-y-1.5">
                        <label class="block font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            2. JAR Build
                        </label>

                        @if($isLoadingBuilds)
                            <div class="text-xs text-gray-400 flex items-center gap-2 py-2.5">
                                <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Loading builds...
                            </div>
                        @else
                            <select
                                wire:change="selectBuild($event.target.value)"
                                class="w-full text-xs font-semibold rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white p-3 focus:ring-2 focus:ring-blue-500 outline-none transition"
                            >
                                <option value="latest" @selected($selectedBuildNumber === 'latest' || empty($selectedBuildNumber))>
                                    Latest Build (Recommended)
                                </option>
                                @foreach($availableBuilds as $b)
                                    @php
                                        $isSpecial = in_array(strtoupper($selectedSoftware ?? ''), ['FABRIC', 'FORGE', 'NEOFORGE', 'SPONGE', 'LEGACYFABRIC', 'QUILT'], true);
                                        $val = $isSpecial && !empty($b['name']) ? $b['name'] : (string) ($b['buildNumber'] ?? $b['name'] ?? '');
                                    @endphp
                                    <option value="{{ $val }}" @selected((string)$selectedBuildNumber === (string)$val)>
                                        Build {{ $b['name'] ?? ('#' . ($b['buildNumber'] ?? '')) }}
                                        @if(!empty($b['created'])) ({{ substr($b['created'], 0, 10) }}) @endif
                                    </option>
                                @endforeach
                            </select>

                            @if(!empty($selectedBuild['changes']))
                                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 p-3 text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800 mt-2">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">Changelog:</span>
                                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-[11px]">
                                        @foreach(array_slice($selectedBuild['changes'], 0, 3) as $change)
                                            <li class="truncate">{{ $change }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Safety & Configuration details --}}
                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 p-3.5 space-y-2 text-xs text-gray-600 dark:text-gray-300">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>Creates safety backup (<code class="text-gray-800 dark:text-gray-200">server.jar.bak</code>)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-500 font-bold">✓</span>
                            <span>Accepts Minecraft EULA (<code class="text-gray-800 dark:text-gray-200">eula=true</code>)</span>
                        </div>
                        @if(!empty($selectedBuild['jarSize']))
                            <div class="flex items-center gap-2">
                                <span class="text-blue-500 font-bold">📦</span>
                                <span>Download Size: ~{{ round($selectedBuild['jarSize'] / (1024 * 1024), 1) }} MB</span>
                            </div>
                        @endif
                    </div>

                    @if($this->isServerRunning())
                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2">
                            <span class="text-amber-500 shrink-0 mt-0.5">⚠️</span>
                            <div>
                                <strong>Server is online:</strong> Replacing the JAR is safe, but you will need to restart the server to boot the new version.
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Modal Footer Actions --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-200 dark:border-gray-800">
                    <button
                        type="button"
                        @click="closing = true; $wire.closeInstallModal()"
                        wire:loading.attr="disabled"
                        class="px-4 py-2.5 text-xs font-semibold rounded-xl border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="startVersionChange"
                        wire:loading.attr="disabled"
                        @disabled($isChanging || empty($selectedSoftware) || empty($selectedVersion))
                        class="px-5 py-2.5 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-500 text-white shadow-md disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2"
                    >
                        <span wire:loading.remove wire:target="startVersionChange">Install {{ $selectedSoftware }} {{ $selectedVersion }}</span>
                        <span wire:loading wire:target="startVersionChange" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Starting Download...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── 4. VERSION CHANGE HISTORY ────────────────────────────────────────── --}}
    @if(!empty($recentChanges))
        <div class="mvc-card rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Recent Version Changes
            </h3>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 text-gray-400 uppercase font-semibold">
                            <th class="py-2.5 px-3">Software & Version</th>
                            <th class="py-2.5 px-3">Build</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3">Date</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($recentChanges as $change)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
                                <td class="py-3 px-3 font-semibold text-gray-900 dark:text-white">
                                    {{ $change['software'] }} {{ $change['minecraft_version'] }}
                                </td>
                                <td class="py-3 px-3 text-gray-500 dark:text-gray-400">
                                    {{ $change['build_name'] ?: ('#' . ($change['build_number'] ?? 'latest')) }}
                                </td>
                                <td class="py-3 px-3">
                                    @if($change['status'] === 'done')
                                        <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-500">
                                            Completed
                                        </span>
                                    @elseif($change['status'] === 'failed')
                                        <span class="inline-flex items-center rounded-full bg-rose-500/10 px-2 py-0.5 text-xs font-medium text-rose-500">
                                            Failed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-500">
                                            {{ ucfirst($change['status']) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-gray-400">
                                    {{ !empty($change['created_at']) ? substr($change['created_at'], 0, 19) : '' }}
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <button
                                        type="button"
                                        wire:click="openLogModal({{ $change['id'] }})"
                                        class="text-blue-500 hover:text-blue-600 font-medium"
                                    >
                                        View Log
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── 5. CONFIRMATION MODAL ────────────────────────────────────────────── --}}
    @if($showConfirmModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" wire:click.self="closeConfirmModal">
            <div class="mvc-card rounded-2xl p-6 max-w-md w-full shadow-2xl border border-gray-300 dark:border-gray-700 space-y-5 bg-white dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Confirm Version Change</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Please review before continuing.</p>
                    </div>
                </div>

                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 p-4 text-xs space-y-2 border border-gray-200 dark:border-gray-800">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Software:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedSoftware }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Minecraft Version:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedVersion }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Build:</span>
                        <span class="text-gray-900 dark:text-white">{{ $selectedBuild['name'] ?? ('#' . ($selectedBuild['buildNumber'] ?? 'latest')) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Safety Backup:</span>
                        <span class="text-emerald-500 font-medium">server.jar → server.jar.bak</span>
                    </div>
                </div>

                @if($this->isServerRunning())
                    <div class="rounded-xl bg-amber-500/10 border border-amber-500/30 p-3 text-xs text-amber-700 dark:text-amber-300">
                        ⚠️ <strong>Server is running:</strong> Replacing the JAR while running is safe for files, but you must restart the server afterwards to boot the new version.
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        wire:click="closeConfirmModal"
                        class="rounded-xl px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="startVersionChange"
                        class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-500 transition-colors"
                    >
                        Confirm & Install JAR
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── 6. HISTORICAL LOG MODAL ─────────────────────────────────────────── --}}
    @if($showLogModal && $viewingRecord)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" wire:click.self="closeLogModal">
            <div class="mvc-card rounded-2xl p-6 max-w-2xl w-full shadow-2xl border border-gray-300 dark:border-gray-700 space-y-4 bg-white dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            Execution Log #{{ $viewingRecord['id'] }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $viewingRecord['software'] }} {{ $viewingRecord['minecraft_version'] }} ({{ $viewingRecord['status'] }})
                        </p>
                    </div>
                    <button type="button" wire:click="closeLogModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mvc-terminal rounded-xl p-4 text-xs h-72 overflow-y-auto whitespace-pre-wrap leading-relaxed border border-gray-800">
{{ $viewingRecord['log'] ?: 'No log recorded.' }}
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        wire:click="closeLogModal"
                        class="rounded-xl bg-gray-100 dark:bg-gray-800 px-4 py-2 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
</x-filament-panels::page>
