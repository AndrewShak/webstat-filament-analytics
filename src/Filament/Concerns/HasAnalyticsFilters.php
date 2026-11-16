<?php

declare(strict_types=1);

namespace Webstat\Analytics\Filament\Concerns;

use Livewire\Attributes\Url;
use Webstat\Analytics\Support\Locale;
use Webstat\Analytics\Enums\Audience;
use Webstat\Analytics\Enums\Granularity;
use Webstat\Analytics\Enums\Preset;
use Webstat\Analytics\Reports\DateRange;
use Webstat\Analytics\Reports\Filters;

/**
 * Shared filter state for every analytics tab: URL-synced, remembered in the session so the
 * selection follows the user between tabs. All changes are Livewire round-trips (no page reload).
 */
trait HasAnalyticsFilters
{
    #[Url(as: 'range')]
    public string $range = 'month';

    #[Url(as: 'from')]
    public ?string $from = null;

    #[Url(as: 'to')]
    public ?string $to = null;

    #[Url(as: 'group')]
    public string $group = 'auto';

    #[Url(as: 'who')]
    public string $audience = 'humans';

    public bool $live = false;

    /** Runs on every request (initial load and Livewire updates) so all text renders in the chosen language. */
    public function bootHasAnalyticsFilters(): void
    {
        Locale::apply();
    }

    /** Switch the dashboard language (ru/en); full reload so the sidebar and layout follow as well. */
    public function setAnalyticsLanguage(string $lang): void
    {
        if (Locale::set($lang)) {
            $this->persistAnalyticsFilters();
            $this->redirect(static::getUrl(), navigate: false);
        }
    }

    public function mountHasAnalyticsFilters(): void
    {
        if (! request()->hasAny(['range', 'from', 'to', 'group', 'who']) && ($saved = session('analytics.filters'))) {
            foreach (['range', 'from', 'to', 'group', 'audience'] as $k) {
                if (array_key_exists($k, $saved)) {
                    $this->{$k} = $saved[$k];
                }
            }
        }
    }

    public function updatedRange(): void
    {
        $this->persistAnalyticsFilters();
    }

    public function updatedFrom(): void
    {
        $this->range = Preset::Custom->value;
        $this->persistAnalyticsFilters();
    }

    public function updatedTo(): void
    {
        $this->range = Preset::Custom->value;
        $this->persistAnalyticsFilters();
    }

    public function updatedGroup(): void
    {
        $this->persistAnalyticsFilters();
    }

    public function updatedAudience(): void
    {
        $this->persistAnalyticsFilters();
    }

    protected function persistAnalyticsFilters(): void
    {
        session(['analytics.filters' => $this->only(['range', 'from', 'to', 'group', 'audience'])]);
    }

    public function analyticsFilters(): Filters
    {
        return new Filters(
            DateRange::make(
                Preset::tryFrom($this->range) ?? Preset::Month,
                $this->from,
                $this->to,
                Granularity::tryFrom($this->group),
            ),
            Audience::tryFrom($this->audience) ?? Audience::Humans,
        );
    }
}
