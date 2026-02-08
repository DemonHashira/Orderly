<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Inventory\InventoryLedgerService;
use App\Services\Orders\OrderWorkflowService;
use App\Services\Returns\ReturnService;

if (! function_exists('createOrderWithItem')) {
    function createOrderWithItem(int $quantity, ?string $status = null): Order
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
            'current_status' => $status ?? OrderStatus::Draft->value,
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

if (! function_exists('createStock')) {
    function createStock(Order $order, int $qtyOnHand, int $qtyReserved): InventoryStock
    {
        return InventoryStock::factory()->create([
            'organization_id' => $order->organization_id,
            'product_id' => $order->items->first()->product_id,
            'qty_on_hand' => $qtyOnHand,
            'qty_reserved' => $qtyReserved,
        ]);
    }
}

if (! function_exists('makeOrderWorkflowService')) {
    function makeOrderWorkflowService(): OrderWorkflowService
    {
        return new OrderWorkflowService(new InventoryLedgerService);
    }
}

if (! function_exists('makeReturnService')) {
    function makeReturnService(): ReturnService
    {
        return new ReturnService(new InventoryLedgerService);
    }
}
