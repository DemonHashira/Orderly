<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-store')->firstOrFail();

        fake()->seed(20260304);

        $firstNames = [
            'Mariya', 'Georgi', 'Elena', 'Ivan', 'Petya', 'Radoslav', 'Nadezhda', 'Svetoslav',
            'Dimitar', 'Katerina', 'Nikolay', 'Aleksandra', 'Viktor', 'Daniela', 'Hristo', 'Yordan',
            'Desislava', 'Teodor', 'Kristina', 'Borislav', 'Milena', 'Plamen', 'Radina', 'Tsvetan',
            'Monika', 'Stanislav', 'Yoana', 'Atanas', 'Simona', 'Valentin', 'Veronika', 'Petar',
        ];
        $middleNames = [
            'Petrov', 'Georgiev', 'Ivanov', 'Dimitrov', 'Nikolov', 'Hristov', 'Atanasov', 'Todorov',
            'Iliev', 'Kostov', 'Mihaylov', 'Radev',
        ];
        $lastNames = [
            'Petrova', 'Stoyanov', 'Dimitrova', 'Georgiev', 'Koleva', 'Iliev', 'Marinova', 'Atanasov',
            'Petkov', 'Ruseva', 'Angelov', 'Todorova', 'Ivanov', 'Nikolov', 'Mihaylov', 'Vasileva',
            'Hadzhieva', 'Yanev', 'Krasteva', 'Borisov', 'Stefanova', 'Rangelov', 'Popova', 'Velikov',
        ];

        for ($index = 1; $index <= 80; $index++) {
            $firstName = $firstNames[($index - 1) % count($firstNames)];
            $lastName = $lastNames[($index - 1) % count($lastNames)];
            $phone = sprintf('+35988%07d', 1000000 + $index);
            $email = Str::of($firstName.'.'.$lastName.'.'.$index)
                ->lower()
                ->ascii()
                ->replace(' ', '.')
                ->replace("'", '')
                ->toString().'@example.com';

            $hasMiddleName = $index % 5 === 0;

            Customer::query()->updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'phone' => $phone,
                ],
                [
                    'organization_id' => $org->id,
                    'first_name' => $firstName,
                    'middle_name' => $hasMiddleName ? $middleNames[($index - 1) % count($middleNames)] : null,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                    'notes' => fake()->boolean(25) ? 'Prefers anime releases and manga bundles.' : null,
                ],
            );
        }
    }
}
