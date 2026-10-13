<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.stat-card :label="__('Total Users')" :value="number_format($totalUsers)" :hint="__('Excludes administrators')" />
                <x-admin.stat-card :label="__('Total Videos')" :value="number_format($totalVideos)" />
                <x-admin.stat-card :label="__('Total Revenue')" :value="'₹'.number_format($totalRevenue, 2)" :hint="__('Successful orders only')" />
                <x-admin.stat-card :label="__('Total Orders')" :value="number_format($totalOrders)" :hint="__('All statuses')" />
            </div>

            <div class="mt-8 bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-slate-800 px-6 py-4">
                    <h3 class="font-semibold text-gray-900 dark:text-slate-100">{{ __('Recent successful orders') }}</h3>
                    <a href="{{ route('admin.orders.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                        {{ __('View all') }}
                    </a>
                </div>

                @if ($recentOrders->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500 dark:text-slate-400">{{ __('No paid orders yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-slate-800/60 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">{{ __('User') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Video') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Paid on') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                @foreach ($recentOrders as $order)
                                    <tr>
                                        <td class="px-6 py-3">
                                            <div class="font-medium text-gray-900 dark:text-slate-100">{{ $order->user?->name ?? __('Deleted user') }}</div>
                                            <div class="text-xs text-gray-500 dark:text-slate-400">{{ $order->user?->email }}</div>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-300">{{ $order->video?->title ?? __('Deleted video') }}</td>
                                        <td class="px-6 py-3 font-medium text-gray-900 dark:text-slate-100">₹{{ number_format((float) $order->amount, 2) }}</td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-slate-400">{{ $order->created_at->format('d M Y, H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
