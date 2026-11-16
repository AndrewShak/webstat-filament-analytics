<x-filament-panels::page>
    @include('analytics::filament.partials.styles')
    <div class="wa-root" @if ($this->live) wire:poll.15s @endif>
        @include('analytics::filament.partials.filters')

        <div class="wa-grid">
            <x-filament::section :heading="__('analytics::analytics.sections.conversions')" :description="__('analytics::analytics.sections.conversions_help')">
                @if (! $goals)
                    <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.goal.no_active') }}</p>
                @else
                    <div style="display:flex;flex-direction:column;gap:.8rem">
                        @foreach ($goals as $g)
                            <div>
                                <div style="display:flex;justify-content:space-between;font-size:.85rem;gap:1rem">
                                    <strong>{{ $g['name'] }}</strong>
                                    <span><strong>{{ $g['rate'] }}%</strong> <span style="opacity:.6">· {{ number_format($g['sessions']) }} / {{ number_format($g['total']) }} {{ __('analytics::analytics.metrics.sessions_l') }}@if ($g['value']) · Σ {{ number_format($g['value']) }}@endif</span></span>
                                </div>
                                <div style="height:.5rem;border-radius:9999px;background:color-mix(in srgb,currentColor 10%,transparent);overflow:hidden;margin-top:.3rem">
                                    <div style="height:100%;width:{{ min(100, $g['rate']) }}%;background:var(--success-500)"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.funnel')" :description="__('analytics::analytics.sections.funnel_help')">
                @if (count($funnel) < 2)
                    <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.goal.funnel_hint') }}</p>
                @else
                    <div style="display:flex;flex-direction:column;align-items:center;gap:.35rem">
                        @foreach ($funnel as $s)
                            <div style="width:{{ max(8, $s['of_first']) }}%;min-width:9rem;background:var(--primary-500);opacity:{{ max(.35, $s['of_first'] / 100) }};color:#fff;border-radius:.4rem;padding:.45rem .7rem;font-size:.82rem;display:flex;justify-content:space-between;gap:.75rem">
                                <span>{{ $s['step'] }}. {{ $s['name'] }}</span>
                                <strong>{{ number_format($s['count']) }}</strong>
                            </div>
                            @if ($s['of_prev'] !== null)
                                <span style="font-size:.72rem;opacity:.65">↓ {{ $s['of_prev'] }}% · {{ __('analytics::analytics.goal.dropoff', ['n' => round(100 - $s['of_prev'], 1)]) }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section :heading="__('analytics::analytics.sections.manage_goals')">
            {{ $this->table }}
        </x-filament::section>

        <x-filament::section :heading="__('analytics::analytics.sections.js_api')" collapsible collapsed>
            <pre style="font-size:.8rem;overflow-x:auto">analytics.track('signup', 49);          // event goal (+ optional value)
&lt;button data-wa-goal="subscribe"&gt;…&lt;/button&gt;  // declarative event goal
&lt;div data-wa-ignore&gt;…&lt;/div&gt;                 // never tracked
&lt;div data-wa-mask&gt;…&lt;/div&gt;                   // clicks tracked, label text omitted</pre>
        </x-filament::section>
    </div>
</x-filament-panels::page>
