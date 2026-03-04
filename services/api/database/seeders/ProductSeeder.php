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

        $products = [];

        $mangaSeries = [
            ['code' => 'JJK', 'title' => 'Jujutsu Kaisen', 'volumes' => 14, 'base_price' => 12.90, 'description' => 'Manga volume with crisp action panels and bonus art.'],
            ['code' => 'AOT', 'title' => 'Attack on Titan', 'volumes' => 12, 'base_price' => 13.50, 'description' => 'Final arc chapters with premium cover finish.'],
            ['code' => 'SPY', 'title' => 'Spy x Family', 'volumes' => 12, 'base_price' => 12.75, 'description' => 'Slice-of-life espionage with bonus stickers.'],
            ['code' => 'CSM', 'title' => 'Chainsaw Man', 'volumes' => 12, 'base_price' => 13.40, 'description' => 'High-intensity shonen panels with poster insert.'],
            ['code' => 'BL', 'title' => 'Blue Lock', 'volumes' => 12, 'base_price' => 12.50, 'description' => 'Sports manga with fast-paced match storytelling.'],
            ['code' => 'OP', 'title' => 'One Piece', 'volumes' => 16, 'base_price' => 11.90, 'description' => 'Adventure manga volume with color chapter pages.'],
        ];

        $lightNovelSeries = [
            ['code' => 'MUSH', 'title' => 'Mushoku Tensei', 'volumes' => 10, 'base_price' => 18.90, 'description' => 'Light novel edition with color insert and glossary.'],
            ['code' => 'REZERO', 'title' => 'Re:Zero', 'volumes' => 10, 'base_price' => 19.50, 'description' => 'Collector-ready paperback with matte finish.'],
            ['code' => 'OVER', 'title' => 'Overlord', 'volumes' => 10, 'base_price' => 20.00, 'description' => 'Fantasy light novel with detailed world map.'],
            ['code' => 'TOR', 'title' => 'Toradora!', 'volumes' => 8, 'base_price' => 17.90, 'description' => 'Classic romantic comedy light novel release.'],
            ['code' => 'SLIME', 'title' => 'That Time I Got Reincarnated as a Slime', 'volumes' => 8, 'base_price' => 19.20, 'description' => 'Isekai light novel with bonus short story.'],
            ['code' => 'DANM', 'title' => 'Is It Wrong to Try to Pick Up Girls in a Dungeon?', 'volumes' => 8, 'base_price' => 18.60, 'description' => 'Dungeon fantasy light novel with character guide.'],
        ];

        foreach ($mangaSeries as $series) {
            for ($volume = 1; $volume <= $series['volumes']; $volume++) {
                $products[] = [
                    'sku' => sprintf('MANGA-%s-%03d', $series['code'], $volume),
                    'name' => sprintf('%s Vol. %d', $series['title'], $volume),
                    'description' => $series['description'],
                    'sale_price' => round($series['base_price'] + (($volume - 1) % 5) * 0.35, 2),
                ];
            }
        }

        foreach ($lightNovelSeries as $series) {
            for ($volume = 1; $volume <= $series['volumes']; $volume++) {
                $products[] = [
                    'sku' => sprintf('LN-%s-%03d', $series['code'], $volume),
                    'name' => sprintf('%s Light Novel Vol. %d', $series['title'], $volume),
                    'description' => $series['description'],
                    'sale_price' => round($series['base_price'] + (($volume - 1) % 4) * 0.40, 2),
                ];
            }
        }

        $mangaBoxSets = [
            ['code' => 'AOT', 'name' => 'Attack on Titan Manga Box Set (Vol. 1-8)', 'price' => 89.00],
            ['code' => 'OP', 'name' => 'One Piece East Blue Box Set (Vol. 1-12)', 'price' => 119.00],
            ['code' => 'JJK', 'name' => 'Jujutsu Kaisen Starter Box Set (Vol. 1-6)', 'price' => 69.00],
            ['code' => 'BL', 'name' => 'Blue Lock Starter Box Set (Vol. 1-5)', 'price' => 62.00],
            ['code' => 'CSM', 'name' => 'Chainsaw Man Box Set (Vol. 1-11)', 'price' => 108.00],
            ['code' => 'SPY', 'name' => 'Spy x Family Family Bundle (Vol. 1-9)', 'price' => 96.00],
            ['code' => 'HXH', 'name' => 'Hunter x Hunter Collector Box (Vol. 1-8)', 'price' => 88.00],
            ['code' => 'NAR', 'name' => 'Naruto Origins Box Set (Vol. 1-10)', 'price' => 112.00],
        ];

        foreach ($mangaBoxSets as $index => $set) {
            $products[] = [
                'sku' => sprintf('BOX-%s-%03d', $set['code'], $index + 1),
                'name' => $set['name'],
                'description' => 'Bundled manga set with shelf-ready packaging.',
                'sale_price' => $set['price'],
            ];
        }

        $figurines = [
            ['code' => 'GOJO', 'name' => 'Satoru Gojo Scale Figure', 'price' => 79.00],
            ['code' => 'MIKASA', 'name' => 'Mikasa Ackerman Action Figure', 'price' => 74.00],
            ['code' => 'LUFFY', 'name' => 'Luffy Gear Fifth Figure', 'price' => 84.00],
            ['code' => 'ANYA', 'name' => 'Anya Forger Figure', 'price' => 68.00],
            ['code' => 'DENJI', 'name' => 'Denji Chainsaw Form Figure', 'price' => 77.00],
            ['code' => 'RIN', 'name' => 'Rin Itoshi Figure', 'price' => 66.00],
            ['code' => 'AINZ', 'name' => 'Ainz Ooal Gown Figure', 'price' => 88.00],
            ['code' => 'RUDEUS', 'name' => 'Rudeus Greyrat Figure', 'price' => 71.00],
            ['code' => 'NARUTO', 'name' => 'Naruto Sage Mode Figure', 'price' => 75.00],
            ['code' => 'GON', 'name' => 'Gon Freecss Figure', 'price' => 69.00],
            ['code' => 'EREN', 'name' => 'Eren Titan Form Figure', 'price' => 82.00],
            ['code' => 'MAKIMA', 'name' => 'Makima Collector Figure', 'price' => 78.00],
            ['code' => 'VEGETA', 'name' => 'Vegeta Battle Figure', 'price' => 73.00],
            ['code' => 'LEVI', 'name' => 'Levi Ackerman Figure', 'price' => 76.00],
            ['code' => 'REM', 'name' => 'Rem Maid Figure', 'price' => 72.00],
            ['code' => 'YUTA', 'name' => 'Yuta Okkotsu Figure', 'price' => 74.00],
        ];

        foreach ($figurines as $index => $figure) {
            $products[] = [
                'sku' => sprintf('FIG-%s-%03d', $figure['code'], $index + 1),
                'name' => $figure['name'],
                'description' => 'Limited edition anime figurine with display stand.',
                'sale_price' => $figure['price'],
            ];
        }

        $merchItems = [
            ['code' => 'POSTER', 'name' => 'Premium Poster', 'price' => 14.00],
            ['code' => 'KEYCHAIN', 'name' => 'Character Keychain', 'price' => 9.50],
            ['code' => 'TSHIRT', 'name' => 'Graphic T-Shirt', 'price' => 24.00],
            ['code' => 'MUG', 'name' => 'Themed Ceramic Mug', 'price' => 15.00],
            ['code' => 'STICKER', 'name' => 'Sticker Pack', 'price' => 7.00],
            ['code' => 'TOTE', 'name' => 'Canvas Tote Bag', 'price' => 19.00],
            ['code' => 'MOUSEPAD', 'name' => 'Desk Mousepad', 'price' => 13.00],
            ['code' => 'NOTEBOOK', 'name' => 'Hardcover Notebook', 'price' => 12.50],
        ];

        for ($series = 1; $series <= 3; $series++) {
            foreach ($merchItems as $index => $item) {
                $products[] = [
                    'sku' => sprintf('MERCH-%s-%03d', $item['code'], (($series - 1) * count($merchItems)) + $index + 1),
                    'name' => sprintf('%s Series %d', $item['name'], $series),
                    'description' => 'Official merchandise item for daily use and gifts.',
                    'sale_price' => $item['price'] + (($series - 1) * 0.75),
                ];
            }
        }

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
