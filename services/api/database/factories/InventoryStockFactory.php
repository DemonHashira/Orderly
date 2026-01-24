<?php

namespace Database\Factories;

use App\Models\InventoryStock;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryStockFactory extends Factory
{
    protected $model = InventoryStock::class;

    public function definition(): array
    {
        $qtyOnHand = fake()->numberBetween(0, 200);
        $qtyReserved = fake()->numberBetween(0, min(50, $qtyOnHand));

        return [
            'organization_id' => Organization::query()->value('id') ?? Organization::factory(),
            'product_id' => Product::factory(),
            'qty_on_hand' => $qtyOnHand,
            'qty_reserved' => $qtyReserved,
            'reorder_threshold' => fake()->optional(0.7)->numberBetween(10, 50),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'qty_on_hand' => fake()->numberBetween(0, 10),
            'qty_reserved' => 0,
            'reorder_threshold' => 20,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'qty_on_hand' => 0,
            'qty_reserved' => 0,
        ]);
    }

    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'qty_on_hand' => fake()->numberBetween(50, 200),
            'qty_reserved' => fake()->numberBetween(0, 20),
        ]);
    }
}
