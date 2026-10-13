@php
    // Light counterpart of <x-toast>, for the Breeze-styled admin screens.
    $tones = [
        'success' => ['bar' => 'bg-green-500', 'icon' => 'text-green-600', 'path' => 'M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z'],
        'error' => ['bar' => 'bg-red-500', 'icon' => 'text-red-600 dark:text-red-400', 'path' => 'M10 2a8 8 0 100 16 8 8 0 000-16zm0 3.5a1 1 0 011 1V11a1 1 0 11-2 0V6.5a1 1 0 011-1zM10 15a1.1 1.1 0 110-2.2 1.1 1.1 0 010 2.2z'],
        'info' => ['bar' => 'bg-amber-500', 'icon' => 'text-amber-600', 'path' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 11-2 0 1 1 0 012 0zm-1 3a1 1 0 00-1 1v3a1 1 0 102 0v-3a1 1 0 00-1-1z'],
    ];

    $messages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'info' => session('info'),
    ]);
@endphp

@if ($messages)
    <div class="pointer-events-none fixed inset-x-0 top-20 z-50 flex justify-center px-4" aria-live="polite">
        <div class="w-full max-w-md space-y-2">
            @foreach ($messages as $tone => $message)
                @php $style = $tones[$tone]; @endphp
                <div x-data="{ show: false }"
                     x-init="$nextTick(() => show = true); setTimeout(() => show = false, 9000)"
                     x-show="show"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     x-cloak
                     role="status"
                     class="pointer-events-auto flex items-start gap-3 overflow-hidden rounded-lg bg-white dark:bg-slate-900 p-4 text-sm text-gray-800 dark:text-slate-100 shadow-lg ring-1 ring-gray-900/5">
                    <span class="-my-4 -ms-4 w-1 self-stretch {{ $style['bar'] }}"></span>
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                         class="mt-0.5 h-4 w-4 shrink-0 {{ $style['icon'] }}">
                        <path fill-rule="evenodd" d="{{ $style['path'] }}" clip-rule="evenodd"/>
                    </svg>
                    <p class="flex-1 leading-relaxed">{{ $message }}</p>
                    <button type="button" x-on:click="show = false"
                            class="shrink-0 text-gray-400 dark:text-slate-500 transition hover:text-gray-700 dark:hover:text-slate-200" aria-label="{{ __('Dismiss') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path d="M6.3 5a.9.9 0 00-1.3 1.3L8.7 10l-3.7 3.7A.9.9 0 106.3 15L10 11.3 13.7 15a.9.9 0 101.3-1.3L11.3 10 15 6.3A.9.9 0 1013.7 5L10 8.7 6.3 5z"/>
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif
