@props(['status'])

@php
    $statuses = app(\App\Services\OrderStatusService::class);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset '.$statuses->badgeClasses($status)]) }}>
    {{ $statuses->label($status) }}
</span>
