@extends('layouts.app')

@section('title', 'Topping')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Daftar Topping</h2>
            <p class="text-sm text-slate-500">Tambahan opsional yang bisa dipilih pembeli saat memesan.</p>
        </div>
        <a href="{{ route('toppings.create') }}" class="btn-primary">+ Tambah Topping</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th">Nama Topping</th>
                        <th class="th">Harga</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($toppings as $topping)
                        <tr class="hover:bg-slate-50/70">
                            <td class="td font-medium text-slate-900">{{ $topping->name }}</td>
                            <td class="td text-slate-600"><x-money :value="$topping->price" /></td>
                            <td class="td">
                                @if ($topping->is_available)
                                    <span class="badge-green">Tersedia</span>
                                @else
                                    <span class="badge-slate">Habis</span>
                                @endif
                            </td>
                            <td class="td">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('toppings.edit', $topping) }}" class="btn-ghost">Ubah</a>
                                    <form
                                        method="POST"
                                        action="{{ route('toppings.destroy', $topping) }}"
                                        onsubmit="return confirm('Hapus topping ini? Riwayat pesanan lama tetap tersimpan.')"
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
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">
                                Belum ada topping. Tambahkan topping pertama Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
