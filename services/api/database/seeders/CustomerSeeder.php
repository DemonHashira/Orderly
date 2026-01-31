<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-store')->firstOrFail();

        $customers = [
            ['full_name' => 'Mariya Petrova', 'phone' => '+359888120301', 'email' => 'mpetrova@example.com'],
            ['full_name' => 'Georgi Stoyanov', 'phone' => '+359887210456', 'email' => 'gstoyanov@example.com'],
            ['full_name' => 'Elena Dimitrova', 'phone' => '+359889334455', 'email' => 'edimitrova@example.com'],
            ['full_name' => 'Ivan Georgiev', 'phone' => '+359877990011', 'email' => 'igeorgiev@example.com'],
            ['full_name' => 'Petya Koleva', 'phone' => '+359889004422', 'email' => 'pkoleva@example.com'],
            ['full_name' => 'Radoslav Iliev', 'phone' => '+359888777333', 'email' => 'riliev@example.com'],
            ['full_name' => 'Nadezhda Marinova', 'phone' => '+359887111222', 'email' => 'nmarinova@example.com'],
            ['full_name' => 'Svetoslav Atanasov', 'phone' => '+359885331144', 'email' => 'satanasov@example.com'],
            ['full_name' => 'Dimitar Petkov', 'phone' => '+359887444888', 'email' => 'dpetkov@example.com'],
            ['full_name' => 'Katerina Ruseva', 'phone' => '+359889777111', 'email' => 'kruseva@example.com'],
            ['full_name' => 'Nikolay Angelov', 'phone' => '+359888330099', 'email' => 'nangelov@example.com'],
            ['full_name' => 'Aleksandra Todorova', 'phone' => '+359887880066', 'email' => 'atodorova@example.com'],
            ['full_name' => 'Viktor Ivanov', 'phone' => '+359888920155', 'email' => 'vivanov@example.com'],
            ['full_name' => 'Daniela Ivanova', 'phone' => '+359886991122', 'email' => 'divanova@example.com'],
            ['full_name' => 'Hristo Nikolov', 'phone' => '+359888559977', 'email' => 'hnikolov@example.com'],
            ['full_name' => 'Yordan Mihaylov', 'phone' => '+359887222333', 'email' => 'ymihaylov@example.com'],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'phone' => $customer['phone'],
                ],
                [
                    'organization_id' => $org->id,
                    'full_name' => $customer['full_name'],
                    'phone' => $customer['phone'],
                    'email' => $customer['email'],
                    'notes' => fake()->boolean(25) ? 'Prefers anime releases and manga bundles.' : null,
                ],
            );
        }
    }
}
