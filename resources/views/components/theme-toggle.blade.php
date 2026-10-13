@props(['surface' => 'public'])

@php
    // Two surfaces, one control: the public header rides on themed tokens,
    // the Breeze admin bar on its own gray/slate scale.
    $classes = $surface === 'admin'
        ? 'border-gray-300 text-gray-500 hover:text-gray-800 hover:border-gray-400 dark:border-slate-700 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:border-slate-500'
        : 'border-ink-line text-muted hover:text-paper hover:border-paper/40';
@endphp

<button type="button"
        x-data
        x-on:click="$store.theme.toggle()"
        {{ $attributes->merge([
            'class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full border transition '.$classes,
        ]) }}
        :aria-label="$store.theme.dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
        :title="$store.theme.dark ? '{{ __('Switch to light mode') }}' : '{{ __('Switch to dark mode') }}'"
        aria-label="{{ __('Switch theme') }}">

    {{-- Moon: shown while dark is on, i.e. "you are in dark mode". --}}
    <svg x-show="$store.theme.dark" x-cloak viewBox="0 0 20 20" fill="currentColor"
         class="h-4 w-4" aria-hidden="true">
        <path d="M17.3 11.9A7.5 7.5 0 018.1 2.7a.8.8 0 00-1.1-.9 9 9 0 1011.2 11.2.8.8 0 00-.9-1.1z"/>
    </svg>

    {{-- Sun --}}
    <svg x-show="! $store.theme.dark" x-cloak viewBox="0 0 20 20" fill="currentColor"
         class="h-4 w-4" aria-hidden="true">
        <path d="M10 14a4 4 0 100-8 4 4 0 000 8z"/>
        <path fill-rule="evenodd" d="M10 0a1 1 0 011 1v1.5a1 1 0 11-2 0V1a1 1 0 011-1zm0 16a1 1 0 011 1v1.5a1 1 0 11-2 0V17a1 1 0 011-1zM20 10a1 1 0 01-1 1h-1.5a1 1 0 110-2H19a1 1 0 011 1zM4 10a1 1 0 01-1 1H1.5a1 1 0 110-2H3a1 1 0 011 1zm13.07-7.07a1 1 0 010 1.41l-1.06 1.06a1 1 0 11-1.42-1.41l1.07-1.06a1 1 0 011.41 0zM5.41 14.59a1 1 0 010 1.41l-1.06 1.07a1 1 0 11-1.42-1.42l1.07-1.06a1 1 0 011.41 0zm11.66 2.48a1 1 0 01-1.41 0l-1.06-1.07a1 1 0 011.41-1.41l1.06 1.06a1 1 0 010 1.42zM5.41 5.41a1 1 0 01-1.41 0L2.93 4.34a1 1 0 011.42-1.41L5.4 4a1 1 0 010 1.41z" clip-rule="evenodd"/>
    </svg>
</button>
