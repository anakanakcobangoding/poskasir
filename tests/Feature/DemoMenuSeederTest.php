<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Topping;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoMenuSeeder;

test('seeds the demo menu for a mi ayam and bakso stall', function () {
    $this->seed(DemoMenuSeeder::class);

    expect(Category::count())->toBe(4)
        ->and(Product::count())->toBe(17)
        ->and(Topping::count())->toBe(7)
        ->and(Category::orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Mi Ayam', 'Bakso', 'Cemilan', 'Minuman'])
        ->and(Product::where('name', 'Mi Ayam Original')->first()?->price)->toBe('15000.00')
        ->and(Product::where('name', 'Bakso Urat Spesial')->first()?->price)->toBe('18000.00')
        ->and(Topping::where('name', 'Telur Rebus')->first()?->price)->toBe('4000.00');
});

test('is safe to run repeatedly without duplicating rows', function () {
    $this->seed(DemoMenuSeeder::class);
    $this->seed(DemoMenuSeeder::class);

    expect(Category::count())->toBe(4)
        ->and(Product::count())->toBe(17)
        ->and(Topping::count())->toBe(7);
});

test('keeps data that is not part of the demo menu', function () {
    $customCategory = Category::factory()->create(['name' => 'Kopi']);
    Product::factory()->for($customCategory)->create(['name' => 'Kopi Susu']);

    $this->seed(DemoMenuSeeder::class);

    expect(Category::where('name', 'Kopi')->exists())->toBeTrue()
        ->and(Product::where('name', 'Kopi Susu')->exists())->toBeTrue();
});

test('the database seeder does not create a default account in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse()
        ->and(Category::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(Topping::count())->toBe(0);
});

test('the database seeder creates demo data in the testing environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(Category::count())->toBe(4);
});

test('the demo menu seeder does not write data outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->seed(DemoMenuSeeder::class);

    expect(Category::count())->toBe(0)
        ->and(Product::count())->toBe(0)
        ->and(Topping::count())->toBe(0);
});

test('the demo menu seeder still works in the testing environment', function () {
    $this->seed(DemoMenuSeeder::class);

    expect(Category::count())->toBe(4)
        ->and(Product::count())->toBe(17)
        ->and(Topping::count())->toBe(7);
});
