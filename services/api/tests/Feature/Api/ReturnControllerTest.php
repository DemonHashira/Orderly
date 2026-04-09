<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('index returns returns scoped to organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createReturnApiUserWithRole($organization->id, 'Owner');
    [$order, $product] = createReturnedOrderWithItem($organization, $user, 2);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    $otherUser = createReturnApiUserWithRole($otherOrganization->id, 'Owner');
    [$otherOrder, $otherProduct] = createReturnedOrderWithItem($otherOrganization, $otherUser, 1);
    createReturnForOrder($otherOrder, $otherProduct, quantity: 1, restockable: true);

    Sanctum::actingAs($user);

    $this->getJson('/api/returns?per_page=10')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $returnOrder->id);
});

test('show returns same-organization return order details', function () {
    $organization = Organization::factory()->create();
    $user = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $user, 2);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    Sanctum::actingAs($user);

    $this->getJson('/api/returns/'.$returnOrder->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $returnOrder->id)
        ->assertJsonPath('data.order.id', $order->id)
        ->assertJsonCount(1, 'data.items');
});

test('show includes linked order customer name', function () {
    $organization = Organization::factory()->create();
    $user = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $user, 2);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    Sanctum::actingAs($user);

    $this->getJson('/api/returns/'.$returnOrder->id)
        ->assertStatus(200)
        ->assertJsonPath(
            'data.order.customer_name',
            trim(implode(' ', array_filter([
                $order->customer->first_name,
                $order->customer->middle_name,
                $order->customer->last_name,
            ]))),
        );
});

test('show returns 404 for cross-organization return order', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createReturnApiUserWithRole($organization->id, 'Owner');
    $otherUser = createReturnApiUserWithRole($otherOrganization->id, 'Owner');

    [$otherOrder, $otherProduct] = createReturnedOrderWithItem($otherOrganization, $otherUser, 1);
    $otherReturn = createReturnForOrder($otherOrder, $otherProduct, quantity: 1, restockable: true);

    Sanctum::actingAs($user);

    $this->getJson('/api/returns/'.$otherReturn->id)->assertStatus(404);
});

test('show by order returns return order for same-organization order', function () {
    $organization = Organization::factory()->create();
    $user = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $user, 2);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders/'.$order->id.'/return')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $returnOrder->id)
        ->assertJsonPath('data.order_id', $order->id);
});

test('show by order returns 404 for cross-organization order', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createReturnApiUserWithRole($organization->id, 'Owner');
    $otherUser = createReturnApiUserWithRole($otherOrganization->id, 'Owner');

    [$order] = createReturnedOrderWithItem($otherOrganization, $otherUser, 1);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders/'.$order->id.'/return')->assertStatus(404);
});

test('show by order returns 404 when no return exists', function () {
    $organization = Organization::factory()->create();
    $user = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order] = createReturnedOrderWithItem($organization, $user, 1);

    Sanctum::actingAs($user);

    $this->getJson('/api/orders/'.$order->id.'/return')->assertStatus(404);
});

test('owner can add item and restock return', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 3);
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ])
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.items');

    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $returnOrder->id);

    $this->assertDatabaseHas('inventory_movements', [
        'reference_type' => 'Return',
        'reference_id' => $returnOrder->id,
        'type' => 'return',
        'qty_delta' => 2,
    ]);
});

test('logistics manager is view only for returns', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');
    $logistics = createReturnApiUserWithRole($organization->id, 'Logistics Manager');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 2);
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($logistics);

    $this->getJson('/api/returns')->assertStatus(200);
    $this->getJson('/api/returns/'.$returnOrder->id)->assertStatus(200);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'product_id' => $product->id,
        'quantity' => 1,
        'restockable' => true,
    ])->assertStatus(403);

    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')->assertStatus(403);
});

test('inventory manager can add item and restock return', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');
    $inventory = createReturnApiUserWithRole($organization->id, 'Inventory Manager');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 2);
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($inventory);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'product_id' => $product->id,
        'quantity' => 1,
        'restockable' => true,
    ])
        ->assertStatus(200)
        ->assertJsonCount(1, 'data.items');

    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $returnOrder->id);

    $this->assertDatabaseHas('inventory_movements', [
        'reference_type' => 'Return',
        'reference_id' => $returnOrder->id,
        'type' => 'return',
        'qty_delta' => 1,
    ]);
});

test('order manager is view-only for returns', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');
    $orderManager = createReturnApiUserWithRole($organization->id, 'Order Manager');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 2);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    Sanctum::actingAs($orderManager);

    $this->getJson('/api/returns')->assertStatus(200);
    $this->getJson('/api/returns/'.$returnOrder->id)->assertStatus(200);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'product_id' => $product->id,
        'quantity' => 1,
        'restockable' => true,
    ])->assertStatus(403);

    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')->assertStatus(403);
});

test('add item validation errors return 422', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order] = createReturnedOrderWithItem($organization, $owner, 1);
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'quantity' => 0,
        'restockable' => 'x',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_id', 'quantity', 'restockable']);
});

test('add item product not in original order returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order] = createReturnedOrderWithItem($organization, $owner, 1);
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);
    $otherProduct = Product::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'product_id' => $otherProduct->id,
        'quantity' => 1,
        'restockable' => true,
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'return_item_not_in_order');
});

test('add item quantity exceeded returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 1);
    $returnOrder = ReturnOrder::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/returns/'.$returnOrder->id.'/items', [
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'return_quantity_exceeded');
});

test('restock is idempotent and does not duplicate movements', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 1);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')->assertStatus(200);
    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')->assertStatus(200);

    expect(InventoryMovement::query()
        ->where('reference_type', 'Return')
        ->where('reference_id', $returnOrder->id)
        ->where('type', 'return')
        ->count())->toBe(1);
});

test('restock marks return as processed and removes it from the restock queue filter', function () {
    $organization = Organization::factory()->create();
    $owner = createReturnApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createReturnedOrderWithItem($organization, $owner, 1);
    $returnOrder = createReturnForOrder($order, $product, quantity: 1, restockable: true);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/returns/'.$returnOrder->id.'/restock')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $returnOrder->id)
        ->assertJsonPath('data.restocked_at', fn ($value) => is_string($value) && $value !== '');

    $this->getJson('/api/returns?has_restockable=1')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

function createReturnApiUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}

function createReturnedOrderWithItem(Organization $organization, User $creator, int $quantity): array
{
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'sale_price' => 10.00,
    ]);

    $order = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $creator->id,
        'current_status' => OrderStatus::Returned->value,
        'total_amount' => 0,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => '10.00',
        'total_price' => number_format($quantity * 10, 2, '.', ''),
    ]);

    return [$order->refresh(), $product];
}

function createReturnForOrder(Order $order, Product $product, int $quantity, bool $restockable): ReturnOrder
{
    $returnOrder = ReturnOrder::factory()->create([
        'order_id' => $order->id,
        'reason' => 'Initial return',
    ]);

    $returnOrder->items()->create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'restockable' => $restockable,
    ]);

    return $returnOrder->refresh();
}
