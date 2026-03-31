<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
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

test('index returns paginated shipments scoped to organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'courier' => 'DHL',
        'tracking_number' => 'TRACK-SCOPE',
    ]);

    $otherUser = createShipmentApiUserWithRole($otherOrganization->id, 'Owner');
    [$otherOrder] = createOrderForShipmentApi($otherOrganization, $otherUser, OrderStatus::Shipped->value, 1);
    Shipment::factory()->create([
        'order_id' => $otherOrder->id,
        'courier' => 'DHL',
        'tracking_number' => 'TRACK-OTHER',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/shipments?courier=dhl&tracking_number=scope&per_page=10')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $shipment->id)
        ->assertJsonPath('data.0.order.id', $order->id);
});

test('index filters shipments by outcome', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$shippedOrder] = createOrderForShipmentApi($organization, $user, OrderStatus::Shipped->value, 1);
    $shippedShipment = Shipment::factory()->create([
        'order_id' => $shippedOrder->id,
        'tracking_number' => 'TRK-SHIPPED',
    ]);

    [$deliveredOrder] = createOrderForShipmentApi($organization, $user, OrderStatus::Delivered->value, 1);
    Shipment::factory()->create([
        'order_id' => $deliveredOrder->id,
        'tracking_number' => 'TRK-DELIVERED',
    ]);

    [$returnedOrder] = createOrderForShipmentApi($organization, $user, OrderStatus::Returned->value, 1);
    Shipment::factory()->create([
        'order_id' => $returnedOrder->id,
        'tracking_number' => 'TRK-RETURNED',
    ]);

    [$unpaidOrder] = createOrderForShipmentApi($organization, $user, OrderStatus::Unpaid->value, 1);
    Shipment::factory()->create([
        'order_id' => $unpaidOrder->id,
        'tracking_number' => 'TRK-UNPAID',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/shipments?outcome=shipped&per_page=10')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $shippedShipment->id)
        ->assertJsonPath('data.0.order.current_status', OrderStatus::Shipped->value);
});

test('show returns same-organization shipment', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Owner');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/shipments/'.$shipment->id)
        ->assertStatus(200)
        ->assertJsonPath('data.id', $shipment->id)
        ->assertJsonPath('data.order.id', $order->id);
});

test('show returns 404 for cross-organization shipment', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createShipmentApiUserWithRole($organization->id, 'Owner');
    $otherUser = createShipmentApiUserWithRole($otherOrganization->id, 'Owner');

    [$order] = createOrderForShipmentApi($otherOrganization, $otherUser, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/shipments/'.$shipment->id)->assertStatus(404);
});

test('logistics manager can create shipment for ready-to-ship order', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order, $product] = createOrderForShipmentApi($organization, $user, OrderStatus::ReadyToShip->value, 2);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 2,
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/orders/'.$order->id.'/shipments', [
        'courier' => 'DHL',
        'tracking_number' => 'TRK-0001',
    ]);

    $response
        ->assertStatus(201)
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonPath('data.courier', 'DHL')
        ->assertJsonPath('data.order.current_status', OrderStatus::Shipped->value);

    $this->assertDatabaseHas('shipments', [
        'order_id' => $order->id,
        'tracking_number' => 'TRK-0001',
    ]);
});

test('create shipment for invalid order status returns 409', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Draft->value, 1);

    Sanctum::actingAs($user);

    $this->postJson('/api/orders/'.$order->id.'/shipments', [
        'courier' => 'UPS',
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_order_transition');
});

test('create shipment is idempotent and does not duplicate sale movement', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order, $product] = createOrderForShipmentApi($organization, $user, OrderStatus::ReadyToShip->value, 2);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 2,
    ]);

    Sanctum::actingAs($user);

    $first = $this->postJson('/api/orders/'.$order->id.'/shipments', [
        'courier' => 'DHL',
        'tracking_number' => 'TRK-1000',
    ]);

    $second = $this->postJson('/api/orders/'.$order->id.'/shipments', [
        'courier' => 'DHL',
        'tracking_number' => 'TRK-1001',
    ]);

    $first->assertStatus(201);
    $second->assertStatus(201);

    expect($second->json('data.id'))->toBe($first->json('data.id'));

    $this->assertDatabaseCount('shipments', 1);
    expect(InventoryMovement::query()
        ->where('reference_type', 'Order')
        ->where('reference_id', $order->id)
        ->where('type', 'sale')
        ->count())->toBe(1);
});

test('logistics manager can mark shipment delivered', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id, 'delivered_at' => null]);

    Sanctum::actingAs($user);

    $this->postJson('/api/shipments/'.$shipment->id.'/delivered')
        ->assertStatus(200)
        ->assertJsonPath('data.order.current_status', OrderStatus::Delivered->value);
});

test('mark delivered before shipped returns 409', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Confirmed->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/shipments/'.$shipment->id.'/delivered')
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_order_transition');
});

test('logistics manager can mark shipment returned and response includes return summary', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/shipments/'.$shipment->id.'/returned', [
        'reason' => 'Customer refused package',
    ])
        ->assertStatus(200)
        ->assertJsonPath('shipment.id', $shipment->id)
        ->assertJsonPath('shipment.order.current_status', OrderStatus::Returned->value)
        ->assertJsonPath('return.order_id', $order->id)
        ->assertJsonPath('return.reason', 'Customer refused package');

    $this->assertDatabaseHas('return_orders', [
        'order_id' => $order->id,
        'reason' => 'Customer refused package',
    ]);
});

test('logistics manager can mark shipment unpaid and response includes return summary', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/shipments/'.$shipment->id.'/unpaid', [
        'reason' => 'COD payment failed',
    ])
        ->assertStatus(200)
        ->assertJsonPath('shipment.id', $shipment->id)
        ->assertJsonPath('shipment.order.current_status', OrderStatus::Unpaid->value)
        ->assertJsonPath('return.order_id', $order->id)
        ->assertJsonPath('return.reason', 'COD payment failed');

    $this->assertDatabaseHas('return_orders', [
        'order_id' => $order->id,
        'reason' => 'COD payment failed',
    ]);
});

test('mark returned before shipped returns 409', function () {
    $organization = Organization::factory()->create();
    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');

    [$order] = createOrderForShipmentApi($organization, $user, OrderStatus::Confirmed->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/shipments/'.$shipment->id.'/returned', [
        'reason' => 'Invalid state',
    ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_order_transition');
});

test('order and inventory managers can view shipments but cannot mutate', function () {
    $organization = Organization::factory()->create();

    $owner = createShipmentApiUserWithRole($organization->id, 'Owner');
    $orderManager = createShipmentApiUserWithRole($organization->id, 'Order Manager');
    $inventoryManager = createShipmentApiUserWithRole($organization->id, 'Inventory Manager');

    [$order, $product] = createOrderForShipmentApi($organization, $owner, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 0,
    ]);

    Sanctum::actingAs($orderManager);
    $this->getJson('/api/shipments')->assertStatus(200);
    $this->getJson('/api/shipments/'.$shipment->id)->assertStatus(200);
    $this->postJson('/api/orders/'.$order->id.'/shipments', [
        'courier' => 'DHL',
    ])->assertStatus(403);

    Sanctum::actingAs($inventoryManager);
    $this->getJson('/api/shipments')->assertStatus(403);
    $this->getJson('/api/shipments/'.$shipment->id)->assertStatus(403);
    $this->postJson('/api/shipments/'.$shipment->id.'/delivered')->assertStatus(403);
});

test('owner can perform shipment lifecycle actions', function () {
    $organization = Organization::factory()->create();
    $owner = createShipmentApiUserWithRole($organization->id, 'Owner');

    [$order, $product] = createOrderForShipmentApi($organization, $owner, OrderStatus::ReadyToShip->value, 1);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 5,
        'qty_reserved' => 1,
    ]);

    Sanctum::actingAs($owner);

    $create = $this->postJson('/api/orders/'.$order->id.'/shipments', [
        'courier' => 'DHL',
        'tracking_number' => 'OWNER-TRK',
    ])->assertStatus(201);

    $shipmentId = (int) $create->json('data.id');

    $this->postJson('/api/shipments/'.$shipmentId.'/delivered')
        ->assertStatus(200)
        ->assertJsonPath('data.order.current_status', OrderStatus::Delivered->value);
});

test('cross-organization outcome endpoints return 404', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createShipmentApiUserWithRole($organization->id, 'Logistics Manager');
    $otherUser = createShipmentApiUserWithRole($otherOrganization->id, 'Owner');

    [$order] = createOrderForShipmentApi($otherOrganization, $otherUser, OrderStatus::Shipped->value, 1);
    $shipment = Shipment::factory()->create(['order_id' => $order->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/shipments/'.$shipment->id.'/delivered')->assertStatus(404);
    $this->postJson('/api/shipments/'.$shipment->id.'/returned', ['reason' => 'x'])->assertStatus(404);
    $this->postJson('/api/shipments/'.$shipment->id.'/unpaid', ['reason' => 'x'])->assertStatus(404);
});

function createShipmentApiUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}

function createOrderForShipmentApi(Organization $organization, User $creator, string $status, int $quantity): array
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
        'current_status' => $status,
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
