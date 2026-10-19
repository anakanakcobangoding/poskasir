<?php

namespace App\Models;

use Database\Factories\ToppingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price', 'is_available'])]
class Topping extends Model
{
    /** @use HasFactory<ToppingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    /** @return HasMany<OrderItemTopping, $this> */
    public function orderItemToppings(): HasMany
    {
        return $this->hasMany(OrderItemTopping::class);
    }
}
