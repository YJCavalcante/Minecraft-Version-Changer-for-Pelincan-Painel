<?php

return [
    'download_timeout'       => (int) env('VERSIONS_DOWNLOAD_TIMEOUT', 600),
    'download_start_timeout' => (int) env('VERSIONS_DOWNLOAD_START_TIMEOUT', 90),
    'queue'                  => env('VERSIONS_QUEUE', null),
    'api_cache_ttl'          => (int) env('VERSIONS_API_CACHE_TTL', 21600),
    'builds_cache_ttl'       => (int) env('VERSIONS_BUILDS_CACHE_TTL', 900),
    'builds_limit'           => (int) env('VERSIONS_BUILDS_LIMIT', 60),
    'detection_cache_ttl'    => (int) env('VERSIONS_DETECTION_CACHE_TTL', 30),
    'keep_backup'            => (bool) env('VERSIONS_KEEP_BACKUP', true),
    'auto_stop_server'       => (bool) env('VERSIONS_AUTO_STOP', true),
    'auto_restart_server'    => (bool) env('VERSIONS_AUTO_RESTART', true),
    'abort_if_not_stopped'   => (bool) env('VERSIONS_ABORT_IF_NOT_STOPPED', true),
    'stop_wait_seconds'      => (int) env('VERSIONS_STOP_WAIT', 60),
    'clean_libraries'        => (bool) env('VERSIONS_CLEAN_LIBRARIES', true),
    'navigation_sort'        => (int) env('VERSIONS_NAV_SORT', 8),
    'stale_after_minutes'    => (int) env('VERSIONS_STALE_AFTER', 90),
    'history_retention_days' => (int) env('VERSIONS_HISTORY_RETENTION', 90),
    'warm_builds_versions'   => (int) env('VERSIONS_WARM_BUILDS_VERSIONS', 2),
    'allowed_download_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('VERSIONS_ALLOWED_HOSTS', ''))))),
];
