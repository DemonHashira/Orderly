<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            SalesChannelSeeder::class,
            CustomerSeeder::class,
            CustomerAddressSeeder::class,
            ProductSeeder::class,
            InventorySeeder::class,
            DemoOrderSeeder::class,
        ]);
    }
}
