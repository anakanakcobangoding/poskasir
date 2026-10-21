<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Topping;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosController extends Controller
{
    public function index(): View
    {
        $categories = Category::active()
            ->with([
                'products' => fn ($query) => $query
                    ->available()
                    ->with(['toppings' => fn ($query) => $query->where('is_available', true)->orderBy('name')])
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->get();

        $toppings = Topping::where('is_available', true)->orderBy('name')->get();

        $posToppings = $toppings->map(fn (Topping $topping): array => [
            'id' => $topping->id,
            'name' => $topping->name,
            'price' => (float) $topping->price,
            'image_url' => $topping->image ? route('toppings.image', $topping, absolute: false) : null,
        ])->values();

        $posProductToppings = $categories
            ->flatMap->products
            ->mapWithKeys(fn (Product $product): array => [
                (string) $product->id => $product->toppings
                    ->map(fn (Topping $topping): int => $topping->id)
                    ->values()
                    ->all(),
            ]);

        $posProductImages = $categories
            ->flatMap->products
            ->mapWithKeys(fn (Product $product): array => [
                (string) $product->id => $product->image ? route('products.image', $product, absolute: false) : null,
            ]);

        $posQrisImageUrl = $this->qrisImageUrl();

        return view('pos.index', compact('categories', 'toppings', 'posToppings', 'posProductToppings', 'posProductImages', 'posQrisImageUrl'));
    }

    private function qrisImageUrl(): ?string
    {
        $path = Setting::where('key', SettingController::QRIS_IMAGE_KEY)->value('value');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $disk = Storage::disk(SettingController::QRIS_IMAGE_DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        return route('pos.qris-image', absolute: false);
    }

    public function qrisImage(): StreamedResponse
    {
        $path = Setting::where('key', SettingController::QRIS_IMAGE_KEY)->value('value');
        $disk = Storage::disk(SettingController::QRIS_IMAGE_DISK);

        abort_unless(is_string($path) && $path !== '' && $disk->exists($path), 404);

        return $disk->response($path, headers: ['Cache-Control' => 'private, no-store']);
    }
}
