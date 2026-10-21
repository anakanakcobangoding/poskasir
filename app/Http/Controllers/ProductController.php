<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::with('category')->orderBy('name')->get();

        return view('products.index', compact('products'));
    }

    public function create(): View
    {
        return view('products.form', $this->formData());
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $toppingIds = $validated['topping_ids'] ?? [];

        $imagePath = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;

        try {
            DB::transaction(function () use ($validated, $toppingIds, $imagePath): void {
                $product = Product::create(Arr::except($validated, ['topping_ids', 'image']) + ['image' => $imagePath]);
                $product->toppings()->sync($toppingIds);
            });
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return view('products.form', ['product' => $product->load('toppings')] + $this->formData());
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $toppingIds = $validated['topping_ids'] ?? [];

        $oldPath = $product->image;
        $newPath = $request->hasFile('image') ? $this->storeImage($request->file('image')) : null;
        $updateData = Arr::except($validated, ['topping_ids', 'image', 'remove_image']);

        if ($newPath !== null) {
            $updateData['image'] = $newPath;
        } elseif ($request->boolean('remove_image')) {
            $updateData['image'] = null;
        }

        try {
            DB::transaction(function () use ($product, $updateData, $toppingIds): void {
                $product->update($updateData);
                $product->toppings()->sync($toppingIds);
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath && $oldPath !== $product->image) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $imagePath = $product->image;
        $product->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('products.index')->with('success', 'Produk "'.$product->name.'" berhasil dihapus. Riwayat pesanan tetap utuh.');
    }

    public function image(Product $product): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($product->image && $disk->exists($product->image), 404);

        return $disk->response($product->image, headers: ['Cache-Control' => 'private, no-store']);
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('products', 'public');

        if ($path === false) {
            Log::error('Product image could not be stored.', ['disk' => 'public']);

            throw ValidationException::withMessages(['image' => 'Gambar produk gagal disimpan. Periksa storage lalu coba lagi.']);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'toppings' => Topping::orderBy('name')->get(),
        ];
    }
}
