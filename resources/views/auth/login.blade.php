@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <div class="w-full max-w-sm">
        <div class="mb-6 flex flex-col items-center gap-3 text-center">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500 text-lg font-bold text-white">W</span>
            <div>
                <p class="text-base font-semibold text-slate-900">{{ config('app.name') }}</p>
                <p class="text-sm text-slate-500">Masuk untuk mulai melayani pesanan.</p>
            </div>
        </div>

        <div class="card p-6">
            <x-flash />

            @if ($errors->any())
                <div class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16.5v.01" />
                    </svg>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="label">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        class="input"
                        autocomplete="username"
                        autofocus
                        required
                    >
                </div>

                <div>
                    <label for="password" class="label">Kata sandi</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="input"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary w-full">Masuk</button>
            </form>
        </div>
    </div>
@endsection
