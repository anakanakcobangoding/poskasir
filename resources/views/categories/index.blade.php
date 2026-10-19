@extends('layouts.app')

@section('title', 'Kategori')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Kategori Menu</h2>
            <p class="text-sm text-slate-500">Kelompokkan menu agar mudah dicari saat melayani pembeli.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn-primary">+ Tambah Kategori</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th">Nama</th>
                        <th class="th">Urutan</th>
                        <th class="th">Jumlah Produk</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $category)
                        <tr class="hover:bg-slate-50/70">
                            <td class="td font-medium text-slate-900">{{ $category->name }}</td>
                            <td class="td text-slate-600">{{ $category->sort_order }}</td>
                            <td class="td text-slate-600">{{ $category->products_count }} produk</td>
                            <td class="td">
                                @if ($category->is_active)
                                    <span class="badge-green">Aktif</span>
                                @else
                                    <span class="badge-slate">Nonaktif</span>
                                @endif
                            </td>
                            <td class="td">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('categories.edit', $category) }}" class="btn-ghost">Ubah</a>
                                    <form
                                        method="POST"
                                        action="{{ route('categories.destroy', $category) }}"
                                        onsubmit="return confirm('Hapus kategori ini? Pastikan tidak ada produk di dalamnya.')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">
                                Belum ada kategori. Tambahkan kategori pertama Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
