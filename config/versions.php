<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Download Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum amount of time in seconds to wait for Wings to finish
    | pulling and writing the new server.jar file onto the server.
    |
    */
    'download_timeout' => (int) env('VERSIONS_DOWNLOAD_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Custom Queue Name
    |--------------------------------------------------------------------------
    |
    | The Laravel queue name to dispatch version change jobs to. Default is null
    | (uses Pelican's default queue consumed by standard workers).
    |
    */
    'queue' => env('VERSIONS_QUEUE', null),

    /*
    |--------------------------------------------------------------------------
    | API Cache TTL
    |--------------------------------------------------------------------------
    |
    | Cache duration in seconds for responses from the MCJars API.
    |
    */
    'api_cache_ttl' => (int) env('VERSIONS_API_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Keep Backup
    |--------------------------------------------------------------------------
    |
    | Whether to keep the previous server.jar as server.jar.bak during
    | the version change process.
    |
    */
    'keep_backup' => (bool) env('VERSIONS_KEEP_BACKUP', true),

    /*
    |--------------------------------------------------------------------------
    | Power Automation
    |--------------------------------------------------------------------------
    |
    | Automatically stop the server safely before changing versions and
    | restart it automatically once the download and verification finishes.
    |
    */
    'auto_stop_server'    => (bool) env('VERSIONS_AUTO_STOP', true),
    'auto_restart_server' => (bool) env('VERSIONS_AUTO_RESTART', true),

    /*
    |--------------------------------------------------------------------------
    | Maintenance Automation
    |--------------------------------------------------------------------------
    |
    | Auto-accept Minecraft EULA (eula=true) and clean the legacy /libraries/
    | directory to prevent class collisions across Minecraft versions.
    |
    */
    'auto_accept_eula' => (bool) env('VERSIONS_AUTO_ACCEPT_EULA', true),
    'clean_libraries'  => (bool) env('VERSIONS_CLEAN_LIBRARIES', true),

    /*
    |--------------------------------------------------------------------------
    | Sidebar Navigation Sort
    |--------------------------------------------------------------------------
    |
    | The sort order for the Versions page in the server sidebar navigation.
    |
    */
    'navigation_sort' => (int) env('VERSIONS_NAV_SORT', 8),

    /*
    |--------------------------------------------------------------------------
    | Default Minecraft Versions
    |--------------------------------------------------------------------------
    |
    | Common modern Minecraft versions displayed by default or prioritized.
    |
    */
    'default_mc_versions' => [
        '1.21.5', '1.21.4', '1.21.3', '1.21.1', '1.20.6', '1.20.4',
        '1.20.2', '1.20.1', '1.19.4', '1.18.2', '1.17.1', '1.16.5',
    ],
];
