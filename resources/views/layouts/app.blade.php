<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kasir') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<div class="min-h-screen lg:flex">
    <aside
        id="app-sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-200 transition-transform duration-200 lg:static lg:translate-x-0"
    >
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-emerald-500 text-sm font-bold text-white">W</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold leading-tight text-white">{{ config('app.name') }}</p>
                <p class="text-xs text-slate-400">Mi Ayam &amp; Bakso</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 p-3">
            <x-nav-link :href="route('pos.index')" :active="request()->routeIs('pos.index')">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="20" r="1.5" /><circle cx="18" cy="20" r="1.5" />
                    <path d="M2 3h3l2.6 12.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L21 7H6" />
                </svg>
                <span>Kasir</span>
            </x-nav-link>
            <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                </svg>
                <span>Kategori</span>
            </x-nav-link>
            <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 8l-9-5-9 5 9 5 9-5z" /><path d="M3 8v8l9 5 9-5V8" /><path d="M12 13v8" />
                </svg>
                <span>Produk</span>
            </x-nav-link>
            <x-nav-link :href="route('toppings.index')" :active="request()->routeIs('toppings.*')">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" /><path d="M12 7v10M7 12h10" />
                </svg>
                <span>Topping</span>
            </x-nav-link>
        </nav>

        <div class="border-t border-white/10 px-5 py-4 text-xs text-slate-400">
            Menu &amp; kasir dalam satu tempat.
        </div>
    </aside>

    <div id="app-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button
                type="button"
                id="app-sidebar-toggle"
                class="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 lg:hidden"
                aria-label="Buka menu navigasi"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
            <h1 class="text-base font-semibold text-slate-900">@yield('title', 'Kasir')</h1>
            <span class="ml-auto hidden items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 sm:inline-flex">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Toko buka
            </span>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
