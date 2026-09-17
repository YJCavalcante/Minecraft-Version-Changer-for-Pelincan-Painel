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
