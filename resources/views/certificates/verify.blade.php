<x-public-layout :title="__('Verify a certificate')">

    <div class="mx-auto max-w-2xl px-5 py-16 sm:px-8 lg:py-24">

        <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">Certificate check</p>

        @if ($certificate)
            <h1 class="mt-3 font-display text-[clamp(1.8rem,4vw,2.5rem)] font-semibold leading-tight tracking-tight">
                This certificate is genuine
            </h1>

            <div class="glass mt-8 rounded-2xl p-7 ring-1 ring-bull/30 sm:p-9">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-bull/15 text-bull">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" clip-rule="evenodd"/>
                    </svg>
                </span>

                <dl class="mt-7 space-y-6">
                    <div>
                        <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Awarded to</dt>
                        <dd class="mt-1 font-display text-2xl font-semibold text-paper">
                            {{ $certificate->user?->name ?? __('Deleted account') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">For completing</dt>
                        <dd class="mt-1 font-display text-lg font-semibold text-paper">
                            {{ $certificate->category?->name ?? __('Removed category') }}
                        </dd>
                        <dd class="tabular mt-1 font-data text-xs text-muted">
                            {{ $certificate->lessons_count }} {{ \Illuminate\Support\Str::plural('lesson', $certificate->lessons_count) }}
                        </dd>
                    </div>

                    <div class="grid gap-6 border-t border-ink-line pt-6 sm:grid-cols-2">
                        <div>
                            <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Completed on</dt>
                            <dd class="tabular mt-1 font-data text-sm text-paper">
                                {{ $certificate->completed_at->format('d F Y') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Reference</dt>
                            <dd class="tabular mt-1 font-data text-sm text-paper">{{ $certificate->reference }}</dd>
                        </div>
                    </div>
                </dl>
            </div>

            <p class="mt-6 text-sm leading-relaxed text-muted">
                Issued by {{ config('academy.brand') }} on
                {{ $certificate->created_at->format('d F Y') }}. This records course completion only —
                it is not a licence, a qualification, or financial advice.
            </p>
        @else
            <h1 class="mt-3 font-display text-[clamp(1.8rem,4vw,2.5rem)] font-semibold leading-tight tracking-tight">
                No certificate with that reference
            </h1>

            <div class="glass mt-8 rounded-2xl p-7 sm:p-9">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-bear/15 text-bear">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 3.5a1 1 0 011 1V11a1 1 0 11-2 0V6.5a1 1 0 011-1zM10 15a1.1 1.1 0 110-2.2 1.1 1.1 0 010 2.2z" clip-rule="evenodd"/>
                    </svg>
                </span>

                <p class="mt-6 font-data text-[0.65rem] uppercase tracking-eyebrow text-muted">Reference checked</p>
                <p class="tabular mt-1 break-all font-data text-sm text-paper">{{ $reference }}</p>

                <p class="mt-6 text-sm leading-relaxed text-muted">
                    We have no record of this reference. Check it has been copied in full — they look like
                    <span class="font-data text-paper">TPA-01-00002-7F3A9C21</span>.
                </p>
            </div>
        @endif

        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('home') }}"
               class="rounded-full bg-paper px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-ink transition hover:bg-white">
                {{ config('academy.brand') }}
            </a>
            <a href="{{ route('home') }}#courses"
               class="rounded-full border border-ink-line px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-paper/40">
                Browse courses
            </a>
        </div>
    </div>

</x-public-layout>
