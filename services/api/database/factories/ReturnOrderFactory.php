<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\ReturnOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReturnOrderFactory extends Factory
{
    protected $model = ReturnOrder::class;

    public function definition(): array
    {
        $reasons = [
            'Defective product',
            'Wrong item received',
            'Changed mind',
            'Size/fit issues',
            'Product damaged during shipping',
            'Not as described',
            'Quality not as expected',
        ];

        return [
            'order_id' => Order::factory(),
            'reason' => fake()->randomElement($reasons),
            'returned_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => now()->subDays(rand(1, 10)),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => null,
        ]);
    }
}
