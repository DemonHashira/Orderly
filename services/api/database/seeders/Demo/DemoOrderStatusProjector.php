<?php

namespace Database\Seeders\Demo;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Carbon;

class DemoOrderStatusProjector
{
    // Project status history based on the order's final status
    public function project(
        Order $order,
        int $orderManagerUserId,
        int $logisticsUserId,
        string $finalStatus,
        Carbon $createdAt,
    ): void {
        $flow = match ($finalStatus) {
            'draft' => ['draft'],
            'confirmed' => ['draft', 'confirmed'],
            'ready_to_ship' => ['draft', 'confirmed', 'ready_to_ship'],
            'shipped' => ['draft', 'confirmed', 'ready_to_ship', 'shipped'],
            'delivered' => ['draft', 'confirmed', 'ready_to_ship', 'shipped', 'delivered'],
            'returned' => ['draft', 'confirmed', 'ready_to_ship', 'shipped', 'returned'],
            'unpaid' => ['draft', 'confirmed', 'ready_to_ship', 'shipped', 'unpaid'],
            'cancelled' => ['draft', 'cancelled'],
            default => ['draft', $finalStatus],
        };

        foreach ($flow as $i => $status) {
            $at = $createdAt->copy()->addDays($i);

            $changedBy = in_array($status, ['shipped', 'delivered', 'returned', 'unpaid'], true)
                ? $logisticsUserId
                : $orderManagerUserId;

            $row = OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $status,
                'changed_by' => $changedBy,
            ]);

            $row->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
    }
}
