@props(['rows' => [], 'color' => 'var(--primary-500)', 'suffix' => '', 'empty' => null, 'translate' => null])
@php $max = max(1, ...array_map(fn ($r) => $r['value'], $rows ?: [['value' => 1]])); @endphp
@if (! $rows)
    <p style="opacity:.6;font-size:.85rem">{{ $empty ?? __('analytics::analytics.no_data') }}</p>
@else
    <div style="display:flex;flex-direction:column;gap:.45rem">
        @foreach ($rows as $row)
            @php
                $label = $row['label'] !== '' ? $row['label'] : '—';
                if ($translate && \Illuminate\Support\Facades\Lang::has("analytics::analytics.{$translate}.{$label}")) { $label = __("analytics::analytics.{$translate}.{$label}"); }
            @endphp
            <div style="position:relative;border-radius:.4rem;overflow:hidden;font-size:.85rem">
                <div style="position:absolute;inset:0;width:{{ round($row['value'] / $max * 100, 1) }}%;background:{{ $color }};opacity:.16"></div>
                <div style="position:relative;display:flex;justify-content:space-between;gap:.75rem;padding:.3rem .55rem">
                    <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $label }}">
                        {{ $label }}@if (! empty($row['hint']))<span style="opacity:.55"> · {{ \Illuminate\Support\Str::limit($row['hint'], 50) }}</span>@endif
                    </span>
                    <strong style="font-variant-numeric:tabular-nums">{{ number_format($row['value']) }}{{ $suffix }}</strong>
                </div>
            </div>
        @endforeach
    </div>
@endif
