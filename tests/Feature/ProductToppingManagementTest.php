<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Topping;

beforeEach(function () {
    actingAsUser(['role' => UserRole::Admin]);
});

test('a product can be created with several toppings', function () {
    $category = Category::factory()->create();
    $toppingA = Topping::factory()->create(['name' => 'Telur Rebus']);
    $toppingB = Topping::factory()->create(['name' => 'Ceker Extra']);

    $this->post(route('products.store'), [
        'category_id' => (string) $category->id,
        'name' => 'Mi Ayam Spesial',
        'price' => '15000',
        'is_available' => '1',
        'topping_ids' => [(string) $toppingA->id, (string) $toppingB->id],
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Mi Ayam Spesial')->sole();

    expect($product->toppings()->orderBy('toppings.id')->pluck('toppings.id')->all())
        ->toBe([$toppingA->id, $toppingB->id]);
});

test('editing one product does not change the toppings of another product', function () {
    $category = Category::factory()->create();
    $toppingA = Topping::factory()->create();
    $toppingB = Topping::factory()->create();

    $productA = Product::factory()->for($category)->create();
    $productB = Product::factory()->for($category)->create();

    $productA->toppings()->attach($toppingA->id);
    $productB->toppings()->attach($toppingB->id);

    $this->put(route('products.update', $productA), [
        'category_id' => (string) $category->id,
        'name' => 'Produk A Diubah',
        'price' => '12000',
        'is_available' => '1',
        'topping_ids' => [(string) $toppingB->id],
    ])->assertRedirect(route('products.index'));

    expect($productA->refresh()->toppings()->pluck('toppings.id')->all())->toBe([$toppingB->id])
        ->and($productB->refresh()->toppings()->pluck('toppings.id')->all())->toBe([$toppingB->id]);
});

test('reducing the selection removes only the unchecked links for that product', function () {
    $category = Category::factory()->create();
    $keep = Topping::factory()->create();
    $drop = Topping::factory()->create();

    $product = Product::factory()->for($category)->create();
    $other = Product::factory()->for($category)->create();

    $product->toppings()->attach([$keep->id, $drop->id]);
    $other->toppings()->attach([$keep->id, $drop->id]);

    $this->put(route('products.update', $product), [
        'category_id' => (string) $category->id,
        'name' => $product->name,
        'price' => '12000',
        'is_available' => '1',
        'topping_ids' => [(string) $keep->id],
    ])->assertRedirect(route('products.index'));

    expect($product->refresh()->toppings()->pluck('toppings.id')->all())->toBe([$keep->id])
        ->and($other->refresh()->toppings()->orderBy('toppings.id')->pluck('toppings.id')->all())
        ->toBe([$keep->id, $drop->id]);
});

test('clearing the selection removes every link for that product without touching others', function () {
    $category = Category::factory()->create();
    $topping = Topping::factory()->create();

    $product = Product::factory()->for($category)->create();
    $other = Product::factory()->for($category)->create();

    $product->toppings()->attach($topping->id);
    $other->toppings()->attach($topping->id);

    $this->put(route('products.update', $product), [
        'category_id' => (string) $category->id,
        'name' => $product->name,
        'price' => '12000',
        'is_available' => '1',
        'topping_ids' => '',
    ])->assertRedirect(route('products.index'));

    expect($product->refresh()->toppings()->count())->toBe(0)
        ->and($other->refresh()->toppings()->pluck('toppings.id')->all())->toBe([$topping->id]);
});

test('an invalid topping id is rejected and the product is not created', function () {
    $category = Category::factory()->create();

    $this->post(route('products.store'), [
        'category_id' => (string) $category->id,
        'name' => 'Mi Ayam Spesial',
        'price' => '15000',
        'is_available' => '1',
        'topping_ids' => ['9999'],
    ])->assertSessionHasErrors('topping_ids.0');

    $this->assertDatabaseCount('products', 0);
});

test('an invalid topping id is rejected and the product is not changed', function () {
    $category = Category::factory()->create();
    $topping = Topping::factory()->create();

    $product = Product::factory()->for($category)->create(['name' => 'Mi Ayam']);
    $product->toppings()->attach($topping->id);

    $this->put(route('products.update', $product), [
        'category_id' => (string) $category->id,
        'name' => 'Mi Ayam Diubah',
        'price' => '12000',
        'is_available' => '1',
        'topping_ids' => ['9999'],
    ])->assertSessionHasErrors('topping_ids.0');

    $product->refresh();

    expect($product->name)->toBe('Mi Ayam')
        ->and($product->toppings()->pluck('toppings.id')->all())->toBe([$topping->id]);
});

test('the edit form pre-checks the toppings already linked to the product', function () {
    $topping = Topping::factory()->create(['name' => 'Telur Rebus']);

    $product = Product::factory()->create(['name' => 'Mi Ayam']);
    $product->toppings()->attach($topping->id);

    $this->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('topping_ids[]', false)
        ->assertSee('value="'.$topping->id.'"', false)
        ->assertSee('checked', false);
});

test('the create and edit forms list every available topping', function () {
    $toppingA = Topping::factory()->create(['name' => 'Telur Rebus']);
    $toppingB = Topping::factory()->create(['name' => 'Ceker Extra']);
    $product = Product::factory()->create();

    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('Telur Rebus')
        ->assertSee('Ceker Extra');

    $this->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('Telur Rebus')
        ->assertSee('Ceker Extra');
});

test('a product without toppings can still be created and ordered by the cashier', function () {
    $category = Category::factory()->create();

    $this->post(route('products.store'), [
        'category_id' => (string) $category->id,
        'name' => 'Es Teh Polos',
        'price' => '5000',
        'is_available' => '1',
    ])->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Es Teh Polos')->sole();

    expect($product->toppings()->count())->toBe(0);

    $this->postJson(route('orders.store'), [
        'type' => 'TA',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertCreated();

    expect(Order::sole()->total)->toBe('10000.00');
});
