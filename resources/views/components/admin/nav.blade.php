@php
    $sections = [
        ['route' => 'admin.dashboard', 'pattern' => 'admin.dashboard', 'label' => __('Overview')],
        ['route' => 'admin.videos.index', 'pattern' => 'admin.videos.*', 'label' => __('Videos')],
        ['route' => 'admin.categories.index', 'pattern' => 'admin.categories.*', 'label' => __('Categories')],
        ['route' => 'admin.orders.index', 'pattern' => 'admin.orders.*', 'label' => __('Orders')],
        ['route' => 'admin.coupons.index', 'pattern' => 'admin.coupons.*', 'label' => __('Coupons')],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 dark:border-slate-800 pb-3">
    @foreach ($sections as $section)
        @php $active = request()->routeIs($section['pattern']); @endphp
        <a href="{{ route($section['route']) }}"
           @class([
               'rounded-md px-3 py-1.5 text-sm font-medium transition',
               'bg-gray-900 text-white dark:bg-slate-100 dark:text-slate-900' => $active,
               'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-slate-100' => ! $active,
           ])>{{ $section['label'] }}</a>
    @endforeach
</nav>
