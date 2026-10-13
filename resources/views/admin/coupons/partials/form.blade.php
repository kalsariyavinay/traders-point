<x-admin.form-errors />

<div class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="code" :value="__('Code')" />
            <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono uppercase"
                          :value="old('code', $coupon->code)" required autofocus
                          placeholder="DIWALI25" />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ __('Letters, numbers and hyphens. Shoppers may type it in any case.') }}</p>
            <x-admin.field-error name="code" :messages="$errors->get('code')" />
        </div>

        <div>
            <x-input-label for="discount_percent" :value="__('Discount (%)')" />
            <x-text-input id="discount_percent" name="discount_percent" type="number" min="1" max="100" step="1"
                          class="mt-1 block w-full" :value="old('discount_percent', $coupon->discount_percent)" required />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ __('A 100% coupon still charges ₹1 — Razorpay will not take a smaller order.') }}</p>
            <x-admin.field-error name="discount_percent" :messages="$errors->get('discount_percent')" />
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="max_uses" :value="__('Maximum uses')" />
            <x-text-input id="max_uses" name="max_uses" type="number" min="1" step="1"
                          class="mt-1 block w-full" :value="old('max_uses', $coupon->max_uses)" />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ __('Leave empty for unlimited. Only paid orders count towards it.') }}</p>
            <x-admin.field-error name="max_uses" :messages="$errors->get('max_uses')" />
        </div>

        <div>
            <x-input-label for="expires_at" :value="__('Expires on')" />
            <x-text-input id="expires_at" name="expires_at" type="datetime-local" class="mt-1 block w-full"
                          :value="old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i'))" />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ __('Leave empty to never expire.') }}</p>
            <x-admin.field-error name="expires_at" :messages="$errors->get('expires_at')" />
        </div>
    </div>

    <div class="rounded-md border border-gray-200 dark:border-slate-800 p-4">
        <label class="inline-flex cursor-pointer items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="peer sr-only"
                   @checked(old('is_active', $coupon->exists ? $coupon->is_active : true))>
            <span class="relative h-6 w-11 rounded-full bg-gray-300 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition after:content-[''] peer-checked:bg-green-500 peer-checked:after:translate-x-5"></span>
            <span class="text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('Accept this coupon at checkout') }}</span>
        </label>
        <x-admin.field-error name="is_active" :messages="$errors->get('is_active')" />
    </div>

    @if ($coupon->exists)
        <p class="text-xs text-gray-500">
            {{ __('Used :count time(s) so far.', ['count' => $coupon->used_count]) }}
        </p>
    @endif

    <div class="flex items-center gap-4 border-t border-gray-100 dark:border-slate-800 pt-6">
        <x-admin.submit-button :label="$submitLabel" />
        <a href="{{ route('admin.coupons.index') }}" class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-100">
            {{ __('Cancel') }}
        </a>
    </div>
</div>
