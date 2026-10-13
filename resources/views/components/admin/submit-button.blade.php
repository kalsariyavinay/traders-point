@props(['label', 'busyLabel' => null])

{{--
    Reads `submitting` from the enclosing adminForm / adminUpload component, so
    a second click cannot fire a second request.
--}}
<button type="submit"
        :disabled="submitting"
        {{ $attributes->merge([
            'class' => 'inline-flex items-center gap-2 px-4 py-2 bg-gray-800 border border-transparent rounded-md '
                .'font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150 '
                .'hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 '
                .'focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 '
                .'dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white dark:focus:bg-white '
                .'dark:focus:ring-offset-slate-900',
        ]) }}>

    <svg x-show="submitting" x-cloak class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
    </svg>

    <span x-show="! submitting">{{ $label }}</span>
    <span x-show="submitting" x-cloak>{{ $busyLabel ?? __('Saving…') }}</span>
</button>
