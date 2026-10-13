@php
    // One flash key per tone; whichever the last request set is what shows.
    $tones = [
        'success' => ['color' => 'text-bull', 'ring' => 'ring-bull/30', 'icon' => 'M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z'],
        'error' => ['color' => 'text-bear', 'ring' => 'ring-bear/30', 'icon' => 'M10 2a8 8 0 100 16 8 8 0 000-16zm0 3.5a1 1 0 011 1V11a1 1 0 11-2 0V6.5a1 1 0 011-1zM10 15a1.1 1.1 0 110-2.2 1.1 1.1 0 010 2.2z'],
        'info' => ['color' => 'text-gold', 'ring' => 'ring-gold/30', 'icon' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 11-2 0 1 1 0 012 0zm-1 3a1 1 0 00-1 1v3a1 1 0 102 0v-3a1 1 0 00-1-1z'],
    ];

    $messages = array_filter(
        array_map(fn ($tone) => session($tone), array_combine(array_keys($tones), array_keys($tones)))
    );
@endphp

@if ($messages)
    <div class="pointer-events-none fixed inset-x-0 top-24 z-[60] flex justify-center px-4" aria-live="polite">
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
                     class="glass pointer-events-auto flex items-start gap-3 rounded-xl p-4 text-sm text-paper shadow-2xl ring-1 {{ $style['ring'] }}">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                         class="mt-0.5 h-4 w-4 shrink-0 {{ $style['color'] }}">
                        <path fill-rule="evenodd" d="{{ $style['icon'] }}" clip-rule="evenodd"/>
                    </svg>
                    <p class="flex-1 leading-relaxed">{{ $message }}</p>
                    <button type="button" x-on:click="show = false"
                            class="shrink-0 text-muted transition hover:text-paper" aria-label="{{ __('Dismiss') }}">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                            <path d="M6.3 5a.9.9 0 00-1.3 1.3L8.7 10l-3.7 3.7A.9.9 0 106.3 15L10 11.3 13.7 15a.9.9 0 101.3-1.3L11.3 10 15 6.3A.9.9 0 1013.7 5L10 8.7 6.3 5z"/>
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif
