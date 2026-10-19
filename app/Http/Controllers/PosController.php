<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topping;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        $categories = Category::active()
            ->with(['products' => fn ($query) => $query->available()->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        $toppings = Topping::where('is_available', true)->orderBy('name')->get();

        $posToppings = $toppings->map(fn (Topping $topping): array => [
            'id' => $topping->id,
            'name' => $topping->name,
            'price' => (float) $topping->price,
        ])->values();

        return view('pos.index', compact('categories', 'toppings', 'posToppings'));
    }
}
