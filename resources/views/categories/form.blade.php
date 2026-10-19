@extends('layouts.app')

@section('title', isset($category) ? 'Ubah Kategori' : 'Tambah Kategori')

@section('content')
    <form
        method="POST"
        action="{{ isset($category) ? route('categories.update', $category) : route('categories.store') }}"
        class="card max-w-2xl space-y-5 p-6"
    >
        @csrf
        @isset($category)
            @method('PUT')
        @endisset

        <div>
            <label for="name" class="label">Nama kategori</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $category->name ?? '') }}"
                class="input"
                placeholder="Contoh: Mi Ayam"
                required
            >
            @error('name')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="sort_order" class="label">Urutan tampil</label>
            <input
                id="sort_order"
                type="number"
                name="sort_order"
                value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                class="input"
                min="0"
                step="1"
                inputmode="numeric"
                required
            >
            <p class="mt-1.5 text-xs text-slate-500">Angka kecil tampil lebih dulu di halaman kasir.</p>
            @error('sort_order')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-checkbox
            name="is_active"
            label="Tampilkan kategori ini di halaman kasir"
            :checked="old('is_active', $category->is_active ?? true)"
        />

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('categories.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>
@endsection
