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
        $org = Organization::query()->where('slug', 'otaku-corner')->firstOrFail();

        $users = [
            [
                'name' => 'Viktor Logodazhki',
                'email' => 'vlogodazhki@otakucorner.test',
                'role' => 'Administrator',
            ],
            [
                'name' => 'Kiril Hadzhiyski',
                'email' => 'khadzhiyski@otakucorner.test',
                'role' => 'Order Manager',
            ],
            [
                'name' => 'Nikolay Pugyov',
                'email' => 'npugyov@otakucorner.test',
                'role' => 'Warehouse Manager',
            ],
            [
                'name' => 'Aleksandar Ivanov',
                'email' => 'aivanov@otakucorner.test',
                'role' => 'Inventory Controller',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'organization_id' => $org->id,
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$userData['role']]);
        }
    }
}
