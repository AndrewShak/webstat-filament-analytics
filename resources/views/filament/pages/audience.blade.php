<x-filament-panels::page>
    @include('analytics::filament.partials.styles')
    @php
        $o = $overview;
        $newTotal = $newVsReturning['new_users'] + $newVsReturning['returning_users'];
    @endphp
    <div class="wa-root" @if ($this->live) wire:poll.15s @endif>
        @include('analytics::filament.partials.filters')

        <div class="wa-stats">
            <x-analytics::stat :label="__('analytics::analytics.metrics.users')" :value="number_format($o['users']['value'])" :delta="$o['users']['delta']" />
            <x-analytics::stat :label="__('analytics::analytics.metrics.sessions')" :value="number_format($o['sessions']['value'])" :delta="$o['sessions']['delta']" />
            <x-analytics::stat :label="__('analytics::analytics.metrics.pageviews')" :value="number_format($o['pageviews']['value'])" :delta="$o['pageviews']['delta']" />
            <x-analytics::stat :label="__('analytics::analytics.metrics.avg_duration')" :value="\Webstat\Analytics\Support\Svg::duration($o['avg_duration']['value'])" :delta="$o['avg_duration']['delta']" />
            <x-analytics::stat :label="__('analytics::analytics.metrics.active_now')" :value="number_format($activeNow)" :hint="__('analytics::analytics.metrics.last5')" />
        </div>

        <x-filament::section :heading="__('analytics::analytics.sections.traffic')">
            <x-analytics::line-chart :labels="$series['labels']" :series="[
                ['name' => __('analytics::analytics.metrics.pageviews'), 'color' => 'var(--info-500)', 'values' => $series['pageviews']],
                ['name' => __('analytics::analytics.metrics.sessions'), 'color' => 'var(--success-500)', 'values' => $series['sessions']],
                ['name' => __('analytics::analytics.metrics.users'), 'color' => 'var(--primary-500)', 'values' => $series['users']],
            ]" />
        </x-filament::section>

        <div class="wa-grid">
            <x-filament::section :heading="__('analytics::analytics.sections.human_bot')" :description="__('analytics::analytics.sections.human_bot_help')">
                <x-analytics::donut :items="[
                    ['label' => __('analytics::analytics.audience.humans'), 'value' => $humanVsBot['humans']],
                    ['label' => __('analytics::analytics.audience.bots'), 'value' => $humanVsBot['bots']],
                ]" />
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.new_returning')">
                <p class="wa-sub">{{ __('analytics::analytics.sections.users') }}</p>
                <x-analytics::donut :items="[
                    ['label' => __('analytics::analytics.new'), 'value' => $newVsReturning['new_users']],
                    ['label' => __('analytics::analytics.returning'), 'value' => $newVsReturning['returning_users']],
                ]" />
                <p class="wa-sub" style="margin-top:1rem">{{ __('analytics::analytics.metrics.sessions') }}: {{ number_format($newVsReturning['new_sessions']) }} / {{ number_format($newVsReturning['returning_sessions']) }}</p>
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.countries')" :description="__('analytics::analytics.sections.geo_help')">
                @if ($geoCoverage['total'] > 0 && $geoCoverage['with_country'] === 0)
                    <p style="font-size:.82rem;color:var(--warning-600);margin:0 0 .6rem">{{ __('analytics::analytics.geo_not_configured') }} <code>php artisan analytics:geo-update</code></p>
                @endif
                <x-analytics::bar-list :rows="$geo['countries']" color="var(--info-500)" />
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.cities')">
                <x-analytics::bar-list :rows="$geo['cities']" color="var(--success-500)" />
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.ips')" :description="__('analytics::analytics.sections.ips_help')">
                <x-analytics::bar-list :rows="$topIps" color="var(--warning-500)" :empty="__('analytics::analytics.ip_not_stored')" />
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.device_models')">
                <x-analytics::bar-list :rows="$tech['models']" color="var(--primary-500)" />
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.age')" :description="__('analytics::analytics.sections.demo_help')">
                <x-analytics::donut :items="$demographics['age']" translate="unknown_map" />
            </x-filament::section>

            <x-filament::section :heading="__('analytics::analytics.sections.gender')">
                <x-analytics::donut :items="$demographics['gender']" translate="gender_map" />
            </x-filament::section>
        </div>

        <x-filament::section :heading="__('analytics::analytics.sections.recent')" :description="__('analytics::analytics.sections.recent_help')">
            @if (! $recent)
                <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.no_data') }}</p>
            @else
                <div style="overflow-x:auto">
                <table class="wa-table">
                    <thead><tr>
                        <th>{{ __('analytics::analytics.recent.time') }}</th><th>IP</th><th>{{ __('analytics::analytics.recent.location') }}</th>
                        <th>{{ __('analytics::analytics.recent.device') }}</th><th>{{ __('analytics::analytics.recent.system') }}</th>
                        <th>{{ __('analytics::analytics.recent.source') }}</th><th>{{ __('analytics::analytics.recent.landing') }}</th>
                        <th class="num">{{ __('analytics::analytics.metrics.pageviews') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($recent as $v)
                        @php
                            $dev = trim(($v['device_brand'] ? $v['device_brand'].' ' : '').($v['device_model'] ?? ''));
                            $src = $v['search_engine'] ?: ($v['referrer_host'] ?: __('analytics::analytics.source_map.'.$v['source_type']));
                        @endphp
                        <tr>
                            <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($v['started_at'])->translatedFormat('j M H:i') }}</td>
                            <td style="font-family:monospace">{{ $v['ip'] ?? '—' }}@if ($v['is_bot']) <x-filament::badge color="gray" size="sm">{{ $v['bot_name'] ?: 'bot' }}</x-filament::badge>@endif</td>
                            <td>{{ $v['country'] ? \Webstat\Analytics\Support\Countries::flag($v['country']).' '.\Webstat\Analytics\Support\Countries::name($v['country']) : '—' }}@if ($v['city']), {{ $v['city'] }}@endif</td>
                            <td>{{ __('analytics::analytics.device_map.'.($v['device'] ?? 'desktop')) }}@if ($dev) · {{ $dev }}@endif @if ($v['screen'])<span style="opacity:.55"> · {{ $v['screen'] }}</span>@endif</td>
                            <td>{{ $v['browser'] }}{{ $v['browser_version'] ? ' '.$v['browser_version'] : '' }} · {{ trim($v['os'].' '.($v['os_version'] ?? '')) }}</td>
                            <td>{{ $src }}</td>
                            <td style="max-width:14rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $v['landing_path'] }}">{{ $v['landing_path'] }}</td>
                            <td class="num">{{ $v['pageviews'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
