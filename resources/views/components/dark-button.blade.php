@props(['tone' => 'primary'])

@php
    $tones = [
        'primary' => 'bg-paper text-ink hover:bg-white',
        'ghost' => 'border border-ink-line text-paper hover:border-paper/40',
        'danger' => 'bg-bear text-ink hover:brightness-110',
    ];
@endphp

<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center rounded-full px-6 py-2.5 font-data text-xs font-medium '
        .'uppercase tracking-eyebrow transition '.($tones[$tone] ?? $tones['primary']),
]) }}>
    {{ $slot }}
</button>
