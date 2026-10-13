@php
    /*
     | The hero's signature. A seeded OHLC series so the chart is identical on
     | every render, drawn as real candles that grow off the time axis in
     | sequence, with the support level inked in last.
     */
    $width = 760;
    $height = 540;
    $padRight = 56;   // room for the price axis
    $padBottom = 40;  // room for the time axis

    mt_srand(20260110);

    $series = [];
    $price = 176.0;

    for ($i = 0; $i < 30; $i++) {
        $open = $price;
        // Sell-off into support, then the recovery. The move is placed in the
        // stretch that clears the hero card, so the visible candles travel the
        // full height of the plot instead of hugging the top.
        $drift = match (true) {
            $i < 9 => -0.6,
            $i < 19 => -6.0,
            default => 5.4,
        };
        $close = max(40, $open + $drift + mt_rand(-55, 55) / 10);
        $series[] = [
            'open' => $open,
            'close' => $close,
            'high' => max($open, $close) + mt_rand(6, 26) / 10,
            'low' => min($open, $close) - mt_rand(6, 26) / 10,
        ];
        $price = $close;
    }

    mt_srand();

    $highest = max(array_column($series, 'high'));
    $lowest = min(array_column($series, 'low'));
    $span = max(1, $highest - $lowest);

    $plotWidth = $width - $padRight;
    $plotHeight = $height - $padBottom;
    $step = $plotWidth / count($series);
    $bodyWidth = $step * 0.52;

    $y = fn (float $value) => $plotHeight - (($value - $lowest) / $span) * ($plotHeight - 24) - 12;

    // Support sits just under the pullback low, where a trader would mark it.
    $supportPrice = $lowest + $span * 0.18;
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-full w-full" role="img"
     aria-label="Illustration of a candlestick chart trending upward with a marked support level">
    <defs>
        <linearGradient id="hero-glow" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="currentColor" stop-opacity="0.16"/>
            <stop offset="100%" stop-color="currentColor" stop-opacity="0"/>
        </linearGradient>
    </defs>

    {{-- Price axis --}}
    @foreach ([0.08, 0.32, 0.56, 0.8] as $fraction)
        @php $gridPrice = $lowest + $span * $fraction; @endphp
        <line x1="0" y1="{{ $y($gridPrice) }}" x2="{{ $plotWidth }}" y2="{{ $y($gridPrice) }}"
              class="stroke-ink-line" stroke-width="1"/>
        <text class="max-lg:hidden fill-muted" x="{{ $plotWidth + 10 }}" y="{{ $y($gridPrice) + 4 }}"
              font-family="IBM Plex Mono, monospace" font-size="11">
            {{ number_format($gridPrice, 2) }}
        </text>
    @endforeach

    {{-- Time axis --}}
    <line x1="0" y1="{{ $plotHeight }}" x2="{{ $plotWidth }}" y2="{{ $plotHeight }}"
          class="stroke-ink-line" stroke-width="1"/>

    {{-- Candles --}}
    @foreach ($series as $index => $candle)
        @php
            $centre = $index * $step + $step / 2;
            $isBull = $candle['close'] >= $candle['open'];
            $strokeClass = $isBull ? 'stroke-bull' : 'stroke-bear';
            $fillClass = $isBull ? 'fill-bull' : 'fill-bear';
            $bodyTop = $y(max($candle['open'], $candle['close']));
            $bodyHeight = max(2, abs($y($candle['open']) - $y($candle['close'])));
        @endphp
        <g class="candle" style="animation-delay: {{ 0.04 * $index }}s">
            <line x1="{{ $centre }}" y1="{{ $y($candle['high']) }}"
                  x2="{{ $centre }}" y2="{{ $y($candle['low']) }}"
                  class="{{ $strokeClass }}" stroke-width="1.25" stroke-opacity="0.75"/>
            <rect x="{{ $centre - $bodyWidth / 2 }}" y="{{ $bodyTop }}"
                  width="{{ $bodyWidth }}" height="{{ $bodyHeight }}" rx="1"
                  class="{{ $fillClass }}" fill-opacity="{{ $isBull ? 0.92 : 0.8 }}"/>
        </g>
    @endforeach

    {{-- Support level, drawn across after the candles land --}}
    <line class="level stroke-gold" x1="0" y1="{{ $y($supportPrice) }}" x2="{{ $plotWidth }}" y2="{{ $y($supportPrice) }}"
          stroke-width="1.5" stroke-dasharray="760"/>
    <g class="soft-in max-lg:hidden" style="animation-delay: 1.9s">
        <rect x="{{ $plotWidth + 4 }}" y="{{ $y($supportPrice) - 9 }}" width="48" height="18" rx="3" class="fill-gold"/>
        <text x="{{ $plotWidth + 28 }}" y="{{ $y($supportPrice) + 4 }}" text-anchor="middle"
              class="fill-ink" font-family="IBM Plex Mono, monospace" font-size="10" font-weight="500">
            {{ number_format($supportPrice, 1) }}
        </text>
    </g>
</svg>
