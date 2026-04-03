<?php

namespace Database\Seeders\Support;

use InvalidArgumentException;

final class TenantProductCatalogs
{
    public static function productsFor(string $slug): array
    {
        return match ($slug) {
            'otaku-store' => self::otakuStoreProducts(),
            'gear-hub' => self::gearHubProducts(),
            default => throw new InvalidArgumentException("Unknown tenant product catalog [{$slug}]."),
        };
    }

    public static function archiveSkusFor(string $slug): array
    {
        return match ($slug) {
            'otaku-store' => [
                'MANGA-JJK-001',
                'MANGA-AOT-002',
                'MANGA-SPY-003',
                'LN-MUSH-001',
                'LN-REZERO-002',
                'BOX-AOT-001',
                'FIG-GOJO-001',
                'MERCH-POSTER-001',
            ],
            'gear-hub' => [
                'GH-HEADSET-001',
                'GH-KEYBOARD-002',
                'GH-WEBCAM-003',
                'GH-DOCK-001',
                'GH-MIC-002',
                'GH-MOUSEPAD-001',
                'GH-NOTEBOOK-003',
                'GH-STICKER-021',
            ],
            default => throw new InvalidArgumentException("Unknown tenant archive SKU catalog [{$slug}]."),
        };
    }

    public static function anchorSkusFor(string $slug): array
    {
        return match ($slug) {
            'otaku-store' => [
                'core_sku' => 'MANGA-JJK-004',
                'poster_sku' => 'MERCH-POSTER-001',
                'confirmed_sku' => 'MANGA-SPY-005',
                'ready_sku' => 'FIG-ANYA-004',
                'shipped_sku' => 'LN-REZERO-003',
                'defective_sku' => 'FIG-GOJO-001',
                'sticker_sku' => 'MERCH-STICKER-021',
                'keychain_sku' => 'MERCH-KEYCHAIN-018',
            ],
            'gear-hub' => [
                'core_sku' => 'GH-WEBCAM-004',
                'poster_sku' => 'GH-MOUSEPAD-001',
                'confirmed_sku' => 'GH-KEYBOARD-005',
                'ready_sku' => 'GH-MIC-004',
                'shipped_sku' => 'GH-NOTEBOOK-003',
                'defective_sku' => 'GH-HEADSET-001',
                'sticker_sku' => 'GH-STICKER-021',
                'keychain_sku' => 'GH-KEYCHAIN-018',
            ],
            default => throw new InvalidArgumentException("Unknown tenant anchor SKU catalog [{$slug}]."),
        };
    }

    private static function otakuStoreProducts(): array
    {
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

        return $products;
    }

    private static function gearHubProducts(): array
    {
        $products = [];

        $deviceSeries = [
            ['code' => 'HEADSET', 'title' => 'Aurora Wireless Headset', 'models' => 12, 'base_price' => 119.00, 'description' => 'Low-latency wireless headset tuned for gaming and remote collaboration.'],
            ['code' => 'KEYBOARD', 'title' => 'Forge Mechanical Keyboard', 'models' => 10, 'base_price' => 89.00, 'description' => 'Mechanical keyboard with hot-swappable switches and compact footprint.'],
            ['code' => 'WEBCAM', 'title' => 'Studio 4K Webcam', 'models' => 8, 'base_price' => 99.00, 'description' => 'Creator-focused webcam with HDR framing and dual-noise microphones.'],
            ['code' => 'DOCK', 'title' => 'Creator USB-C Dock', 'models' => 6, 'base_price' => 129.00, 'description' => 'Desktop dock with multi-display support and high-speed data passthrough.'],
            ['code' => 'MIC', 'title' => 'Broadcast USB Microphone', 'models' => 8, 'base_price' => 109.00, 'description' => 'Cardioid microphone bundle for streaming, calls, and podcasting.'],
        ];

        foreach ($deviceSeries as $series) {
            for ($model = 1; $model <= $series['models']; $model++) {
                $products[] = [
                    'sku' => sprintf('GH-%s-%03d', $series['code'], $model),
                    'name' => sprintf('%s Model %d', $series['title'], $model),
                    'description' => $series['description'],
                    'sale_price' => round($series['base_price'] + (($model - 1) % 4) * 6.50, 2),
                ];
            }
        }

        $bundles = [
            ['name' => 'Streaming Starter Bundle', 'price' => 239.00],
            ['name' => 'Remote Work Setup Bundle', 'price' => 269.00],
            ['name' => 'Creator Audio Bundle', 'price' => 229.00],
            ['name' => 'Portable Desk Upgrade Kit', 'price' => 179.00],
            ['name' => 'Conference Room Refresh Pack', 'price' => 319.00],
            ['name' => 'Travel Office Essentials Bundle', 'price' => 149.00],
        ];

        foreach ($bundles as $index => $bundle) {
            $products[] = [
                'sku' => sprintf('GH-BUNDLE-%03d', $index + 1),
                'name' => $bundle['name'],
                'description' => 'Curated bundle for fast deployment across workstations and creator desks.',
                'sale_price' => $bundle['price'],
            ];
        }

        $deskGear = [
            ['code' => 'MOUSEPAD', 'name' => 'Precision Mousepad', 'price' => 18.00],
            ['code' => 'KEYCHAIN', 'name' => 'Cable Keychain Kit', 'price' => 8.50],
            ['code' => 'NOTEBOOK', 'name' => 'Tech Notes Notebook', 'price' => 14.00],
            ['code' => 'CABLE', 'name' => 'Cable Organizer', 'price' => 11.00],
            ['code' => 'STICKER', 'name' => 'Device Sticker Pack', 'price' => 6.50],
            ['code' => 'STAND', 'name' => 'Laptop Stand', 'price' => 32.00],
            ['code' => 'MAT', 'name' => 'Desk Mat', 'price' => 24.00],
            ['code' => 'CLEAN', 'name' => 'Screen Cleaning Kit', 'price' => 12.00],
        ];

        for ($series = 1; $series <= 3; $series++) {
            foreach ($deskGear as $index => $item) {
                $products[] = [
                    'sku' => sprintf('GH-%s-%03d', $item['code'], (($series - 1) * count($deskGear)) + $index + 1),
                    'name' => sprintf('%s Series %d', $item['name'], $series),
                    'description' => 'Desk accessory focused on workstation organization and creator comfort.',
                    'sale_price' => $item['price'] + (($series - 1) * 1.25),
                ];
            }
        }

        return $products;
    }
}
