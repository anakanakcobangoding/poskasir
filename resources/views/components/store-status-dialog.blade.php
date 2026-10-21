<div id="store-operation" data-status="{{ \App\Http\Controllers\SettingController::storeStatus() }}" data-url="{{ route('store.status', absolute: false) }}" @if(auth()->user()?->isAdmin()) data-open-url="{{ route('settings.store-profile.open', absolute: false) }}" data-close-url="{{ route('settings.store-profile.close', absolute: false) }}" @endif></div>
@if(auth()->user()?->isAdmin())
<dialog id="store-status-dialog" aria-labelledby="store-status-title" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border-0 p-6 shadow-xl backdrop:bg-slate-950/50">
    <h2 id="store-status-title" class="text-lg font-semibold"></h2>
    <p data-store-dialog-description class="mt-3 text-sm leading-6 text-slate-600"></p>
    <p data-store-dialog-error role="alert" class="mt-3 text-sm text-red-700" hidden></p>
    <div class="mt-5 flex flex-wrap justify-end gap-3">
        <button type="button" data-store-cancel class="btn-secondary" autofocus>Batal</button>
        <button type="button" data-store-confirm class="btn-primary"></button>
    </div>
</dialog>
@endif
