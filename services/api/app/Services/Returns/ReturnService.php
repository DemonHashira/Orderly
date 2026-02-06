<?php

namespace App\Services\Returns;

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Domain\Returns\Exceptions\InvalidReturnItemQuantity;
use App\Domain\Returns\Exceptions\ReturnItemNotInOrder;
use App\Domain\Returns\Exceptions\ReturnQuantityExceeded;
use App\Models\Order;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Services\Inventory\InventoryLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class ReturnService
{
    public function __construct(
        private InventoryLedgerService $inventory,
    ) {}

    // Create a return record if it doesn't exist
    public function createReturnIfMissing(
        int $orderId,
        int $actorUserId,
        string $reason,
        ?Carbon $returnedAt = null,
    ): ReturnOrder {
        $returnedAt ??= now();

        return DB::transaction(function () use ($orderId, $reason, $returnedAt) {
            $order = Order::query()->with(['return'])->lockForUpdate()->findOrFail($orderId);
            $status = OrderStatus::from($order->current_status);

            // Validate order status allows returns
            if (! in_array($status, [OrderStatus::Returned, OrderStatus::Unpaid, OrderStatus::Shipped], true)) {
                throw InvalidOrderTransition::forStatus($status, 'create return');
            }

            // Create a return record if missing
            $return = ReturnOrder::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'reason' => $reason,
                    'returned_at' => $returnedAt,
                ]
            );

            // Update reason/returned_at if return already existed
            $return->forceFill([
                'reason' => $return->reason ?? $reason,
                'returned_at' => $return->returned_at ?? $returnedAt,
            ])->save();

            return $return->refresh();
        });
    }

    // Add item to return with validation
    public function addReturnItem(
        int $returnOrderId,
        int $productId,
        int $quantity,
        bool $restockable,
    ): ReturnItem {
        if ($quantity <= 0) {
            throw InvalidReturnItemQuantity::forQuantity($quantity);
        }

        return DB::transaction(function () use ($returnOrderId, $productId, $quantity, $restockable) {
            $returnOrder = ReturnOrder::query()
                ->with(['order.items', 'items'])
                ->lockForUpdate()
                ->findOrFail($returnOrderId);

            // Validate product was in original order
            $ordered = $returnOrder->order->items->firstWhere('product_id', $productId);

            if (! $ordered) {
                throw ReturnItemNotInOrder::forProduct($productId);
            }

            // Calculate the already returned quantity for this product
            $alreadyReturnedQty = (int) $returnOrder->items
                ->where('product_id', $productId)
                ->sum('quantity');

            $maxAllowed = (int) $ordered->quantity - $alreadyReturnedQty;

            // Validate not exceeding ordered quantity
            if ($quantity > $maxAllowed) {
                throw ReturnQuantityExceeded::forProduct(
                    productId: $productId,
                    maxAllowed: $maxAllowed,
                    requested: $quantity,
                );
            }

            // If an item already exists, increment quantity instead of creating a duplicate
            $existing = $returnOrder->items->firstWhere('product_id', $productId);

            if ($existing) {
                $existing->forceFill([
                    'quantity' => (int) $existing->quantity + $quantity,
                    'restockable' => $existing->restockable && $restockable,
                ])->save();

                return $existing->refresh();
            }

            // Create a new return item
            return ReturnItem::query()->create([
                'return_id' => $returnOrder->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'restockable' => $restockable,
            ]);
        });
    }

    // Process return and restock restockable items
    public function restockReturn(int $returnOrderId, int $actorUserId): ReturnOrder
    {
        return DB::transaction(function () use ($returnOrderId, $actorUserId) {
            $returnOrder = ReturnOrder::query()
                ->with(['order', 'items'])
                ->lockForUpdate()
                ->findOrFail($returnOrderId);

            // Restock restockable items back to inventory
            $this->inventory->restockFromReturn($returnOrder, $actorUserId);

            return $returnOrder->refresh();
        });
    }
}
