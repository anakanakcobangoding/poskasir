<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.form');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        $category->loadCount('products');

        return view('categories.form', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori "'.$category->name.'" masih berisi '.$category->products()->count().' produk dan tidak dapat dihapus. Pindahkan atau hapus produknya terlebih dahulu.');
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return back()->with('error', 'Kategori "'.$category->name.'" tidak dapat dihapus karena masih terkait data lain.');
        }

        return redirect()->route('categories.index')->with('success', 'Kategori "'.$category->name.'" berhasil dihapus.');
    }
}
