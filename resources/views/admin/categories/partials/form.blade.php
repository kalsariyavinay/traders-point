<x-admin.form-errors />

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $category->name)" required autofocus />
    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ __('The URL slug is generated from this name.') }}</p>
    <x-admin.field-error name="name" :messages="$errors->get('name')" />
</div>

<div class="mt-6 flex items-center gap-4">
    <x-admin.submit-button :label="$submitLabel" />
    <a href="{{ route('admin.categories.index') }}"
       class="text-sm text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-slate-100">
        {{ __('Cancel') }}
    </a>
</div>
