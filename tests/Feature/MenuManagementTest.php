<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;

test('category page lists categories with their product count', function () {
    $category = Category::factory()->create(['name' => 'Mi Ayam']);
    Product::factory()->count(2)->for($category)->create();

    $this->get(route('categories.index'))
        ->assertOk()
        ->assertSee('Mi Ayam')
        ->assertSee('2 produk');
});

test('a category can be created', function () {
    $this->post(route('categories.store'), [
        'name' => 'Bakso',
        'sort_order' => '2',
        'is_active' => '1',
    ])
        ->assertRedirect(route('categories.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('categories', [
        'name' => 'Bakso',
        'sort_order' => 2,
        'is_active' => true,
    ]);
});

test('a category requires a unique name and a sort order', function () {
    Category::factory()->create(['name' => 'Minuman']);

    $this->post(route('categories.store'), [
        'name' => 'Minuman',
        'sort_order' => '',
    ])->assertSessionHasErrors([
        'name' => 'Nama kategori sudah digunakan, pilih nama lain.',
        'sort_order' => 'Urutan wajib diisi.',
    ]);

    $this->assertDatabaseCount('categories', 1);
});

test('a category can be updated without duplicating its own name', function () {
    $category = Category::factory()->create(['name' => 'Minuman', 'sort_order' => 1]);

    $this->put(route('categories.update', $category), [
        'name' => 'Minuman Dingin',
        'sort_order' => '3',
        'is_active' => '0',
    ])->assertRedirect(route('categories.index'));

    $category->refresh();

    expect($category->name)->toBe('Minuman Dingin')
        ->and($category->sort_order)->toBe(3)
        ->and($category->is_active)->toBeFalse();
});

test('a category with products cannot be deleted', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    $this->delete(route('categories.destroy', $category))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->create();

    $this->delete(route('categories.destroy', $category))
        ->assertRedirect(route('categories.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a product can be created for a category', function () {
    $category = Category::factory()->create();

    $this->post(route('products.store'), [
        'category_id' => (string) $category->id,
        'name' => 'Mi Ayam Spesial',
        'price' => '15000',
        'is_available' => '1',
    ])->assertRedirect(route('products.index'));

    $this->assertDatabaseHas('products', [
        'category_id' => $category->id,
        'name' => 'Mi Ayam Spesial',
        'price' => 15000,
        'is_available' => true,
    ]);
});

test('a product requires a valid category and a numeric price', function () {
    $this->post(route('products.store'), [
        'category_id' => '999',
        'name' => '',
        'price' => 'seribu',
        'is_available' => '1',
    ])->assertSessionHasErrors([
        'category_id' => 'Kategori yang dipilih tidak ditemukan.',
        'name' => 'Nama produk wajib diisi.',
        'price' => 'Harga harus berupa angka.',
    ]);

    $this->assertDatabaseCount('products', 0);
});

test('a product can be updated', function () {
    $product = Product::factory()->create(['name' => 'Mi Ayam', 'price' => 12000]);
    $otherCategory = Category::factory()->create();

    $this->put(route('products.update', $product), [
        'category_id' => (string) $otherCategory->id,
        'name' => 'Mi Ayam Jumbo',
        'price' => '18000',
        'is_available' => '0',
    ])->assertRedirect(route('products.index'));

    $product->refresh();

    expect($product->name)->toBe('Mi Ayam Jumbo')
        ->and($product->price)->toBe('18000.00')
        ->and($product->category_id)->toBe($otherCategory->id)
        ->and($product->is_available)->toBeFalse();
});

test('a product can be deleted while order history stays intact', function () {
    $product = Product::factory()->create();

    $this->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('a topping can be created and updated', function () {
    $this->post(route('toppings.store'), [
        'name' => 'Telur Rebus',
        'price' => '3000',
        'is_available' => '1',
    ])->assertRedirect(route('toppings.index'));

    $topping = Topping::where('name', 'Telur Rebus')->sole();

    $this->put(route('toppings.update', $topping), [
        'name' => 'Telur Rebus Spesial',
        'price' => '3500',
        'is_available' => '0',
    ])->assertRedirect(route('toppings.index'));

    $topping->refresh();

    expect($topping->name)->toBe('Telur Rebus Spesial')
        ->and($topping->price)->toBe('3500.00')
        ->and($topping->is_available)->toBeFalse();
});

test('a topping requires a unique name and a numeric price', function () {
    Topping::factory()->create(['name' => 'Keju']);

    $this->post(route('toppings.store'), [
        'name' => 'Keju',
        'price' => 'gratis',
        'is_available' => '1',
    ])->assertSessionHasErrors([
        'name' => 'Nama topping sudah digunakan, pilih nama lain.',
        'price' => 'Harga harus berupa angka.',
    ]);

    $this->assertDatabaseCount('toppings', 1);
});

test('a topping can be deleted', function () {
    $topping = Topping::factory()->create();

    $this->delete(route('toppings.destroy', $topping))
        ->assertRedirect(route('toppings.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('toppings', ['id' => $topping->id]);
});
