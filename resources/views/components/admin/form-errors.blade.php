{{--
    A summary at the top of the form, so a field that failed below the fold is
    still obvious. Covers both the server round trip and the XHR path, plus any
    outright failure the upload reported.
--}}
@if ($errors->any())
    <div role="alert"
         class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
        <p class="font-semibold">
            {{ trans_choice('Please fix the field below.|Please fix the :count fields below.', $errors->count(), ['count' => $errors->count()]) }}
        </p>
        <ul class="mt-2 list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div x-show="failure" x-cloak role="alert"
     class="mb-6 flex items-start gap-3 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
    <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
        <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 3.5a1 1 0 011 1V11a1 1 0 11-2 0V6.5a1 1 0 011-1zM10 15a1.1 1.1 0 110-2.2 1.1 1.1 0 010 2.2z" clip-rule="evenodd"/>
    </svg>
    <span x-text="failure"></span>
</div>
