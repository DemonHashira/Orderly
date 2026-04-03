<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Organization;
use Database\Seeders\Support\TenantSeedPresets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TenantSeedPresets::all() as $presetIndex => $preset) {
            $org = Organization::query()->where('slug', $preset['slug'])->firstOrFail();
            $names = $preset['customer_names'];

            fake()->seed((int) $preset['customer_seed']);

            for ($index = 1; $index <= 80; $index++) {
                $isMale = $index % 2 === 1;
                $firstNamePool = $isMale ? $names['male_first'] : $names['female_first'];
                $middleNamePool = $isMale ? $names['male_middle'] : $names['female_middle'];
                $lastNamePool = $isMale ? $names['male_last'] : $names['female_last'];

                $firstName = $firstNamePool[($index - 1) % count($firstNamePool)];
                $lastName = $lastNamePool[($index - 1) % count($lastNamePool)];
                $phone = sprintf('+35988%07d', (($presetIndex + 1) * 1000000) + $index);
                $email = Str::of($firstName.'.'.$lastName.'.'.$index)
                    ->lower()
                    ->ascii()
                    ->replace(' ', '.')
                    ->replace("'", '')
                    ->toString().'@'.$preset['customer_domain'];

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
                        'notes' => fake()->boolean(25) ? $preset['customer_note'] : null,
                    ],
                );
            }
        }
    }
}
