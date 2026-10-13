<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-paper">
            {{ __('Profile information') }}
        </h2>

        <p class="mt-1 text-sm leading-relaxed text-muted">
            {{ __("Update your name and the email address you sign in with.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <x-dark-label for="name" :value="__('Name')" />
            <x-dark-input id="name" name="name" type="text" class="mt-2" :value="old('name', $user->name)"
                          required autofocus autocomplete="name" />
            <x-dark-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-dark-label for="email" :value="__('Email')" />
            <x-dark-input id="email" name="email" type="email" class="mt-2" :value="old('email', $user->email)"
                          required autocomplete="username" />
            <x-dark-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="mt-3 text-sm text-muted">
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" class="text-paper underline decoration-ink-line underline-offset-2 transition hover:decoration-paper">
                        {{ __('Send the verification link again.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-bull">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4 pt-1">
            <x-dark-button>{{ __('Save') }}</x-dark-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-cloak
                   x-init="setTimeout(() => show = false, 3000)"
                   class="font-data text-xs uppercase tracking-eyebrow text-bull">{{ __('Saved') }}</p>
            @endif
        </div>
    </form>
</section>
