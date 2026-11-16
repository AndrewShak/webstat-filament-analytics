<x-filament-panels::page>
    @include('analytics::filament.partials.styles')
    <div class="wa-root" @if ($this->live) wire:poll.15s @endif>
        @include('analytics::filament.partials.filters')

        <div class="wa-grid">
            <x-filament::section :heading="__('analytics::analytics.sections.source_types')">
                <x-analytics::donut :items="$sources['types']" translate="source_map" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.search_engines')">
                <x-analytics::bar-list :rows="$sources['search']" color="var(--warning-500)" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.referrers')">
                <x-analytics::bar-list :rows="$sources['referrers']" color="var(--info-500)" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.landing')" :description="__('analytics::analytics.sections.landing_help')">
                <x-analytics::bar-list :rows="$sources['landing']" color="var(--success-500)" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.titles')">
                <x-analytics::bar-list :rows="$sources['titles']" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.top_pages')">
                <x-analytics::bar-list :rows="$sources['pages']" />
            </x-filament::section>
        </div>

        <x-filament::section :heading="__('analytics::analytics.sections.paths')" :description="__('analytics::analytics.sections.paths_help')">
            @if ($paths)
                <table class="wa-table">
                    <thead><tr><th>{{ __('analytics::analytics.from') }}</th><th>{{ __('analytics::analytics.to') }}</th><th class="num">{{ __('analytics::analytics.transitions') }}</th></tr></thead>
                    <tbody>
                    @foreach ($paths as $p)
                        <tr><td>{{ $p['label'] }}</td><td>→ {{ $p['hint'] }}</td><td class="num">{{ number_format($p['value']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.no_data') }}</p>
            @endif
        </x-filament::section>

        <div class="wa-grid">
            <x-filament::section :heading="__('analytics::analytics.sections.devices')">
                <x-analytics::donut :items="$tech['devices']" translate="device_map" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.browsers')">
                <x-analytics::bar-list :rows="$tech['browsers']" color="var(--primary-500)" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.os')">
                <x-analytics::bar-list :rows="$tech['os_versions']" color="var(--info-500)" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.device_models')">
                <x-analytics::bar-list :rows="$tech['models']" color="var(--primary-500)" />
            </x-filament::section>
            <x-filament::section :heading="__('analytics::analytics.sections.screens')">
                <x-analytics::bar-list :rows="$tech['screens']" color="var(--success-500)" />
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
