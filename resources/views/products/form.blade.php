@extends('layouts.app')

@section('title', isset($product) ? 'Ubah Produk' : 'Tambah Produk')

@section('content')
    <form
        method="POST"
        action="{{ isset($product) ? route('products.update', $product) : route('products.store') }}"
        class="card max-w-2xl space-y-5 p-6"
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

        <x-checkbox
            name="is_available"
            label="Produk tersedia dan bisa dipesan"
            :checked="old('is_available', $product->is_available ?? true)"
        />

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('products.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>
@endsection
