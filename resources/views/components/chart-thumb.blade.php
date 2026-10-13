@props(['video'])

@php
    /*
     | Card art for a video with no uploaded thumbnail. The series is seeded
     | from the slug, so each video keeps its own distinct sparkline instead of
     | showing an empty grey box.
     */
    $seed = crc32($video->slug);
    mt_srand($seed);

    $bars = 16;
    $series = [];
    $value = 50.0;

    for ($i = 0; $i < $bars; $i++) {
        $open = $value;
        $close = max(12, min(88, $open + mt_rand(-110, 135) / 10));
        $series[] = [$open, $close];
        $value = $close;
    }

    mt_srand();

    $toneClass = $video->is_free ? 'fill-bull' : 'fill-gold';
    $step = 100 / $bars;
@endphp

<svg viewBox="0 0 100 56" preserveAspectRatio="none" aria-hidden="true"
     {{ $attributes->merge(['class' => 'h-full w-full']) }}>
    <rect width="100" height="56" class="fill-ink-raised"/>

    @foreach ([14, 28, 42] as $gridY)
        <line x1="0" y1="{{ $gridY }}" x2="100" y2="{{ $gridY }}" class="stroke-ink-line" stroke-width="0.5"/>
    @endforeach

    @foreach ($series as $index => [$open, $close])
        @php
            $centre = $index * $step + $step / 2;
            $top = 56 - max($open, $close) * 0.56;
            $height = max(1, abs($close - $open) * 0.56);
            $rising = $close >= $open;
        @endphp
        <rect x="{{ $centre - $step * 0.22 }}" y="{{ $top }}"
              width="{{ $step * 0.44 }}" height="{{ $height }}" rx="0.6"
              class="{{ $toneClass }}" fill-opacity="{{ $rising ? 0.85 : 0.35 }}"/>
    @endforeach
</svg>
