<section x-data="{ open: @js($errors->userDeletion->isNotEmpty()) }">
    <header>
        <h2 class="font-display text-lg font-semibold text-paper">
            {{ __('Delete account') }}
        </h2>

        <p class="mt-1 text-sm leading-relaxed text-muted">
            {{ __('Deleting your account removes your purchases and watch history for good. There is no undo, and videos you paid for will not be refunded.') }}
        </p>
    </header>

    <x-dark-button type="button" tone="danger" class="mt-6" x-on:click="open = true">
        {{ __('Delete account') }}
    </x-dark-button>

    {{-- Confirmation dialog --}}
    <div x-show="open" x-cloak
         x-on:keydown.escape.window="open = false"
         class="fixed inset-0 z-[70] flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="delete-account-title">

        <div class="absolute inset-0 bg-ink/80 backdrop-blur-sm" x-on:click="open = false" aria-hidden="true"></div>

        <div class="glass relative w-full max-w-lg rounded-2xl p-6 sm:p-8"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <h3 id="delete-account-title" class="font-display text-lg font-semibold text-paper">
                    {{ __('Delete your account?') }}
                </h3>

                <p class="mt-2 text-sm leading-relaxed text-muted">
                    {{ __('Enter your password to confirm. Everything tied to this account is removed permanently.') }}
                </p>

                <div class="mt-6">
                    <x-dark-label for="password" :value="__('Password')" class="sr-only" />
                    <x-dark-input id="password" name="password" type="password"
                                  :placeholder="__('Password')" x-ref="password" />
                    <x-dark-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <x-dark-button type="button" tone="ghost" x-on:click="open = false">
                        {{ __('Cancel') }}
                    </x-dark-button>
                    <x-dark-button tone="danger">
                        {{ __('Delete account') }}
                    </x-dark-button>
                </div>
            </form>
        </div>
    </div>
</section>
