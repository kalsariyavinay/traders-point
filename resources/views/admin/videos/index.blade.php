<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">{{ __('Videos') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" action="{{ route('admin.videos.index') }}" class="flex items-center gap-2">
                    <x-text-input name="search" :value="$search" class="w-72" type="search"
                                  :placeholder="__('Search title, description or category…')" />
                    <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                    @if ($search !== '')
                        <a href="{{ route('admin.videos.index') }}" class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200">
                            {{ __('Clear') }}
                        </a>
                    @endif
                </form>

                <a href="{{ route('admin.videos.create') }}"
                   class="inline-flex items-center rounded-md bg-gray-800 dark:bg-slate-100 dark:text-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:hover:bg-white">
                    {{ __('New video') }}
                </a>
            </div>

            <div class="bg-white dark:bg-slate-900 overflow-hidden shadow-sm sm:rounded-lg">
                @if ($videos->isEmpty())
                    <p class="px-6 py-8 text-sm text-gray-500 dark:text-slate-400">
                        {{ $search !== '' ? __('No videos match that search.') : __('No videos yet. Add your first one.') }}
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-800 text-sm">
                            <thead class="bg-gray-50 dark:bg-slate-800/60 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-slate-400">
                                <tr>
                                    <th class="px-6 py-3 font-medium">{{ __('Video') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Category') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Access') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                                    <th class="px-6 py-3 font-medium">{{ __('Source') }}</th>
                                    <th class="px-6 py-3 text-right font-medium">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                                @foreach ($videos as $video)
                                    <tr>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-3">
                                                @if ($video->thumbnailUrl())
                                                    <img src="{{ $video->thumbnailUrl() }}" alt=""
                                                         class="h-12 w-20 shrink-0 rounded border border-gray-200 dark:border-slate-800 object-cover">
                                                @else
                                                    <div class="flex h-12 w-20 shrink-0 items-center justify-center rounded border border-dashed border-gray-200 dark:border-slate-700 dark:border-slate-800 text-[10px] uppercase text-gray-400 dark:text-slate-500">
                                                        {{ __('No image') }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-slate-100">{{ $video->title }}</div>
                                                    <div class="text-xs text-gray-500 dark:text-slate-400">{{ $video->slug }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3 text-gray-700 dark:text-slate-300">{{ $video->category?->name ?? '—' }}</td>
                                        <td class="px-6 py-3">
                                            @if ($video->is_free)
                                                <x-admin.badge tone="green">{{ __('Free') }}</x-admin.badge>
                                            @else
                                                <span class="font-medium text-gray-900 dark:text-slate-100">₹{{ number_format((float) $video->price, 2) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3">
                                            <x-admin.badge :tone="$video->isPublished() ? 'blue' : 'amber'">
                                                {{ $video->status->label() }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="px-6 py-3">
                                            <x-admin.badge :tone="$video->hasPlaceholderLink() ? 'red' : 'gray'">
                                                {{ $video->isExternalVideo() ? __('Link') : __('Upload') }}
                                            </x-admin.badge>
                                            @if ($video->hasPlaceholderLink())
                                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                                    {{ __('Placeholder link — this lesson cannot play') }}
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <a href="{{ route('admin.videos.edit', $video) }}"
                                                   class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>

                                                <x-admin.confirm-action
                                                    :action="route('admin.videos.destroy', $video)"
                                                    :label="__('Delete')"
                                                    :title="__('Delete :title?', ['title' => $video->title])"
                                                    :body="__('The uploaded file and thumbnail are deleted with it. A video with paid orders cannot be removed — set it to draft instead.')"
                                                    :confirm="__('Delete video')" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="mt-4">{{ $videos->links() }}</div>
        </div>
    </div>
</x-app-layout>
