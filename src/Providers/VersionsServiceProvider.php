<?php

namespace Pelican\Versions\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Pelican\Versions\Console\Commands\WarmVersionCacheCommand;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                WarmVersionCacheCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('versions:warm-cache')
                ->dailyAt('03:00')
                ->withoutOverlapping()
                ->runInBackground()
                ->onFailure(function () {
                    \Illuminate\Support\Facades\Log::warning('[Versions] Daily cache warm failed.');
                });
        });
    }
}
