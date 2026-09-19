<x-filament-panels::page>

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
        background-color: var(--mvc-surface) !important;
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
        background-color: var(--mvc-terminal-bg) !important;
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

    .mvc-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 99999 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 1rem !important;
        margin: 0 !important;
        background-color: rgba(0, 0, 0, 0.78) !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
    }

    .mvc-modal {
        background-color: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 1.25rem !important;
        padding: 1.5rem !important;
        width: 100% !important;
        max-width: 32rem !important;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.55) !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 1.25rem !important;
        box-sizing: border-box !important;
    }
    .dark .mvc-modal {
        background-color: #111827 !important;
        border-color: #374151 !important;
    }
    .mvc-modal-wide {
        max-width: 42rem !important;
    }
    .mvc-modal-sm {
        max-width: 28rem !important;
    }
    .mvc-modal-footer {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 0.75rem !important;
        padding-top: 1.25rem !important;
        padding-bottom: 0 !important;
        margin-top: 1.25rem !important;
        border-top: 1px solid #e5e7eb !important;
    }
    .dark .mvc-modal-footer {
        border-top-color: #374151 !important;
    }

    .mvc-banner-alert {
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
        padding: 1rem !important;
        border-radius: 0.75rem !important;
        border: 1px solid rgba(245, 158, 11, 0.3) !important;
        background-color: rgba(245, 158, 11, 0.1) !important;
        color: #78350f !important;
    }
    .dark .mvc-banner-alert {
        color: #fef3c7 !important;
    }
    .mvc-banner-alert svg {
        width: 1.5rem !important;
        height: 1.5rem !important;
        color: #f59e0b !important;
        flex-shrink: 0 !important;
        display: block !important;
    }

    .mvc-version-row {
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        gap: 1rem !important;
    }
    @media (min-width: 640px) {
        .mvc-version-row {
            flex-direction: row !important;
            align-items: center !important;
        }
    }
    .mvc-version-row-side {
        border-top: 1px solid #f3f4f6 !important;
        padding-top: 0.5rem !important;
        text-align: left !important;
    }
    @media (min-width: 640px) {
        .mvc-version-row-side {
            border-top: none !important;
            padding-top: 0 !important;
            text-align: right !important;
        }
    }
    .mvc-installed-wrap {
        display: flex !important;
        align-items: center !important;
        gap: 1rem !important;
    }
    .mvc-installed-icon {
        width: 2.75rem !important;
        height: 2.75rem !important;
        border-radius: 0.75rem !important;
        object-fit: contain !important;
        background-color: rgba(0, 0, 0, 0.05) !important;
        padding: 0.375rem !important;
        border: 1px solid #e5e7eb !important;
        flex-shrink: 0 !important;
        display: block !important;
        box-sizing: border-box !important;
    }
    .dark .mvc-installed-icon {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border-color: #374151 !important;
    }
    .mvc-status-badge {
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.25rem !important;
        padding: 0.125rem 0.5rem !important;
        border-radius: 9999px !important;
        font-size: 0.625rem !important;
        font-weight: 600 !important;
        background-color: rgba(16, 185, 129, 0.1) !important;
        color: #059669 !important;
        border: 1px solid rgba(16, 185, 129, 0.2) !important;
        line-height: 1rem !important;
    }
    .dark .mvc-status-badge {
        color: #34d399 !important;
    }
    .mvc-status-dot {
        display: inline-block !important;
        width: 0.375rem !important;
        height: 0.375rem !important;
        border-radius: 50% !important;
        background-color: #10b981 !important;
        flex-shrink: 0 !important;
    }
    .mvc-installed-title-row {
        display: flex !important;
        align-items: baseline !important;
        gap: 0.5rem !important;
        margin-top: 0.125rem !important;
    }

    .mvc-catalog-header {
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        gap: 0.75rem !important;
    }
    @media (min-width: 640px) {
        .mvc-catalog-header {
            flex-direction: row !important;
            align-items: center !important;
        }
    }
    .mvc-catalog-title {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        font-size: 1.125rem !important;
        font-weight: 700 !important;
        color: var(--mvc-text) !important;
        margin: 0 !important;
    }
    .mvc-catalog-title svg {
        width: 1.25rem !important;
        height: 1.25rem !important;
        color: #3b82f6 !important;
        flex-shrink: 0 !important;
        display: block !important;
    }
    .mvc-search-wrap {
        position: relative !important;
        width: 100% !important;
    }
    @media (min-width: 640px) {
        .mvc-search-wrap {
            width: 16rem !important;
        }
    }
    .mvc-search-input {
        width: 100% !important;
        font-size: 0.75rem !important;
        border-radius: 0.75rem !important;
        border: 1px solid #d1d5db !important;
        background-color: #ffffff !important;
        color: #111827 !important;
        padding: 0.5rem 0.75rem 0.5rem 2.25rem !important;
        outline: none !important;
        box-sizing: border-box !important;
        transition: box-shadow 0.15s !important;
    }
    .dark .mvc-search-input {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
        color: #f9fafb !important;
    }
    .mvc-search-input:focus {
        box-shadow: 0 0 0 2px #3b82f6 !important;
    }
    .mvc-search-icon {
        position: absolute !important;
        left: 0.625rem !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 1rem !important;
        height: 1rem !important;
        color: #9ca3af !important;
        pointer-events: none !important;
        display: block !important;
        margin: 0 !important;
    }

    .mvc-card-catalog {
        padding: 1.25rem 1.25rem 1rem 1.25rem !important;
    }
    .mvc-category-bar {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 0.5rem !important;
        border-top: 1px solid #e5e7eb !important;
        padding-top: 1rem !important;
        padding-bottom: 0 !important;
        margin-top: 1rem !important;
    }
    .dark .mvc-category-bar {
        border-top-color: #374151 !important;
    }
    .mvc-category-btn {
        padding: 0.42rem 0.82rem !important;
        font-size: 0.77rem !important;
        font-weight: 500 !important;
        border-radius: 0.5rem !important;
        cursor: pointer !important;
        transition: all 0.15s ease-in-out !important;
        border: none !important;
        line-height: 1.15rem !important;
    }
    .mvc-category-btn-active {
        background-color: #2563eb !important;
        color: #ffffff !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }
    .mvc-category-btn-inactive {
        background-color: transparent !important;
        color: #4b5563 !important;
    }
    .dark .mvc-category-btn-inactive {
        color: #9ca3af !important;
    }
    .mvc-category-btn-inactive:hover {
        background-color: #f3f4f6 !important;
        color: #111827 !important;
    }
    .dark .mvc-category-btn-inactive:hover {
        background-color: #1f2937 !important;
        color: #ffffff !important;
    }

    .mvc-software-grid {
        display: grid !important;
        grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
        gap: 0.875rem !important;
    }
    @media (min-width: 768px) {
        .mvc-software-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        }
    }
    .mvc-software-card {
        display: flex !important;
        align-items: flex-start !important;
        gap: 0.875rem !important;
        padding: 1rem !important;
        border-radius: 1rem !important;
        text-align: left !important;
        width: 100% !important;
        box-sizing: border-box !important;
        cursor: pointer !important;
        transition: all 0.15s ease-in-out !important;
    }
    .mvc-software-card:hover {
        border-color: rgba(59, 130, 246, 0.5) !important;
        background-color: #f9fafb !important;
    }
    .dark .mvc-software-card:hover {
        background-color: rgba(31, 41, 55, 0.6) !important;
    }
    .mvc-software-img {
        width: 2.5rem !important;
        height: 2.5rem !important;
        border-radius: 0.75rem !important;
        object-fit: contain !important;
        background-color: rgba(0, 0, 0, 0.05) !important;
        padding: 0.25rem !important;
        flex-shrink: 0 !important;
        display: block !important;
        box-sizing: border-box !important;
    }
    .dark .mvc-software-img {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }
    .mvc-software-fallback-img {
        width: 2.5rem !important;
        height: 2.5rem !important;
        border-radius: 0.75rem !important;
        background-color: rgba(59, 130, 246, 0.1) !important;
        color: #3b82f6 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 700 !important;
        font-size: 0.875rem !important;
        flex-shrink: 0 !important;
        box-sizing: border-box !important;
    }
    .mvc-card-head {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
    }
    .mvc-install-badge {
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.25rem !important;
        padding: 0.125rem 0.5rem !important;
        border-radius: 9999px !important;
        font-size: 0.625rem !important;
        font-weight: 600 !important;
        background-color: rgba(59, 130, 246, 0.1) !important;
        border: 1px solid rgba(59, 130, 246, 0.2) !important;
        color: #3b82f6 !important;
        flex-shrink: 0 !important;
        white-space: nowrap !important;
    }

    .mvc-modal-head {
        display: flex !important;
        align-items: flex-start !important;
        justify-content: space-between !important;
        gap: 0.875rem !important;
        border-bottom: 1px solid #e5e7eb !important;
        padding-bottom: 1rem !important;
    }
    .dark .mvc-modal-head {
        border-bottom-color: #1f2937 !important;
    }
    .mvc-modal-head-left {
        display: flex !important;
        align-items: center !important;
        gap: 0.875rem !important;
        min-width: 0 !important;
    }
    .mvc-modal-software-icon {
        width: 2.75rem !important;
        height: 2.75rem !important;
        border-radius: 0.75rem !important;
        object-fit: contain !important;
        background-color: rgba(0, 0, 0, 0.05) !important;
        padding: 0.375rem !important;
        border: 1px solid #e5e7eb !important;
        flex-shrink: 0 !important;
        display: block !important;
        box-sizing: border-box !important;
    }
    .dark .mvc-modal-software-icon {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border-color: #374151 !important;
    }
    .mvc-version-search-wrap {
        position: relative !important;
        margin-bottom: 0.375rem !important;
        width: 100% !important;
    }
    .mvc-version-search-input {
        width: 100% !important;
        font-size: 0.75rem !important;
        border-radius: 0.75rem !important;
        border: 1px solid #d1d5db !important;
        background-color: #f9fafb !important;
        color: #111827 !important;
        padding: 0.5rem 0.75rem 0.5rem 2rem !important;
        outline: none !important;
        box-sizing: border-box !important;
        transition: box-shadow 0.15s !important;
    }
    .dark .mvc-version-search-input {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
        color: #f9fafb !important;
    }
    .mvc-version-search-input:focus {
        box-shadow: 0 0 0 2px #3b82f6 !important;
    }
    .mvc-version-search-icon {
        position: absolute !important;
        left: 0.5rem !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: 0.875rem !important;
        height: 0.875rem !important;
        color: #9ca3af !important;
        pointer-events: none !important;
        display: block !important;
        margin: 0 !important;
    }
    .mvc-select {
        width: 100% !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        border-radius: 0.75rem !important;
        border: 1px solid #d1d5db !important;
        background-color: #f9fafb !important;
        color: #111827 !important;
        padding: 0.75rem !important;
        outline: none !important;
        box-sizing: border-box !important;
        transition: box-shadow 0.15s !important;
    }
    .dark .mvc-select {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
        color: #f9fafb !important;
    }
    .mvc-select:focus {
        box-shadow: 0 0 0 2px #3b82f6 !important;
    }

    .mvc-info-box {
        border-radius: 0.75rem !important;
        border: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
        padding: 0.875rem !important;
        font-size: 0.75rem !important;
        color: #4b5563 !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 0.5rem !important;
    }
    .dark .mvc-info-box {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
        color: #d1d5db !important;
    }
    .mvc-info-box-row {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }
    .mvc-info-box-icon {
        flex-shrink: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .mvc-changelog-box {
        border-radius: 0.75rem !important;
        border: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
        padding: 0.75rem !important;
        font-size: 0.75rem !important;
        color: #4b5563 !important;
        margin-top: 0.5rem !important;
    }
    .dark .mvc-changelog-box {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
        color: #9ca3af !important;
    }

    .mvc-summary-box {
        border-radius: 0.75rem !important;
        border: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
        padding: 1rem !important;
        font-size: 0.75rem !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 0.5rem !important;
    }
    .dark .mvc-summary-box {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
    }
    .mvc-summary-row {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.5rem !important;
    }

    .mvc-clean-box {
        border-radius: 0.75rem !important;
        border: 1px solid #e5e7eb !important;
        background-color: #f9fafb !important;
        padding: 0.875rem !important;
        font-size: 0.75rem !important;
        transition: background-color 0.15s, border-color 0.15s !important;
    }
    .mvc-clean-box.mvc-clean-active {
        border-color: rgba(248, 113, 113, 0.5) !important;
        background-color: rgba(239, 68, 68, 0.05) !important;
    }
    .dark .mvc-clean-box {
        border-color: #374151 !important;
        background-color: #1f2937 !important;
    }
    .dark .mvc-clean-box.mvc-clean-active {
        border-color: rgba(248, 113, 113, 0.5) !important;
        background-color: rgba(239, 68, 68, 0.07) !important;
    }
    .mvc-clean-label {
        display: flex !important;
        align-items: flex-start !important;
        gap: 0.625rem !important;
        cursor: pointer !important;
        user-select: none !important;
    }

    .mvc-java-warning-wrap {
        display: flex !important;
        align-items: flex-start !important;
        gap: 0.625rem !important;
        border-radius: 0.75rem !important;
        padding: 0.875rem !important;
        font-size: 0.75rem !important;
        border: 1px solid rgba(251, 191, 36, 0.4) !important;
        background-color: rgba(251, 191, 36, 0.1) !important;
    }

    .mvc-confirm-circle {
        width: 2.5rem !important;
        height: 2.5rem !important;
        border-radius: 50% !important;
        background-color: rgba(59, 130, 246, 0.1) !important;
        color: #3b82f6 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
    }
    .mvc-confirm-circle svg {
        width: 1.5rem !important;
        height: 1.5rem !important;
        display: block !important;
    }

    .mvc-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0.625rem 1.25rem !important;
        min-width: 5.5rem !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        border-radius: 0.75rem !important;
        line-height: 1rem !important;
        cursor: pointer !important;
        transition: all 0.15s ease-in-out !important;
        border: 1px solid transparent !important;
        box-sizing: border-box !important;
        text-align: center !important;
    }
    .mvc-btn-secondary {
        border-color: #d1d5db !important;
        color: #374151 !important;
        background-color: transparent !important;
    }
    .mvc-btn-secondary:hover {
        background-color: #f3f4f6 !important;
    }
    .dark .mvc-btn-secondary {
        border-color: #374151 !important;
        color: #d1d5db !important;
    }
    .dark .mvc-btn-secondary:hover {
        background-color: #1f2937 !important;
    }

    .mvc-btn-primary {
        background-color: #2563eb !important;
        color: #ffffff !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }
    .mvc-btn-primary:hover {
        background-color: #1d4ed8 !important;
    }
    .mvc-btn-primary:disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }
</style>

<div class="mvc space-y-6" @if($isChanging) wire:poll.2000ms="pollProgress" @endif>

    @if($this->isServerRunning())
        <div class="mvc-banner-alert">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div class="text-sm">
                <span class="font-semibold">Server is currently online ({{ ucfirst($containerStatus) }}):</span>
                For best results and to prevent file lock issues or save corruption, stop your Minecraft server before switching JAR versions.
            </div>
        </div>
    @endif

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
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Version Change Complete</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">The server.jar has been updated. Restart your server to run the new version.</p>
                        </div>
                    @else
                        <div class="h-8 w-8 rounded-full bg-rose-500/20 text-rose-500 flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
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

    @if($currentVersionInfo)
        <div class="mvc-card rounded-2xl p-5 shadow-sm">
            <div class="mvc-version-row">
                <div class="mvc-installed-wrap">
                    @php
                        $installedIcon = $currentVersionInfo['icon'] ?? null;
                        if (!$installedIcon && !empty($currentVersionInfo['software'])) {
                            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $currentVersionInfo['software']));
                            $installedIcon = "https://s3.mcjars.app/icons/{$slug}.png";
                        }
                    @endphp
                    @if(!empty($installedIcon))
                        <img
                            src="{{ $installedIcon }}"
                            alt="{{ $currentVersionInfo['software'] }}"
                            class="mvc-installed-icon"
                            onerror="this.onerror=null;this.src='data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';this.alt=''"
                        />
                    @endif
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Current Installed Version
                            </span>
                            <span class="mvc-status-badge">
                                <span class="mvc-status-dot"></span>
                                Active
                            </span>
                            @if(!empty($currentVersionInfo['source']) && $currentVersionInfo['source'] === 'modpack')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                    Modpack
                                </span>
                            @elseif(!empty($currentVersionInfo['source']) && $currentVersionInfo['source'] === 'disk')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                    Auto-detected
                                </span>
                            @endif
                        </div>
                        <div class="mvc-installed-title-row">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ $currentVersionInfo['software'] }} <span class="text-blue-600 dark:text-blue-400">{{ $currentVersionInfo['version'] }}</span>
                            </h3>
                            @if(!empty($currentVersionInfo['build']))
                                <span class="text-xs font-mono text-gray-500 dark:text-gray-400">({{ $currentVersionInfo['build'] }})</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($currentVersionInfo['installed_at']))
                    <div class="mvc-version-row-side">
                        <span class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Installed</span>
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $currentVersionInfo['installed_at'] }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="space-y-4">
        <div class="mvc-card rounded-2xl mvc-card-catalog">
            <div class="mvc-catalog-header">
                <h2 class="mvc-catalog-title">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                    Catalog
                </h2>

                <div class="mvc-search-wrap">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="search"
                        placeholder="Filter software (e.g. Paper, Fabric)..."
                        class="mvc-search-input"
                        aria-label="Filter software"
                    />
                    <svg class="mvc-search-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
            </div>

            <div class="mvc-category-bar">
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
                        wire:key="cat-{{ $catKey }}"
                        type="button"
                        wire:click="selectCategory('{{ $catKey }}')"
                        class="mvc-category-btn {{ $selectedCategory === $catKey ? 'mvc-category-btn-active' : 'mvc-category-btn-inactive' }}"
                    >
                        {{ $catLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            @php $filteredGroup = $this->filteredTypes; @endphp

            @if(empty($filteredGroup))
                <div class="mvc-card rounded-2xl p-8 text-center text-gray-500 dark:text-gray-400">
                    <p>No software types found matching your query.</p>
                </div>
            @else
                @foreach($filteredGroup as $catName => $softwares)
                    <div wire:key="cat-group-{{ $catName }}" class="space-y-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-400 px-1">
                            {{ ucfirst($catName) }} Softwares
                        </div>

                        <div class="mvc-software-grid">
                            @foreach($softwares as $key => $software)
                                @php $isActive = ($selectedSoftware === $key); @endphp
                                <button
                                    wire:key="sw-{{ $key }}"
                                    type="button"
                                    wire:click="openSoftwareModal('{{ $key }}')"
                                    wire:loading.attr="disabled"
                                    class="mvc-card mvc-software-card {{ $isActive ? 'mvc-card-active' : '' }}"
                                >
                                    @if(!empty($software['icon']))
                                        <img
                                            src="{{ $software['icon'] }}"
                                            alt="{{ $software['name'] }}"
                                            class="mvc-software-img"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';this.alt=''"
                                        />
                                    @else
                                        <div class="mvc-software-fallback-img">
                                            {{ substr($software['name'] ?? $key, 0, 2) }}
                                        </div>
                                    @endif

                                    <div class="min-w-0 flex-1" style="min-width:0; flex:1;">
                                        <div class="mvc-card-head">
                                            <span class="font-bold text-sm text-gray-900 dark:text-white truncate">
                                                {{ $software['name'] }}
                                            </span>
                                            <span class="mvc-install-badge">
                                                Install →
                                            </span>
                                        </div>

                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
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

    @if(!empty($recentChanges))
        <div class="mvc-card rounded-2xl p-6 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
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
                            <tr wire:key="hist-{{ $change['id'] }}" class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
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

</div>

<div class="mvc">

    @if($showInstallModal && $selectedSoftware && $softwareDetails)
        <div
            x-data="{ closing: false }"
            x-show="!closing"
            class="mvc-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="mvc-modal-title"
            @keydown.escape.window="closing = true; $wire.closeInstallModal()"
            @click.self="closing = true; $wire.closeInstallModal()"
        >
            <div class="mvc-modal">
                <div class="mvc-modal-head">
                    <div class="mvc-modal-head-left">
                        @if(!empty($softwareDetails['icon']))
                            <img src="{{ $softwareDetails['icon'] }}" class="mvc-modal-software-icon" alt="{{ $softwareDetails['name'] ?? $selectedSoftware }}">
                        @endif
                        <div style="min-width:0;">
                            <h3 id="mvc-modal-title" class="text-base font-bold text-gray-900 dark:text-white" style="margin:0;">
                                Install {{ $softwareDetails['name'] ?? $selectedSoftware }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400" style="margin:0.125rem 0 0 0;">
                                {{ $softwareDetails['description'] ?? 'Configure version and build for your server.' }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closing = true; $wire.closeInstallModal()"
                        wire:loading.attr="disabled"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 text-lg leading-none transition"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4 text-xs" style="display:flex; flex-direction:column; gap:1rem;">
                    <div style="display:flex; flex-direction:column; gap:0.375rem;">
                        <label for="mvc-select-version" class="block font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            1. Minecraft Version
                        </label>

                        @if($isLoadingVersions)
                            <div class="text-xs text-gray-400 flex items-center gap-2 py-2.5" style="display:flex; align-items:center; gap:0.5rem;">
                                <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" aria-hidden="true" style="width:1rem;height:1rem;">
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
                            @php
                                $search = strtolower(trim($versionSearch));
                                $filteredVersions = empty($search)
                                    ? $availableVersions
                                    : array_filter(
                                        $availableVersions,
                                        fn($k) => str_contains(strtolower((string)$k), $search),
                                        ARRAY_FILTER_USE_KEY
                                    );
                            @endphp

                            <div class="mvc-version-search-wrap">
                                <input
                                    type="text"
                                    wire:model.live.debounce.150ms="versionSearch"
                                    placeholder="Filter versions... (e.g. 1.20.4)"
                                    class="mvc-version-search-input"
                                    aria-label="Filter versions"
                                >
                                <svg class="mvc-version-search-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                                </svg>
                            </div>

                            @if(empty($filteredVersions))
                                <div class="text-xs text-gray-400 py-1.5 pl-1">
                                    No versions match "{{ $versionSearch }}".
                                </div>
                            @else
                                <select
                                    id="mvc-select-version"
                                    wire:change="selectVersion($event.target.value)"
                                    class="mvc-select"
                                >
                                    @foreach($filteredVersions as $vKey => $vData)
                                        <option wire:key="ver-{{ $vKey }}" value="{{ $vKey }}" @selected($selectedVersion === $vKey)>
                                            Minecraft {{ $vKey }} @if(!empty($vData['type']) && $vData['type'] !== 'RELEASE') ({{ $vData['type'] }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                            @endif

                            @if($versionDetails)
                                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; padding-top:0.25rem; font-size:11px;">
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

                    <div style="display:flex; flex-direction:column; gap:0.375rem;">
                        <label for="mvc-select-build" class="block font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            2. JAR Build
                        </label>

                        @if($isLoadingBuilds)
                            <div class="text-xs text-gray-400 flex items-center gap-2 py-2.5" style="display:flex; align-items:center; gap:0.5rem;">
                                <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" aria-hidden="true" style="width:1rem;height:1rem;">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Loading builds...
                            </div>
                        @else
                            <select
                                id="mvc-select-build"
                                wire:change="selectBuild($event.target.value)"
                                class="mvc-select"
                            >
                                <option value="latest" @selected($selectedBuildNumber === 'latest' || empty($selectedBuildNumber))>
                                    Latest Build (Recommended)
                                </option>
                                @foreach($availableBuilds as $b)
                                    @php
                                        $isSpecial = in_array(strtoupper($selectedSoftware ?? ''), ['FABRIC', 'FORGE', 'NEOFORGE', 'SPONGE', 'LEGACYFABRIC', 'QUILT'], true);
                                        $val = $isSpecial && !empty($b['name']) ? $b['name'] : (string) ($b['buildNumber'] ?? $b['name'] ?? '');
                                    @endphp
                                    <option wire:key="build-{{ $val }}" value="{{ $val }}" @selected((string)$selectedBuildNumber === (string)$val)>
                                        Build {{ $b['name'] ?? ('#' . ($b['buildNumber'] ?? '')) }}
                                        @if(!empty($b['created'])) ({{ substr($b['created'], 0, 10) }}) @endif
                                    </option>
                                @endforeach
                            </select>

                            @if(!empty($selectedBuild['changes']))
                                <div class="mvc-changelog-box">
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">Changelog:</span>
                                    <ul class="list-disc list-inside mt-1 space-y-0.5 text-[11px]">
                                        @foreach(array_slice($selectedBuild['changes'], 0, 3) as $change)
                                            <li wire:key="cl-{{ $loop->index }}" class="truncate">{{ $change }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </div>

                    @if(!empty($javaWarning))
                        <div class="mvc-java-warning-wrap">
                            <span class="shrink-0 text-base" style="flex-shrink:0;">☕</span>
                            <div style="display:flex; flex-direction:column; gap:0.125rem;">
                                <p class="font-semibold text-amber-800 dark:text-amber-200" style="margin:0;">Java Compatibility Warning</p>
                                <p class="text-amber-700 dark:text-amber-300" style="margin:0;">
                                    Minecraft {{ $javaWarning['version'] }} requires
                                    <strong>Java {{ $javaWarning['required'] }}</strong>, but your server image
                                    is configured with <strong>Java {{ $javaWarning['detected'] }}</strong>.
                                </p>
                                <p class="text-amber-600 dark:text-amber-400" style="margin:0.25rem 0 0 0;">
                                    You may need to update your server's Docker image before starting the server.
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="mvc-info-box">
                        <div class="mvc-info-box-row">
                            <span class="mvc-info-box-icon text-emerald-500 font-bold">✓</span>
                            <span>Creates safety backup (<code class="text-gray-800 dark:text-gray-200">server.jar.bak</code>)</span>
                        </div>
                        <div class="mvc-info-box-row">
                            <span class="mvc-info-box-icon text-emerald-500 font-bold">✓</span>
                            <span>Preserves Minecraft EULA agreement (<code class="text-gray-800 dark:text-gray-200">eula.txt</code>)</span>
                        </div>
                        @if(!empty($selectedBuild['jarSize']))
                            <div class="mvc-info-box-row">
                                <span class="mvc-info-box-icon text-blue-500 font-bold">📦</span>
                                <span>Download Size: ~{{ round($selectedBuild['jarSize'] / (1024 * 1024), 1) }} MB</span>
                            </div>
                        @endif
                    </div>

                    <div class="mvc-clean-box {{ $cleanInstall ? 'mvc-clean-active' : '' }}">
                        <label class="mvc-clean-label">
                            <input
                                type="checkbox"
                                wire:model.live="cleanInstall"
                                class="rounded border-gray-400 text-red-500 focus:ring-red-400 cursor-pointer"
                                style="margin-top:0.15rem; flex-shrink:0;"
                            >
                            <div style="flex:1;">
                                <span class="font-semibold {{ $cleanInstall ? 'text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                    Clean Install — Delete all server files before installing
                                </span>
                                <p class="text-gray-500 dark:text-gray-400" style="margin:0.125rem 0 0 0;">
                                    Useful when migrating between ecosystems (e.g. Vanilla → Forge). Unchecked by default.
                                </p>
                            </div>
                        </label>

                        @if($cleanInstall)
                            <div class="mt-2.5 rounded-lg p-2.5 flex items-start gap-2 text-red-800 dark:text-red-300" style="display:flex; align-items:flex-start; gap:0.5rem; border:1px solid rgba(248,113,113,0.4);background-color:rgba(239,68,68,0.1); margin-top:0.625rem;">
                                <span style="flex-shrink:0;">⚠️</span>
                                <span class="font-medium">This is irreversible. All world data, plugins, mods, configs and server files will be permanently deleted before the new version is installed. Only <code class="font-mono">server.jar.bak</code> and <code class="font-mono">eula.txt</code> will be preserved.</span>
                            </div>
                        @endif
                    </div>

                    @if($this->isServerRunning())
                        <div class="rounded-xl p-3 text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2" style="display:flex; align-items:flex-start; gap:0.5rem; border:1px solid rgba(245,158,11,0.3);background-color:rgba(245,158,11,0.1);">
                            <span class="text-amber-500 shrink-0" style="flex-shrink:0;">⚠️</span>
                            <div>
                                <strong>Server is online:</strong> Replacing the JAR is safe, but you will need to restart the server to boot the new version.
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mvc-modal-footer">
                    <button
                        type="button"
                        @click="closing = true; $wire.closeInstallModal()"
                        wire:loading.attr="disabled"
                        class="mvc-btn mvc-btn-secondary"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="startVersionChange"
                        wire:loading.attr="disabled"
                        @disabled($isChanging || empty($selectedSoftware) || empty($selectedVersion))
                        class="mvc-btn mvc-btn-primary"
                    >
                        <span wire:loading.remove wire:target="startVersionChange">Install</span>
                        <span wire:loading wire:target="startVersionChange" style="display:inline-flex; align-items:center; gap:0.375rem;">
                            <svg class="animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true" style="width:0.875rem;height:0.875rem;color:#ffffff;">
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

    @if($showConfirmModal)
        <div
            class="mvc-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="mvc-confirm-title"
            @keydown.escape.window="$wire.closeConfirmModal()"
            wire:click.self="closeConfirmModal"
        >
            <div class="mvc-modal mvc-modal-sm">
                <div class="flex items-center gap-3" style="display:flex; align-items:center; gap:0.75rem;">
                    <div class="mvc-confirm-circle">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="mvc-confirm-title" class="text-base font-bold text-gray-900 dark:text-white" style="margin:0;">Confirm Version Change</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400" style="margin:0.125rem 0 0 0;">Please review before continuing.</p>
                    </div>
                </div>

                <div class="mvc-summary-box">
                    <div class="mvc-summary-row">
                        <span class="text-gray-500 dark:text-gray-400">Software:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedSoftware }}</span>
                    </div>
                    <div class="mvc-summary-row">
                        <span class="text-gray-500 dark:text-gray-400">Minecraft Version:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $selectedVersion }}</span>
                    </div>
                    <div class="mvc-summary-row">
                        <span class="text-gray-500 dark:text-gray-400">Build:</span>
                        <span class="text-gray-900 dark:text-white">{{ $selectedBuild['name'] ?? ('#' . ($selectedBuild['buildNumber'] ?? 'latest')) }}</span>
                    </div>
                    <div class="mvc-summary-row">
                        <span class="text-gray-500 dark:text-gray-400">Safety Backup:</span>
                        <span class="text-emerald-500 font-medium">server.jar → server.jar.bak</span>
                    </div>
                </div>

                @if($this->isServerRunning())
                    <div class="rounded-xl p-3 text-xs text-amber-700 dark:text-amber-300" style="background-color:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);">
                        ⚠️ <strong>Server is running:</strong> Replacing the JAR while running is safe for files, but you must restart the server afterwards to boot the new version.
                    </div>
                @endif

                <div class="mvc-modal-footer">
                    <button
                        type="button"
                        wire:click="closeConfirmModal"
                        class="mvc-btn mvc-btn-secondary"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="startVersionChange"
                        class="mvc-btn mvc-btn-primary"
                    >
                        Confirm & Install JAR
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showLogModal && $viewingRecord)
        <div
            class="mvc-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="mvc-log-title"
            @keydown.escape.window="$wire.closeLogModal()"
            wire:click.self="closeLogModal"
        >
            <div class="mvc-modal mvc-modal-wide">
                <div class="flex items-center justify-between" style="display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <h3 id="mvc-log-title" class="text-base font-bold text-gray-900 dark:text-white" style="margin:0;">
                            Execution Log #{{ $viewingRecord['id'] }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400" style="margin:0.125rem 0 0 0;">
                            {{ $viewingRecord['software'] }} {{ $viewingRecord['minecraft_version'] }} ({{ $viewingRecord['status'] }})
                        </p>
                    </div>
                    <button type="button" wire:click="closeLogModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" style="width:1.25rem;height:1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mvc-terminal rounded-xl p-4 text-xs h-72 overflow-y-auto whitespace-pre-wrap leading-relaxed border border-gray-800">
{{ $viewingRecord['log'] ?: 'No log recorded.' }}
                </div>

                <div class="mvc-modal-footer">
                    <button
                        type="button"
                        wire:click="closeLogModal"
                        class="mvc-btn mvc-btn-secondary"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
</x-filament-panels::page>
