<?php

declare(strict_types=1);

namespace Webstat\Analytics\Tests\Support;

use Filament\Panel;
use Filament\PanelProvider;
use Webstat\Analytics\Filament\AnalyticsPlugin;

/** A minimal panel that registers the plugin exactly like a real project's AdminPanelProvider. */
final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')->authGuard('web')->login()->plugin(AnalyticsPlugin::make());
    }
}
