<?php

namespace Pelican\Versions;

use App\Models\Subuser;
use Filament\Contracts\Plugin;
use Filament\Panel;

class VersionsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'versions';
    }

    public function register(Panel $panel): void
    {
        Subuser::registerCustomPermissions(
            'versions',
            ['change'],
            null,
            'tabler-arrows-exchange',
            false
        );

        $panelName = str($panel->getId())->title();
        $pagesDir = plugin_path($this->getId(), "src/Filament/{$panelName}/Pages");

        if (is_dir($pagesDir)) {
            $panel->discoverPages(
                $pagesDir,
                "Pelican\\Versions\\Filament\\{$panelName}\\Pages"
            );
        }
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }
}
