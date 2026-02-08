<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Orders\OrderPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recalculate order totals updates line and order totals', function () {
    $org = Organization::factory()->create();
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $channel = SalesChannel::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);
    $product = Product::factory()->create([
        'organization_id' => $org->id,
        'sale_price' => 10.00,
    ]);

    $order = Order::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Draft->value,
        'total_amount' => 0,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 10.00,
        'total_price' => 1.00,
    ]);

    $service = new OrderPricingService;

    $service->recalculateOrderTotals($order);

    $item->refresh();
    $order->refresh();

    expect((string) $item->total_price)->toBe('20.00')
        ->and((string) $order->total_amount)->toBe('20.00');
});

test('validate item pricing returns mismatches', function () {
    $org = Organization::factory()->create();
    $customer = Customer::factory()->create(['organization_id' => $org->id]);
    $channel = SalesChannel::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    $product = Product::factory()->create([
        'organization_id' => $org->id,
        'sale_price' => 12.50,
    ]);

    $order = Order::factory()->create([
        'organization_id' => $org->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Draft->value,
        'total_amount' => 0,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => '10.00',
        'total_price' => '10.00',
    ]);

    $service = new OrderPricingService;

    $mismatches = $service->validateItemPricing($order);

    expect($mismatches)->toHaveCount(1)
        ->and($mismatches[0]['order_item_id'])->toBe($item->id)
        ->and($mismatches[0]['product_id'])->toBe($product->id)
        ->and($mismatches[0]['unit_price'])->toBe('10.00')
        ->and($mismatches[0]['expected_price'])->toBe('12.50');
});
