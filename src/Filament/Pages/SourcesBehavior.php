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

final class SourcesBehavior extends Page
{
    use HasAnalyticsFilters;

    protected static ?string $cluster = AnalyticsCluster::class;

    protected static ?string $slug = 'sources';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function getNavigationLabel(): string
    {
        return Locale::t('tabs.sources');
    }

    public function getTitle(): string|Htmlable
    {
        return Locale::t('tabs.sources');
    }

    public function getView(): string
    {
        return 'analytics::filament.pages.sources';
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
            'sources' => $r->sources($f),
            'paths' => $r->paths($f),
            'tech' => $r->tech($f),
        ];
    }
}
