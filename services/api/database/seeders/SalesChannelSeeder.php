<?php

namespace Database\Seeders;

use App\Models\SalesChannel;
use Illuminate\Database\Seeder;

class SalesChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['name' => 'Phone Order', 'code' => 'phone'],
            ['name' => 'Email Order', 'code' => 'email'],
            ['name' => 'Instagram DM', 'code' => 'instagram'],
            ['name' => 'Facebook Marketplace', 'code' => 'marketplace'],
        ];

        foreach ($channels as $channel) {
            SalesChannel::query()->updateOrCreate(
                ['code' => $channel['code']],
                $channel,
            );
        }
    }
}
