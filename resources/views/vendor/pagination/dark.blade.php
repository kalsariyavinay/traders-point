@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="flex items-center justify-between gap-4 border-t border-ink-line pt-6">

        @if ($paginator->onFirstPage())
            <span class="font-data text-xs uppercase tracking-eyebrow text-muted/40">{{ __('Previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="font-data text-xs uppercase tracking-eyebrow text-paper transition hover:text-gold">
                {{ __('Previous') }}
            </a>
        @endif

        <p class="tabular font-data text-xs uppercase tracking-eyebrow text-muted">
            {{ __('Page') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        </p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="font-data text-xs uppercase tracking-eyebrow text-paper transition hover:text-gold">
                {{ __('Next') }}
            </a>
        @else
            <span class="font-data text-xs uppercase tracking-eyebrow text-muted/40">{{ __('Next') }}</span>
        @endif
    </nav>
@endif
