<?php

namespace App\Services\Inventory;

use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\ReturnOrder;
use Illuminate\Support\Facades\DB;

final class InventoryLedgerService
{
    // Reserve stock for confirmed order
    public function reserveForOrder(Order $order, int $actorUserId): void
    {
        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;

            // Lock and fetch current stock
            $stock = InventoryStock::query()
                ->where('organization_id', $order->organization_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Calculate available stock
            $available = $stock->qty_on_hand - $stock->qty_reserved;

            // Check if enough available stock exists
            if ($available < $qty) {
                throw InsufficientStock::available(
                    productId: (int) $item->product_id,
                    available: (int) $available,
                    required: $qty,
                );
            }

            // Reserve the stock
            $stock->forceFill([
                'qty_reserved' => $stock->qty_reserved + $qty,
            ])->save();
        }
    }

    // Release reserved stock
    public function releaseReservationForOrder(Order $order, int $actorUserId, string $reason): void
    {
        foreach ($order->items as $item) {
            InventoryStock::query()
                ->where('organization_id', $order->organization_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->update([
                    'qty_reserved' => DB::raw(
                        'CASE WHEN qty_reserved - '.(int) $item->quantity.' < 0 THEN 0 '.
                        'ELSE qty_reserved - '.(int) $item->quantity.' END'
                    ),
                ]);
        }
    }

    // Commit sale when order ships
    public function commitSaleForOrder(Order $order, int $actorUserId): void
    {
        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;

            $stock = InventoryStock::query()
                ->where('organization_id', $order->organization_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate sufficient stock
            if ($stock->qty_on_hand < $qty) {
                throw InsufficientStock::onHand(
                    productId: (int) $item->product_id,
                    onHand: (int) $stock->qty_on_hand,
                    required: $qty,
                );
            }

            // Create sale movement for audit trail
            InventoryMovement::query()->create([
                'organization_id' => $order->organization_id,
                'product_id' => $item->product_id,
                'performed_by_user_id' => $actorUserId,
                'type' => 'sale',
                'qty_delta' => -$qty,
                'reason' => 'Order shipped',
                'reference_type' => 'Order',
                'reference_id' => $order->id,
            ]);

            // Decrement stock and release reservation
            $stock->forceFill([
                'qty_on_hand' => $stock->qty_on_hand - $qty,
                'qty_reserved' => max(0, $stock->qty_reserved - $qty),
            ])->save();
        }
    }

    // Restock inventory from return
    public function restockFromReturn(ReturnOrder $returnOrder, int $actorUserId, string $reason = 'Return restocked'): void
    {
        $returnOrder->loadMissing(['order', 'items']);

        foreach ($returnOrder->items as $item) {
            // Skip non-restockable items
            if (! $item->restockable) {
                continue;
            }

            $qty = (int) $item->quantity;

            // Lock stock row for update
            $stock = InventoryStock::query()
                ->where('organization_id', $returnOrder->order->organization_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyRestocked = InventoryMovement::query()
                ->where('reference_type', 'Return')
                ->where('reference_id', $returnOrder->id)
                ->where('product_id', $item->product_id)
                ->where('type', 'return')
                ->exists();

            if ($alreadyRestocked) {
                continue;
            }

            // Create return movement for the audit trail
            InventoryMovement::query()->create([
                'organization_id' => $returnOrder->order->organization_id,
                'product_id' => $item->product_id,
                'performed_by_user_id' => $actorUserId,
                'type' => 'return',
                'qty_delta' => $qty,
                'reason' => $reason,
                'reference_type' => 'Return',
                'reference_id' => $returnOrder->id,
            ]);

            // Increment stock
            $stock->forceFill([
                'qty_on_hand' => $stock->qty_on_hand + $qty,
            ])->save();
        }
    }
}
