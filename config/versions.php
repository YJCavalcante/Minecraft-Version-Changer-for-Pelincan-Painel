<?php

return [
    'download_timeout'    => (int) env('VERSIONS_DOWNLOAD_TIMEOUT', 600),
    'queue'               => env('VERSIONS_QUEUE', null),
    'api_cache_ttl'       => (int) env('VERSIONS_API_CACHE_TTL', 300),
    'keep_backup'         => (bool) env('VERSIONS_KEEP_BACKUP', true),
    'auto_stop_server'    => (bool) env('VERSIONS_AUTO_STOP', true),
    'auto_restart_server' => (bool) env('VERSIONS_AUTO_RESTART', true),
    'clean_libraries'     => (bool) env('VERSIONS_CLEAN_LIBRARIES', true),
    'navigation_sort'     => (int) env('VERSIONS_NAV_SORT', 8),
    'default_mc_versions' => [
        '1.21.5', '1.21.4', '1.21.3', '1.21.1', '1.20.6', '1.20.4',
        '1.20.2', '1.20.1', '1.19.4', '1.18.2', '1.17.1', '1.16.5',
    ],
];
