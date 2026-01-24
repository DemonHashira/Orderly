<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $types = ['adjustment', 'sale', 'return', 'damage', 'restock', 'transfer'];
        $type = fake()->randomElement($types);

        $qtyDelta = match ($type) {
            'sale', 'damage' => -1 * fake()->numberBetween(1, 10),
            'return', 'restock' => fake()->numberBetween(1, 50),
            'adjustment' => fake()->numberBetween(-20, 20),
            'transfer' => fake()->randomElement([-5, 5, -10, 10]),
        };

        return [
            'organization_id' => Organization::query()->value('id') ?? Organization::factory(),
            'product_id' => Product::factory(),
            'performed_by_user_id' => User::query()->value('id'),
            'type' => $type,
            'qty_delta' => $qtyDelta,
            'reason' => fake()->optional(0.6)->sentence(),
            'reference_type' => fake()->optional(0.3)->randomElement(['Order', 'Return', 'Transfer']),
            'reference_id' => fake()->optional(0.3)->numberBetween(1, 100),
        ];
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sale',
            'qty_delta' => -1 * fake()->numberBetween(1, 10),
            'reason' => 'Sold to customer',
        ]);
    }

    public function restock(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'restock',
            'qty_delta' => fake()->numberBetween(10, 100),
            'reason' => 'Received from supplier',
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjustment',
            'qty_delta' => fake()->numberBetween(-10, 10),
            'reason' => 'Inventory count adjustment',
        ]);
    }
}
