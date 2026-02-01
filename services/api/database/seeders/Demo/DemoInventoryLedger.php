<?php

namespace Database\Seeders\Demo;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoInventoryLedger
{
    // Reserve inventory for order items
    public function reserveItems(int $organizationId, Collection $items): void
    {
        foreach ($items as $item) {
            $qty = (int) $item->quantity;

            InventoryStock::query()
                ->where('organization_id', $organizationId)
                ->where('product_id', $item->product_id)
                ->update([
                    'qty_reserved' => DB::raw('qty_reserved + '.$qty),
                ]);
        }
    }

    // Apply sale when order ships
    public function applySale(int $organizationId, ?int $performedByUserId, int $orderId, Collection $items): void
    {
        foreach ($items as $item) {
            $qty = (int) $item->quantity;

            InventoryMovement::query()->create([
                'organization_id' => $organizationId,
                'product_id' => $item->product_id,
                'performed_by_user_id' => $performedByUserId,
                'type' => 'sale',
                'qty_delta' => -1 * $qty,
                'reason' => 'Sold to customer (seeded order).',
                'reference_type' => 'Order',
                'reference_id' => $orderId,
            ]);

            InventoryStock::query()
                ->where('organization_id', $organizationId)
                ->where('product_id', $item->product_id)
                ->update([
                    'qty_on_hand' => DB::raw('GREATEST(qty_on_hand - '.$qty.', 0)'),
                ]);
        }
    }

    // Restock returned items
    public function applyReturnRestock(
        int $organizationId,
        ?int $performedByUserId,
        int $returnOrderId,
        Collection $returnItems,
    ): void {
        foreach ($returnItems as $returnItem) {
            $qty = (int) $returnItem->quantity;
            $restockable = (bool) $returnItem->restockable;

            InventoryMovement::query()->create([
                'organization_id' => $organizationId,
                'product_id' => $returnItem->product_id,
                'performed_by_user_id' => $performedByUserId,
                'type' => 'return',
                'qty_delta' => $restockable ? $qty : 0,
                'reason' => 'Return processed (seeded).',
                'reference_type' => 'Return',
                'reference_id' => $returnOrderId,
            ]);

            if ($restockable) {
                InventoryStock::query()
                    ->where('organization_id', $organizationId)
                    ->where('product_id', $returnItem->product_id)
                    ->update([
                        'qty_on_hand' => DB::raw('qty_on_hand + '.$qty),
                    ]);
            }
        }
    }

    // Recalculate reserved quantities for all open orders
    public function recalculateReserved(int $organizationId): void
    {
        InventoryStock::query()
            ->where('organization_id', $organizationId)
            ->update(['qty_reserved' => 0]);

        $openOrderIds = Order::query()
            ->where('organization_id', $organizationId)
            ->whereIn('current_status', ['draft', 'confirmed', 'ready_to_ship'])
            ->pluck('id');

        if ($openOrderIds->isEmpty()) {
            return;
        }

        $aggregates = OrderItem::query()
            ->select(['product_id', DB::raw('SUM(quantity) as qty')])
            ->whereIn('order_id', $openOrderIds)
            ->groupBy('product_id')
            ->get();

        foreach ($aggregates as $row) {
            InventoryStock::query()
                ->where('organization_id', $organizationId)
                ->where('product_id', $row->product_id)
                ->update(['qty_reserved' => (int) $row->qty]);
        }
    }
}
