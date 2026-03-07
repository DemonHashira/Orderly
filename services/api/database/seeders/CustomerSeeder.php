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

        $maleFirstNames = [
            'Georgi', 'Ivan', 'Radoslav', 'Svetoslav', 'Dimitar', 'Nikolay', 'Viktor', 'Hristo',
            'Yordan', 'Teodor', 'Borislav', 'Plamen', 'Tsvetan', 'Stanislav', 'Atanas', 'Valentin',
            'Petar', 'Kiril', 'Lyubomir', 'Milen',
        ];
        $femaleFirstNames = [
            'Mariya', 'Elena', 'Petya', 'Nadezhda', 'Katerina', 'Aleksandra', 'Daniela', 'Desislava',
            'Kristina', 'Milena', 'Radina', 'Monika', 'Yoana', 'Simona', 'Veronika', 'Kalina',
            'Gergana', 'Diana', 'Violeta', 'Yana',
        ];
        $maleMiddleNames = [
            'Petrov', 'Georgiev', 'Ivanov', 'Dimitrov', 'Nikolov', 'Hristov', 'Atanasov', 'Todorov',
            'Iliev', 'Kostov', 'Mihaylov', 'Radev',
        ];
        $femaleMiddleNames = [
            'Petrova', 'Georgieva', 'Ivanova', 'Dimitrova', 'Nikolova', 'Hristova', 'Atanasova', 'Todorova',
            'Ilieva', 'Kostova', 'Mihaylova', 'Radeva',
        ];
        $maleLastNames = [
            'Stoyanov', 'Georgiev', 'Iliev', 'Atanasov', 'Petkov', 'Angelov', 'Ivanov', 'Nikolov',
            'Mihaylov', 'Yanev', 'Borisov', 'Rangelov', 'Velikov', 'Kolev', 'Dimitrov', 'Vasilev',
        ];
        $femaleLastNames = [
            'Petrova', 'Dimitrova', 'Koleva', 'Marinova', 'Ruseva', 'Todorova', 'Hadzhieva', 'Krasteva',
            'Stefanova', 'Popova', 'Georgieva', 'Ilieva', 'Atanasova', 'Petkova', 'Angelova', 'Vasileva',
        ];

        for ($index = 1; $index <= 80; $index++) {
            $isMale = $index % 2 === 1;
            $firstNamePool = $isMale ? $maleFirstNames : $femaleFirstNames;
            $middleNamePool = $isMale ? $maleMiddleNames : $femaleMiddleNames;
            $lastNamePool = $isMale ? $maleLastNames : $femaleLastNames;

            $firstName = $firstNamePool[($index - 1) % count($firstNamePool)];
            $lastName = $lastNamePool[($index - 1) % count($lastNamePool)];
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
                    'middle_name' => $hasMiddleName ? $middleNamePool[($index - 1) % count($middleNamePool)] : null,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'email' => $email,
                    'notes' => fake()->boolean(25) ? 'Prefers anime releases and manga bundles.' : null,
                ],
            );
        }
    }
}
