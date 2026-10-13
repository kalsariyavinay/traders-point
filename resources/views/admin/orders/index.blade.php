<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">{{ __('Orders') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-admin.stat-card :label="__('Total Revenue')" :value="'₹'.number_format($revenue, 2)"
                                   :hint="__('Successful orders only')" />
                <x-admin.stat-card :label="__('Orders shown')" :value="number_format($orders->total())"
                                   :hint="$status === 'all' ? __('All statuses') : __('Status: :status', ['status' => $status])" />
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('admin.orders.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <x-text-input name="search" :value="$search" class="w-80" type="search"
                                  :placeholder="__('Search user, video or Razorpay id…')" />
                    <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                    @if ($search !== '')
                        <a href="{{ route('admin.orders.index', ['status' => $status]) }}"
                           class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">{{ __('Clear') }}</a>
                    @endif
                </form>

                <div class="flex flex-wrap gap-2">
                    @foreach ([...$statuses, 'all'] as $option)
                        @php $active = $status === $option; @endphp
                        <a href="{{ route('admin.orders.index', ['status' => $option, 'search' => $search ?: null]) }}"
                           @class([
                               'rounded-md px-3 py-1.5 text-xs font-semibold uppercase tracking-wider transition',
                               'bg-gray-900 text-white' => $active,
                               'bg-white dark:bg-slate-900 text-gray-600 dark:text-slate-400 shadow-sm hover:text-gray-900 dark:hover:text-slate-100' => ! $active,
                           ])>{{ $option }}</a>
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg">
                @if ($orders->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500">{{ __('No orders found for this filter.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-slate-800/60 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">{{ __('User') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Video') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Razorpay') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                @foreach ($orders as $order)
                                    <tr>
                                        <td class="px-6 py-3">
                                            <div class="font-medium text-gray-900 dark:text-slate-100">{{ $order->user?->name ?? __('Deleted user') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ $order->user?->email }}</div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="text-gray-900 dark:text-slate-100">{{ $order->video?->title ?? __('Deleted video') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ $order->video?->category?->name }}</div>
                                        </td>
                                        <td class="px-6 py-3 font-medium text-gray-900 dark:text-slate-100">
                                            ₹{{ number_format((float) $order->amount, 2) }}
                                        </td>
                                        <td class="px-6 py-3">
                                            <x-admin.badge :tone="match ($order->status) {
                                                \App\Enums\OrderStatus::Success => 'green',
                                                \App\Enums\OrderStatus::Pending => 'amber',
                                                \App\Enums\OrderStatus::Failed => 'red',
                                            }">{{ $order->status->label() }}</x-admin.badge>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="font-mono text-xs text-gray-600 dark:text-slate-400">
                                                {{ $order->razorpay_payment_id ?? '—' }}
                                            </div>
                                            <div class="font-mono text-[11px] text-gray-400 dark:text-slate-500">
                                                {{ $order->razorpay_order_id ?? '—' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-slate-400">
                                            {{ $order->created_at->format('d M Y, H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="mt-4">{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
