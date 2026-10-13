@php
    $sourceValue = old('video_source', $video->exists && $video->isExternalVideo() ? 'link' : 'upload');
    $maxVideoMb = (int) (\App\Http\Requests\Admin\StoreVideoRequest::MAX_VIDEO_KB / 1024);
    // What the stored link will actually be framed as, when it differs from
    // what was pasted. Showing it is the only way an admin can tell that a
    // page URL was rewritten -- otherwise they find out from a learner.
    $storedUrl = old('video_url', $video->isExternalVideo() ? $video->video_path : '');
    $resolvedEmbed = \App\Support\VideoEmbed::wasRewritten($storedUrl)
        ? \App\Support\VideoEmbed::resolve($storedUrl)
        : null;
@endphp

<x-admin.form-errors />

<div x-data="{
        isFree: {{ old('is_free', $video->is_free) ? 'true' : 'false' }},
        source: @js($sourceValue),
        thumbnail: @js($video->thumbnailUrl()),
        previewThumbnail(event) {
            const file = event.target.files[0];
            if (file) {
                this.thumbnail = URL.createObjectURL(file);
            }
        },
     }" class="space-y-6">

    <div>
        <x-input-label for="title" :value="__('Title')" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                      :value="old('title', $video->title)" required autofocus />
        <x-admin.field-error name="title" :messages="$errors->get('title')" />
    </div>

    <div>
        <x-input-label for="category_id" :value="__('Category')" />
        <select id="category_id" name="category_id" required
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">{{ __('— Select a category —') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                    @selected((int) old('category_id', $video->category_id) === $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <x-admin.field-error name="category_id" :messages="$errors->get('category_id')" />
    </div>

    <div>
        <x-input-label for="description" :value="__('Description')" />
        <textarea id="description" name="description" rows="4"
                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $video->description) }}</textarea>
        <x-admin.field-error name="description" :messages="$errors->get('description')" />
    </div>

    <div class="max-w-xs">
        <x-input-label for="duration_seconds" :value="__('Duration (seconds)')" />
        <x-text-input id="duration_seconds" name="duration_seconds" type="number" min="1" step="1"
                      class="mt-1 block w-full" :value="old('duration_seconds', $video->duration_seconds)" />
        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
            {{ __('Needed for progress and certificates: a lesson counts as completed once 90% of this is watched. Leave empty if unknown.') }}
        </p>
        <x-admin.field-error name="duration_seconds" :messages="$errors->get('duration_seconds')" />
    </div>

    {{-- Video source: an uploaded file on the private disk, or an external link. --}}
    <fieldset class="rounded-md border border-gray-200 dark:border-slate-800 p-4">
        <legend class="px-1 text-sm font-medium text-gray-700 dark:text-slate-300">{{ __('Video source') }}</legend>

        <div class="flex flex-wrap gap-6">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                <input type="radio" name="video_source" value="upload" x-model="source"
                       class="border-gray-300 dark:border-slate-700 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500">
                {{ __('Upload file') }}
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-slate-300">
                <input type="radio" name="video_source" value="link" x-model="source"
                       class="border-gray-300 dark:border-slate-700 text-indigo-600 dark:text-indigo-400 focus:ring-indigo-500">
                {{ __('Streaming / embed link') }}
            </label>
        </div>
        <x-admin.field-error name="video_source" :messages="$errors->get('video_source')" />

        <div x-show="source === 'upload'" x-cloak class="mt-4">
            <x-input-label for="video_file" :value="__('Video file')" />
            <input id="video_file" name="video_file" type="file" accept="video/*"
                   x-bind:disabled="source !== 'upload'"
                   class="mt-1 block w-full text-sm text-gray-600 dark:text-slate-400 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700 dark:file:bg-slate-100 dark:file:text-slate-900 dark:hover:file:bg-white">
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                {{ __('mp4, webm, mov, avi or mkv — up to :size MB. Stored privately, outside the web root.', ['size' => $maxVideoMb]) }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                {{ __('A large file can take a few minutes. Keep this tab open — you will see the progress below.') }}
            </p>
            @if ($video->exists && ! $video->isExternalVideo())
                <p class="mt-2 text-xs text-gray-600 dark:text-slate-400">
                    {{ __('Current file:') }}
                    <code class="rounded bg-gray-100 dark:bg-slate-800 px-1 py-0.5">{{ basename($video->video_path) }}</code>
                    <span class="text-gray-400 dark:text-slate-500">{{ __('(leave empty to keep it)') }}</span>
                </p>
                {{--
                    The storage path used to be printed here raw, which reads
                    like the start of a URL and is not one: uploads live outside
                    the web root so a paid lesson cannot simply be downloaded.
                    Showing the address that does work stops the guessing.
                --}}
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                    {{ __('Stored outside the web root, so it has no fixed address. Each viewer is handed a signed link that is tied to their session and expires in :ttl seconds, so a copied URL is useless to anyone else.', ['ttl' => \App\Support\StreamToken::ttl()]) }}
                </p>
            @endif
            <x-admin.field-error name="video_file" :messages="$errors->get('video_file')" />
        </div>

        <div x-show="source === 'link'" x-cloak class="mt-4">
            <x-input-label for="video_url" :value="__('Streaming / embed URL')" />
            <x-text-input id="video_url" name="video_url" type="url" class="mt-1 block w-full"
                          x-bind:disabled="source !== 'link'"
                          :value="old('video_url', $video->isExternalVideo() ? $video->video_path : '')"
                          placeholder="https://" />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
                {{ __('A normal YouTube, Vimeo or Drive link is fine — it is converted to its embeddable form when the lesson is played.') }}
            </p>
            @if ($resolvedEmbed)
                <p class="mt-1 text-xs text-gray-600 dark:text-slate-400">
                    {{ __('Will be embedded as') }}
                    <code class="rounded bg-gray-100 dark:bg-slate-800 px-1 py-0.5">{{ $resolvedEmbed }}</code>
                </p>
            @endif
            <x-admin.field-error name="video_url" :messages="$errors->get('video_url')" />
        </div>
    </fieldset>

    <div>
        <x-input-label for="thumbnail" :value="__('Thumbnail')" />
        <div class="mt-1 flex items-start gap-4">
            <template x-if="thumbnail">
                <img :src="thumbnail" alt="" class="h-20 w-32 rounded-md border border-gray-200 dark:border-slate-800 object-cover">
            </template>
            <div class="flex-1">
                <input id="thumbnail" name="thumbnail" type="file" accept="image/*"
                       x-on:change="previewThumbnail($event)"
                       class="block w-full text-sm text-gray-600 dark:text-slate-400 file:mr-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700 dark:file:bg-slate-100 dark:file:text-slate-900 dark:hover:file:bg-white">
                <p class="mt-1 text-xs text-gray-500">{{ __('jpg, png or webp — up to 2 MB.') }}</p>
                <x-admin.field-error name="thumbnail" :messages="$errors->get('thumbnail')" />
            </div>
        </div>
    </div>

    {{-- Free / Paid toggle. The hidden field carries "0" when the switch is off. --}}
    <div class="rounded-md border border-gray-200 dark:border-slate-800 p-4">
        <label class="inline-flex cursor-pointer items-center gap-3">
            <input type="hidden" name="is_free" value="0">
            <input type="checkbox" name="is_free" value="1" x-model="isFree" class="peer sr-only">
            <span class="relative h-6 w-11 rounded-full bg-gray-300 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition after:content-[''] peer-checked:bg-green-500 peer-checked:after:translate-x-5"></span>
            <span class="text-sm font-medium text-gray-700 dark:text-slate-300"
                  x-text="isFree ? @js(__('Free video')) : @js(__('Paid video'))"></span>
        </label>
        <x-admin.field-error name="is_free" :messages="$errors->get('is_free')" />

        <div x-show="! isFree" x-cloak class="mt-4 max-w-xs">
            <x-input-label for="price" :value="__('Price (₹)')" />
            {{--
                x-bind:disabled is not cosmetic. A free video stores price 0.00,
                which violates min="1" -- and because x-show has hidden this
                field, the browser cannot focus it to report that, so it
                cancels the submit with no message at all and Save looks dead.
                Disabling the inactive branch excludes it from constraint
                validation and from the payload; the server forces price to 0
                for a free video anyway (StoreVideoRequest::prepareForValidation).
            --}}
            <x-text-input id="price" name="price" type="number" step="0.01" min="1" class="mt-1 block w-full"
                          x-bind:disabled="isFree"
                          :value="old('price', $video->exists ? $video->price : null)" />
            <x-admin.field-error name="price" :messages="$errors->get('price')" />
        </div>
    </div>

    <div class="max-w-xs">
        <x-input-label for="status" :value="__('Status')" />
        <select id="status" name="status" required
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (\App\Enums\VideoStatus::cases() as $case)
                <option value="{{ $case->value }}"
                    @selected(old('status', $video->status?->value ?? \App\Enums\VideoStatus::Draft->value) === $case->value)>
                    {{ $case->label() }}
                </option>
            @endforeach
        </select>
        <x-admin.field-error name="status" :messages="$errors->get('status')" />
    </div>

    {{-- Real byte progress, because a 120 MB upload with no feedback feels broken. --}}
    <div x-show="uploading" x-cloak class="rounded-md border border-gray-200 bg-gray-50 p-4 dark:border-slate-800 dark:bg-slate-800/60">
        <div class="flex items-center justify-between text-xs font-medium text-gray-700 dark:text-slate-300">
            <span x-show="progress < 100">{{ __('Uploading…') }}</span>
            <span x-show="progress >= 100" x-cloak>{{ __('Upload sent — saving…') }}</span>
            <span class="tabular" x-text="progress + '%'"></span>
        </div>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-slate-700"
             role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress">
            <div class="h-full rounded-full bg-gray-800 transition-[width] duration-200 dark:bg-slate-100"
                 :style="`width: ${progress}%`"></div>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">
            {{ __('Do not close this tab until it finishes.') }}
        </p>
    </div>

    <div class="flex items-center gap-4 border-t border-gray-100 dark:border-slate-800 pt-6">
        <x-admin.submit-button :label="$submitLabel" :busy-label="__('Saving…')" />
        <a href="{{ route('admin.videos.index') }}"
           x-bind:class="submitting && 'pointer-events-none opacity-50'"
           class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-100">
            {{ __('Cancel') }}
        </a>
    </div>
</div>
