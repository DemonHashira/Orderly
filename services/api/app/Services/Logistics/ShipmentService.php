<?php

namespace App\Services\Logistics;

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Orders\OrderWorkflowService;
use App\Services\Returns\ReturnService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class ShipmentService
{
    public function __construct(
        private OrderWorkflowService $workflow,
        private ReturnService $returns,
    ) {}

    // Create shipment record and transition order to shipped
    public function createShipment(
        int $orderId,
        int $actorUserId,
        string $courier,
        string $trackingNumber,
        ?Carbon $shippedAt = null,
    ): Shipment {
        $shippedAt ??= now();

        return DB::transaction(function () use ($orderId, $actorUserId, $courier, $trackingNumber, $shippedAt) {
            $order = Order::query()->with(['shipment', 'items'])->lockForUpdate()->findOrFail($orderId);
            $status = OrderStatus::from($order->current_status);

            // Validate order is ready to ship
            if (! in_array($status, [OrderStatus::ReadyToShip, OrderStatus::Shipped], true)) {
                throw InvalidOrderTransition::forStatus($status, 'create shipment');
            }

            // Transition to shipped if not already
            if ($status === OrderStatus::ReadyToShip) {
                $this->workflow->transition($order->id, OrderStatus::Shipped, $actorUserId);
                $order->refresh();
            }

            // Create or update shipment record
            $shipment = Shipment::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'courier' => $courier,
                    'tracking_number' => $trackingNumber,
                    'shipped_at' => $shippedAt,
                ]
            );

            return $shipment->refresh();
        });
    }

    // Mark shipment as delivered and transition order status
    public function markDelivered(int $shipmentId, int $actorUserId, ?Carbon $deliveredAt = null): Shipment
    {
        $deliveredAt ??= now();

        return DB::transaction(function () use ($shipmentId, $actorUserId, $deliveredAt) {
            $shipment = Shipment::query()->with(['order'])->lockForUpdate()->findOrFail($shipmentId);

            // Update delivery timestamp
            $shipment->forceFill(['delivered_at' => $deliveredAt])->save();

            $order = $shipment->order()->lockForUpdate()->firstOrFail();
            $status = OrderStatus::from($order->current_status);

            // Transition order to delivered if currently shipped
            if ($status === OrderStatus::Shipped) {
                $this->workflow->transition($order->id, OrderStatus::Delivered, $actorUserId);
            }

            return $shipment->refresh();
        });
    }

    // Mark a shipment as returned and create a return record
    public function markReturned(int $shipmentId, int $actorUserId, string $reason, ?Carbon $returnedAt = null): array
    {
        return $this->handleFailedDelivery(
            shipmentId: $shipmentId,
            actorUserId: $actorUserId,
            targetStatus: OrderStatus::Returned,
            reason: $reason,
            returnedAt: $returnedAt
        );
    }

    // Mark a shipment as unpaid and create a return record
    public function markUnpaid(int $shipmentId, int $actorUserId, string $reason, ?Carbon $returnedAt = null): array
    {
        return $this->handleFailedDelivery(
            shipmentId: $shipmentId,
            actorUserId: $actorUserId,
            targetStatus: OrderStatus::Unpaid,
            reason: $reason,
            returnedAt: $returnedAt
        );
    }

    // Handle failed delivery outcomes (returned or unpaid)
    private function handleFailedDelivery(
        int $shipmentId,
        int $actorUserId,
        OrderStatus $targetStatus,
        string $reason,
        ?Carbon $returnedAt = null,
    ): array {
        $returnedAt ??= now();

        return DB::transaction(function () use ($shipmentId, $actorUserId, $targetStatus, $reason, $returnedAt) {
            $shipment = Shipment::query()->with(['order'])->lockForUpdate()->findOrFail($shipmentId);

            $order = $shipment->order()->lockForUpdate()->firstOrFail();
            $status = OrderStatus::from($order->current_status);

            // Transition order to target status if currently shipped
            if ($status === OrderStatus::Shipped) {
                $this->workflow->transition($order->id, $targetStatus, $actorUserId);
                $order->refresh();
            }

            // Create a return record
            $returnOrder = $this->returns->createReturnIfMissing(
                orderId: $order->id,
                actorUserId: $actorUserId,
                reason: $reason,
                returnedAt: $returnedAt,
            );

            return [$shipment->refresh(), $returnOrder];
        });
    }
}
