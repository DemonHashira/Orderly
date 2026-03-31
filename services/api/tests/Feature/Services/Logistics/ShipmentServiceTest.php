<?php

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Shipment;
use App\Services\Inventory\InventoryLedgerService;
use App\Services\Logistics\ShipmentService;
use App\Services\Orders\OrderWorkflowService;
use App\Services\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('create shipment transitions and creates record', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::ReadyToShip->value);
    InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 5,
        'qty_reserved' => 2,
    ]);

    $service = makeShipmentService();

    $shipment = $service->createShipment(
        orderId: $order->id,
        actorUserId: $order->created_by,
        courier: 'DHL',
        trackingNumber: 'TRACK123',
    );

    $order->refresh();

    expect($order->current_status)->toBe(OrderStatus::Shipped->value)
        ->and($shipment->order_id)->toBe($order->id)
        ->and($shipment->courier)->toBe('DHL');
});

test('create shipment throws for invalid status', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Draft->value);

    $service = makeShipmentService();

    $this->expectException(InvalidOrderTransition::class);

    $service->createShipment(
        orderId: $order->id,
        actorUserId: $order->created_by,
        courier: 'UPS',
        trackingNumber: 'TRACK999',
    );
});

test('mark delivered transitions order', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Shipped->value);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $service = makeShipmentService();

    $updated = $service->markDelivered($shipment->id, $order->created_by);

    $order->refresh();

    expect($order->current_status)->toBe(OrderStatus::Delivered->value)
        ->and($updated->delivered_at)->not->toBeNull();
});

test('mark returned creates return and updates status', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Shipped->value);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $service = makeShipmentService();

    [$updatedShipment, $returnOrder] = $service->markReturned(
        shipmentId: $shipment->id,
        actorUserId: $order->created_by,
        reason: 'Customer refused',
    );

    $order->refresh();

    expect($order->current_status)->toBe(OrderStatus::Returned->value)
        ->and($returnOrder->order_id)->toBe($order->id)
        ->and($updatedShipment->id)->toBe($shipment->id);
});

test('mark unpaid creates return and updates status', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Shipped->value);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    $service = makeShipmentService();

    [$updatedShipment, $returnOrder] = $service->markUnpaid(
        shipmentId: $shipment->id,
        actorUserId: $order->created_by,
        reason: 'Payment failed',
    );

    $order->refresh();

    expect($order->current_status)->toBe(OrderStatus::Unpaid->value)
        ->and($returnOrder->order_id)->toBe($order->id)
        ->and($updatedShipment->id)->toBe($shipment->id);
});

test('create shipment is idempotent', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::ReadyToShip->value);
    $productId = $order->items->first()->product_id;
    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $productId,
        'qty_on_hand' => 5,
        'qty_reserved' => 2,
    ]);

    $service = makeShipmentService();

    $service->createShipment(
        orderId: $order->id,
        actorUserId: $order->created_by,
        courier: 'DHL',
        trackingNumber: 'TRACK123',
    );

    $service->createShipment(
        orderId: $order->id,
        actorUserId: $order->created_by,
        courier: 'DHL',
        trackingNumber: 'TRACK123',
    );

    $stock->refresh();

    expect($stock->qty_on_hand)->toBe(3)
        ->and($stock->qty_reserved)->toBe(0)
        ->and(InventoryMovement::query()->where([
            'reference_type' => 'Order',
            'reference_id' => $order->id,
            'type' => 'sale',
        ])->count())->toBe(1);
});

function makeShipmentService(): ShipmentService
{
    $inventory = new InventoryLedgerService;
    $workflow = new OrderWorkflowService($inventory);
    $returns = new ReturnService($inventory);

    return new ShipmentService($workflow, $returns);
}

test('create shipment is idempotent and does not double sale movement', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::ReadyToShip->value);

    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 5,
        'qty_reserved' => 2,
    ]);

    $service = makeShipmentService();

    $first = $service->createShipment(
        orderId: $order->id,
        actorUserId: $order->created_by,
        courier: 'DHL',
        trackingNumber: 'TRACK123',
    );

    $order->refresh();
    $stock->refresh();

    expect($order->current_status)->toBe(OrderStatus::Shipped->value)
        ->and($stock->qty_on_hand)->toBe(3)
        ->and($stock->qty_reserved)->toBe(0)
        ->and(InventoryMovement::query()
            ->where('reference_type', 'Order')
            ->where('reference_id', $order->id)
            ->where('type', 'sale')
            ->count())->toBe(1);

    // Call again (already shipped) — should not create another sale movement or decrement stock again
    $second = $service->createShipment(
        orderId: $order->id,
        actorUserId: $order->created_by,
        courier: 'DHL',
        trackingNumber: 'TRACK456',
    );

    $order->refresh();
    $stock->refresh();

    expect($order->current_status)->toBe(OrderStatus::Shipped->value)
        ->and($stock->qty_on_hand)->toBe(3)
        ->and($stock->qty_reserved)->toBe(0)
        ->and(InventoryMovement::query()
            ->where('reference_type', 'Order')
            ->where('reference_id', $order->id)
            ->where('type', 'sale')
            ->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->tracking_number)->toBe('TRACK456');

});
