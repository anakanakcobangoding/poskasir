<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreToppingRequest;
use App\Http\Requests\UpdateToppingRequest;
use App\Models\Topping;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
        Topping::create($request->validated());

        return redirect()->route('toppings.index')->with('success', 'Topping berhasil ditambahkan.');
    }

    public function edit(Topping $topping): View
    {
        return view('toppings.form', compact('topping'));
    }

    public function update(UpdateToppingRequest $request, Topping $topping): RedirectResponse
    {
        $topping->update($request->validated());

        return redirect()->route('toppings.index')->with('success', 'Topping berhasil diperbarui.');
    }

    public function destroy(Topping $topping): RedirectResponse
    {
        $topping->delete();

        return redirect()->route('toppings.index')->with('success', 'Topping "'.$topping->name.'" berhasil dihapus. Riwayat pesanan tetap utuh.');
    }
}
