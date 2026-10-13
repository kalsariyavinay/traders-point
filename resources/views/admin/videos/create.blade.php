<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">{{ __('New video') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />

            <div class="bg-white dark:bg-slate-900 p-6 shadow-sm sm:rounded-lg">
                @if ($categories->isEmpty())
                    <p class="text-sm text-gray-600 dark:text-slate-400">
                        {{ __('Create a category first, then come back to add videos.') }}
                        <a href="{{ route('admin.categories.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                            {{ __('New category') }}
                        </a>
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.videos.store') }}" enctype="multipart/form-data" x-data="adminUpload({
                              maxVideoBytes: {{ \App\Http\Requests\Admin\StoreVideoRequest::MAX_VIDEO_KB * 1024 }},
                              maxThumbnailBytes: {{ \App\Http\Requests\Admin\StoreVideoRequest::MAX_THUMBNAIL_KB * 1024 }},
                              fallbackRedirect: @js(route('admin.videos.index')),
                              tokenUrl: @js(route('csrf-token')),
                          })"
                          x-on:submit.prevent="submit($event)"
                          x-on:invalid.capture="reportBlocked($event)">
                        @csrf
                        @include('admin.videos.partials.form', ['submitLabel' => __('Create video')])
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
