@extends('layouts.app')

@section('title', 'Gambar QRIS')

@section('content')
    <div class="mb-5">
        <h2 class="text-lg font-semibold text-slate-900">Gambar QRIS</h2>
        <p class="text-sm text-slate-500">Unggah gambar QRIS milik usaha Anda. Gambar ini hanya ditampilkan kepada kasir sebagai petunjuk pembayaran, bukan konfirmasi otomatis.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <div class="card space-y-4 p-6">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Gambar saat ini</h3>
                <p class="text-xs text-slate-500">Dipakai oleh halaman kasir saat metode pembayaran QRIS dipilih.</p>
            </div>

            @if ($qrisImageUrl)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <img
                        src="{{ $qrisImageUrl }}"
                        alt="Gambar QRIS yang sedang digunakan"
                        class="mx-auto w-full max-w-xs rounded-lg bg-white p-2 shadow-sm"
                    >
                </div>
                <p class="break-all text-xs text-slate-400">{{ $qrisImagePath }}</p>
            @else
                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center">
                    <p class="text-sm font-medium text-slate-600">Belum ada gambar QRIS</p>
                    <p class="mt-1 text-xs text-slate-500">Kasir akan melihat petunjuk bahwa gambar QRIS belum diatur.</p>
                </div>
            @endif
        </div>

        <div class="card space-y-5 p-6">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Unggah gambar baru</h3>
                <p class="text-xs text-slate-500">Format JPG, JPEG, PNG, atau WEBP. Ukuran maksimal 2 MB.</p>
            </div>

            <form
                method="POST"
                action="{{ route('settings.qris.update') }}"
                enctype="multipart/form-data"
                class="space-y-4"
            >
                @csrf
                @method('PUT')

                <div>
                    <label for="qris_image" class="label">File gambar QRIS</label>
                    <input
                        id="qris_image"
                        type="file"
                        name="qris_image"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full cursor-pointer rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                        required
                    >
                    @error('qris_image')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Gambar lama baru akan dihapus setelah gambar baru berhasil disimpan.
                </p>

                <button type="submit" class="btn-primary">Unggah dan Simpan</button>
            </form>
        </div>
    </div>
@endsection
