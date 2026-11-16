<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;
use Webstat\Analytics\Support\Locale;

/** The "Analytics" navigation entry; its pages become the sub-navigation (tabs). */
final class AnalyticsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $slug = 'analytics';

    public static function getNavigationLabel(): string
    {
        return Locale::t('navigation');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return Locale::t('navigation');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('analytics.filament.navigation_sort', 90);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('analytics.filament.navigation_group');
    }

    public static function canAccess(): bool
    {
        return AnalyticsAccess::allowed();
    }
}
