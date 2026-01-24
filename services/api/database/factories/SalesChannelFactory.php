<?php

namespace Database\Factories;

use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesChannelFactory extends Factory
{
    protected $model = SalesChannel::class;

    public function definition(): array
    {
        $channels = [
            ['name' => 'Online Store', 'code' => 'online'],
            ['name' => 'Phone Order', 'code' => 'phone'],
            ['name' => 'In-Store', 'code' => 'store'],
            ['name' => 'Email Order', 'code' => 'email'],
            ['name' => 'WhatsApp', 'code' => 'whatsapp'],
        ];

        $channel = fake()->randomElement($channels);

        return [
            'name' => $channel['name'],
            'code' => $channel['code'].'-'.fake()->unique()->numberBetween(1, 999),
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Online Store',
            'code' => 'online',
        ]);
    }

    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Phone Order',
            'code' => 'phone',
        ]);
    }

    public function inStore(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'In-Store',
            'code' => 'store',
        ]);
    }
}
