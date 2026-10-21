@extends('layouts.app')

@section('title', 'Profil Toko')

@section('content')
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Profil Toko</h2>
            <p class="text-sm text-slate-500">Kelola identitas toko yang ditampilkan pada aplikasi dan struk.</p>
        </div>


        <section class="card flex flex-wrap items-center justify-between gap-4 p-4">
            <div><h3 class="font-semibold">Status Toko</h3><p class="text-sm text-slate-500">Status berlaku untuk seluruh kasir.</p></div>
            <x-store-status />
        </section>

        <form method="POST" enctype="multipart/form-data" action="{{ route('settings.store-profile.update') }}" class="card max-w-2xl space-y-5 p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="store_name" class="label">Nama Toko</label>
                <input
                    id="store_name"
                    type="text"
                    name="store_name"
                    value="{{ old('store_name', $storeName ?? '') }}"
                    class="input"
                    placeholder="Contoh: Warung Cerdas"
                    required
                    maxlength="100"
                >
                @error('store_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="pos_name" class="label">Nama POS</label>
                <input
                    id="pos_name"
                    type="text"
                    name="pos_name"
                    value="{{ old('pos_name', $posName ?? '') }}"
                    class="input"
                    placeholder="Contoh: Kasir Utama"
                    required
                    maxlength="100"
                >
                @error('pos_name')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="main_title" class="label">Judul halaman Welcome</label>
                <input id="main_title" name="main_title" maxlength="150" class="input" value="{{ old('main_title', $mainTitle) }}">
                @error('main_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="logo" class="label">Logo Toko</label>
                <div class="mb-3 flex items-center gap-3">
                    @if($storeLogoUrl)<img data-store-logo-existing src="{{ $storeLogoUrl }}" alt="Logo toko tersimpan" class="h-16 w-16 rounded-xl object-contain">@else<x-store-logo data-store-logo-placeholder class="h-16 w-16 rounded-xl" />@endif
                    <img id="store-logo-preview" data-store-logo-preview alt="Pratinjau logo toko" class="h-16 w-16 rounded-xl object-contain" hidden>
                    <span class="text-sm text-slate-500">Pratinjau logo toko</span>
                </div>
                <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="input">
                <p class="mt-1 text-xs text-slate-500">JPG, PNG, atau WebP. Maksimum 2 MB.</p>
                @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @if($storeLogoUrl)<label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1"> Hapus logo dari profil</label>@endif
            </div>

            <div>
                <label for="subtitle" class="label">Subjudul</label>
                <input
                    id="subtitle"
                    type="text"
                    name="subtitle"
                    value="{{ old('subtitle', \App\Http\Controllers\SettingController::subtitle()) }}"
                    class="input"
                    placeholder="Contoh: Mi Ayam & Bakso Enak"
                    maxlength="255"
                >
                <p class="mt-1.5 text-xs text-slate-500">Opsional. Subjudul yang ditampilkan di bawah nama toko di sidebar.</p>
                @error('subtitle')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
                <a href="{{ route('settings.qris.edit') }}" class="btn-ghost">QRIS</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    const logoInput = document.getElementById('logo');
    const updateStoreLogoPreview = () => {
        const file = logoInput?.files?.[0];
        const preview = document.querySelector('[data-store-logo-preview]');
        const existing = document.querySelector('[data-store-logo-existing]');
        const placeholder = document.querySelector('[data-store-logo-placeholder]');
        const remove = document.querySelector('[name=remove_logo]')?.checked ?? false;
        if (!preview) return;
        preview.hidden = !file;
        if (file) {
            if (preview.dataset.objectUrl) URL.revokeObjectURL(preview.dataset.objectUrl);
            preview.dataset.objectUrl = URL.createObjectURL(file);
            preview.src = preview.dataset.objectUrl;
        }
        if (existing) existing.hidden = Boolean(file) || remove;
        if (placeholder) placeholder.hidden = Boolean(file);
    };
    logoInput?.addEventListener('change', updateStoreLogoPreview);
    document.querySelector('[name=remove_logo]')?.addEventListener('change', updateStoreLogoPreview);
</script>
@endpush
