@props(['video', 'owned' => false])

@php
    // Aliases added by the dashboard query; absent elsewhere.
    $purchasedAt = $video->purchased_at ?? null;
    $watchedAt = $video->last_watched_at ?? null;
    $progress = (int) ($video->progress_seconds ?? 0);
    $completedAt = $video->completed_at ?? null;
    $resumeLabel = ! $completedAt && $progress > 0
        ? sprintf('%d:%02d', intdiv($progress, 60), $progress % 60)
        : null;
@endphp

<article class="glass glass-interactive flex flex-col overflow-hidden rounded-2xl">

    <a href="{{ route('videos.show', $video) }}" class="group relative block aspect-video overflow-hidden"
       tabindex="-1" aria-hidden="true">
        @if ($video->thumbnailUrl())
            <img src="{{ $video->thumbnailUrl() }}" alt=""
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <x-chart-thumb :video="$video" class="h-full w-full transition duration-500 group-hover:scale-[1.03]" />
        @endif

        <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/20 to-transparent"></div>

        <div class="absolute left-3 top-3">
            @if ($video->is_free)
                <span class="rounded-full bg-bull px-2.5 py-1 font-data text-[0.65rem] font-medium uppercase tracking-eyebrow text-ink">
                    Free
                </span>
            @else
                <span class="tabular rounded-full bg-gold px-2.5 py-1 font-data text-[0.7rem] font-medium text-ink">
                    ₹{{ number_format((float) $video->price, 0) }}
                </span>
            @endif
        </div>

        @if ($completedAt)
            <div class="absolute right-3 top-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-bull px-2.5 py-1 font-data text-[0.6rem] font-medium uppercase tracking-eyebrow text-ink"
                      title="{{ __('Completed') }}">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" clip-rule="evenodd"/>
                    </svg>
                    Completed
                </span>
            </div>
        @elseif ($owned)
            <div class="absolute right-3 top-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-bull/20 text-bull" title="{{ __('You own this') }}">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" clip-rule="evenodd"/>
                    </svg>
                </span>
            </div>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-5">
        <p class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">
            {{ $video->category?->name ?? __('Uncategorised') }}
        </p>

        <h3 class="mt-2 font-display text-base font-semibold leading-snug text-paper">
            <a href="{{ route('videos.show', $video) }}" class="transition hover:text-white">
                {{ $video->title }}
            </a>
        </h3>

        <p class="mt-2 font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">
            @if ($completedAt)
                <span class="text-bull">Completed {{ \Illuminate\Support\Carbon::parse($completedAt)->format('d M Y') }}</span>
            @elseif ($resumeLabel)
                <span class="text-bull">Stopped at {{ $resumeLabel }}</span>
            @elseif ($owned && $purchasedAt)
                Bought {{ \Illuminate\Support\Carbon::parse($purchasedAt)->format('d M Y') }}
            @elseif ($watchedAt)
                Opened {{ \Illuminate\Support\Carbon::parse($watchedAt)->diffForHumans() }}
            @else
                In your library
            @endif
        </p>

        <a href="{{ route('videos.show', $video) }}"
           class="mt-5 inline-flex items-center justify-center gap-2 rounded-full bg-paper px-5 py-2.5 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:bg-white">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                <path d="M7 4.5v11a1 1 0 001.5.87l9-5.5a1 1 0 000-1.74l-9-5.5A1 1 0 007 4.5z"/>
            </svg>
            <span>{{ $completedAt ? 'Watch again' : ($resumeLabel ? 'Resume' : 'Watch') }}</span>
            <span class="sr-only">{{ $video->title }}</span>
        </a>
    </div>
</article>
