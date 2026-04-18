<?php

namespace Database\Seeders\Demo;

class DemoOrderScenarios
{
    private const int TARGET_ORDERS = 220;

    /**
     * @param  array<string>  $channelCodes
     * @param  array{
     *     core_sku?: string,
     *     poster_sku?: string,
     *     confirmed_sku?: string,
     *     ready_sku?: string,
     *     shipped_sku?: string,
     *     defective_sku?: string,
     *     sticker_sku?: string,
     *     keychain_sku?: string,
     * }  $anchorSkus
     * @return array<int, array<string, mixed>>
     */
    public static function make(
        array $channelCodes,
        string $referencePrefix = 'OC',
        array $anchorSkus = [],
    ): array {
        mt_srand(20260304);

        $anchorSkus = [
            'core_sku' => 'MANGA-JJK-004',
            'poster_sku' => 'MERCH-POSTER-001',
            'confirmed_sku' => 'MANGA-SPY-005',
            'ready_sku' => 'FIG-ANYA-004',
            'shipped_sku' => 'LN-REZERO-003',
            'defective_sku' => 'FIG-GOJO-001',
            'sticker_sku' => 'MERCH-STICKER-021',
            'keychain_sku' => 'MERCH-KEYCHAIN-018',
            ...$anchorSkus,
        ];

        $scenarios = [
            ['reference' => self::orderReference($referencePrefix, '0001'), 'status' => 'draft', 'channel' => 'instagram', 'days_ago' => 12, 'items' => 3],
            ['reference' => self::orderReference($referencePrefix, '0002'), 'status' => 'confirmed', 'channel' => 'instagram', 'days_ago' => 10, 'items' => 2],
            ['reference' => self::orderReference($referencePrefix, '0003'), 'status' => 'ready_to_ship', 'channel' => 'phone', 'days_ago' => 9, 'items' => 4],
            ['reference' => self::orderReference($referencePrefix, '0004'), 'status' => 'shipped', 'channel' => 'marketplace', 'days_ago' => 7, 'items' => 3],
            ['reference' => self::orderReference($referencePrefix, '0005'), 'status' => 'delivered', 'channel' => 'phone', 'days_ago' => 15, 'items' => 2],
            ['reference' => self::orderReference($referencePrefix, '0006'), 'status' => 'returned', 'channel' => 'marketplace', 'days_ago' => 20, 'items' => 3, 'return' => true, 'return_delay_days' => 7],
            ['reference' => self::orderReference($referencePrefix, '0007'), 'status' => 'unpaid', 'channel' => 'email', 'days_ago' => 8, 'items' => 2, 'return' => true, 'return_delay_days' => 4],
            ['reference' => self::orderReference($referencePrefix, '0008'), 'status' => 'cancelled', 'channel' => 'email', 'days_ago' => 6, 'items' => 2],
            ['reference' => self::orderReference($referencePrefix, '0009'), 'status' => 'delivered', 'channel' => 'instagram', 'days_ago' => 3, 'items' => 2],
            ['reference' => self::orderReference($referencePrefix, '0010'), 'status' => 'shipped', 'channel' => 'phone', 'days_ago' => 4, 'items' => 3],
            ['reference' => self::orderReference($referencePrefix, '0011'), 'status' => 'ready_to_ship', 'channel' => 'instagram', 'days_ago' => 2, 'items' => 1],
            ['reference' => self::orderReference($referencePrefix, '0012'), 'status' => 'confirmed', 'channel' => 'marketplace', 'days_ago' => 5, 'items' => 2],
        ];

        $scenarios = [...$scenarios, ...self::reportAnchorScenarios($referencePrefix, $anchorSkus)];

        for ($i = count($scenarios) + 1; $i <= self::TARGET_ORDERS; $i++) {
            $status = self::pickWeighted(
                [
                    'delivered' => 28,
                    'shipped' => 16,
                    'ready_to_ship' => 14,
                    'confirmed' => 12,
                    'draft' => 10,
                    'cancelled' => 8,
                    'returned' => 8,
                    'unpaid' => 4,
                ],
            );
            $channel = self::pickWeightedChannel($channelCodes);

            $daysAgo = in_array($status, ['returned', 'unpaid'], true)
                ? mt_rand(6, 24)
                : self::pickDaysAgo();

            $scenario = [
                'reference' => sprintf('%s-2026-%04d', $referencePrefix, $i),
                'status' => $status,
                'channel' => $channel,
                'days_ago' => $daysAgo,
                'items' => mt_rand(1, 4),
            ];

            if (in_array($status, ['returned', 'unpaid'], true)) {
                $scenario['return'] = true;
                $scenario['return_delay_days'] = min(7, max(4, $daysAgo - 1));
            }

            $scenarios[] = $scenario;
        }

        return $scenarios;
    }

    /**
     * @param  array<string, string>  $anchorSkus
     * @return array<int, array<string, mixed>>
     */
    private static function reportAnchorScenarios(string $referencePrefix, array $anchorSkus): array
    {
        return [
            [
                'reference' => self::anchorReference($referencePrefix, '001'),
                'status' => 'draft',
                'channel' => 'instagram',
                'days_ago' => 21,
                'items' => 2,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['core_sku'], 'quantity' => 4],
                    ['sku' => $anchorSkus['poster_sku'], 'quantity' => 2],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '002'),
                'status' => 'confirmed',
                'channel' => 'phone',
                'days_ago' => 18,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['confirmed_sku'], 'quantity' => 5],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '003'),
                'status' => 'ready_to_ship',
                'channel' => 'marketplace',
                'days_ago' => 14,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['ready_sku'], 'quantity' => 2],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '004'),
                'status' => 'delivered',
                'channel' => 'instagram',
                'days_ago' => 11,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['core_sku'], 'quantity' => 6],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '005'),
                'status' => 'shipped',
                'channel' => 'phone',
                'days_ago' => 9,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['shipped_sku'], 'quantity' => 3],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '006'),
                'status' => 'returned',
                'channel' => 'marketplace',
                'days_ago' => 8,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['defective_sku'], 'quantity' => 2],
                ],
                'return' => true,
                'return_reason' => 'Defective product',
                'restockable_mode' => 'all',
                'apply_restock' => false,
                'return_delay_days' => 5,
                'return_all_items' => true,
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '007'),
                'status' => 'returned',
                'channel' => 'email',
                'days_ago' => 16,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['poster_sku'], 'quantity' => 4],
                ],
                'return' => true,
                'return_reason' => 'Not as described',
                'restockable_mode' => 'all',
                'apply_restock' => false,
                'return_delay_days' => 6,
                'return_all_items' => true,
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '008'),
                'status' => 'returned',
                'channel' => 'instagram',
                'days_ago' => 13,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['poster_sku'], 'quantity' => 3],
                ],
                'return' => true,
                'return_reason' => 'Wrong item received',
                'restockable_mode' => 'all',
                'apply_restock' => false,
                'return_delay_days' => 5,
                'return_all_items' => true,
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '009'),
                'status' => 'delivered',
                'channel' => 'marketplace',
                'days_ago' => 4,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['core_sku'], 'quantity' => 4],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '010'),
                'status' => 'delivered',
                'channel' => 'phone',
                'days_ago' => 33,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['core_sku'], 'quantity' => 3],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '011'),
                'status' => 'delivered',
                'channel' => 'marketplace',
                'days_ago' => 37,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['poster_sku'], 'quantity' => 2],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '012'),
                'status' => 'returned',
                'channel' => 'instagram',
                'days_ago' => 41,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['defective_sku'], 'quantity' => 1],
                ],
                'return' => true,
                'return_reason' => 'Defective product',
                'restockable_mode' => 'all',
                'apply_restock' => true,
                'return_delay_days' => 6,
                'return_all_items' => true,
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '013'),
                'status' => 'shipped',
                'channel' => 'email',
                'days_ago' => 52,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['shipped_sku'], 'quantity' => 2],
                ],
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '014'),
                'status' => 'returned',
                'channel' => 'marketplace',
                'days_ago' => 19,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['sticker_sku'], 'quantity' => 3],
                ],
                'return' => true,
                'return_reason' => 'Changed mind',
                'restockable_mode' => 'all',
                'apply_restock' => false,
                'return_delay_days' => 6,
                'return_all_items' => true,
            ],
            [
                'reference' => self::anchorReference($referencePrefix, '015'),
                'status' => 'returned',
                'channel' => 'phone',
                'days_ago' => 11,
                'items' => 1,
                'item_blueprint' => [
                    ['sku' => $anchorSkus['keychain_sku'], 'quantity' => 2],
                ],
                'return' => true,
                'return_reason' => 'Wrong size ordered',
                'restockable_mode' => 'all',
                'apply_restock' => false,
                'return_delay_days' => 5,
                'return_all_items' => true,
            ],
        ];
    }

    private static function orderReference(string $referencePrefix, string $suffix): string
    {
        return sprintf('%s-2026-%s', $referencePrefix, $suffix);
    }

    private static function anchorReference(string $referencePrefix, string $suffix): string
    {
        return sprintf('%s-2026-ANCHOR-%s', $referencePrefix, $suffix);
    }

    /**
     * @param  array<string, int>  $weights
     */
    private static function pickWeighted(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $pick = mt_rand(1, $totalWeight);
        $runningTotal = 0;

        foreach ($weights as $value => $weight) {
            $runningTotal += $weight;

            if ($pick <= $runningTotal) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    /**
     * @param  array<string>  $channelCodes
     */
    private static function pickWeightedChannel(array $channelCodes): string
    {
        $available = array_values(array_unique($channelCodes));

        if ($available === []) {
            return 'phone';
        }

        $weights = array_filter([
            'instagram' => 28,
            'marketplace' => 26,
            'phone' => 24,
            'email' => 22,
        ], function ($channel) use ($available) {
            return in_array($channel, $available, true);
        }, ARRAY_FILTER_USE_KEY);

        if ($weights === []) {
            $weights[$available[0]] = 1;
        }

        return self::pickWeighted($weights);
    }

    private static function pickDaysAgo(): int
    {
        return self::pickWeighted([
            '2' => 8,
            '3' => 10,
            '4' => 10,
            '5' => 10,
            '6' => 10,
            '7' => 10,
            '8' => 9,
            '9' => 9,
            '10' => 8,
            '11' => 7,
            '12' => 7,
            '13' => 6,
            '14' => 6,
            '15' => 6,
            '16' => 5,
            '17' => 5,
            '18' => 5,
            '19' => 4,
            '20' => 4,
            '21' => 4,
            '22' => 4,
            '23' => 3,
            '24' => 3,
            '25' => 2,
            '26' => 2,
            '27' => 2,
            '28' => 2,
            '29' => 2,
            '30' => 2,
            '31' => 1,
            '32' => 1,
            '33' => 1,
            '34' => 1,
            '35' => 1,
        ]);
    }
}
