<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Organization;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

        return [
            'organization_id' => Organization::query()->value('id') ?? Organization::factory(),
            'customer_id' => Customer::factory(),
            'sales_channel_id' => SalesChannel::query()->value('id') ?? SalesChannel::factory(),
            'created_by' => User::query()->value('id') ?? User::factory(),
            'reference' => 'ORD-'.strtoupper(fake()->bothify('??####')),
            'total_amount' => 0, // Will be calculated from items
            'current_status' => fake()->randomElement($statuses),
            'internal_notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'pending',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'confirmed',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'processing',
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'shipped',
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'delivered',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'cancelled',
        ]);
    }
}
