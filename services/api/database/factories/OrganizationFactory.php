<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $names = [
            'Otaku Corner',
            'Tech Haven',
            'Fashion Boutique',
            'Gadget World',
            'Book Paradise',
            'Home & Garden',
            'Sports Arena',
            'Beauty Shop',
        ];

        $name = fake()->randomElement($names);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999),
        ];
    }
}
