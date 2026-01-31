<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-store')->firstOrFail();

        $products = [
            [
                'sku' => 'MANGA-JJK-001',
                'name' => 'Jujutsu Kaisen Vol. 1',
                'description' => 'Manga volume with crisp action panels and bonus art.',
                'sale_price' => 12.90,
            ],
            [
                'sku' => 'MANGA-AOT-034',
                'name' => 'Attack on Titan Vol. 34',
                'description' => 'Final arc volume with premium cover finish.',
                'sale_price' => 14.50,
            ],
            [
                'sku' => 'MANGA-SPY-010',
                'name' => 'Spy x Family Vol. 10',
                'description' => 'Slice-of-life espionage with bonus stickers.',
                'sale_price' => 13.20,
            ],
            [
                'sku' => 'LN-MUSH-001',
                'name' => 'Mushoku Tensei Light Novel Vol. 1',
                'description' => 'Light novel with color insert and glossary.',
                'sale_price' => 18.90,
            ],
            [
                'sku' => 'LN-REZERO-002',
                'name' => 'Re:Zero Light Novel Vol. 2',
                'description' => 'Collector-ready paperback with matte finish.',
                'sale_price' => 19.50,
            ],
            [
                'sku' => 'FIG-OP-001',
                'name' => 'One Piece Luffy Figure 18cm',
                'description' => 'PVC figure with dynamic pose and base.',
                'sale_price' => 69.00,
            ],
            [
                'sku' => 'FIG-DS-002',
                'name' => 'Demon Slayer Nezuko Figure 16cm',
                'description' => 'Detailed paintwork with bamboo muzzle accessory.',
                'sale_price' => 72.50,
            ],
            [
                'sku' => 'FIG-NAR-003',
                'name' => 'Naruto Sage Mode Figure 20cm',
                'description' => 'Battle stance with scroll prop.',
                'sale_price' => 84.00,
            ],
            [
                'sku' => 'MERCH-GH-001',
                'name' => 'Ghibli Totoro Plush 30cm',
                'description' => 'Soft plush with stitched details.',
                'sale_price' => 29.90,
            ],
            [
                'sku' => 'MERCH-MHA-002',
                'name' => 'My Hero Academia Hoodie',
                'description' => 'Unisex fleece hoodie with embroidered logo.',
                'sale_price' => 49.90,
            ],
            [
                'sku' => 'MERCH-HAI-003',
                'name' => 'Haikyuu!! Volleyball Keychain Set',
                'description' => 'Metal enamel set of 5 characters.',
                'sale_price' => 15.90,
            ],
            [
                'sku' => 'ART-SP-001',
                'name' => 'Studio Ghibli Artbook',
                'description' => 'Large-format art book with sketches and interviews.',
                'sale_price' => 39.00,
            ],
            [
                'sku' => 'GAME-PL-001',
                'name' => 'Pokemon Trading Card Booster Pack',
                'description' => 'Random booster with 10 cards.',
                'sale_price' => 6.50,
            ],
            [
                'sku' => 'MANGA-CSM-006',
                'name' => 'Chainsaw Man Vol. 6',
                'description' => 'Manga volume with action poster insert.',
                'sale_price' => 13.90,
            ],
            [
                'sku' => 'LN-OVER-001',
                'name' => 'Overlord Light Novel Vol. 1',
                'description' => 'Fantasy light novel with detailed map.',
                'sale_price' => 20.00,
            ],
            [
                'sku' => 'FIG-OP-004',
                'name' => 'One Piece Zoro Figure 19cm',
                'description' => 'Three-sword style pose with stand.',
                'sale_price' => 76.00,
            ],
            [
                'sku' => 'MERCH-JJK-004',
                'name' => 'Jujutsu Kaisen Pin Set',
                'description' => 'Set of 6 enamel pins.',
                'sale_price' => 18.00,
            ],
            [
                'sku' => 'MANGA-BL-001',
                'name' => 'Blue Lock Vol. 1',
                'description' => 'Sports manga with high-energy artwork.',
                'sale_price' => 12.50,
            ],
            [
                'sku' => 'LN-TOR-001',
                'name' => 'Toradora! Light Novel Vol. 1',
                'description' => 'Classic romantic comedy light novel.',
                'sale_price' => 17.90,
            ],
            [
                'sku' => 'MERCH-SPY-005',
                'name' => 'Spy x Family Desk Mat',
                'description' => 'Large desk mat with stitched edges.',
                'sale_price' => 24.90,
            ],
        ];

        foreach ($products as $product) {
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
