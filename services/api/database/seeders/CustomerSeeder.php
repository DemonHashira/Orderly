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
            ['first_name' => 'Mariya', 'last_name' => 'Petrova', 'phone' => '+359888120301', 'email' => 'mpetrova@example.com'],
            ['first_name' => 'Georgi', 'last_name' => 'Stoyanov', 'phone' => '+359887210456', 'email' => 'gstoyanov@example.com'],
            ['first_name' => 'Elena', 'last_name' => 'Dimitrova', 'phone' => '+359889334455', 'email' => 'edimitrova@example.com'],
            ['first_name' => 'Ivan', 'last_name' => 'Georgiev', 'phone' => '+359877990011', 'email' => 'igeorgiev@example.com'],
            ['first_name' => 'Petya', 'last_name' => 'Koleva', 'phone' => '+359889004422', 'email' => 'pkoleva@example.com'],
            ['first_name' => 'Radoslav', 'last_name' => 'Iliev', 'phone' => '+359888777333', 'email' => 'riliev@example.com'],
            ['first_name' => 'Nadezhda', 'last_name' => 'Marinova', 'phone' => '+359887111222', 'email' => 'nmarinova@example.com'],
            ['first_name' => 'Svetoslav', 'middle_name' => 'Petrov', 'last_name' => 'Atanasov', 'phone' => '+359885331144', 'email' => 'satanasov@example.com'],
            ['first_name' => 'Dimitar', 'last_name' => 'Petkov', 'phone' => '+359887444888', 'email' => 'dpetkov@example.com'],
            ['first_name' => 'Katerina', 'last_name' => 'Ruseva', 'phone' => '+359889777111', 'email' => 'kruseva@example.com'],
            ['first_name' => 'Nikolay', 'last_name' => 'Angelov', 'phone' => '+359888330099', 'email' => 'nangelov@example.com'],
            ['first_name' => 'Aleksandra', 'last_name' => 'Todorova', 'phone' => '+359887880066', 'email' => 'atodorova@example.com'],
            ['first_name' => 'Viktor', 'middle_name' => 'Nikolov', 'last_name' => 'Ivanov', 'phone' => '+359888920155', 'email' => 'vivanov@example.com'],
            ['first_name' => 'Daniela', 'last_name' => 'Ivanova', 'phone' => '+359886991122', 'email' => 'divanova@example.com'],
            ['first_name' => 'Hristo', 'last_name' => 'Nikolov', 'phone' => '+359888559977', 'email' => 'hnikolov@example.com'],
            ['first_name' => 'Yordan', 'last_name' => 'Mihaylov', 'phone' => '+359887222333', 'email' => 'ymihaylov@example.com'],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'phone' => $customer['phone'],
                ],
                [
                    'organization_id' => $org->id,
                    'first_name' => $customer['first_name'],
                    'middle_name' => $customer['middle_name'] ?? null,
                    'last_name' => $customer['last_name'],
                    'phone' => $customer['phone'],
                    'email' => $customer['email'],
                    'notes' => fake()->boolean(25) ? 'Prefers anime releases and manga bundles.' : null,
                ],
            );
        }
    }
}
