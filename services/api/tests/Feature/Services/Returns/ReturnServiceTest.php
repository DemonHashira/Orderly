<?php

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Domain\Returns\Exceptions\InvalidReturnItemQuantity;
use App\Domain\Returns\Exceptions\ReturnItemNotInOrder;
use App\Domain\Returns\Exceptions\ReturnQuantityExceeded;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Services\Inventory\InventoryLedgerService;
use App\Services\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('create return for shipped order', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Shipped->value);
    $service = makeReturnService();

    $returnOrder = $service->createReturnIfMissing(
        orderId: $order->id,
        actorUserId: $order->created_by,
        reason: 'Damaged',
    );

    expect($returnOrder->order_id)->toBe($order->id);
    $this->assertDatabaseHas('return_orders', [
        'id' => $returnOrder->id,
        'order_id' => $order->id,
    ]);
});

test('create return throws for invalid status', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Draft->value);
    $service = makeReturnService();

    $this->expectException(InvalidOrderTransition::class);

    $service->createReturnIfMissing(
        orderId: $order->id,
        actorUserId: $order->created_by,
        reason: 'Not allowed',
    );
});

test('add return item validations', function () {
    $order = createOrderWithItem(quantity: 2, status: OrderStatus::Shipped->value);
    $service = makeReturnService();

    $returnOrder = $service->createReturnIfMissing(
        orderId: $order->id,
        actorUserId: $order->created_by,
        reason: 'Wrong size',
    );

    $this->expectException(InvalidReturnItemQuantity::class);
    $service->addReturnItem($returnOrder->id, $order->items->first()->product_id, 0, true);
});

test('add return item throws when product not in order', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Shipped->value);
    $service = makeReturnService();

    $returnOrder = $service->createReturnIfMissing(
        orderId: $order->id,
        actorUserId: $order->created_by,
        reason: 'Wrong product',
    );

    $otherProduct = Product::factory()->create(['organization_id' => $order->organization_id]);

    $this->expectException(ReturnItemNotInOrder::class);
    $service->addReturnItem($returnOrder->id, $otherProduct->id, 1, true);
});

test('add return item throws when quantity exceeds ordered', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Shipped->value);
    $service = makeReturnService();

    $returnOrder = $service->createReturnIfMissing(
        orderId: $order->id,
        actorUserId: $order->created_by,
        reason: 'Damaged',
    );

    $this->expectException(ReturnQuantityExceeded::class);
    $service->addReturnItem($returnOrder->id, $order->items->first()->product_id, 2, true);
});

test('restock return updates inventory', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Returned->value);
    $product = $order->items->first()->product;

    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);
    $returnOrder->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    $service = new ReturnService(new InventoryLedgerService);

    $service->restockReturn($returnOrder->id, $order->created_by);

    $stock->refresh();

    expect($stock->qty_on_hand)->toBe(7);

    $this->assertDatabaseHas('inventory_movements', [
        'organization_id' => $order->organization_id,
        'product_id' => $product->id,
        'type' => 'return',
        'qty_delta' => 2,
        'reference_id' => $returnOrder->id,
    ]);
});

function createReturnOrderWithItem(Order $order, Product $product, int $quantity, bool $restockable): ReturnOrder
{
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);
    $returnOrder->items()->create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'restockable' => $restockable,
    ]);

    return $returnOrder;
}

test('restock return is idempotent and does not double restock', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Returned->value);
    $product = $order->items->first()->product;

    $stock = InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);
    $returnOrder->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    $service = new ReturnService(new InventoryLedgerService);

    $service->restockReturn($returnOrder->id, $order->created_by);
    $service->restockReturn($returnOrder->id, $order->created_by);

    $stock->refresh();

    // Expected: only restocked once
    expect($stock->qty_on_hand)->toBe(7)
        ->and(InventoryMovement::query()
            ->where('reference_type', 'Return')
            ->where('reference_id', $returnOrder->id)
            ->where('type', 'return')
            ->count())->toBe(1);
});

test('restock return marks the return as restocked', function () {
    $order = createOrderWithItem(quantity: 1, status: OrderStatus::Returned->value);
    $product = $order->items->first()->product;

    InventoryStock::factory()->create([
        'organization_id' => $order->organization_id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    $returnOrder = createReturnOrderWithItem($order, $product, quantity: 1, restockable: true);
    $service = new ReturnService(new InventoryLedgerService);

    $service->restockReturn($returnOrder->id, $order->created_by);

    expect($returnOrder->refresh()->restocked_at)->not->toBeNull();
});
