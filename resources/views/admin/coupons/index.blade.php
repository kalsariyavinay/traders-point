<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">{{ __('Coupons') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('admin.coupons.index') }}" class="flex items-center gap-2">
                    <x-text-input name="search" :value="$search" class="w-64" type="search"
                                  :placeholder="__('Search by code…')" />
                    <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                    @if ($search !== '')
                        <a href="{{ route('admin.coupons.index') }}" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                            {{ __('Clear') }}
                        </a>
                    @endif
                </form>

                <a href="{{ route('admin.coupons.create') }}"
                   class="inline-flex items-center rounded-md bg-gray-800 dark:bg-slate-100 dark:text-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:hover:bg-white">
                    {{ __('New coupon') }}
                </a>
            </div>

            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg">
                @if ($coupons->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500 dark:text-slate-400">
                        {{ $search !== '' ? __('No coupons match that search.') : __('No coupons yet.') }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-slate-800/60 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Discount') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Uses') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Given away') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Revenue') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Expires') }}</th>
                                    <th class="px-6 py-3 text-right font-medium">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                @foreach ($coupons as $coupon)
                                    <tr>
                                        <td class="px-6 py-3 font-mono font-medium text-gray-900 dark:text-slate-100">{{ $coupon->code }}</td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-300">{{ $coupon->discount_percent }}%</td>
                                        <td class="px-6 py-3">
                                            <x-admin.badge :tone="match ($coupon->statusLabel()) {
                                                'Live' => 'green',
                                                'Paused' => 'amber',
                                                default => 'red',
                                            }">{{ $coupon->statusLabel() }}</x-admin.badge>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-300">
                                            {{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}
                                            @unless ($coupon->max_uses)
                                                <span class="text-xs text-gray-400 dark:text-slate-500">({{ __('unlimited') }})</span>
                                            @endunless
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-300">
                                            ₹{{ number_format((float) ($coupon->discount_given ?? 0), 2) }}
                                        </td>
                                        <td class="px-6 py-3 font-medium text-gray-900 dark:text-slate-100">
                                            ₹{{ number_format((float) ($coupon->revenue ?? 0), 2) }}
                                            <span class="block text-xs font-normal text-gray-400 dark:text-slate-500">
                                                {{ $coupon->paid_orders }} {{ Str::plural('order', $coupon->paid_orders) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-slate-400">
                                            {{ $coupon->expires_at?->format('d M Y') ?? __('Never') }}
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <x-admin.confirm-action
                                                    :action="route('admin.coupons.toggle', $coupon)"
                                                    method="PATCH"
                                                    tone="neutral"
                                                    :label="$coupon->is_active ? __('Pause') : __('Resume')"
                                                    :title="$coupon->is_active
                                                        ? __('Pause :code?', ['code' => $coupon->code])
                                                        : __('Resume :code?', ['code' => $coupon->code])"
                                                    :body="$coupon->is_active
                                                        ? __('Checkout will stop accepting this code straight away. Its dates and usage count are left alone.')
                                                        : __('Checkout will start accepting this code again, subject to its expiry and usage limit.')"
                                                    :confirm="$coupon->is_active ? __('Pause coupon') : __('Resume coupon')" />

                                                <a href="{{ route('admin.coupons.edit', $coupon) }}"
                                                   class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>

                                                <x-admin.confirm-action
                                                    :action="route('admin.coupons.destroy', $coupon)"
                                                    :label="__('Delete')"
                                                    :title="__('Delete :code?', ['code' => $coupon->code])"
                                                    :body="__('A coupon that has been used on an order cannot be deleted — pause it instead so the discount record survives.')"
                                                    :confirm="__('Delete coupon')" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="mt-4">{{ $coupons->links() }}</div>
        </div>
    </div>
</x-app-layout>
