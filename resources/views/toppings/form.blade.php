@extends('layouts.app')

@section('title', isset($topping) ? 'Ubah Topping' : 'Tambah Topping')

@section('content')
    <form
        method="POST"
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
