@php
    $viewer = auth()->user();
    $ownsEverything = (bool) $viewer?->isAdmin();
@endphp

<x-public-layout>

    {{-- ─────────────────────────── Hero ─────────────────────────── --}}
    <section class="relative isolate overflow-hidden">
        <div class="graticule absolute inset-0" aria-hidden="true"></div>

        <div class="absolute inset-y-0 right-0 h-full w-full lg:w-[74%]" aria-hidden="true">
            <x-hero-chart />
        </div>

        {{-- Fades the chart back so the glass card stays readable over it. --}}
        <div class="absolute inset-0 bg-gradient-to-r from-ink via-ink/90 to-ink/40 lg:via-ink/75" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-32 -top-32 h-[28rem] w-[28rem] rounded-full bg-bull/10 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
            <div class="glass max-w-xl rounded-3xl p-8 sm:p-10">
                <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-bull">
                    {{ config('academy.hero.eyebrow') }}
                </p>

                <h1 class="mt-5 text-balance font-display text-[clamp(2.1rem,5.5vw,3.5rem)] font-semibold leading-[1.08] tracking-tight">
                    {{ config('academy.hero.headline') }}
                </h1>

                <p class="mt-5 text-base leading-relaxed text-muted sm:text-lg">
                    {{ config('academy.hero.subheadline') }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="#courses"
                       class="rounded-full bg-paper px-6 py-3 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:bg-white">
                        Explore courses
                    </a>
                    <a href="{{ route('home', ['free' => 1]) }}#courses"
                       class="inline-flex items-center gap-2 rounded-full border border-ink-line px-6 py-3 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-bull/50">
                        <span class="h-1.5 w-1.5 rounded-full bg-bull"></span>
                        Watch free videos
                        @if ($freeCount)
                            <span class="tabular text-muted">({{ $freeCount }})</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>

    </section>

    {{-- Stats band, set like a terminal readout --}}
    <section class="border-y border-ink-line bg-ink-raised/40">
        <dl class="mx-auto grid max-w-7xl grid-cols-2 divide-y divide-ink-line px-5 sm:px-8 md:grid-cols-4 md:divide-y-0 md:divide-x">
            @foreach (config('academy.stats') as $stat)
                <div class="py-6 md:px-8 md:first:pl-0">
                    <dt class="flex min-h-[2.25rem] items-start font-data text-[0.65rem] uppercase tracking-eyebrow text-muted md:min-h-[1.5rem]">{{ $stat['label'] }}</dt>
                    <dd class="tabular mt-2 font-data text-2xl font-medium text-paper">{{ $stat['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- ───────────────────────── Catalogue ───────────────────────── --}}
    <section id="courses" class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-xl">
                <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">The catalogue</p>
                <h2 class="mt-3 text-balance font-display text-[clamp(1.6rem,3.5vw,2.5rem)] font-semibold leading-tight tracking-tight">
                    Pick a chart and start reading it
                </h2>
            </div>
            <p class="tabular font-data text-xs uppercase tracking-eyebrow text-muted">
                {{ $videos->total() }} {{ Str::plural('video', $videos->total()) }}
            </p>
        </div>

        {{-- Filters: one tab per category that has something published --}}
        <div class="mt-8 flex flex-wrap items-center gap-2">
            @php
                $tabBase = 'rounded-full border px-4 py-2 font-data text-[0.7rem] uppercase tracking-eyebrow transition';
                $tabOff = 'border-ink-line text-muted hover:border-paper/30 hover:text-paper';
            @endphp

            <a href="{{ route('home', ['free' => $freeOnly ? 1 : null]) }}#courses"
               @class([$tabBase, 'border-paper bg-paper text-ink' => $activeCategory === '', $tabOff => $activeCategory !== ''])>
                All
            </a>

            @foreach ($categories as $category)
                <a href="{{ route('home', ['category' => $category->slug, 'free' => $freeOnly ? 1 : null]) }}#courses"
                   @class([$tabBase, 'border-paper bg-paper text-ink' => $activeCategory === $category->slug, $tabOff => $activeCategory !== $category->slug])>
                    {{ $category->name }}
                    <span class="tabular ml-1 opacity-60">{{ $category->videos_count }}</span>
                </a>
            @endforeach

            <span class="mx-1 hidden h-5 w-px bg-ink-line sm:block"></span>

            <a href="{{ route('home', ['category' => $activeCategory ?: null, 'free' => $freeOnly ? null : 1]) }}#courses"
               @class([$tabBase, 'inline-flex items-center gap-2', 'border-bull bg-bull/15 text-bull' => $freeOnly, $tabOff => ! $freeOnly])>
                <span class="h-1.5 w-1.5 rounded-full {{ $freeOnly ? 'bg-bull' : 'bg-muted' }}"></span>
                Free only
            </a>
        </div>

        @if ($videos->isEmpty())
            <div class="glass mt-10 rounded-2xl p-10 text-center">
                <p class="font-display text-lg font-semibold">Nothing published here yet</p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
                    @if ($activeCategory !== '' || $freeOnly)
                        Try another category, or clear the filters to see everything.
                    @else
                        The first lessons are on their way.
                    @endif
                </p>
                @if ($activeCategory !== '' || $freeOnly)
                    <a href="{{ route('home') }}#courses"
                       class="mt-6 inline-block rounded-full border border-ink-line px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-paper/40">
                        Clear filters
                    </a>
                @elseif ($ownsEverything)
                    <a href="{{ route('admin.videos.create') }}"
                       class="mt-6 inline-block rounded-full bg-paper px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-ink transition hover:bg-white">
                        Add the first video
                    </a>
                @endif
            </div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    <x-video-card :video="$video"
                                  :owned="$ownsEverything || in_array($video->id, $ownedIds, true)" />
                @endforeach
            </div>

            @if ($videos->hasPages())
                <div class="mt-10">{{ $videos->links('vendor.pagination.dark') }}</div>
            @endif
        @endif
    </section>

    {{-- ─────────────────────── Testimonials ─────────────────────── --}}
    <section class="border-y border-ink-line bg-ink-raised/40">
        <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8">
            <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">From the desk</p>
            <h2 class="mt-3 max-w-2xl text-balance font-display text-[clamp(1.6rem,3.5vw,2.5rem)] font-semibold leading-tight tracking-tight">
                What changed for people who sat through the charts
            </h2>

            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach (config('academy.testimonials') as $testimonial)
                    <figure class="glass flex h-full flex-col rounded-2xl p-7">
                        <svg viewBox="0 0 24 24" class="h-5 w-5 text-gold/70" fill="currentColor" aria-hidden="true">
                            <path d="M9.5 5C6.5 6.6 5 9.2 5 12.8V19h6v-6.2H8.2c0-2 .6-3.4 1.9-4.2L9.5 5zm9 0c-3 1.6-4.5 4.2-4.5 7.8V19h6v-6.2h-2.8c0-2 .6-3.4 1.9-4.2L18.5 5z"/>
                        </svg>
                        <blockquote class="mt-5 flex-1 text-[0.95rem] leading-relaxed text-paper/90">
                            {{ $testimonial['quote'] }}
                        </blockquote>
                        <figcaption class="mt-6 border-t border-ink-line pt-4">
                            <p class="font-display text-sm font-semibold">{{ $testimonial['name'] }}</p>
                            <p class="mt-0.5 font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">
                                {{ $testimonial['role'] }}
                            </p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─────────────────────────── FAQ ─────────────────────────── --}}
    <section id="faq" class="mx-auto max-w-3xl px-5 py-20 sm:px-8">
        <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">Common questions</p>
        <h2 class="mt-3 text-balance font-display text-[clamp(1.6rem,3.5vw,2.5rem)] font-semibold leading-tight tracking-tight">
            Before you start
        </h2>

        <div class="mt-10 divide-y divide-ink-line border-y border-ink-line">
            @foreach (config('academy.faqs') as $faq)
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 text-left [&::-webkit-details-marker]:hidden">
                        <span class="font-display text-base font-semibold leading-snug text-paper">
                            {{ $faq['question'] }}
                        </span>
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                             class="h-4 w-4 shrink-0 text-muted transition-transform duration-200 group-open:rotate-180">
                            <path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd"/>
                        </svg>
                    </summary>
                    <p class="pb-6 pr-10 text-sm leading-relaxed text-muted">
                        {{ $faq['answer'] }}
                    </p>
                </details>
            @endforeach
        </div>

        <div class="glass mt-12 flex flex-col items-start gap-5 rounded-2xl p-8 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-lg font-semibold">Start with the free modules</p>
                <p class="mt-1 text-sm text-muted">No account needed to watch anything marked FREE.</p>
            </div>
            <a href="{{ route('home', ['free' => 1]) }}#courses"
               class="shrink-0 rounded-full bg-bull px-6 py-3 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:brightness-110">
                Watch free videos
            </a>
        </div>
    </section>

</x-public-layout>
