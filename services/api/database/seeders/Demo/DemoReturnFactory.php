<?php

namespace Database\Seeders\Demo;

use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoReturnFactory
{
    // Create return with items for an order
    public function createFor(int $orderId, Collection $items, Carbon $returnedAt, string $outcome): array
    {
        $returnOrder = ReturnOrder::query()->create([
            'order_id' => $orderId,
            'reason' => fake()->randomElement([
                'Changed mind',
                'Defective product',
                'Wrong item received',
                'Not as described',
                'Unpaid / refused delivery',
            ]),
            'returned_at' => $returnedAt,
        ]);

        $returnOrder->forceFill(['created_at' => $returnedAt, 'updated_at' => $returnedAt])->save();

        // Unpaid typically returns the whole item
        $returnedItemsSource = $outcome === 'unpaid'
            ? $items
            : $items->shuffle()->take(max(1, (int) ceil($items->count() / 2)));

        $created = collect();

        foreach ($returnedItemsSource as $item) {
            $qty = $outcome === 'unpaid'
                ? (int) $item->quantity
                : fake()->numberBetween(1, (int) $item->quantity);

            $restockable = $outcome === 'unpaid' || fake()->boolean(80);

            $created->push(ReturnItem::query()->create([
                'return_id' => $returnOrder->id,
                'product_id' => $item->product_id,
                'quantity' => $qty,
                'restockable' => $restockable,
            ]));
        }

        return [$returnOrder, $created];
    }
}
