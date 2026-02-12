<?php

use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Models\InventoryStock;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Services\Inventory\InventoryLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('reserve for order increments reserved stock', function () {
    $order = createOrderWithItem(2);
    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    $service = new InventoryLedgerService;

    $service->reserveForOrder($order, $order->created_by);

    $stock->refresh();

    expect($stock->qty_reserved)->toBe(2);
});

test('reserve for order throws when insufficient available', function () {
    $order = createOrderWithItem(3);
    InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 2,
        'qty_reserved' => 0,
    ]);

    $service = new InventoryLedgerService;

    $this->expectException(InsufficientStock::class);

    $service->reserveForOrder($order, $order->created_by);
});

test('commit sale for order decrements on hand and reservations', function () {
    $order = createOrderWithItem(2);
    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 5,
        'qty_reserved' => 2,
    ]);

    $service = new InventoryLedgerService;

    $service->commitSaleForOrder($order, $order->created_by);

    $stock->refresh();

    expect($stock->qty_on_hand)->toBe(3)
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

test('commit sale for order throws when insufficient on hand', function () {
    $order = createOrderWithItem(4);
    InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $order->items->first()->product_id,
        'qty_on_hand' => 2,
        'qty_reserved' => 4,
    ]);

    $service = new InventoryLedgerService;

    $this->expectException(InsufficientStock::class);

    $service->commitSaleForOrder($order, $order->created_by);
});

test('restock from return increases on hand for restockable items only', function () {
    $order = createOrderWithItem(1);
    $product = $order->items->first()->product;
    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    $returnOrder = ReturnOrder::factory()->create([
        'order_id' => $order->id,
    ]);

    ReturnItem::factory()->create([
        'return_id' => $returnOrder->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    ReturnItem::factory()->create([
        'return_id' => $returnOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'restockable' => false,
    ]);

    $service = new InventoryLedgerService;

    $service->restockFromReturn($returnOrder, $order->created_by);

    $stock->refresh();

    expect($stock->qty_on_hand)->toBe(7);
    $this->assertDatabaseHas('inventory_movements', [
        'organization_id' => $order->organization_id,
        'product_id' => $product->id,
        'type' => 'return',
        'qty_delta' => 2,
        'reference_type' => 'Return',
        'reference_id' => $returnOrder->id,
    ]);
});
