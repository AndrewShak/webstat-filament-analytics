<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class AnalyticsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'webstat-analytics';
    }

    public function register(Panel $panel): void
    {
        $panel->discoverClusters(
            __DIR__,
            __NAMESPACE__,
        );
    }

    public function boot(Panel $panel): void
    {
    }
}
