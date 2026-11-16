<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Webstat\Analytics\Filament\AnalyticsAccess;
use Webstat\Analytics\Support\Locale;
use Webstat\Analytics\Filament\AnalyticsCluster;
use Webstat\Analytics\Filament\Concerns\HasAnalyticsFilters;
use Webstat\Analytics\Reports\AnalyticsReports;

final class Audience extends Page
{
    use HasAnalyticsFilters;

    protected static ?string $cluster = AnalyticsCluster::class;

    protected static ?string $slug = 'audience';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getNavigationLabel(): string
    {
        return Locale::t('tabs.audience');
    }

    public function getTitle(): string|Htmlable
    {
        return Locale::t('tabs.audience');
    }

    public function getView(): string
    {
        return 'analytics::filament.pages.audience';
    }

    public static function canAccess(): bool
    {
        return AnalyticsAccess::allowed();
    }

    public function getViewData(): array
    {
        $r = app(AnalyticsReports::class);
        $f = $this->analyticsFilters();

        return [
            'filters' => $f,
            'overview' => $r->overview($f),
            'series' => $r->timeseries($f),
            'humanVsBot' => $r->humanVsBot($f),
            'newVsReturning' => $r->newVsReturning($f),
            'geo' => $r->geo($f),
            'demographics' => $r->demographics($f),
            'activeNow' => $r->activeNow(),
            'recent' => $r->recentVisits($f),
            'topIps' => $r->topIps($f),
            'geoCoverage' => $r->geoCoverage($f),
            'tech' => $r->tech($f),
        ];
    }
}
