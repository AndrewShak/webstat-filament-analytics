@props(['cells' => [], 'max' => 1, 'height' => 0, 'path' => null, 'showPage' => true])
@php
    $ph = $height > 0 ? min($height, 12000) : 3000;
    $w = 1280;
    $url = $path ? url($path) : null;
@endphp
<div x-data="{ s: 1, init() { const f = () => { this.s = this.$el.clientWidth / {{ $w }}; }; f(); new ResizeObserver(f).observe(this.$el); } }"
     :style="{ height: ({{ $ph }} * s) + 'px' }"
     style="position:relative;width:100%;overflow:hidden;border-radius:.5rem;border:1px solid color-mix(in srgb,currentColor 14%,transparent);background:color-mix(in srgb,currentColor 4%,transparent)">
    @if ($showPage && $url)
        <iframe src="{{ $url }}" loading="lazy" tabindex="-1" sandbox="allow-same-origin allow-scripts" referrerpolicy="same-origin"
                :style="{ transform: 'scale(' + s + ')' }"
                style="position:absolute;left:0;top:0;width:{{ $w }}px;height:{{ $ph }}px;border:0;transform-origin:0 0;pointer-events:none;opacity:.55;background:#fff"></iframe>
    @endif
    @foreach ($cells as [$cx, $cy, $c])
        @php $a = round(35 + 65 * ($c / $max)); @endphp
        <div title="{{ $c }}" style="position:absolute;left:{{ $cx }}%;top:{{ $cy }}%;width:5%;aspect-ratio:1;transform:translate(-50%,-50%);border-radius:9999px;pointer-events:auto;background:radial-gradient(circle,color-mix(in srgb,var(--danger-500) {{ $a }}%,transparent) 0%,color-mix(in srgb,var(--warning-400) {{ round($a / 3) }}%,transparent) 45%,transparent 70%)"></div>
    @endforeach
</div>
