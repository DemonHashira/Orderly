<?php

use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Models\InventoryStock;
use App\Services\Inventory\InventoryLedgerService;
use App\Services\Orders\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('transition draft to confirmed reserves stock', function () {
    $order = createOrderWithItem(quantity: 3, status: OrderStatus::Draft->value);
    $stock = createStock($order, qtyOnHand: 10, qtyReserved: 0);
    $service = makeOrderWorkflowService();

    $actorId = $order->created_by;
    $service->transition($order->id, OrderStatus::Confirmed, $actorId);

    $stock->refresh();
    $order->refresh();

    expect($order->current_status)->toBe(OrderStatus::Confirmed->value)
        ->and($stock->qty_reserved)->toBe(3);
    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => OrderStatus::Confirmed->value,
        'changed_by' => $actorId,
    ]);
});

test('transition ready to ship to shipped commits sale', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::ReadyToShip->value);
    $stock = createStock($order, qtyOnHand: 5, qtyReserved: 2);
    $service = makeOrderWorkflowService();

    $service->transition($order->id, OrderStatus::Shipped, $order->created_by);

    $stock->refresh();
    $order->refresh();

    expect($order->current_status)->toBe(OrderStatus::Shipped->value)
        ->and($stock->qty_on_hand)->toBe(3)
        ->and($stock->qty_reserved)->toBe(0);
    $this->assertDatabaseHas('inventory_movements', [
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'type' => 'sale',
        'qty_delta' => -2,
        'reference_type' => 'Order',
        'reference_id' => $order->id,
    ]);
});

test('invalid transition throws exception', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Draft->value);

    $service = makeOrderWorkflowService();

    $this->expectException(InvalidOrderTransition::class);

    $service->transition($order->id, OrderStatus::Shipped, $order->created_by);
});

test('transition confirmed to cancelled releases reserved stock', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::Confirmed->value);

    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 10,
        'qty_reserved' => 2,
    ]);

    $service = new OrderWorkflowService(new InventoryLedgerService);

    $service->transition($order->id, OrderStatus::Cancelled, $order->created_by);

    $order->refresh();
    $stock->refresh();

    expect($order->current_status)->toBe(OrderStatus::Cancelled->value)
        ->and($stock->qty_reserved)->toBe(0);

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => OrderStatus::Cancelled->value,
        'changed_by' => $order->created_by,
    ]);
});

test('transition ready to ship to cancelled releases reserved stock', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::ReadyToShip->value);

    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 10,
        'qty_reserved' => 2,
    ]);

    $service = new OrderWorkflowService(new InventoryLedgerService);

    $service->transition($order->id, OrderStatus::Cancelled, $order->created_by);

    $order->refresh();
    $stock->refresh();

    expect($order->current_status)->toBe(OrderStatus::Cancelled->value)
        ->and($stock->qty_reserved)->toBe(0);
});

test('transition to shipped rolls back when insufficient stock', function () {
    $order = createOrderWithItem(quantity: 4, status: OrderStatus::ReadyToShip->value);

    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 2,
        'qty_reserved' => 4,
    ]);

    $service = new OrderWorkflowService(new InventoryLedgerService);

    $this->expectException(InsufficientStock::class);

    try {
        $service->transition($order->id, OrderStatus::Shipped, $order->created_by);
    } finally {
        $order->refresh();
        $stock->refresh();

        expect($order->current_status)->toBe(OrderStatus::ReadyToShip->value)
            ->and($stock->qty_on_hand)->toBe(2)
            ->and($stock->qty_reserved)->toBe(4);

        $this->assertDatabaseMissing('inventory_movements', [
            'reference_type' => 'Order',
            'reference_id' => $order->id,
            'type' => 'sale',
        ]);

        $this->assertDatabaseMissing('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Shipped->value,
        ]);
    }
});
