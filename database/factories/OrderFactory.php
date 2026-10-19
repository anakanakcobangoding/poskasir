<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10000, 100000);

        return [
            'order_number' => fake()->unique()->numerify('WC-######'),
            'type' => OrderType::TA,
            'status' => OrderStatus::New,
            'customer_name' => fake()->optional()->name(),
            'table_number' => null,
            'delivery_address' => null,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'payment_method' => null,
            'amount_paid' => null,
            'change' => null,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function dineIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrderType::DI,
            'table_number' => fake()->randomElement(['A1', 'A2', 'B1', 'B2']),
        ]);
    }

    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrderType::DLV,
            'delivery_address' => fake()->address(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $amountPaid = ceil($attributes['total']);

            return [
                'payment_method' => PaymentMethod::Cash,
                'payment_status' => PaymentStatus::Paid,
                'amount_paid' => $amountPaid,
                'change' => $amountPaid - $attributes['total'],
                'paid_at' => now(),
            ];
        });
    }
}
