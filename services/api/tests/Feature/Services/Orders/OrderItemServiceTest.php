<?php

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('add item creates item and recalculates totals', function () {
    $order = createOrder(OrderStatus::Draft->value);
    $product = Product::factory()->create([
        'organization_id' => $order->organization_id,
        'sale_price' => 7.50,
    ]);

    $service = new OrderItemService(new OrderPricingService);

    $item = $service->addItem(
        orderId: $order->id,
        productId: $product->id,
        quantity: 2,
        unitPrice: '7.50',
    );

    $order->refresh();

    expect($item->product_id)->toBe($product->id)
        ->and((string) $item->total_price)->toBe('15.00')
        ->and((string) $order->total_amount)->toBe('15.00');
});

test('add item fails when order not draft', function () {
    $order = createOrder(OrderStatus::Confirmed->value);
    $product = Product::factory()->create([
        'organization_id' => $order->organization_id,
    ]);

    $service = new OrderItemService(new OrderPricingService);

    $this->expectException(InvalidOrderTransition::class);

    $service->addItem(
        orderId: $order->id,
        productId: $product->id,
        quantity: 1,
        unitPrice: '5.00',
    );
});

test('update item fails for invalid quantity', function () {
    $order = createOrder(OrderStatus::Draft->value);
    $product = Product::factory()->create([
        'organization_id' => $order->organization_id,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => '5.00',
        'total_price' => '5.00',
    ]);

    $service = new OrderItemService(new OrderPricingService);

    $this->expectException(InvalidArgumentException::class);

    $service->updateItem(
        orderItemId: $item->id,
        quantity: 0,
        unitPrice: '5.00',
    );
});

test('remove item deletes item and recalculates totals', function () {
    $order = createOrder(OrderStatus::Draft->value);
    $product = Product::factory()->create([
        'organization_id' => $order->organization_id,
        'sale_price' => 10.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '10.00',
        'total_price' => '20.00',
    ]);

    $service = new OrderItemService(new OrderPricingService);

    $service->removeItem($item->id);

    $order->refresh();

    $this->assertDatabaseMissing('order_items', ['id' => $item->id]);
    expect((string) $order->total_amount)->toBe('0.00');
});

function createOrder(string $status): Order
{
    $org = Organization::factory()->create();
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $channel = SalesChannel::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    return Order::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => $status,
        'total_amount' => 0,
    ]);
}
