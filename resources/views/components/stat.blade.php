@props(['label', 'value', 'delta' => null, 'hint' => null])
<div style="border:1px solid color-mix(in srgb,currentColor 14%,transparent);border-radius:.75rem;padding:.9rem 1.1rem">
    <div style="font-size:.78rem;opacity:.65">{{ $label }}</div>
    <div style="font-size:1.7rem;font-weight:600;line-height:1.3;font-variant-numeric:tabular-nums">{{ $value }}</div>
    @if ($delta !== null)
        <div style="font-size:.75rem;color:{{ $delta >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }}">
            {{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}% <span style="opacity:.6;color:currentColor">{{ __('analytics::analytics.vs_prev') }}</span>
        </div>
    @elseif ($hint)
        <div style="font-size:.75rem;opacity:.6">{{ $hint }}</div>
    @endif
</div>
