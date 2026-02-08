<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::query()->value('id') ?? Organization::factory(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.2)->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('+359#########'),
            'email' => fake()->safeEmail(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
