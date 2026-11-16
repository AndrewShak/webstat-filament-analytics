@props(['labels' => [], 'series' => [], 'height' => 240])
@php
    $w = 800; $h = (int) $height; $pt = 12; $pb = 12; $n = count($labels);
    $all = collect($series)->flatMap(fn ($s) => $s['values'])->all();
    $max = \Webstat\Analytics\Support\Svg::niceMax($all ? max($all) : 0);
    $x = fn (int $i) => $n > 1 ? round($i * $w / ($n - 1), 2) : $w / 2;
    $y = fn ($v) => round($h - $pb - ($v / $max) * ($h - $pt - $pb), 2);
    $step = max(1, (int) ceil($n / 8));
@endphp
<div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:.8rem;margin-bottom:.5rem">
    @foreach ($series as $s)
        <span style="display:inline-flex;align-items:center;gap:.35rem"><i style="width:.7rem;height:.7rem;border-radius:9999px;background:{{ $s['color'] }}"></i>{{ $s['name'] }}</span>
    @endforeach
</div>
<div style="position:relative;padding-left:2.5rem">
    @foreach ([0, .25, .5, .75, 1] as $t)
        <span style="position:absolute;left:0;top:calc({{ (1 - $t) * 100 }}% - .5rem);font-size:.7rem;opacity:.6">{{ number_format($max * $t) }}</span>
    @endforeach
    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="width:100%;height:{{ $h }}px;display:block;overflow:visible" role="img">
        @foreach ([0, .25, .5, .75, 1] as $t)
            <line x1="0" x2="{{ $w }}" y1="{{ $y($max * $t) }}" y2="{{ $y($max * $t) }}" stroke="currentColor" stroke-opacity=".12" vector-effect="non-scaling-stroke"/>
        @endforeach
        @foreach ($series as $s)
            @php
                $pts = [];
                foreach ($s['values'] as $i => $v) { $pts[] = $x($i) . ' ' . $y($v); }
                $line = $pts ? 'M' . implode(' L', $pts) : '';
            @endphp
            @if ($line)
                <path d="{{ $line }} L{{ $x($n - 1) }} {{ $y(0) }} L{{ $x(0) }} {{ $y(0) }} Z" fill="{{ $s['color'] }}" fill-opacity=".08"/>
                <path d="{{ $line }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
            @endif
        @endforeach
        @foreach ($labels as $i => $label)
            <rect x="{{ $x($i) - $w / max(1, $n) / 2 }}" y="0" width="{{ $w / max(1, $n) }}" height="{{ $h }}" fill="transparent">
                <title>{{ $label }}@foreach ($series as $s) · {{ $s['name'] }}: {{ number_format($s['values'][$i] ?? 0) }}@endforeach</title>
            </rect>
        @endforeach
    </svg>
    <div style="display:flex;justify-content:space-between;font-size:.7rem;opacity:.6;margin-top:.25rem">
        @foreach ($labels as $i => $label)
            @if ($i % $step === 0)<span>{{ $label }}</span>@endif
        @endforeach
    </div>
</div>
