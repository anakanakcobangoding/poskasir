@props(['size' => 'h-10 w-10'])
@php($logoUrl = \App\Http\Controllers\SettingController::storeLogoUrl())
<span {{ $attributes->class(['grid shrink-0 place-items-center overflow-hidden rounded-xl bg-emerald-600 text-sm font-bold text-white', $size]) }}>
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="Logo {{ \App\Http\Controllers\SettingController::storeName() }}" class="h-full w-full object-contain" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
        <span hidden aria-hidden="true">{{ mb_substr(\App\Http\Controllers\SettingController::storeName(), 0, 1) }}</span>
    @else
        <span aria-hidden="true">{{ mb_substr(\App\Http\Controllers\SettingController::storeName(), 0, 1) }}</span>
    @endif
</span>
