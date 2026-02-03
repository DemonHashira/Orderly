<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-store')->firstOrFail();

        $users = [
            [
                'first_name' => 'Viktor',
                'last_name' => 'Logodazhki',
                'email' => 'vlogodazhki@otakustore.test',
                'role' => 'Owner',
            ],
            [
                'first_name' => 'Kiril',
                'last_name' => 'Hadzhiyski',
                'email' => 'khadzhiyski@otakustore.test',
                'role' => 'Order Manager',
            ],
            [
                'first_name' => 'Nikolay',
                'last_name' => 'Pugyov',
                'email' => 'npugyov@otakustore.test',
                'role' => 'Logistics Manager',
            ],
            [
                'first_name' => 'Aleksandar',
                'last_name' => 'Ivanov',
                'email' => 'aivanov@otakustore.test',
                'role' => 'Inventory Manager',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'organization_id' => $org->id,
                    'first_name' => $userData['first_name'],
                    'middle_name' => $userData['middle_name'] ?? null,
                    'last_name' => $userData['last_name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
