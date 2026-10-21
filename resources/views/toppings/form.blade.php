@extends('layouts.app')

@section('title', isset($topping) ? 'Ubah Topping' : 'Tambah Topping')

@section('content')
    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ isset($topping) ? route('toppings.update', $topping) : route('toppings.store') }}"
        class="card max-w-2xl space-y-5 p-6"
    >
        @csrf
        @isset($topping)
            @method('PUT')
        @endisset

        <div>
            <label for="name" class="label">Nama topping</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $topping->name ?? '') }}"
                class="input"
                placeholder="Contoh: Telur Rebus"
                required
            >
            @error('name')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="price" class="label">Harga tambahan</label>
            <div class="flex">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3.5 text-sm font-medium text-slate-500">Rp</span>
                <input
                    id="price"
                    type="number"
                    name="price"
                    value="{{ old('price', $topping->price ?? '') }}"
                    class="input rounded-l-none"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="3000"
                    required
                >
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka tanpa titik, contoh: 3000.</p>
            @error('price')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="hpp" class="label">HPP (Harga Pokok Penjualan)</label>
            <div class="flex">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3.5 text-sm font-medium text-slate-500">Rp</span>
                <input
                    id="hpp"
                    type="number"
                    name="hpp"
                    value="{{ old('hpp', $topping->hpp ?? '') }}"
                    class="input rounded-l-none"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="1500"
                >
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Opsional. Masukkan angka tanpa titik, contoh: 1500. Kosongkan jika belum diketahui.</p>
            @error('hpp')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="image" class="label">Gambar Topping</label>
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="input text-base" aria-describedby="topping-image-help" data-image-preview-input="topping-image-preview">
            <p id="topping-image-help" class="mt-1 text-xs text-slate-500">Opsional. JPG, PNG, atau WebP, maksimal 2 MB.</p>
            @error('image')<p class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            <img id="topping-image-preview" @if (isset($topping) && $topping->image) src="{{ route('toppings.image', $topping, absolute: false) }}" @endif alt="Preview topping" width="96" height="96" class="mt-3 h-24 w-24 rounded-xl object-cover" @if (!isset($topping) || !$topping->image) hidden @endif>
            @if (isset($topping) && $topping->image)
                <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1"> Lepaskan gambar dari topping</label>
            @endif
        </div>

        <x-checkbox
            name="is_available"
            label="Topping tersedia dan bisa dipilih"
            :checked="old('is_available', $topping->is_available ?? true)"
        />

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('toppings.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>
@endsection
