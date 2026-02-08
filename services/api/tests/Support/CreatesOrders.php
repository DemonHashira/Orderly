<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;

trait CreatesOrders
{
    private function createOrderWithItem(string $status, int $quantity): Order
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $channel = SalesChannel::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $product = Product::factory()->create(['organization_id' => $org->id]);

        $order = Order::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'sales_channel_id' => $channel->id,
            'created_by' => $user->id,
            'current_status' => $status,
            'total_amount' => 0,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => '5.00',
            'total_price' => (string) ($quantity * 5),
        ]);

        return $order->refresh()->load(['items.product']);
    }
}
