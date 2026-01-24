<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    public function definition(): array
    {
        $statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

        return [
            'order_id' => Order::factory(),
            'status' => fake()->randomElement($statuses),
            'changed_by' => User::query()->value('id'),
        ];
    }
}
