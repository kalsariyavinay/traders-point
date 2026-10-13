<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-ink">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · '.config('academy.brand') : config('academy.brand') }}</title>
    <meta name="description" content="{{ $description ?? config('academy.hero.subheadline') }}">

    @include('partials.theme-script')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=sora:600,700|dm-sans:400,500|ibm-plex-mono:400,500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="site min-h-screen bg-ink font-body text-paper antialiased selection:bg-gold/30">

    <header x-data="{ scrolled: false }"
            x-on:scroll.window="scrolled = window.scrollY > 24"
            class="sticky top-0 z-50 transition-colors duration-300"
            :class="scrolled ? 'border-b border-ink-line bg-ink/80 backdrop-blur-xl' : 'border-b border-transparent'">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                {{-- Mark: three candles, one of them bullish. --}}
                <svg viewBox="0 0 28 28" aria-hidden="true" class="h-7 w-7 shrink-0">
                    <rect x="3" y="11" width="4" height="9" rx="1" class="fill-muted"/>
                    <path d="M5 8v3M5 20v2" class="stroke-muted" stroke-width="1.4"/>
                    <rect x="12" y="6" width="4" height="14" rx="1" class="fill-bull"/>
                    <path d="M14 3v3M14 20v3" class="stroke-bull" stroke-width="1.4"/>
                    <rect x="21" y="14" width="4" height="6" rx="1" class="fill-muted"/>
                    <path d="M23 11v3M23 20v2" class="stroke-muted" stroke-width="1.4"/>
                </svg>
                <span class="font-display text-[0.95rem] font-semibold leading-tight tracking-tight">
                    Traders&nbsp;Point<span class="block text-[0.7rem] font-data font-normal uppercase tracking-eyebrow text-muted">Academy</span>
                </span>
            </a>

            <nav class="hidden items-center gap-8 md:flex">
                <a href="{{ route('home') }}#courses" class="font-data text-xs uppercase tracking-eyebrow text-muted transition hover:text-paper">Courses</a>
                <a href="{{ route('home', ['free' => 1]) }}#courses" class="font-data text-xs uppercase tracking-eyebrow text-muted transition hover:text-paper">Free</a>
                <a href="{{ route('home') }}#faq" class="font-data text-xs uppercase tracking-eyebrow text-muted transition hover:text-paper">Questions</a>
            </nav>

            <div class="flex items-center gap-3">
                <x-theme-toggle />

                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="hidden font-data text-xs uppercase tracking-eyebrow text-gold transition hover:text-paper sm:block">Admin</a>
                    @endif
                    <a href="{{ route('dashboard') }}"
                       class="rounded-full border border-ink-line px-4 py-2 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-paper/40">
                        My library
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="font-data text-xs uppercase tracking-eyebrow text-muted transition hover:text-paper">Sign in</a>
                    <a href="{{ route('register') }}"
                       class="rounded-full bg-paper px-4 py-2 font-data text-xs font-medium uppercase tracking-eyebrow text-ink transition hover:bg-white">
                        Join
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <x-toast />

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t border-ink-line">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8">
            <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                <div class="max-w-md">
                    <p class="font-display text-lg font-semibold">{{ config('academy.brand') }}</p>
                    <p class="mt-3 text-sm leading-relaxed text-muted">
                        Educational content only. We teach you to read setups and size positions —
                        we do not give buy or sell calls, and nothing here is investment advice.
                        Trading in securities carries risk of loss.
                    </p>
                </div>

                <div class="flex gap-12">
                    <div>
                        <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">Watch</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li><a href="{{ route('home') }}#courses" class="text-paper/80 transition hover:text-paper">All courses</a></li>
                            <li><a href="{{ route('home', ['free' => 1]) }}#courses" class="text-paper/80 transition hover:text-paper">Free videos</a></li>
                        </ul>
                    </div>
                    <div>
                        <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">Account</p>
                        <ul class="mt-3 space-y-2 text-sm">
                            @auth
                                <li><a href="{{ route('dashboard') }}" class="text-paper/80 transition hover:text-paper">My library</a></li>
                            @else
                                <li><a href="{{ route('login') }}" class="text-paper/80 transition hover:text-paper">Sign in</a></li>
                                <li><a href="{{ route('register') }}" class="text-paper/80 transition hover:text-paper">Create account</a></li>
                            @endauth
                        </ul>
                    </div>
                </div>
            </div>

            <p class="mt-10 font-data text-[0.7rem] uppercase tracking-eyebrow text-muted/70">
                &copy; {{ now()->year }} {{ config('academy.brand') }}
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
