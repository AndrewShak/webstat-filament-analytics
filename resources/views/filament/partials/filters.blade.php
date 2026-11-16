@php use Webstat\Analytics\Enums\{Audience, Granularity, Preset}; $waLocale = \Webstat\Analytics\Support\Locale::current(); @endphp
<div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem 1rem">
    <x-filament::tabs>
        @foreach ([Preset::Day, Preset::Week, Preset::Month, Preset::Quarter, Preset::Year, Preset::Custom] as $p)
            <x-filament::tabs.item :active="$this->range === $p->value" wire:click="$set('range', '{{ $p->value }}')">
                {{ $p->label() }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($this->range === 'custom')
        <x-filament::input.wrapper style="width:10.5rem"><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        <x-filament::input.wrapper style="width:10.5rem"><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
    @endif

    <x-filament::input.wrapper style="width:9.5rem">
        <x-filament::input.select wire:model.live="group" aria-label="{{ __('analytics::analytics.group.label') }}">
            <option value="auto">{{ __('analytics::analytics.group.auto') }}</option>
            @foreach (Granularity::cases() as $g)<option value="{{ $g->value }}">{{ $g->label() }}</option>@endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>

    <x-filament::input.wrapper style="width:10.5rem">
        <x-filament::input.select wire:model.live="audience" aria-label="{{ __('analytics::analytics.audience.label') }}">
            @foreach (Audience::cases() as $a)<option value="{{ $a->value }}">{{ $a->label() }}</option>@endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>

    <label style="display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem">
        <x-filament::input.checkbox wire:model.live="live" /> {{ __('analytics::analytics.live') }}
    </label>
    <div style="display:inline-flex;border-radius:.5rem;overflow:hidden;border:1px solid color-mix(in srgb,currentColor 18%,transparent);margin-left:auto" role="group" aria-label="{{ __('analytics::analytics.language') }}">
        @foreach (\Webstat\Analytics\Support\Locale::supported() as $l)
            <button type="button" wire:click="setAnalyticsLanguage('{{ $l }}')" @disabled($waLocale === $l)
                    style="padding:.3rem .65rem;font-size:.8rem;font-weight:{{ $waLocale === $l ? 600 : 400 }};background:{{ $waLocale === $l ? 'color-mix(in srgb,var(--primary-500) 18%,transparent)' : 'transparent' }};cursor:{{ $waLocale === $l ? 'default' : 'pointer' }}">{{ strtoupper($l) }}</button>
        @endforeach
    </div>
    <span wire:loading style="font-size:.8rem;opacity:.6"><x-filament::loading-indicator style="width:1rem;height:1rem;display:inline" /></span>
</div>
<p style="font-size:.78rem;opacity:.6;margin:.25rem 0 0">
    {{ $filters->range->from->translatedFormat('j M Y H:i') }} — {{ $filters->range->to->translatedFormat('j M Y H:i') }}
</p>
