<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">{{ __('Categories') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('admin.categories.index') }}" class="flex items-center gap-2">
                    <x-text-input name="search" :value="$search" class="w-64" type="search"
                                  :placeholder="__('Search categories…')" />
                    <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                    @if ($search !== '')
                        <a href="{{ route('admin.categories.index') }}" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                            {{ __('Clear') }}
                        </a>
                    @endif
                </form>

                <a href="{{ route('admin.categories.create') }}"
                   class="inline-flex items-center rounded-md bg-gray-800 dark:bg-slate-100 dark:text-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:hover:bg-white">
                    {{ __('New category') }}
                </a>
            </div>

            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg">
                @if ($categories->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500 dark:text-slate-400">{{ __('No categories found.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-slate-800/60 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Slug') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Videos') }}</th>
                                    <th class="px-6 py-3 text-right font-medium">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                @foreach ($categories as $category)
                                    <tr>
                                        <td class="px-6 py-3 font-medium text-gray-900 dark:text-slate-100">{{ $category->name }}</td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-slate-400">{{ $category->slug }}</td>
                                        <td class="px-6 py-3">
                                            <x-admin.badge :tone="$category->videos_count ? 'blue' : 'gray'">
                                                {{ $category->videos_count }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <a href="{{ route('admin.categories.edit', $category) }}"
                                                   class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>

                                                <x-admin.confirm-action
                                                    :action="route('admin.categories.destroy', $category)"
                                                    :label="__('Delete')"
                                                    :title="__('Delete :name?', ['name' => $category->name])"
                                                    :body="__('A category can only go once its videos have been moved or removed. This cannot be undone.')"
                                                    :confirm="__('Delete category')" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="mt-4">{{ $categories->links() }}</div>
        </div>
    </div>
</x-app-layout>
