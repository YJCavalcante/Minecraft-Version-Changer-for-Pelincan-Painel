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

    {{-- ── 3. MAIN INTERACTION (TWO COLUMNS) ────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- LEFT COLUMN: SOFTWARE CATALOG (7 cols) --}}
        <div class="lg:col-span-7 space-y-4">
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
                            'all'           => 'All Softwares',
                            'recommended'   => 'Recommended',
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

            {{-- Softwares List / Cards Grid --}}
            <div class="space-y-4">
                @php $filteredGroup = $this->filteredTypes; @endphp

                @if(empty($filteredGroup))
                    <div class="mvc-card rounded-2xl p-8 text-center text-gray-500 dark:text-gray-400">
                        <p>No software types found matching your query.</p>
                    </div>
                @else
                    @foreach($filteredGroup as $catName => $softwares)
                        <div class="space-y-2">
                            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 px-1">
                                {{ ucfirst($catName) }}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($softwares as $key => $software)
                                    @php $isActive = ($selectedSoftware === $key); @endphp
                                    <button
                                        type="button"
                                        wire:click="selectSoftware('{{ $key }}')"
                                        class="mvc-card text-left p-4 rounded-xl flex items-start gap-3.5 transition-all {{ $isActive ? 'mvc-card-active' : '' }}"
                                    >
                                        {{-- Software Icon --}}
                                        @if(!empty($software['icon']))
                                            <img
                                                src="{{ $software['icon'] }}"
                                                alt="{{ $software['name'] }}"
                                                class="h-10 w-10 rounded-lg object-contain bg-black/5 dark:bg-white/5 p-1 shrink-0"
                                                loading="lazy"
                                                onerror="this.style.display='none'"
                                            />
                                        @else
                                            <div class="h-10 w-10 rounded-lg bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold text-sm shrink-0">
                                                {{ substr($software['name'] ?? $key, 0, 2) }}
                                            </div>
                                        @endif

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                                    {{ $software['name'] }}
                                                </span>
                                                @if($isActive)
                                                    <span class="inline-flex items-center rounded-full bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-500">
                                                        Selected
                                                    </span>
                                                @endif
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

        {{-- RIGHT COLUMN: VERSION & BUILD SELECTION (5 cols) --}}
        <div class="lg:col-span-5 space-y-6">
            <div class="mvc-card rounded-2xl p-6 shadow-sm space-y-6 sticky top-6">
                {{-- Selected software overview --}}
                @if($selectedSoftware && $softwareDetails)
                    <div class="flex items-center gap-4 pb-4 border-b border-gray-200 dark:border-gray-800">
                        @if(!empty($softwareDetails['icon']))
                            <img src="{{ $softwareDetails['icon'] }}" class="h-12 w-12 rounded-xl object-contain bg-black/5 dark:bg-white/5 p-1.5" />
                        @endif
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ $softwareDetails['name'] }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1">
                                {{ $softwareDetails['description'] ?? '' }}
                            </p>
                            @if(!empty($softwareDetails['homepage']))
                                <a href="{{ $softwareDetails['homepage'] }}" target="_blank" rel="noreferrer" class="text-[11px] text-blue-500 hover:underline flex items-center gap-1 mt-0.5">
                                    Official website
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Step 1: Select Minecraft Version --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        1. Select Minecraft Version
                    </label>

                    @if($isLoadingVersions)
                        <div class="text-xs text-gray-400 flex items-center gap-2 py-2">
                            <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Fetching versions from MCJars...
                        </div>
                    @elseif(empty($availableVersions))
                        <div class="text-xs text-gray-400 py-2">
                            No versions found for this software.
                        </div>
                    @else
                        <select
                            wire:change="selectVersion($event.target.value)"
                            class="w-full text-sm rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            @foreach($availableVersions as $vKey => $vData)
                                <option value="{{ $vKey }}" @selected($selectedVersion === $vKey)>
                                    Minecraft {{ $vKey }} @if(!empty($vData['type']) && $vData['type'] !== 'RELEASE') ({{ $vData['type'] }}) @endif
                                </option>
                            @endforeach
                        </select>

                        {{-- Version Metadata Tags --}}
                        @if($versionDetails)
                            <div class="flex flex-wrap gap-2 pt-1">
                                @if(isset($versionDetails['java']))
                                    <span class="inline-flex items-center gap-1 rounded-md bg-purple-500/10 px-2 py-0.5 text-xs font-medium text-purple-600 dark:text-purple-400">
                                        Java {{ $versionDetails['java'] }}
                                    </span>
                                @endif
                                @if(isset($versionDetails['supported']))
                                    <span class="inline-flex items-center rounded-md {{ $versionDetails['supported'] ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-gray-500/10 text-gray-500' }} px-2 py-0.5 text-xs font-medium">
                                        {{ $versionDetails['supported'] ? 'Supported' : 'Legacy / Unsupported' }}
                                    </span>
                                @endif
                                @if(isset($versionDetails['builds']))
                                    <span class="inline-flex items-center rounded-md bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400">
                                        {{ $versionDetails['builds'] }} builds
                                    </span>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Step 2: Select Build --}}
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        2. Select Build
                    </label>

                    @if($isLoadingBuilds)
                        <div class="text-xs text-gray-400 flex items-center gap-2 py-2">
                            <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Fetching build list...
                        </div>
                    @else
                        <select
                            wire:change="selectBuild($event.target.value ? parseInt($event.target.value) : null)"
                            class="w-full text-sm rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="0" @selected($selectedBuildNumber === null || $selectedBuildNumber === 0)>
                                Latest Build (Recommended)
                            </option>
                            @foreach($availableBuilds as $b)
                                <option value="{{ $b['buildNumber'] ?? 0 }}" @selected($selectedBuildNumber === ($b['buildNumber'] ?? null))>
                                    Build {{ $b['name'] ?? ('#' . ($b['buildNumber'] ?? '')) }}
                                    @if(!empty($b['created'])) ({{ substr($b['created'], 0, 10) }}) @endif
                                </option>
                            @endforeach
                        </select>

                        {{-- Changelog snippet if present --}}
                        @if(!empty($selectedBuild['changes']))
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 p-3 text-xs text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800">
                                <span class="font-semibold text-gray-800 dark:text-gray-200">Changelog:</span>
                                <ul class="list-disc list-inside mt-1 space-y-0.5">
                                    @foreach(array_slice($selectedBuild['changes'], 0, 3) as $change)
                                        <li class="truncate">{{ $change }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Operation Summary Box --}}
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/40 p-4 border border-gray-200 dark:border-gray-800 text-xs space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Target File:</span>
                        <span class="font-mono font-medium text-gray-900 dark:text-white">server.jar</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Backup Safety:</span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">Renames old JAR → server.jar.bak</span>
                    </div>
                    @if(!empty($selectedBuild['jarSize']))
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Download Size:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ round($selectedBuild['jarSize'] / (1024 * 1024), 1) }} MB</span>
                        </div>
                    @endif
                </div>

                {{-- Action Button --}}
                <button
                    type="button"
                    wire:click="openConfirmModal"
                    @disabled($isChanging || empty($selectedSoftware) || empty($selectedVersion))
                    class="w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Install {{ $selectedSoftware }} {{ $selectedVersion }}
                </button>
            </div>
        </div>
    </div>

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
