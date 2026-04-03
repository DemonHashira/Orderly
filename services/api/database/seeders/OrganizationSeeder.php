<?php

namespace Database\Seeders;

use App\Models\Organization;
use Database\Seeders\Support\TenantSeedPresets;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TenantSeedPresets::all() as $preset) {
            Organization::query()->updateOrCreate(
                ['slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'slug' => $preset['slug'],
                ],
            );
        }
    }
}
