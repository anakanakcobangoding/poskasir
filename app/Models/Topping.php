<?php

namespace App\Models;

use Database\Factories\ToppingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price', 'hpp', 'image', 'is_available'])]
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
            'hpp' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    /** @return HasMany<OrderItemTopping, $this> */
    public function orderItemToppings(): HasMany
    {
        return $this->hasMany(OrderItemTopping::class);
    }

    /**
     * Products that allow this topping.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
