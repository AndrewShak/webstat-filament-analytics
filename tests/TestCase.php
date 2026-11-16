<?php

declare(strict_types=1);

namespace Webstat\Analytics\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Webstat\Analytics\AnalyticsServiceProvider;
use Webstat\Analytics\Tests\Support\TestPanelProvider;
use Webstat\Analytics\Tests\Support\TestUser;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $filament = [
            \BladeUI\Icons\BladeIconsServiceProvider::class,
            \BladeUI\Heroicons\BladeHeroiconsServiceProvider::class,
            \RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
        ];

        return [
            ...array_values(array_filter($filament, 'class_exists')),
            AnalyticsServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('app.locale', 'en');
        $app['config']->set('analytics.filament.auto_register', false); // the test panel registers the plugin itself
        $app['config']->set('analytics.filament.cache_seconds', 0);
        $app['config']->set('analytics.geo.driver', 'headers');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function admin(): TestUser
    {
        return TestUser::forceCreate(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('secret')]);
    }
}
