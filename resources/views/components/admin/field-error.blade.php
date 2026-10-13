@props(['name', 'messages' => []])

{{--
    One place for both sources of truth: messages Laravel flashed on a normal
    round trip, and messages the XHR upload mapped back without reloading.
--}}
@if (! empty($messages))
    <ul {{ $attributes->merge(['class' => 'mt-2 space-y-1 text-sm text-red-600 dark:text-red-400']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif

<ul x-show="errorsFor('{{ $name }}').length" x-cloak
    {{ $attributes->merge(['class' => 'mt-2 space-y-1 text-sm text-red-600 dark:text-red-400']) }}>
    <template x-for="message in errorsFor('{{ $name }}')" :key="message">
        <li x-text="message"></li>
    </template>
</ul>
