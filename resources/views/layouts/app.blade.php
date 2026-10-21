<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kasir') · {{ \App\Http\Controllers\SettingController::storeName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<div id="app-shell" class="min-h-screen lg:flex">
    <aside
        id="app-sidebar"
        class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col overflow-y-auto bg-slate-900 text-slate-200 transition-transform duration-200 lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
    >
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <x-store-logo class="h-9 w-9" />
<div data-sidebar-brand class="min-w-0">
                <p class="truncate text-sm font-semibold leading-tight text-white">{{ \App\Http\Controllers\SettingController::storeName() }}</p>
                @if (\App\Http\Controllers\SettingController::subtitle())
                    <p class="truncate text-xs text-slate-300 mt-0.5">{{ \App\Http\Controllers\SettingController::subtitle() }}</p>
                @else
                    <p class="text-xs text-slate-400">Mi Ayam & Bakso</p>
                @endif
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
            <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.*')">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 6h11M9 12h11M9 18h11" /><path d="M4 6h.01M4 12h.01M4 18h.01" />
                </svg>
                <span>Pesanan</span>
            </x-nav-link>
            @if(auth()->user()?->isAdmin())
                <x-nav-link :href="route('dashboard.index')" :active="request()->routeIs('dashboard.index')">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 3v18h18" /><path d="M7 16l4-4 4 4 6-6" />
                    </svg>
                    <span>Dashboard</span>
                </x-nav-link>
                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                    <span>Laporan</span>
                </x-nav-link>
                <x-nav-link :href="route('settings.store-profile.edit')" :active="request()->routeIs('settings.store-profile*')">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 7h-9a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v3" />
                        <path d="M14 17v5" />
                        <path d="M20 17v5" />
                        <path d="M4 17v5" />
                        <path d="M10 17v5" />
                        <path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9" />
                    </svg>
                    <span>Profil Toko</span>
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
                <x-nav-link :href="route('settings.qris.edit')" :active="request()->routeIs('settings.*')">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><path d="M14 14h3v3M20 20h.01M17 20h-.01M20 17h.01" />
                    </svg>
                    <span>QRIS</span>
                </x-nav-link>
                <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <span>Pengguna</span>
                </x-nav-link>
            @endif
        </nav>

        <div data-sidebar-footer class="space-y-3 border-t border-white/10 px-5 py-4">
            <div data-sidebar-avatar aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-sm font-semibold">{{ mb_substr(auth()->user()?->name ?? 'U', 0, 1) }}</div>
            <div data-sidebar-user class="min-w-0">
                <p class="break-words text-sm font-medium text-white">{{ auth()->user()?->name }}</p>
                <p class="break-all text-xs leading-5 text-slate-400">{{ auth()->user()?->email }}</p>
                <p class="mt-1 text-xs text-emerald-300">{{ auth()->user()?->role?->value ?? auth()->user()?->role }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit" aria-label="Keluar"
                    class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-white/15 px-3.5 py-2 text-sm font-medium text-slate-200 transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 5H4v14h5M13 8l4 4-4 4M8 12h13" /></svg><span data-sidebar-logout-label>Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <div id="app-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button
                type="button"
                id="app-sidebar-toggle"
                class="grid h-10 w-10 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50"
                aria-label="Buka menu navigasi" aria-controls="app-sidebar" aria-expanded="false"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
            <h1 class="min-w-0 flex-1 truncate text-base font-semibold text-slate-900">@yield('title', 'Kasir')</h1>
            <div class="ml-auto flex min-w-0 items-center gap-2">
                <x-store-logo class="h-8 w-8 md:hidden" />
                <span class="hidden max-w-40 truncate text-sm font-medium md:block">{{ \App\Http\Controllers\SettingController::storeName() }}</span>
                <x-store-status />
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            <x-flash />
            @yield('content')
        </main>
    </div>
</div>

<x-store-status-dialog />
@stack('scripts')
</body>
</html>
