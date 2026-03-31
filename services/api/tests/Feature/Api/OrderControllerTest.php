<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\SalesChannel;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

function displayNameForCustomer(Customer $customer): string
{
    return trim(implode(' ', array_filter([
        $customer->first_name,
        $customer->middle_name,
        $customer->last_name,
    ])));
}

test('index returns paginated organization orders and supports filters', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrganization->id]);
    $channel = SalesChannel::factory()->create();

    $matchingOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-SEARCH-001',
        'current_status' => OrderStatus::Draft->value,
    ]);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-NOT-MATCH',
        'current_status' => OrderStatus::Cancelled->value,
    ]);

    Order::factory()->create([
        'organization_id' => $otherOrganization->id,
        'customer_id' => $otherCustomer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => User::factory()->create(['organization_id' => $otherOrganization->id])->id,
        'reference' => 'ORD-SEARCH-OTHER',
        'current_status' => OrderStatus::Draft->value,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/orders?q=search&status=draft&per_page=10');

    $response
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingOrder->id)
        ->assertJsonPath('data.0.customer_name', displayNameForCustomer($customer))
        ->assertJsonPath('data.0.sales_channel_name', $channel->name)
        ->assertJsonMissingPath('data.0.status_history');
});

test('index filters by customer channel and created date range', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');
    $matchingCustomer = Customer::factory()->create(['organization_id' => $organization->id]);
    $otherCustomer = Customer::factory()->create(['organization_id' => $organization->id]);
    $matchingChannel = SalesChannel::factory()->create();
    $otherChannel = SalesChannel::factory()->create();

    $matchingOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $matchingCustomer->id,
        'sales_channel_id' => $matchingChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-FILTER-001',
        'current_status' => OrderStatus::Draft->value,
        'created_at' => '2026-02-10 10:00:00',
    ]);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $otherCustomer->id,
        'sales_channel_id' => $matchingChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-FILTER-002',
        'current_status' => OrderStatus::Draft->value,
        'created_at' => '2026-02-10 10:00:00',
    ]);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $matchingCustomer->id,
        'sales_channel_id' => $otherChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-FILTER-003',
        'current_status' => OrderStatus::Draft->value,
        'created_at' => '2026-02-10 10:00:00',
    ]);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $matchingCustomer->id,
        'sales_channel_id' => $matchingChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-FILTER-004',
        'current_status' => OrderStatus::Draft->value,
        'created_at' => '2026-03-10 10:00:00',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(
        '/api/orders?customer_id='.$matchingCustomer->id
        .'&sales_channel_id='.$matchingChannel->id
        .'&created_from=2026-02-01'
        .'&created_to=2026-02-28',
    );

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingOrder->id);
});

test('index validates status and per page query params', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/orders?status=not-a-real-status&per_page=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status', 'per_page']);
});

test('show returns order details with status history for same organization', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Owner');

    $order = createOrderForOrg($organization, $user, OrderStatus::Draft->value);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => Product::factory()->create(['organization_id' => $organization->id])->id,
        'quantity' => 2,
        'unit_price' => '10.00',
        'total_price' => '20.00',
    ]);

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => OrderStatus::Draft->value,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders/'.$order->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.customer_name', displayNameForCustomer($order->customer))
        ->assertJsonPath('data.sales_channel_name', $order->salesChannel->name)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonCount(1, 'data.status_history');
});

test('show returns 404 for cross organization order', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createOrderApiUserWithRole($organization->id, 'Owner');
    $otherUser = User::factory()->create(['organization_id' => $otherOrganization->id]);
    $order = createOrderForOrg($otherOrganization, $otherUser, OrderStatus::Draft->value);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders/'.$order->id)->assertStatus(404);
});

test('order manager can create draft order with items and totals', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $productA = Product::factory()->create(['organization_id' => $organization->id, 'sale_price' => 8.50]);
    $productB = Product::factory()->create(['organization_id' => $organization->id, 'sale_price' => 5.00]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'internal_notes' => 'priority customer',
        'items' => [
            [
                'product_id' => $productA->id,
                'quantity' => 2,
            ],
            [
                'product_id' => $productB->id,
                'quantity' => 1,
                'unit_price' => '4.50',
            ],
        ],
    ]);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.current_status', OrderStatus::Draft->value)
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.total_amount', '21.50')
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(1, 'data.status_history');

    $orderId = (int) $response->json('data.id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'organization_id' => $organization->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Draft->value,
    ]);

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $orderId,
        'status' => OrderStatus::Draft->value,
        'changed_by' => $user->id,
    ]);
});

test('store rejects empty items', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('items');
});

test('store rejects prohibited ownership and status fields', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'organization_id' => 999999,
        'created_by' => 777,
        'current_status' => OrderStatus::Shipped->value,
        'total_amount' => '99.99',
        'reference' => 'ORD-SPOOFED',
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'organization_id',
            'created_by',
            'current_status',
            'total_amount',
            'reference',
        ]);
});

test('store rejects cross organization customer and product', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    $customer = Customer::factory()->create(['organization_id' => $otherOrganization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $otherOrganization->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id', 'items.0.product_id']);
});

test('order manager can confirm draft order', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order, $product] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 2);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$order->id.'/confirm')
        ->assertStatus(200)
        ->assertJsonPath('data.current_status', OrderStatus::Confirmed->value);

    $this->assertDatabaseHas('order_status_histories', [
        'order_id' => $order->id,
        'status' => OrderStatus::Confirmed->value,
        'changed_by' => $user->id,
    ]);
});

test('order manager can update draft order and replace items', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 1);

    $newCustomer = Customer::factory()->create(['organization_id' => $organization->id]);
    $newChannel = SalesChannel::factory()->create();
    $productA = Product::factory()->create(['organization_id' => $organization->id, 'sale_price' => 6.00]);
    $productB = Product::factory()->create(['organization_id' => $organization->id, 'sale_price' => 4.00]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/orders/'.$order->id, [
        'customer_id' => $newCustomer->id,
        'sales_channel_id' => $newChannel->id,
        'internal_notes' => 'updated notes',
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 3],
            ['product_id' => $productB->id, 'quantity' => 2, 'unit_price' => '3.50'],
        ],
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('data.customer_id', $newCustomer->id)
        ->assertJsonPath('data.sales_channel_id', $newChannel->id)
        ->assertJsonPath('data.total_amount', '25.00')
        ->assertJsonCount(2, 'data.items');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'customer_id' => $newCustomer->id,
        'sales_channel_id' => $newChannel->id,
    ]);
});

test('update returns 409 when order is not draft', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Confirmed->value, quantity: 1);

    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->putJson('/api/orders/'.$order->id, [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'order_update_not_allowed');
});

test('logistics manager cannot update order', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');
    $logistics = createOrderApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Draft->value, quantity: 1);
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($logistics);

    $this->putJson('/api/orders/'.$order->id, [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])->assertStatus(403);
});

test('update returns 404 for cross organization order', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');
    $otherUser = createOrderApiUserWithRole($otherOrganization->id, 'Owner');

    [$order] = createOrderWithSingleItemForOrg($otherOrganization, $otherUser, OrderStatus::Draft->value, quantity: 1);
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->putJson('/api/orders/'.$order->id, [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])->assertStatus(404);
});

test('update rejects prohibited and invalid item fields', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order, $product] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 1);

    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();

    Sanctum::actingAs($user);

    $this->putJson('/api/orders/'.$order->id, [
        'organization_id' => 999999,
        'created_by' => 777,
        'current_status' => OrderStatus::Cancelled->value,
        'total_amount' => '99.99',
        'reference' => 'ORD-SPOOFED',
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 0,
                'unit_price' => '10.999',
            ],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'organization_id',
            'created_by',
            'current_status',
            'total_amount',
            'reference',
            'items.0.quantity',
            'items.0.unit_price',
        ]);
});

test('update rejects cross organization customer and product', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 1);

    $customer = Customer::factory()->create(['organization_id' => $otherOrganization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $otherOrganization->id]);

    Sanctum::actingAs($user);

    $this->putJson('/api/orders/'.$order->id, [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id', 'items.0.product_id']);
});

test('order manager can move confirmed order to ready to ship', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Confirmed->value, quantity: 1);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$order->id.'/ready-to-ship')
        ->assertStatus(200)
        ->assertJsonPath('data.current_status', OrderStatus::ReadyToShip->value);
});

test('confirm returns 409 when stock is insufficient', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order, $product] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 3);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 1,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$order->id.'/confirm')
        ->assertStatus(409)
        ->assertJsonPath('code', 'insufficient_stock');
});

test('order manager can cancel draft order', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 1);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$order->id.'/cancel')
        ->assertStatus(200)
        ->assertJsonPath('data.current_status', OrderStatus::Cancelled->value);
});

test('invalid transition returns 409', function () {
    $organization = Organization::factory()->create();
    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $user, OrderStatus::Draft->value, quantity: 1);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$order->id.'/ready-to-ship')
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_order_transition');
});

test('logistics and inventory managers can view orders but cannot mutate', function () {
    $organization = Organization::factory()->create();
    $orderManager = createOrderApiUserWithRole($organization->id, 'Order Manager');
    $logistics = createOrderApiUserWithRole($organization->id, 'Logistics Manager');
    $inventory = createOrderApiUserWithRole($organization->id, 'Inventory Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $orderManager, OrderStatus::Draft->value, quantity: 1);
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($logistics);
    $this->getJson('/api/orders')->assertStatus(200);
    $this->getJson('/api/orders/'.$order->id)->assertStatus(200);
    $this->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ])->assertStatus(403);

    Sanctum::actingAs($inventory);
    $this->getJson('/api/orders')->assertStatus(200);
    $this->getJson('/api/orders/'.$order->id)->assertStatus(200);
    $this->postJson('/api/orders/'.$order->id.'/cancel')->assertStatus(403);
});

test('logistics manager cannot confirm or ready orders to ship', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');
    $logistics = createOrderApiUserWithRole($organization->id, 'Logistics Manager');

    [$draftOrder, $product] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Draft->value, quantity: 1);
    [$confirmedOrder] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Confirmed->value, quantity: 1);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($logistics);

    $this->postJson('/api/orders/'.$draftOrder->id.'/confirm')->assertForbidden();
    $this->postJson('/api/orders/'.$confirmedOrder->id.'/ready-to-ship')->assertForbidden();
});

test('confirm and ready to ship return 404 for cross organization orders', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createOrderApiUserWithRole($organization->id, 'Order Manager');
    $otherUser = createOrderApiUserWithRole($otherOrganization->id, 'Owner');

    [$draftOrder] = createOrderWithSingleItemForOrg($otherOrganization, $otherUser, OrderStatus::Draft->value, quantity: 1);
    [$confirmedOrder] = createOrderWithSingleItemForOrg($otherOrganization, $otherUser, OrderStatus::Confirmed->value, quantity: 1);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$draftOrder->id.'/confirm')->assertNotFound();
    $this->postJson('/api/orders/'.$confirmedOrder->id.'/ready-to-ship')->assertNotFound();
});

test('owner can delete draft order', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');

    [$order] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Draft->value, quantity: 1);

    Sanctum::actingAs($owner);

    $this->deleteJson('/api/orders/'.$order->id)->assertStatus(204);

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    $this->assertDatabaseMissing('order_items', ['order_id' => $order->id]);
});

test('delete returns 409 when order is not draft', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');

    [$order] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Confirmed->value, quantity: 1);

    Sanctum::actingAs($owner);

    $this->deleteJson('/api/orders/'.$order->id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'order_delete_not_allowed');

    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

test('order manager cannot delete orders', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');
    $orderManager = createOrderApiUserWithRole($organization->id, 'Order Manager');

    [$order] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Draft->value, quantity: 1);

    Sanctum::actingAs($orderManager);

    $this->deleteJson('/api/orders/'.$order->id)->assertStatus(403);
});

test('delete returns 404 for cross organization order', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $owner = createOrderApiUserWithRole($organization->id, 'Owner');
    $otherOwner = createOrderApiUserWithRole($otherOrganization->id, 'Owner');

    [$order] = createOrderWithSingleItemForOrg($otherOrganization, $otherOwner, OrderStatus::Draft->value, quantity: 1);

    Sanctum::actingAs($owner);

    $this->deleteJson('/api/orders/'.$order->id)->assertStatus(404);
});

test('delete returns 409 when order has a linked shipment', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');

    [$order] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Draft->value, quantity: 1);
    Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($owner);

    $this->deleteJson('/api/orders/'.$order->id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'order_delete_not_allowed');
});

test('delete returns 409 when order has a linked return', function () {
    $organization = Organization::factory()->create();
    $owner = createOrderApiUserWithRole($organization->id, 'Owner');

    [$order] = createOrderWithSingleItemForOrg($organization, $owner, OrderStatus::Draft->value, quantity: 1);
    ReturnOrder::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($owner);

    $this->deleteJson('/api/orders/'.$order->id)
        ->assertStatus(409)
        ->assertJsonPath('code', 'order_delete_not_allowed');
});

function createOrderApiUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}

function createOrderForOrg(Organization $organization, User $creator, string $status): Order
{
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();

    $order = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $creator->id,
        'current_status' => $status,
        'total_amount' => 0,
    ]);

    $thisStatus = $status;
    OrderStatusHistory::query()->create([
        'order_id' => $order->id,
        'status' => $thisStatus,
        'changed_by' => $creator->id,
    ]);

    return $order;
}

function createOrderWithSingleItemForOrg(Organization $organization, User $creator, string $status, int $quantity): array
{
    $order = createOrderForOrg($organization, $creator, $status);

    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sale_price' => 10.00,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => '10.00',
        'total_price' => number_format($quantity * 10, 2, '.', ''),
    ]);

    $order->refresh();

    return [$order, $product];
}
