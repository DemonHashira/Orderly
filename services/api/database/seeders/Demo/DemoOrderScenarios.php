<?php

namespace Database\Seeders\Demo;

class DemoOrderScenarios
{
    // Generate an array of order scenarios with various statuses and channels
    public static function make(array $channelCodes): array
    {
        $scenarios = [
            // Order Manager-owned states
            ['reference' => 'OC-2026-0001', 'status' => 'draft', 'channel' => 'instagram', 'days_ago' => 12, 'items' => 3],
            ['reference' => 'OC-2026-0002', 'status' => 'confirmed', 'channel' => 'instagram', 'days_ago' => 10, 'items' => 2],
            ['reference' => 'OC-2026-0003', 'status' => 'ready_to_ship', 'channel' => 'phone', 'days_ago' => 9, 'items' => 4],

            // Logistics-owned states
            ['reference' => 'OC-2026-0004', 'status' => 'shipped', 'channel' => 'instagram', 'days_ago' => 7, 'items' => 3],
            ['reference' => 'OC-2026-0005', 'status' => 'delivered', 'channel' => 'phone', 'days_ago' => 15, 'items' => 2],
            ['reference' => 'OC-2026-0006', 'status' => 'returned', 'channel' => 'marketplace', 'days_ago' => 20, 'items' => 3, 'return' => true],
            ['reference' => 'OC-2026-0007', 'status' => 'unpaid', 'channel' => 'email', 'days_ago' => 8, 'items' => 2, 'return' => true],

            // Other
            ['reference' => 'OC-2026-0008', 'status' => 'cancelled', 'channel' => 'email', 'days_ago' => 6, 'items' => 2],
        ];

        for ($i = 9; $i <= 15; $i++) {
            $scenarios[] = [
                'reference' => 'OC-2026-00'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'status' => fake()->randomElement(['draft', 'confirmed', 'ready_to_ship', 'shipped', 'delivered']),
                'channel' => fake()->randomElement($channelCodes),
                'days_ago' => fake()->numberBetween(1, 18),
                'items' => fake()->numberBetween(1, 4),
            ];
        }

        return $scenarios;
    }
}
