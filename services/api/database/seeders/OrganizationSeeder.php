<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::create([
            'name' => 'Demo Store',
            'slug' => 'demo-store',
        ]);
    }
}
