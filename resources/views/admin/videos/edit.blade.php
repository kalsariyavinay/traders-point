<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
            {{ __('Edit video') }}: {{ $video->title }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-admin.nav />
            <div class="bg-white dark:bg-slate-900 p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.videos.update', $video) }}" enctype="multipart/form-data" x-data="adminUpload({
                              maxVideoBytes: {{ \App\Http\Requests\Admin\StoreVideoRequest::MAX_VIDEO_KB * 1024 }},
                              maxThumbnailBytes: {{ \App\Http\Requests\Admin\StoreVideoRequest::MAX_THUMBNAIL_KB * 1024 }},
                              fallbackRedirect: @js(route('admin.videos.index')),
                              tokenUrl: @js(route('csrf-token')),
                          })"
                          x-on:submit.prevent="submit($event)"
                          x-on:invalid.capture="reportBlocked($event)">
                    @csrf
                    @method('PUT')
                    @include('admin.videos.partials.form', ['submitLabel' => __('Save changes')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
