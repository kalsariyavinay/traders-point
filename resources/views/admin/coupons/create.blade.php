<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">{{ __('New coupon') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />
            <div class="bg-white dark:bg-slate-900 p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.coupons.store') }}"
                      x-data="adminForm" x-on:submit="start($event)"
                      x-on:invalid.capture="reportBlocked($event)">
                    @csrf
                    @include('admin.coupons.partials.form', ['submitLabel' => __('Create coupon')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
