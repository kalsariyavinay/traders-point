@php
    $viewer = auth()->user();
    $ownsEverything = (bool) $viewer?->isAdmin();
    // Progress tracking needs the <video> element we control AND somebody to
    // save the position against -- a guest would just fire doomed requests.
    // A remote .mp4 still plays in our own element, so it tracks too; only a
    // provider's iframe is opaque to us.
    $nativePlayer = $viewer && $hasAccess && ! $video->isEmbeddedVideo();
@endphp

<x-public-layout :title="$video->title" :description="$video->description">

    <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:py-14">

        @unless ($video->isPublished())
            <div class="mb-8 rounded-xl border border-gold/40 bg-gold/10 p-4 font-data text-xs uppercase tracking-eyebrow text-gold">
                Draft — visible to administrators only
            </div>
        @endunless

        {{-- Heading --}}
        <div class="max-w-3xl">
            <a href="{{ route('home', ['category' => $video->category?->slug]) }}#courses"
               class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted transition hover:text-paper">
                {{ $video->category?->name ?? __('Uncategorised') }}
            </a>
            <h1 class="mt-3 font-display text-[clamp(1.6rem,4vw,2.5rem)] font-semibold leading-tight tracking-tight">
                {{ $video->title }}
            </h1>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if ($video->is_free)
                    <span class="rounded-full bg-bull px-2.5 py-1 font-data text-[0.65rem] font-medium uppercase tracking-eyebrow text-ink">
                        Free
                    </span>
                @else
                    <span class="tabular rounded-full bg-gold px-3 py-1 font-data text-xs font-medium text-ink">
                        ₹{{ number_format((float) $video->price, 0) }}
                    </span>
                    @if ($hasAccess)
                        <span class="font-data text-[0.65rem] uppercase tracking-eyebrow text-bull">
                            {{ $ownsEverything ? 'Admin access' : 'Purchased' }}
                        </span>
                    @endif
                @endif
            </div>
        </div>

        {{-- Player, or the locked preview --}}
        <div @if ($nativePlayer) x-data="videoProgress({
                 saveUrl: @js(route('videos.progress', $video)),
                 resumeAt: {{ (int) $resumeAt }},
             })" @endif>
        <div class="mt-8 overflow-hidden rounded-2xl border border-ink-line bg-black"
             @if ($hasAccess && ! $video->isEmbeddedVideo())
                 x-data="playerGuard"
                 x-on:contextmenu.prevent
                 x-on:keydown.window="blockShortcut($event)"
                 x-on:dragstart.prevent
             @endif>
            @if ($hasAccess)
                @if ($video->isEmbeddedVideo())
                    {{-- embedUrl() rewrites a provider's page URL into its frame URL;
                         the page form answers X-Frame-Options and renders nothing. --}}
                    <div class="aspect-video">
                        <iframe src="{{ $video->embedUrl() }}" title="{{ $video->title }}"
                                class="h-full w-full" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; autoplay; encrypted-media; picture-in-picture; fullscreen"></iframe>
                    </div>
                @else
                    {{--
                        These attributes remove the browser's own download and
                        cast affordances. They are a deterrent, not a control:
                        the stream link behind them is what actually enforces
                        anything. See PROJECT-BLUEPRINT.md 13.12.
                    --}}
                    <video x-ref="player" class="aspect-video w-full" controls playsinline preload="metadata"
                           controlslist="nodownload noremoteplayback noplaybackrate"
                           disablepictureinpicture disableremoteplayback
                           oncontextmenu="return false"
                           @if ($video->thumbnailUrl()) poster="{{ $video->thumbnailUrl() }}" @endif>
                        <source src="{{ $video->playbackUrl() }}">
                        This browser cannot play the video. Try a recent version of Chrome, Firefox or Safari.
                    </video>
                @endif
            @else
                {{-- Paid and not purchased: a teaser panel, not an empty player. --}}
                <div class="relative isolate {{ $video->thumbnailUrl() ? 'aspect-video' : 'min-h-[20rem]' }} overflow-hidden bg-ink-raised">
                    @if ($video->thumbnailUrl())
                        <img src="{{ $video->thumbnailUrl() }}" alt=""
                             class="absolute inset-0 h-full w-full object-cover opacity-35 blur-[2px]">
                    @else
                        <div class="graticule absolute inset-0" aria-hidden="true"></div>
                    @endif

                    <div class="pointer-events-none absolute left-1/2 top-1/2 h-72 w-72 -translate-x-1/2 -translate-y-1/2 rounded-full bg-gold/10 blur-3xl" aria-hidden="true"></div>

                    <div class="relative flex min-h-[20rem] flex-col items-center justify-center gap-4 px-6 py-14 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gold/15 text-gold ring-1 ring-gold/40">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5.5A1.5 1.5 0 004 8.5v8A1.5 1.5 0 005.5 18h9a1.5 1.5 0 001.5-1.5v-8A1.5 1.5 0 0014.5 7H14V5a4 4 0 00-4-4zm2 6H8V5a2 2 0 114 0v2z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <p class="font-display text-lg font-semibold text-paper">This lesson is paid</p>
                        <p class="max-w-sm text-sm leading-relaxed text-muted">
                            Buy it once for <span class="tabular font-data text-paper">₹{{ number_format((float) $video->price, 0) }}</span>
                            and it stays in your library.
                        </p>
                    </div>
                </div>
            @endif
        </div>

        @if ($hasAccess && $video->isEmbeddedVideo())
            <p class="mt-3 text-xs text-muted">
                {{ __('Player not loading?') }}
                <a href="{{ $video->video_path }}" target="_blank" rel="noopener noreferrer"
                   class="text-paper underline decoration-ink-line underline-offset-4 transition hover:decoration-paper">
                    {{ __('Open this lesson at the source') }}
                </a>.
            </p>
        @endif

        @if ($nativePlayer)
            <div x-show="resumedFrom > 0" x-cloak
                 class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-line bg-ink-raised/50 px-4 py-3">
                <p class="text-sm text-muted">
                    {{ __('Picked up where you left off —') }}
                    <span class="tabular font-data text-paper" x-text="clock(resumedFrom)"></span>.
                </p>
                <button type="button" x-on:click="startOver()"
                        class="font-data text-[0.65rem] uppercase tracking-eyebrow text-paper underline decoration-ink-line underline-offset-4 transition hover:decoration-paper">
                    {{ __('Start from the beginning') }}
                </button>
            </div>
        @endif
        </div>

        {{-- Description + access panel --}}
        <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <div>
                <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">What this covers</p>
                <div class="mt-4 space-y-4 text-[0.95rem] leading-relaxed text-paper/85">
                    @forelse (preg_split('/\R{2,}/', (string) $video->description, -1, PREG_SPLIT_NO_EMPTY) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p class="text-muted">No description has been added for this lesson yet.</p>
                    @endforelse
                </div>
            </div>

            <aside class="glass h-fit rounded-2xl p-6">
                @if ($hasAccess)
                    <p class="font-data text-[0.65rem] uppercase tracking-eyebrow text-bull">
                        {{ $video->is_free ? 'Open to everyone' : 'In your library' }}
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-muted">
                        {{ $video->is_free
                            ? 'Watch it as many times as you like. No account needed.'
                            : 'Sign in with this account any time to watch it again.' }}
                    </p>
                    @guest
                        <a href="{{ route('register') }}"
                           class="mt-6 block rounded-full border border-ink-line px-5 py-3 text-center font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-paper/40">
                            Create an account
                        </a>
                    @endguest
                @else
                    <p class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">One-time price</p>
                    <p class="tabular mt-2 font-data text-3xl font-medium text-paper">
                        ₹{{ number_format((float) $video->price, 0) }}
                    </p>
                    <ul class="mt-5 space-y-2.5 text-sm text-muted">
                        @foreach (['Lifetime access to this lesson', 'Watch on any device', 'Pay once, no subscription'] as $point)
                            <li class="flex items-start gap-2.5">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-bull" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @auth
                        <div class="mt-6"
                             x-data="razorpayCheckout({
                                 orderUrl: @js(route('razorpay.order', $video)),
                                 successUrl: @js(route('payment.success')),
                                 failedUrl: @js(route('payment.failed')),
                             })">
                            <label for="coupon_code" class="block font-data text-[0.6rem] uppercase tracking-eyebrow text-muted">
                                {{ __('Coupon code (optional)') }}
                            </label>
                            <input id="coupon_code" type="text" x-model="coupon" autocomplete="off"
                                   x-on:keydown.enter.prevent="start()"
                                   placeholder="{{ __('e.g. DIWALI25') }}"
                                   class="mb-4 mt-1.5 block w-full rounded-lg border border-ink-line bg-ink-raised/70 px-3 py-2 font-data text-sm uppercase tracking-wider text-paper placeholder:normal-case placeholder:tracking-normal placeholder:text-muted/60 focus:border-gold/60 focus:outline-none focus:ring-2 focus:ring-gold/25">

                            <template x-if="applied">
                                <p class="mb-3 text-xs text-bull">
                                    <span x-text="applied.code"></span>
                                    {{ __('applied —') }}
                                    <span x-text="applied.discount_percent + '%'"></span>
                                    {{ __('off, you pay ₹') }}<span class="tabular font-data"
                                        x-text="(applied.list_price - applied.discount_amount).toFixed(2)"></span>
                                </p>
                            </template>

                            <button type="button" x-on:click="start()" :disabled="loading"
                                    class="w-full rounded-full bg-gold px-5 py-3.5 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:brightness-110 disabled:cursor-wait disabled:opacity-70">
                                <span x-show="! loading">Buy now with Razorpay</span>
                                <span x-show="loading" x-cloak>Opening checkout…</span>
                            </button>

                            <p x-show="error" x-cloak x-text="error"
                               class="mt-3 text-xs leading-relaxed text-bear" role="alert"></p>

                            <p class="mt-3 text-center font-data text-[0.6rem] uppercase tracking-eyebrow text-muted">
                                Card · UPI · Netbanking · Wallet
                            </p>
                        </div>
                    @else
                        <a href="{{ route('login') }}"
                           class="mt-6 block w-full rounded-full bg-gold px-5 py-3.5 text-center font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:brightness-110">
                            Sign in to buy
                        </a>
                        <p class="mt-3 text-center text-xs text-muted">
                            New here? <a href="{{ route('register') }}" class="text-paper underline decoration-ink-line underline-offset-2">Create an account</a>
                        </p>
                    @endauth
                @endif
            </aside>
        </div>

        {{-- More from the same category --}}
        @if ($related->isNotEmpty())
            <section class="mt-20 border-t border-ink-line pt-12">
                <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">
                    More in {{ $video->category?->name }}
                </p>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $other)
                        <x-video-card :video="$other"
                                      :owned="$ownsEverything || in_array($other->id, $ownedIds, true)" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{--
        This must stay INSIDE the component slot. A Blade component renders its
        slot first and its own view second, so an @push written after
        </x-public-layout> registers too late -- @stack('scripts') in the layout
        has already been emitted and the script is silently discarded. That is
        how the Buy button shipped with no checkout.js behind it.
    --}}
    @auth
        @unless ($hasAccess)
            @push('scripts')
                <script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
            @endpush
        @endunless
    @endauth

</x-public-layout>
