<section>
    <header>
        <h2 class="font-display text-lg font-semibold text-paper">
            {{ __('Update password') }}
        </h2>

        <p class="mt-1 text-sm leading-relaxed text-muted">
            {{ __('Use a long, random password so your purchases stay yours.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <x-dark-label for="update_password_current_password" :value="__('Current password')" />
            <x-dark-input id="update_password_current_password" name="current_password" type="password"
                          class="mt-2" autocomplete="current-password" />
            <x-dark-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-dark-label for="update_password_password" :value="__('New password')" />
            <x-dark-input id="update_password_password" name="password" type="password"
                          class="mt-2" autocomplete="new-password" />
            <x-dark-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-dark-label for="update_password_password_confirmation" :value="__('Confirm new password')" />
            <x-dark-input id="update_password_password_confirmation" name="password_confirmation" type="password"
                          class="mt-2" autocomplete="new-password" />
            <x-dark-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 pt-1">
            <x-dark-button>{{ __('Save') }}</x-dark-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-cloak
                   x-init="setTimeout(() => show = false, 3000)"
                   class="font-data text-xs uppercase tracking-eyebrow text-bull">{{ __('Saved') }}</p>
            @endif
        </div>
    </form>
</section>
