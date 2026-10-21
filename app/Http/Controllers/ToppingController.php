<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreToppingRequest;
use App\Http\Requests\UpdateToppingRequest;
use App\Models\Topping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ToppingController extends Controller
{
    public function index(): View
    {
        $toppings = Topping::orderBy('name')->get();

        return view('toppings.index', compact('toppings'));
    }

    public function create(): View
    {
        return view('toppings.form');
    }

    public function store(StoreToppingRequest $request): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['image']);
        $data['image'] = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;
        Topping::create($data);

        return redirect()->route('toppings.index')->with('success', 'Topping berhasil ditambahkan.');
    }

    public function edit(Topping $topping): View
    {
        return view('toppings.form', compact('topping'));
    }

    public function update(UpdateToppingRequest $request, Topping $topping): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['image', 'remove_image']);
        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'));
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        }
        $topping->update($data);

        return redirect()->route('toppings.index')->with('success', 'Topping berhasil diperbarui.');
    }

    public function destroy(Topping $topping): RedirectResponse
    {
        $topping->delete();

        return redirect()->route('toppings.index')->with('success', 'Topping "'.$topping->name.'" berhasil dihapus. Riwayat pesanan tetap utuh.');
    }

    public function image(Topping $topping): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($topping->image && $disk->exists($topping->image), 404);

        return $disk->response($topping->image, headers: ['Cache-Control' => 'private, no-store']);
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('toppings', 'public');
        if ($path === false) {
            Log::error('Topping image could not be stored.', ['disk' => 'public']);
            throw ValidationException::withMessages(['image' => 'Gambar topping gagal disimpan. Periksa storage lalu coba lagi.']);
        }

        return $path;
    }
}
