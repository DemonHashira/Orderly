<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::query()->value('id'),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->bothify('LN-###')),
            'description' => $this->faker->sentence(),
            'sale_price' => $this->faker->randomFloat(2, 5, 50),
            'is_active' => true,
        ];
    }
}
