<?php

declare(strict_types=1);

namespace Webstat\Analytics;

use Filament\Panel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webstat\Analytics\Console\FlushCommand;
use Webstat\Analytics\Console\GeoUpdateCommand;
use Webstat\Analytics\Console\PruneCommand;
use Webstat\Analytics\Filament\AnalyticsPlugin;
use Webstat\Analytics\Http\Middleware\InjectTracker;
use Webstat\Analytics\Ingest\Buffer\DirectBuffer;
use Webstat\Analytics\Ingest\Buffer\EventBuffer;
use Webstat\Analytics\Ingest\Buffer\RedisBuffer;
use Webstat\Analytics\Ingest\DemographicsEnricher;
use Webstat\Analytics\Ingest\Geo\ChainGeoResolver;
use Webstat\Analytics\Ingest\Geo\GeoResolver;
use Webstat\Analytics\Ingest\Geo\HeaderGeoResolver;
use Webstat\Analytics\Ingest\Geo\HttpIpLookup;
use Webstat\Analytics\Ingest\Geo\IpLookup;
use Webstat\Analytics\Ingest\Geo\NullIpLookup;
use Webstat\Analytics\Ingest\Geo\MaxMindGeoResolver;
use Webstat\Analytics\Ingest\Geo\NullGeoResolver;
use Webstat\Analytics\Ingest\NullDemographicsEnricher;
use Webstat\Analytics\Reports\AnalyticsReports;
use Webstat\Analytics\Support\TrackerScript;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/analytics.php', 'analytics');

        $this->app->singleton(TrackerScript::class);
        $this->app->singleton(AnalyticsReports::class);

        $this->app->singleton(GeoResolver::class, function ($app): GeoResolver {
            $driver = (string) config('analytics.geo.driver', 'auto');

            return match ($driver) {
                'auto' => new ChainGeoResolver([new HeaderGeoResolver(), new MaxMindGeoResolver()]),
                'headers' => new HeaderGeoResolver(),
                'maxmind' => new MaxMindGeoResolver(),
                'null' => new NullGeoResolver(),
                default => class_exists($driver) ? $app->make($driver) : new NullGeoResolver(),
            };
        });

        $this->app->singleton(IpLookup::class, fn (): IpLookup => config('analytics.geo.http.enabled') ? new HttpIpLookup() : new NullIpLookup());

        $this->app->singleton(DemographicsEnricher::class, function ($app): DemographicsEnricher {
            $class = config('analytics.demographics.enricher');

            return $class && class_exists((string) $class) ? $app->make($class) : new NullDemographicsEnricher();
        });

        $this->app->singleton(RedisBuffer::class);
        $this->app->singleton(EventBuffer::class, fn ($app): EventBuffer => config('analytics.buffer.driver') === 'redis'
            ? $app->make(RedisBuffer::class)
            : $app->make(DirectBuffer::class));

        // Auto-register the Filament plugin on every panel (restricted per panel by analytics.filament.panels).
        if (config('analytics.filament.auto_register', true) && class_exists(Panel::class) && method_exists(Panel::class, 'configureUsing')) {
            Panel::configureUsing(static fn (Panel $panel) => $panel->plugin(AnalyticsPlugin::make()));
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'analytics');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'analytics');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', 'analytics');

        RateLimiter::for('analytics-collect', fn (Request $r): Limit => Limit::perMinute((int) config('analytics.route.rate_limit', 240))->by($r->ip()));

        if (config('analytics.enabled')) {
            Route::prefix(trim((string) config('analytics.route.prefix', '_wa'), '/'))
                ->domain(config('analytics.route.domain'))
                ->group(__DIR__ . '/../routes/analytics.php');
        }

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('analytics.inject', InjectTracker::class);
        if (config('analytics.inject.enabled') && config('analytics.inject.global')) {
            $router->pushMiddlewareToGroup('web', InjectTracker::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([FlushCommand::class, PruneCommand::class, GeoUpdateCommand::class]);

            $this->publishes([__DIR__ . '/../config/analytics.php' => config_path('analytics.php')], 'analytics-config');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'analytics-migrations');
            $this->publishes([__DIR__ . '/../resources/views' => resource_path('views/vendor/analytics')], 'analytics-views');
            $this->publishes([__DIR__ . '/../resources/lang' => lang_path('vendor/analytics')], 'analytics-lang');
        }

        if (config('analytics.schedule', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                if (config('analytics.buffer.driver') === 'redis') {
                    $schedule->command('analytics:flush')->everyMinute()->withoutOverlapping(5)->runInBackground();
                }
                $schedule->command('analytics:prune')->dailyAt('03:17')->onOneServer();
            });
        }
    }
}
