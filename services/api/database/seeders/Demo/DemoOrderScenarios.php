<?php

namespace Database\Seeders\Demo;

class DemoOrderScenarios
{
    private const TARGET_ORDERS = 120;

    public static function make(array $channelCodes): array
    {
        mt_srand(20260304);

        $scenarios = [
            ['reference' => 'OC-2026-0001', 'status' => 'draft', 'channel' => 'instagram', 'days_ago' => 12, 'items' => 3],
            ['reference' => 'OC-2026-0002', 'status' => 'confirmed', 'channel' => 'instagram', 'days_ago' => 10, 'items' => 2],
            ['reference' => 'OC-2026-0003', 'status' => 'ready_to_ship', 'channel' => 'phone', 'days_ago' => 9, 'items' => 4],
            ['reference' => 'OC-2026-0004', 'status' => 'shipped', 'channel' => 'marketplace', 'days_ago' => 7, 'items' => 3],
            ['reference' => 'OC-2026-0005', 'status' => 'delivered', 'channel' => 'phone', 'days_ago' => 15, 'items' => 2],
            ['reference' => 'OC-2026-0006', 'status' => 'returned', 'channel' => 'marketplace', 'days_ago' => 20, 'items' => 3, 'return' => true],
            ['reference' => 'OC-2026-0007', 'status' => 'unpaid', 'channel' => 'email', 'days_ago' => 8, 'items' => 2, 'return' => true],
            ['reference' => 'OC-2026-0008', 'status' => 'cancelled', 'channel' => 'email', 'days_ago' => 6, 'items' => 2],
            ['reference' => 'OC-2026-0009', 'status' => 'delivered', 'channel' => 'instagram', 'days_ago' => 3, 'items' => 2],
            ['reference' => 'OC-2026-0010', 'status' => 'shipped', 'channel' => 'phone', 'days_ago' => 4, 'items' => 3],
            ['reference' => 'OC-2026-0011', 'status' => 'ready_to_ship', 'channel' => 'instagram', 'days_ago' => 2, 'items' => 1],
            ['reference' => 'OC-2026-0012', 'status' => 'confirmed', 'channel' => 'marketplace', 'days_ago' => 5, 'items' => 2],
        ];

        for ($i = count($scenarios) + 1; $i <= self::TARGET_ORDERS; $i++) {
            $status = self::pickWeighted(
                [
                    'delivered' => 34,
                    'shipped' => 18,
                    'ready_to_ship' => 14,
                    'confirmed' => 12,
                    'draft' => 10,
                    'cancelled' => 6,
                    'returned' => 4,
                    'unpaid' => 2,
                ],
            );
            $channel = self::pickWeightedChannel($channelCodes);

            $scenario = [
                'reference' => sprintf('OC-2026-%04d', $i),
                'status' => $status,
                'channel' => $channel,
                'days_ago' => self::pickDaysAgo(),
                'items' => mt_rand(1, 4),
            ];

            if (in_array($status, ['returned', 'unpaid'], true)) {
                $scenario['return'] = true;
            }

            $scenarios[] = $scenario;
        }

        return $scenarios;
    }

    private static function pickWeightedChannel(array $channelCodes): string
    {
        $weights = [
            'instagram' => 35,
            'marketplace' => 30,
            'phone' => 20,
            'email' => 15,
        ];

        $available = array_values(array_filter(array_keys($weights), static fn (string $code): bool => in_array($code, $channelCodes, true)));

        if ($available === []) {
            return $channelCodes[0] ?? 'phone';
        }

        $filteredWeights = [];
        foreach ($available as $code) {
            $filteredWeights[$code] = $weights[$code];
        }

        return self::pickWeighted($filteredWeights);
    }

    private static function pickDaysAgo(): int
    {
        $bucket = mt_rand(1, 100);

        if ($bucket <= 65) {
            return mt_rand(1, 30);
        }

        if ($bucket <= 90) {
            return mt_rand(31, 60);
        }

        return mt_rand(61, 90);
    }

    private static function pickWeighted(array $weights): string
    {
        $total = array_sum($weights);
        $needle = mt_rand(1, max(1, $total));
        $cursor = 0;

        foreach ($weights as $value => $weight) {
            $cursor += $weight;
            if ($needle <= $cursor) {
                return $value;
            }
        }

        return array_key_first($weights) ?? 'draft';
    }
}
