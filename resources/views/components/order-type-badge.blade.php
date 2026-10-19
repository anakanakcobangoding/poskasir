@props(['type'])

@php
    $label = match ($type) {
        \App\Enums\OrderType::TA => 'Bungkus',
        \App\Enums\OrderType::DI => 'Dine-in',
        \App\Enums\OrderType::DLV => 'Delivery',
    };

    $classes = match ($type) {
        \App\Enums\OrderType::TA => 'bg-sky-50 text-sky-700 ring-sky-500/20',
        \App\Enums\OrderType::DI => 'bg-emerald-50 text-emerald-700 ring-emerald-500/20',
        \App\Enums\OrderType::DLV => 'bg-violet-50 text-violet-700 ring-violet-500/20',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset '.$classes]) }}>
    {{ $label }}
</span>
