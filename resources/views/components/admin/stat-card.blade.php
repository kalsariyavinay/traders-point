@props(['label', 'value', 'hint' => null])

<div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg p-6">
    <div class="text-sm font-medium text-gray-500 dark:text-slate-400">{{ $label }}</div>
    <div class="mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-slate-100">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-gray-400 dark:text-slate-500">{{ $hint }}</div>
    @endif
</div>
