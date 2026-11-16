{{-- Vertical gradient strip: one row per band; colour intensity = percentage (0-100). --}}
@props(['bands' => [], 'color' => 'var(--danger-500)', 'labels' => null])
<div style="display:flex;flex-direction:column;border-radius:.5rem;overflow:hidden;border:1px solid color-mix(in srgb,currentColor 14%,transparent)">
    @foreach ($bands as $i => $pct)
        <div title="{{ $labels[$i] ?? '' }} {{ $pct }}%" style="display:flex;align-items:center;justify-content:space-between;padding:.2rem .6rem;font-size:.72rem;height:1.6rem;background:color-mix(in srgb,{{ $color }} {{ max(0, min(100, $pct)) }}%,transparent)">
            <span style="opacity:.8">{{ $labels[$i] ?? '' }}</span><strong>{{ $pct }}%</strong>
        </div>
    @endforeach
</div>
