@extends('layouts.app')

@section('title', 'Produk')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Daftar Produk</h2>
            <p class="text-sm text-slate-500">Semua menu yang bisa dipesan pembeli beserta harganya.</p>
        </div>
        <a href="{{ route('products.create') }}" class="btn-primary">+ Tambah Produk</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th">Nama Produk</th>
                        <th class="th">Kategori</th>
                        <th class="th">Harga</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr class="hover:bg-slate-50/70">
                            <td class="td font-medium text-slate-900">{{ $product->name }}</td>
                            <td class="td">
                                @if ($product->category)
                                    {{ $product->category->name }}
                                @else
                                    <span class="text-slate-400">Tanpa kategori</span>
                                @endif
                            </td>
                            <td class="td text-slate-600"><x-money :value="$product->price" /></td>
                            <td class="td">
                                @if ($product->is_available)
                                    <span class="badge-green">Tersedia</span>
                                @else
                                    <span class="badge-slate">Habis</span>
                                @endif
                            </td>
                            <td class="td">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('products.edit', $product) }}" class="btn-ghost">Ubah</a>
                                    <form
                                        method="POST"
                                        action="{{ route('products.destroy', $product) }}"
                                        onsubmit="return confirm('Hapus produk ini? Riwayat pesanan lama tetap tersimpan.')"
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
                                Belum ada produk. Tambahkan produk pertama Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
