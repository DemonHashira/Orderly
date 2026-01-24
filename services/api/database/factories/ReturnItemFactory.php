<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReturnItemFactory extends Factory
{
    protected $model = ReturnItem::class;

    public function definition(): array
    {
        return [
            'return_id' => ReturnOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'restockable' => fake()->boolean(80),
        ];
    }

    public function restockable(): static
    {
        return $this->state(fn (array $attributes) => [
            'restockable' => true,
        ]);
    }

    public function notRestockable(): static
    {
        return $this->state(fn (array $attributes) => [
            'restockable' => false,
        ]);
    }
}
