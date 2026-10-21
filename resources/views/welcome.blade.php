@extends('layouts.guest')

@section('title', 'Selamat Datang')

@section('content')
    <main class="relative flex min-h-[calc(100dvh-5rem)] w-full max-w-5xl items-center justify-center overflow-hidden rounded-[2rem] bg-white shadow-xl shadow-emerald-950/10 ring-1 ring-slate-200/80">
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-br from-emerald-50 via-white to-teal-50"></div>
        <div aria-hidden="true" class="absolute -right-24 -top-28 h-80 w-80 rounded-full bg-emerald-200/30 blur-3xl"></div>
        <div aria-hidden="true" class="absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-teal-200/30 blur-3xl"></div>

        <section class="relative z-10 flex w-full flex-col items-center px-6 py-12 text-center sm:px-12 sm:py-16">
            <div class="flex flex-wrap items-center justify-center gap-3">
                <x-store-logo class="h-16 w-16 rounded-2xl shadow-md shadow-emerald-900/10 sm:h-20 sm:w-20" />
                <div class="text-left">
                    <p class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">{{ \App\Http\Controllers\SettingController::storeName() }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ \App\Http\Controllers\SettingController::posName() }}</p>
                </div>
            </div>

            <div class="mt-8">
                <x-store-status />
            </div>

            <p class="mt-8 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Sistem Kasir & Operasional</p>
            <h1 class="mt-4 max-w-3xl text-3xl font-bold leading-tight tracking-tight text-slate-950 sm:text-5xl">{{ \App\Http\Controllers\SettingController::storeTitle() }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">{{ \App\Http\Controllers\SettingController::subtitle() ?: 'Kelola pesanan dan operasional toko dengan mudah melalui WarungCerdas POS.' }}</p>

            <div class="mt-7 rounded-full border border-white/80 bg-white/75 px-4 py-2 text-sm text-slate-600 shadow-sm backdrop-blur">
                <span class="font-medium text-slate-900">{{ auth()->user()->name }}</span>
                <span aria-hidden="true" class="mx-1.5 text-slate-300">·</span>
                <span>{{ auth()->user()->email }}</span>
            </div>

            <p data-store-closed-banner @if(\App\Http\Controllers\SettingController::isStoreOpen()) hidden @endif class="mt-5 max-w-md rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm leading-6 text-red-800" role="status">Toko sedang tutup. Transaksi tidak dapat diproses. Silakan hubungi admin untuk membuka toko kembali.</p>

            <a data-store-start href="{{ route('pos.index') }}" class="mt-8 inline-flex min-h-12 min-w-52 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-7 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-800/20 transition hover:bg-emerald-800 focus:outline-none focus:ring-4 focus:ring-emerald-600/25" @if(!\App\Http\Controllers\SettingController::isStoreOpen()) aria-disabled="true" tabindex="-1" @endif>
                {{ \App\Http\Controllers\SettingController::isStoreOpen() ? 'Mulai Kasir' : 'Kasir Sedang Tutup' }}
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </a>
        </section>
    </main>
    <x-store-status-dialog />
@endsection
