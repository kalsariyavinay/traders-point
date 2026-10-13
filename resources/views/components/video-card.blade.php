@props(['video', 'owned' => false])

<a href="{{ route('videos.show', $video) }}"
   class="glass glass-interactive group block overflow-hidden rounded-2xl">

    <div class="relative aspect-video overflow-hidden">
        @if ($video->thumbnailUrl())
            <img src="{{ $video->thumbnailUrl() }}" alt=""
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
        @else
            <x-chart-thumb :video="$video" class="h-full w-full transition duration-500 group-hover:scale-[1.03]" />
        @endif

        <div class="absolute inset-0 bg-gradient-to-t from-ink via-ink/20 to-transparent"></div>

        {{-- Access badge: green when open to everyone, gold when it is paid for. --}}
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

        @if (! $video->is_free)
            <div class="absolute right-3 top-3">
                @if ($owned)
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-bull/20 text-bull" title="{{ __('You own this') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                @else
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-ink/70 text-gold ring-1 ring-gold/30" title="{{ __('Locked') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5.5A1.5 1.5 0 004 8.5v8A1.5 1.5 0 005.5 18h9a1.5 1.5 0 001.5-1.5v-8A1.5 1.5 0 0014.5 7H14V5a4 4 0 00-4-4zm2 6H8V5a2 2 0 114 0v2z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                @endif
            </div>
        @endif
    </div>

    <div class="p-5">
        <p class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">
            {{ $video->category?->name ?? __('Uncategorised') }}
        </p>
        <h3 class="mt-2 font-display text-base font-semibold leading-snug text-paper transition group-hover:text-white">
            {{ $video->title }}
        </h3>
        @if ($video->description)
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-muted">
                {{ $video->description }}
            </p>
        @endif
    </div>
</a>
