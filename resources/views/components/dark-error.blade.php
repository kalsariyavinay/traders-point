@props(['messages' => []])

@if (! empty($messages))
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm text-bear']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
