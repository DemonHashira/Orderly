<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $couriers = ['Speedy', 'Econt', 'DHL', 'UPS', 'Bulgarian Post', 'Rapido'];
        $shippedAt = fake()->optional(0.8)->dateTimeBetween('-30 days', 'now');
        $deliveredAt = $shippedAt ? fake()->optional(0.6)->dateTimeBetween($shippedAt, 'now') : null;

        return [
            'order_id' => Order::factory(),
            'courier' => fake()->randomElement($couriers),
            'tracking_number' => fake()->optional(0.9)->bothify('??########'),
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'shipped_at' => now()->subDays(rand(1, 5)),
            'delivered_at' => null,
        ]);
    }

    public function delivered(): static
    {
        $shippedAt = now()->subDays(rand(5, 15));

        return $this->state(fn (array $attributes) => [
            'shipped_at' => $shippedAt,
            'delivered_at' => $shippedAt->addDays(rand(2, 7)),
        ]);
    }
}
