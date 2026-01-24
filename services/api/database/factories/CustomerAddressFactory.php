<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        $cities = ['Sofia', 'Plovdiv', 'Varna', 'Burgas', 'Ruse', 'Stara Zagora', 'Pleven', 'Blagoevgrad'];

        return [
            'customer_id' => Customer::factory(),
            'label' => fake()->optional(0.7)->randomElement(['Home', 'Office', 'Work', 'Other']),
            'country' => 'Bulgaria',
            'city' => fake()->randomElement($cities),
            'postal_code' => fake()->numerify('####'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->boolean(30) ? fake()->numerify('Apt ###') : null,
            'is_default' => fake()->boolean(30),
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
