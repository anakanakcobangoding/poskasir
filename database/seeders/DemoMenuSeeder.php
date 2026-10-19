<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Topping;
use Illuminate\Database\Seeder;

class DemoMenuSeeder extends Seeder
{
    /**
     * Seed the demo menu for a mi ayam and bakso stall.
     *
     * Safe to run repeatedly: existing rows are updated in place and nothing is deleted.
     *
     * @var array<int, array{name: string, sort_order: int, products: array<int, array{name: string, price: float}>}>
     */
    private array $categories = [
        [
            'name' => 'Mi Ayam',
            'sort_order' => 1,
            'products' => [
                ['name' => 'Mi Ayam Original', 'price' => 15000],
                ['name' => 'Mi Ayam Bakso', 'price' => 17000],
                ['name' => 'Mi Ayam Ceker', 'price' => 18000],
                ['name' => 'Mi Ayam Yamin Manis', 'price' => 16000],
                ['name' => 'Mi Yamin Goreng', 'price' => 16000],
            ],
        ],
        [
            'name' => 'Bakso',
            'sort_order' => 2,
            'products' => [
                ['name' => 'Bakso Urat Spesial', 'price' => 18000],
                ['name' => 'Bakso Campur', 'price' => 17000],
                ['name' => 'Bakso Mercon', 'price' => 18000],
                ['name' => 'Bakso Kuah', 'price' => 15000],
                ['name' => 'Bakso Bakwan', 'price' => 16000],
            ],
        ],
        [
            'name' => 'Cemilan',
            'sort_order' => 3,
            'products' => [
                ['name' => 'Pangsit Goreng (6 pcs)', 'price' => 8000],
                ['name' => 'Kerupuk Pangsit', 'price' => 3000],
                ['name' => 'Tahu Goreng (3 pcs)', 'price' => 5000],
            ],
        ],
        [
            'name' => 'Minuman',
            'sort_order' => 4,
            'products' => [
                ['name' => 'Es Teh Manis', 'price' => 4000],
                ['name' => 'Es Jeruk', 'price' => 5000],
                ['name' => 'Teh Tawar Hangat', 'price' => 3000],
                ['name' => 'Air Mineral', 'price' => 4000],
            ],
        ],
    ];

    /**
     * @var array<int, array{name: string, price: float}>
     */
    private array $toppings = [
        ['name' => 'Bakso Extra (2 biji)', 'price' => 5000],
        ['name' => 'Ceker Extra', 'price' => 4000],
        ['name' => 'Pangsit (3 pcs)', 'price' => 3000],
        ['name' => 'Telur Rebus', 'price' => 4000],
        ['name' => 'Sawi Hijau', 'price' => 2000],
        ['name' => 'Mie Extra', 'price' => 3000],
        ['name' => 'Kerupuk', 'price' => 2000],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->categories as $categoryData) {
            $category = Category::updateOrCreate(
                ['name' => $categoryData['name']],
                ['sort_order' => $categoryData['sort_order'], 'is_active' => true],
            );

            foreach ($categoryData['products'] as $productData) {
                $category->products()->updateOrCreate(
                    ['name' => $productData['name']],
                    ['price' => $productData['price'], 'is_available' => true],
                );
            }
        }

        foreach ($this->toppings as $toppingData) {
            Topping::updateOrCreate(
                ['name' => $toppingData['name']],
                ['price' => $toppingData['price'], 'is_available' => true],
            );
        }
    }
}
