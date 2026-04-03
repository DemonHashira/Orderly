<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Product;
use Database\Seeders\Support\TenantProductCatalogs;
use Database\Seeders\Support\TenantSeedPresets;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TenantSeedPresets::all() as $preset) {
            $org = Organization::query()->where('slug', $preset['slug'])->firstOrFail();

            foreach (TenantProductCatalogs::productsFor($preset['slug']) as $product) {
                Product::query()->updateOrCreate(
                    [
                        'organization_id' => $org->id,
                        'sku' => $product['sku'],
                    ],
                    [
                        'organization_id' => $org->id,
                        'sku' => $product['sku'],
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'sale_price' => $product['sale_price'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
