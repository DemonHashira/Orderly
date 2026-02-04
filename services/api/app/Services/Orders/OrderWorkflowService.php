<?php

namespace App\Services\Orders;

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Domain\Orders\OrderTransitions;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Inventory\InventoryLedgerService;
use Illuminate\Support\Facades\DB;

final readonly class OrderWorkflowService
{
    public function __construct(
        private InventoryLedgerService $inventory,
    ) {}

    // Transition order to a new status
    public function transition(int $orderId, OrderStatus $to, int $actorUserId): Order
    {
        return DB::transaction(function () use ($orderId, $to, $actorUserId) {
            // Lock order for update to prevent concurrent modifications
            $order = Order::query()
                ->with(['items'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            $from = OrderStatus::from($order->current_status);

            // Validate transition is allowed
            if (! OrderTransitions::canTransition($from, $to)) {
                throw InvalidOrderTransition::between($from, $to);
            }

            // Handle inventory side effects based on transition
            if ($from === OrderStatus::Draft && $to === OrderStatus::Confirmed) {
                // Reserve stock when the order is confirmed
                $this->inventory->reserveForOrder($order, $actorUserId);
            }

            if ($to === OrderStatus::Cancelled) {
                // Release reserved stock if an order is canceled
                $this->inventory->releaseReservationForOrder($order, $actorUserId, reason: 'Order cancelled');
            }

            if ($from === OrderStatus::ReadyToShip && $to === OrderStatus::Shipped) {
                // Commit sale: decrement on-hands and release reserved
                $this->inventory->commitSaleForOrder($order, $actorUserId);
            }

            // Update order status
            $order->forceFill(['current_status' => $to->value])->save();

            // Record status change in history
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $to->value,
                'changed_by' => $actorUserId,
            ]);

            return $order->refresh();
        });
    }
}
