@extends('layouts.app')

@section('title', isset($user) ? 'Ubah Akun' : 'Tambah Akun')

@section('content')
    <form
        method="POST"
        action="{{ isset($user) ? route('users.update', $user) : route('users.store') }}"
        class="card max-w-2xl space-y-5 p-6"
    >
        @csrf
        @isset($user)
            @method('PUT')
        @endisset

        <div>
            <label for="name" class="label">Nama lengkap</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $user->name ?? '') }}"
                class="input"
                placeholder="Contoh: Budi Santoso"
                required
            >
            @error('name')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="label">Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $user->email ?? '') }}"
                class="input"
                placeholder="nama@example.com"
                autocomplete="off"
                required
            >
            @error('email')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="role" class="label">Peran</label>
            <select id="role" name="role" class="input" required>
                @foreach (\App\Enums\UserRole::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value ?? \App\Enums\UserRole::Kasir->value) === $role->value)>
                        {{ $role === \App\Enums\UserRole::Admin ? 'Admin' : 'Kasir' }}
                    </option>
                @endforeach
            </select>
            @error('role')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="label">
                Kata sandi {{ isset($user) ? '(kosongkan jika tidak diubah)' : '' }}
            </label>
            <input
                id="password"
                type="password"
                name="password"
                class="input"
                placeholder="Minimal 12 karakter, kombinasi huruf dan angka"
                autocomplete="new-password"
                @unless (isset($user)) required @endunless
            >
            @error('password')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="label">Konfirmasi kata sandi</label>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="input"
                autocomplete="new-password"
                @unless (isset($user)) required @endunless
            >
        </div>

        <x-checkbox
            name="is_active"
            label="Akun aktif (dapat masuk ke aplikasi)"
            :checked="old('is_active', $user->is_active ?? true)"
        />

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="{{ route('users.index') }}" class="btn-ghost">Batal</a>
        </div>
    </form>
@endsection
