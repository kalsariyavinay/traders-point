@php
    $user = auth()->user();
    $tabBase = 'rounded-full border px-4 py-2 font-data text-[0.7rem] uppercase tracking-eyebrow transition';
    $tabOff = 'border-ink-line text-muted hover:border-paper/30 hover:text-paper';
@endphp

<x-public-layout :title="__('Dashboard')">

    <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:py-14">

        {{-- Heading --}}
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">Your panel</p>
                <h1 class="mt-3 font-display text-[clamp(1.8rem,4vw,2.75rem)] font-semibold leading-tight tracking-tight">
                    {{ __('Hello, :name', ['name' => \Illuminate\Support\Str::of($user->name)->before(' ')]) }}
                </h1>
            </div>

            <div class="flex flex-wrap gap-3">
                @if ($user->isAdmin())
                    <a href="{{ route('admin.dashboard') }}"
                       class="rounded-full border border-gold/40 px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-gold transition hover:border-gold">
                        Admin panel
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}"
                   class="rounded-full border border-ink-line px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-paper/40">
                    Profile
                </a>
            </div>
        </div>

        {{-- Figures --}}
        <dl class="mt-8 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-ink-line bg-ink-line sm:grid-cols-4">
            <div class="bg-ink-raised/60 p-5">
                <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Videos bought</dt>
                <dd class="tabular mt-2 font-data text-2xl font-medium text-paper">{{ $purchasedCount }}</dd>
            </div>
            <div class="bg-ink-raised/60 p-5">
                <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Free lessons opened</dt>
                <dd class="tabular mt-2 font-data text-2xl font-medium text-paper">{{ $watchedCount }}</dd>
            </div>
            <div class="bg-ink-raised/60 p-5">
                <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Lessons completed</dt>
                <dd class="tabular mt-2 font-data text-2xl font-medium text-bull">{{ $completedCount }}</dd>
            </div>
            <div class="col-span-2 bg-ink-raised/60 p-5 sm:col-span-1">
                <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Total paid</dt>
                <dd class="tabular mt-2 font-data text-2xl font-medium text-paper">₹{{ number_format($spent, 0) }}</dd>
            </div>
        </dl>

        {{-- Certificates --}}
        @if ($certificates->isNotEmpty())
            <section class="mt-12">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 class="font-display text-xl font-semibold tracking-tight">Certificates</h2>
                    <p class="font-data text-xs uppercase tracking-eyebrow text-muted">
                        Finish every lesson in a category to unlock it
                    </p>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @foreach ($certificates as $certificate)
                        @php
                            $pct = $certificate['total'] > 0
                                ? (int) round($certificate['completed'] / $certificate['total'] * 100)
                                : 0;
                        @endphp
                        <div @class([
                            'glass flex flex-col gap-4 rounded-2xl p-5 sm:flex-row sm:items-center sm:justify-between',
                            'ring-1 ring-bull/30' => $certificate['earned'],
                        ])>
                            <div class="min-w-0 flex-1">
                                <p class="font-display text-base font-semibold text-paper">
                                    {{ $certificate['category']->name }}
                                </p>
                                <p class="tabular mt-1 font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">
                                    {{ $certificate['completed'] }} / {{ $certificate['total'] }} lessons completed
                                </p>
                                <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-ink-line">
                                    <div class="h-full rounded-full {{ $certificate['earned'] ? 'bg-bull' : 'bg-gold' }}"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                            </div>

                            @if ($certificate['earned'])
                                <a href="{{ route('certificates.download', $certificate['category']) }}"
                                   class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-bull px-5 py-2.5 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:brightness-110">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                        <path d="M10 2a1 1 0 011 1v7.6l2.3-2.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L9 10.6V3a1 1 0 011-1z"/>
                                        <path d="M3 14a1 1 0 112 0v2h10v-2a1 1 0 112 0v2.5A1.5 1.5 0 0115.5 18h-11A1.5 1.5 0 013 16.5V14z"/>
                                    </svg>
                                    Download certificate
                                </a>
                            @else
                                <span class="shrink-0 rounded-full border border-ink-line px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-muted">
                                    {{ $certificate['total'] - $certificate['completed'] }} to go
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Enrolled videos --}}
        <div class="mt-12 flex flex-wrap items-end justify-between gap-4">
            <h2 class="font-display text-xl font-semibold tracking-tight">My enrolled videos</h2>
            <p class="tabular font-data text-xs uppercase tracking-eyebrow text-muted">
                {{ $videos->total() }} {{ \Illuminate\Support\Str::plural('video', $videos->total()) }}
            </p>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            @foreach (['all' => 'All', 'purchased' => 'Bought', 'free' => 'Free watched'] as $key => $label)
                <a href="{{ route('dashboard', ['filter' => $key === 'all' ? null : $key]) }}"
                   @class([$tabBase, 'border-paper bg-paper text-ink' => $filter === $key, $tabOff => $filter !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @if ($videos->isEmpty())
            <div class="glass mt-8 rounded-2xl p-10 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-bull/10 text-bull">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                        <path d="M7 4.5v11a1 1 0 001.5.87l9-5.5a1 1 0 000-1.74l-9-5.5A1 1 0 007 4.5z"/>
                    </svg>
                </span>
                <p class="mt-5 font-display text-lg font-semibold">
                    {{ $filter === 'all' ? 'Nothing here yet' : 'Nothing under this filter' }}
                </p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
                    @if ($filter === 'all')
                        Lessons you open and lessons you buy both land here. The free ones need no payment — start with those.
                    @else
                        Try another tab, or browse the catalogue for something new.
                    @endif
                </p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('home') }}#courses"
                       class="rounded-full bg-paper px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-ink transition hover:bg-white">
                        Browse courses
                    </a>
                    <a href="{{ route('home', ['free' => 1]) }}#courses"
                       class="inline-flex items-center gap-2 rounded-full border border-ink-line px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-bull/50">
                        <span class="h-1.5 w-1.5 rounded-full bg-bull"></span>
                        Watch free videos
                    </a>
                </div>
            </div>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    <x-enrolled-card :video="$video" :owned="in_array($video->id, $ownedIds, true)" />
                @endforeach
            </div>

            @if ($videos->hasPages())
                <div class="mt-10">{{ $videos->links('vendor.pagination.dark') }}</div>
            @endif

            <div class="glass mt-12 flex flex-col items-start gap-5 rounded-2xl p-8 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-display text-lg font-semibold">Ready for the next one?</p>
                    <p class="mt-1 text-sm text-muted">New lessons are added to the catalogue regularly.</p>
                </div>
                <a href="{{ route('home') }}#courses"
                   class="shrink-0 rounded-full bg-paper px-6 py-3 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:bg-white">
                    Browse courses
                </a>
            </div>
        @endif
    </div>

</x-public-layout>
