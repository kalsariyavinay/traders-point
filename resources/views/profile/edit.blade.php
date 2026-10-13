<x-public-layout :title="__('Profile')">

    <div class="mx-auto max-w-3xl px-5 py-10 sm:px-8 lg:py-14">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-data text-[0.7rem] uppercase tracking-eyebrow text-muted">Your account</p>
                <h1 class="mt-3 font-display text-[clamp(1.8rem,4vw,2.5rem)] font-semibold leading-tight tracking-tight">
                    Profile
                </h1>
            </div>
            <a href="{{ route('dashboard') }}"
               class="rounded-full border border-ink-line px-5 py-2.5 font-data text-xs uppercase tracking-eyebrow text-paper transition hover:border-paper/40">
                Back to dashboard
            </a>
        </div>

        <div class="mt-10 space-y-6">
            <div class="glass rounded-2xl p-6 sm:p-8">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="glass rounded-2xl p-6 sm:p-8">
                @include('profile.partials.update-password-form')
            </div>

            <div class="rounded-2xl border border-bear/25 bg-bear/[0.04] p-6 sm:p-8">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>

</x-public-layout>
