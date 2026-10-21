@props(['showAction' => true])
@php($isOpen = \App\Http\Controllers\SettingController::isStoreOpen())
<div data-store-control class="store-control relative inline-flex shrink-0 items-center gap-2">
    @if($showAction)
        <button type="button" data-store-reveal aria-expanded="false" class="store-status-badge rounded-full border px-3 py-2 text-xs font-semibold" data-open="{{ $isOpen ? 'true' : 'false' }}" aria-label="Status operasional toko">
            <span aria-hidden="true">●</span> <span data-store-status-label>{{ $isOpen ? 'TOKO BUKA' : 'TOKO TUTUP' }}</span>
        </button>
    @else
        <span class="store-status-badge rounded-full border px-3 py-2 text-xs font-semibold" data-open="{{ $isOpen ? 'true' : 'false' }}">
            <span aria-hidden="true">●</span> <span data-store-status-label>{{ $isOpen ? 'TOKO BUKA' : 'TOKO TUTUP' }}</span>
        </span>
    @endif
    @if($showAction && auth()->user()?->isAdmin())
        <button type="button" data-store-action class="store-status-action rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white" aria-haspopup="dialog">{{ $isOpen ? 'Tutup Toko' : 'Buka Toko' }}</button>
    @endif
</div>
