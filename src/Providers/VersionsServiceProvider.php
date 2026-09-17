<?php

namespace Pelican\Versions\Providers;

use Illuminate\Support\ServiceProvider;

class VersionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $viewsDir = plugin_path('versions', 'resources/views');
        if (is_dir($viewsDir)) {
            $this->loadViewsFrom($viewsDir, 'versions');
        }

        $configFile = plugin_path('versions', 'config/versions.php');
        if (file_exists($configFile)) {
            $this->mergeConfigFrom($configFile, 'versions');
        }
    }
}
