@extends('layouts.app')

@section('title', isset($product) ? 'Ubah Produk' : 'Tambah Produk')

@section('content')
    <form
        method="POST"
        action="{{ isset($product) ? route('products.update', $product) : route('products.store') }}"
        class="card max-w-2xl space-y-5 p-6"
        enctype="multipart/form-data"
    >
        @csrf
        @isset($product)
            @method('PUT')
        @endisset

        <div>
            <label for="category_id" class="label">Kategori</label>
            <select id="category_id" name="category_id" class="input" required>
                <option value="" disabled @selected(! old('category_id', $product->category_id ?? null))>Pilih kategori</option>
                @foreach ($categories as $category)
                    <option
                        value="{{ $category->id }}"
                        @selected((int) old('category_id', $product->category_id ?? 0) === $category->id)
                    >
                        {{ $category->name }}@unless ($category->is_active) (Nonaktif)@endunless
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="name" class="label">Nama produk</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $product->name ?? '') }}"
                class="input"
                placeholder="Contoh: Mi Ayam Spesial"
                required
            >
            @error('name')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="price" class="label">Harga jual</label>
            <div class="flex">
                <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3.5 text-sm font-medium text-slate-500">Rp</span>
                <input
                    id="price"
                    type="number"
                    name="price"
                    value="{{ old('price', $product->price ?? '') }}"
                    class="input rounded-l-none"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="15000"
                    required
                >
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka tanpa titik, contoh: 15000.</p>
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
                    value="{{ old('hpp', $product->hpp ?? '') }}"
                    class="input rounded-l-none"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="8000"
                >
            </div>
            <p class="mt-1.5 text-xs text-slate-500">Opsional. Masukkan angka tanpa titik, contoh: 8000. Kosongkan jika belum diketahui.</p>
            @error('hpp')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="image" class="label">Gambar Produk</label>
            <input
                id="image"
                type="file"
                name="image"
                class="input"
                accept="image/jpeg,image/png,image/webp"
            >
            <p class="mt-1.5 text-xs text-slate-500">Opsional. Format: JPG, PNG, WebP. Maksimal 2 MB.</p>
            @error('image')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @isset($product)
                @if ($product->image)
                    <div class="mt-3">
                        <p class="text-xs text-slate-500">Gambar saat ini:</p>
                        <img src="{{ route('products.image', $product, absolute: false) }}" alt="{{ $product->name }}" class="mt-1 max-w-xs rounded-lg border border-slate-200 bg-white p-1" onerror="this.style.display='none'">
                        <label class="mt-2 flex items-center gap-2 cursor-pointer text-sm text-red-600 hover:underline">
                            <input type="checkbox" name="remove_image" value="1" class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-2 focus:ring-red-500/40">
                            <span>Hapus gambar saat menyimpan</span>
                        </label>
                    </div>
                @endif
            @endisset
        </div>

        <x-checkbox
            name="is_available"
            label="Produk tersedia dan bisa dipesan"
            :checked="old('is_available', $product->is_available ?? true)"
        />

        <div>
            <span class="label">Topping untuk produk ini</span>
            <p class="mb-2 text-xs text-slate-500">Pilih topping yang boleh ditambahkan pembeli saat memesan produk ini.</p>

            @php
                $selectedToppings = array_map('intval', (array) old('topping_ids', isset($product) ? $product->toppings->pluck('id')->all() : []));
            @endphp

            <input type="hidden" name="topping_ids_present" value="1">

            @if ($toppings->isEmpty())
                <p class="rounded-lg border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-500">
                    Belum ada topping tersedia. Tambahkan topping terlebih dahulu di menu Topping.
                </p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($toppings as $topping)
                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-slate-200 px-3.5 py-2.5 hover:bg-slate-50">
                            <span class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    name="topping_ids[]"
                                    value="{{ $topping->id }}"
                                    @checked(in_array($topping->id, $selectedToppings, true))
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-2 focus:ring-emerald-500/40"
                                >
                                <span class="text-sm text-slate-700">
                                    {{ $topping->name }}@unless ($topping->is_available) <span class="text-slate-400">(Nonaktif)</span>@endunless
                                </span>
                            </span>
                            <span class="text-sm text-slate-500"><x-money :value="$topping->price" /></span>
                        </label>
                    @endforeach
                </div>
            @endif

            @error('topping_ids')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('topping_ids.*')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('products.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>
@endsection
