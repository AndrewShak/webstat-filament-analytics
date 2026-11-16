<x-filament-panels::page>
    @include('analytics::filament.partials.styles')
    <div class="wa-root" @if ($this->live) wire:poll.15s @endif>
        @include('analytics::filament.partials.filters')

        <x-filament::section :heading="__('analytics::analytics.sections.maps')">
            @if (! $path)
                <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.no_data') }}</p>
            @else
                <div style="display:flex;flex-wrap:wrap;gap:.75rem 1rem;align-items:center;margin-bottom:1rem">
                    <x-filament::input.wrapper style="min-width:16rem;flex:1;max-width:32rem">
                        <x-filament::input.select wire:model.live="path">
                            @foreach ($pages as $p)
                                <option value="{{ $p['label'] }}" @selected($p['label'] === $path)>{{ $p['label'] }} ({{ number_format($p['value']) }})</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::tabs>
                        @foreach (['click', 'scroll', 'view', 'link'] as $m)
                            <x-filament::tabs.item :active="$map === $m" wire:click="$set('map', '{{ $m }}')">{{ __("analytics::analytics.maps.{$m}") }}</x-filament::tabs.item>
                        @endforeach
                    </x-filament::tabs>
                    @if ($map === 'click')
                        <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem">
                            <x-filament::input.checkbox wire:model.live="showPage" /> {{ __('analytics::analytics.maps.show_page') }}
                        </label>
                    @endif
                </div>
                <p class="wa-sub">{{ __("analytics::analytics.maps.{$map}_help") }}</p>

                @if ($map === 'click' && $click)
                    <div class="wa-grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
                        <x-analytics::click-heatmap :cells="$click['cells']" :max="$click['max']" :height="$click['height']" :path="$path" :show-page="$showPage" />
                        <div>
                            <p class="wa-sub"><strong>{{ number_format($click['total']) }}</strong> {{ __('analytics::analytics.maps.clicks') }} · {{ __('analytics::analytics.maps.top_elements') }}</p>
                            <x-analytics::bar-list :rows="$click['targets']" color="var(--danger-500)" />
                        </div>
                    </div>
                @elseif ($map === 'scroll' && $scroll)
                    <p class="wa-sub"><strong>{{ number_format($scroll['total']) }}</strong> {{ __('analytics::analytics.maps.views') }}</p>
                    @php $labels = array_map(fn ($d) => $d . '%', array_keys($scroll['reach'])); @endphp
                    <div style="max-width:28rem"><x-analytics::heat-bands :bands="array_values($scroll['reach'])" :labels="$labels" color="var(--primary-500)" /></div>
                @elseif ($map === 'view' && $view)
                    <p class="wa-sub"><strong>{{ number_format($view['total']) }}</strong> {{ __('analytics::analytics.maps.views') }}</p>
                    @php $labels = array_map(fn ($i) => ($i * 5) . '–' . (($i + 1) * 5) . '%', array_keys($view['bands'])); @endphp
                    <div style="max-width:28rem"><x-analytics::heat-bands :bands="$view['bands']" :labels="$labels" color="var(--warning-500)" /></div>
                @elseif ($map === 'link')
                    <x-analytics::bar-list :rows="$links" color="var(--info-500)" />
                @endif
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('analytics::analytics.sections.forms')" :description="__('analytics::analytics.sections.forms_help')">
            @if (! $forms)
                <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.no_data') }}</p>
            @else
                <div style="overflow-x:auto">
                <table class="wa-table">
                    <thead><tr>
                        <th>{{ __('analytics::analytics.forms.form') }}</th>
                        <th class="num">{{ __('analytics::analytics.forms.starts') }}</th>
                        <th class="num">{{ __('analytics::analytics.forms.submits') }}</th>
                        <th class="num">{{ __('analytics::analytics.forms.abandons') }}</th>
                        <th class="num">{{ __('analytics::analytics.forms.conversion') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($forms as $fm)
                        <tr wire:key="form-{{ $loop->index }}" wire:click="$set('form', @js($fm['form']))" style="cursor:pointer;{{ $fm['form'] === $formKey ? 'background:color-mix(in srgb,var(--primary-500) 10%,transparent)' : '' }}">
                            <td style="font-family:monospace">{{ $fm['form'] }}</td>
                            <td class="num">{{ number_format($fm['starts']) }}</td>
                            <td class="num">{{ number_format($fm['submits']) }}</td>
                            <td class="num">{{ number_format($fm['abandons']) }}</td>
                            <td class="num"><x-filament::badge :color="$fm['conversion'] >= 50 ? 'success' : ($fm['conversion'] >= 20 ? 'warning' : 'danger')">{{ $fm['conversion'] }}%</x-filament::badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>

                @if ($fields)
                    <h4 style="margin:1.25rem 0 .5rem;font-weight:600">{{ __('analytics::analytics.forms.fields') }} <code>{{ $formKey }}</code></h4>
                    <x-analytics::bar-list :rows="array_map(fn ($f) => ['label' => $f['field'], 'value' => $f['users'], 'hint' => $f['abandoned_here'] ? __('analytics::analytics.forms.left_here', ['n' => $f['abandoned_here']]) : null], $fields)" color="var(--warning-500)" />
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
