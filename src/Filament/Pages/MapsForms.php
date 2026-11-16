<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Webstat\Analytics\Filament\AnalyticsAccess;
use Webstat\Analytics\Support\Locale;
use Webstat\Analytics\Filament\AnalyticsCluster;
use Webstat\Analytics\Filament\Concerns\HasAnalyticsFilters;
use Webstat\Analytics\Reports\AnalyticsReports;

final class MapsForms extends Page
{
    use HasAnalyticsFilters;

    protected static ?string $cluster = AnalyticsCluster::class;

    protected static ?string $slug = 'maps';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRays;

    #[Url(as: 'page')]
    public ?string $path = null;

    #[Url(as: 'map')]
    public string $map = 'click';

    #[Url(as: 'form')]
    public ?string $form = null;

    public bool $showPage = true;

    public static function getNavigationLabel(): string
    {
        return Locale::t('tabs.maps');
    }

    public function getTitle(): string|Htmlable
    {
        return Locale::t('tabs.maps');
    }

    public function getView(): string
    {
        return 'analytics::filament.pages.maps';
    }

    public static function canAccess(): bool
    {
        return AnalyticsAccess::allowed();
    }

    public function getViewData(): array
    {
        $r = app(AnalyticsReports::class);
        $f = $this->analyticsFilters();

        $pages = $r->mapPages($f);
        $known = array_column($pages, 'label');
        $path = $this->path !== null && in_array($this->path, $known, true) ? $this->path : ($known[0] ?? null);

        $forms = $r->forms($f);
        $formKeys = array_column($forms, 'form');
        $form = $this->form !== null && in_array($this->form, $formKeys, true) ? $this->form : ($formKeys[0] ?? null);

        return [
            'filters' => $f,
            'pages' => $pages,
            'path' => $path,
            'map' => in_array($this->map, ['click', 'scroll', 'view', 'link'], true) ? $this->map : 'click',
            'click' => $path && $this->map === 'click' ? $r->clickMap($f, $path) : null,
            'scroll' => $path && $this->map === 'scroll' ? $r->scrollMap($f, $path) : null,
            'view' => $path && $this->map === 'view' ? $r->viewMap($f, $path) : null,
            'links' => $path && $this->map === 'link' ? $r->linkMap($f, $path) : [],
            'forms' => $forms,
            'formKey' => $form,
            'fields' => $form ? $r->formFields($f, $form) : [],
        ];
    }
}
