<?php

namespace Database\Seeders\Demo;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoShipmentFactory
{
    // Create shipment for order with realistic courier and timing
    public function createFor(Order $order, string $status, Carbon $createdAt): void
    {
        $couriers = ['Speedy', 'Econt', 'DHL', 'UPS', 'Bulgarian Post'];
        $shippedAt = $createdAt->copy()->addDays(3);
        $deliveredAt = $status === 'delivered' ? $createdAt->copy()->addDays(6) : null;

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier' => fake()->randomElement($couriers),
            'tracking_number' => Str::upper(fake()->bothify('??########')),
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
        ]);

        $shipment->forceFill([
            'created_at' => $shippedAt,
            'updated_at' => $deliveredAt ?? $shippedAt,
        ])->save();
    }
}
