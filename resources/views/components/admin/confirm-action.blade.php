@props([
    'action',
    'label',
    'title',
    'method' => 'DELETE',
    'body' => null,
    'confirm' => null,
    'tone' => 'danger',
])

@php
    $triggerTone = $tone === 'danger'
        ? 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300'
        : 'text-gray-600 hover:text-gray-900 dark:text-slate-400 dark:hover:text-slate-100';

    $confirmTone = $tone === 'danger'
        ? 'bg-red-600 text-white hover:bg-red-500 focus:ring-red-500'
        : 'bg-gray-800 text-white hover:bg-gray-700 focus:ring-indigo-500 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white';
@endphp

{{--
    Replaces window.confirm(). Teleported to <body> so it is not clipped by the
    overflow-x-auto wrapper every admin table sits in.
--}}
<div x-data="{ open: false, busy: false }" class="inline-block">
    <button type="button" x-on:click="open = true"
            class="{{ $triggerTone }} transition">{{ $label }}</button>

    <template x-teleport="body">
        <div x-show="open" x-cloak
             x-on:keydown.escape.window="open = false"
             class="fixed inset-0 z-[80] flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="{{ $id = 'confirm-'.Str::random(6) }}">

            <div x-show="open" x-transition.opacity.duration.150ms
                 class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                 x-on:click="open = false" aria-hidden="true"></div>

            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95"
                 class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-slate-900 dark:ring-1 dark:ring-slate-700">

                <h2 id="{{ $id }}" class="text-base font-semibold text-gray-900 dark:text-slate-100">
                    {{ $title }}
                </h2>

                @if ($body)
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-slate-400">{{ $body }}</p>
                @endif

                <form method="POST" action="{{ $action }}" x-on:submit="busy = true"
                      class="mt-6 flex flex-wrap justify-end gap-3">
                    @csrf
                    @method($method)

                    <button type="button" x-on:click="open = false" :disabled="busy"
                            x-ref="cancel"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                        {{ __('Cancel') }}
                    </button>

                    <button type="submit" :disabled="busy"
                            class="inline-flex items-center gap-2 rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:focus:ring-offset-slate-900 {{ $confirmTone }}">
                        <svg x-show="busy" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span x-show="! busy">{{ $confirm ?? $label }}</span>
                        <span x-show="busy" x-cloak>{{ __('Working…') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </template>
</div>
