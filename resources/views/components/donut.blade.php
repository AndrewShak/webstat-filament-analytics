@props(['items' => [], 'translate' => null])
@php
    $total = array_sum(array_column($items, 'value'));
    $palette = \Webstat\Analytics\Support\Svg::PALETTE;
    $offset = 25;
@endphp
@if ($total === 0)
    <p style="opacity:.6;font-size:.85rem">{{ __('analytics::analytics.no_data') }}</p>
@else
<div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap">
    <svg viewBox="0 0 42 42" style="width:8rem;height:8rem;flex:none" role="img">
        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="currentColor" stroke-opacity=".08" stroke-width="6"/>
        @foreach ($items as $i => $it)
            @php $pct = $it['value'] / $total * 100; @endphp
            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $palette[$i % count($palette)] }}" stroke-width="6"
                    stroke-dasharray="{{ round($pct, 3) }} {{ round(100 - $pct, 3) }}" stroke-dashoffset="{{ round($offset, 3) }}">
                <title>{{ $it['label'] }}: {{ number_format($it['value']) }} ({{ round($pct, 1) }}%)</title>
            </circle>
            @php $offset -= $pct; @endphp
        @endforeach
    </svg>
    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.3rem;font-size:.85rem">
        @foreach ($items as $i => $it)
            @php
                $label = $it['label'];
                if ($translate && \Illuminate\Support\Facades\Lang::has("analytics::analytics.{$translate}.{$label}")) { $label = __("analytics::analytics.{$translate}.{$label}"); }
            @endphp
            <li style="display:flex;align-items:center;gap:.5rem">
                <i style="width:.7rem;height:.7rem;border-radius:9999px;background:{{ $palette[$i % count($palette)] }}"></i>
                <span>{{ $label }}</span>
                <strong style="font-variant-numeric:tabular-nums">{{ number_format($it['value']) }}</strong>
                <span style="opacity:.55">{{ round($it['value'] / $total * 100, 1) }}%</span>
            </li>
        @endforeach
    </ul>
</div>
@endif
