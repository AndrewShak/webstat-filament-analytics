<?php

declare(strict_types=1);

namespace Webstat\Analytics\Tests\Support;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Session\Middleware\StartSession;
use Webstat\Analytics\Filament\AnalyticsPlugin;

final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('web')
            ->login()
            ->middleware([
                StartSession::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(AnalyticsPlugin::make());
    }
}
