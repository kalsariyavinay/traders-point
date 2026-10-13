@props(['value' => null])

<label {{ $attributes->merge(['class' => 'block font-data text-[0.65rem] uppercase tracking-eyebrow text-muted']) }}>
    {{ $value ?? $slot }}
</label>
