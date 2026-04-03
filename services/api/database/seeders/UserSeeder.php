<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\Support\TenantSeedPresets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TenantSeedPresets::all() as $preset) {
            $org = Organization::query()->where('slug', $preset['slug'])->firstOrFail();

            foreach ($preset['users'] as $userData) {
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

                if (! empty($userData['role'])) {
                    $user->syncRoles([$userData['role']]);
                } else {
                    $user->syncRoles([]);
                }

                if (! empty($userData['permissions'])) {
                    $user->syncPermissions($userData['permissions']);
                } else {
                    $user->syncPermissions([]);
                }
            }
        }
    }
}
