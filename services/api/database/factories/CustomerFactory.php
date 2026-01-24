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
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('+359#########'),
            'email' => fake()->optional(0.8)->safeEmail(),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
