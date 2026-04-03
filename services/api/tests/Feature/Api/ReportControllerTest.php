<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

test('reports endpoints are tenant scoped and include generated_at metadata', function () {
    Carbon::setTestNow('2026-02-15 20:12:44');

    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $ownerA = createReportApiUserWithRole($orgA->id, 'Owner');
    $ownerB = createReportApiUserWithRole($orgB->id, 'Owner');

    [$customerA, $channelA] = createOrderContext($orgA->id);
    [$customerB, $channelB] = createOrderContext($orgB->id);

    Order::factory()->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customerA->id,
        'sales_channel_id' => $channelA->id,
        'created_by' => $ownerA->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '50.00',
        'created_at' => Carbon::parse('2026-02-10'),
    ]);

    Order::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'sales_channel_id' => $channelB->id,
        'created_by' => $ownerB->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '500.00',
        'created_at' => Carbon::parse('2026-02-10'),
    ]);

    $productA = Product::factory()->create(['organization_id' => $orgA->id]);
    $productB = Product::factory()->create(['organization_id' => $orgB->id]);

    InventoryStock::factory()->create([
        'organization_id' => $orgA->id,
        'product_id' => $productA->id,
        'qty_on_hand' => 10,
        'qty_reserved' => 2,
        'reorder_threshold' => 12,
    ]);

    InventoryStock::factory()->create([
        'organization_id' => $orgB->id,
        'product_id' => $productB->id,
        'qty_on_hand' => 100,
        'qty_reserved' => 5,
        'reorder_threshold' => 10,
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $orgA->id,
        'product_id' => $productA->id,
        'qty_delta' => 7,
        'created_at' => Carbon::parse('2026-02-12'),
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $orgB->id,
        'product_id' => $productB->id,
        'qty_delta' => 70,
        'created_at' => Carbon::parse('2026-02-12'),
    ]);

    $orderForReturnA = Order::factory()->create([
        'organization_id' => $orgA->id,
        'customer_id' => $customerA->id,
        'sales_channel_id' => $channelA->id,
        'created_by' => $ownerA->id,
        'current_status' => OrderStatus::Returned->value,
        'total_amount' => '0.00',
    ]);

    $returnA = ReturnOrder::factory()->create([
        'order_id' => $orderForReturnA->id,
        'returned_at' => Carbon::parse('2026-02-12'),
        'created_at' => Carbon::parse('2026-02-12'),
    ]);
    ReturnItem::factory()->create([
        'return_id' => $returnA->id,
        'product_id' => $productA->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    $orderForReturnB = Order::factory()->create([
        'organization_id' => $orgB->id,
        'customer_id' => $customerB->id,
        'sales_channel_id' => $channelB->id,
        'created_by' => $ownerB->id,
        'current_status' => OrderStatus::Returned->value,
        'total_amount' => '0.00',
    ]);

    $returnB = ReturnOrder::factory()->create([
        'order_id' => $orderForReturnB->id,
        'returned_at' => Carbon::parse('2026-02-12'),
        'created_at' => Carbon::parse('2026-02-12'),
    ]);
    ReturnItem::factory()->create([
        'return_id' => $returnB->id,
        'product_id' => $productB->id,
        'quantity' => 9,
        'restockable' => true,
    ]);

    Sanctum::actingAs($ownerA);

    $this->getJson('/api/reports/orders/summary')
        ->assertOk()
        ->assertJsonPath('data.total_orders', 2)
        ->assertJsonPath('data.total_revenue', '50.00')
        ->assertJsonPath('meta.generated_at', '2026-02-15T20:12:44.000000Z');

    $this->getJson('/api/reports/inventory/summary?from=2026-02-01&to=2026-02-28')
        ->assertOk()
        ->assertJsonPath('data.total_skus', 1)
        ->assertJsonPath('data.total_on_hand', 10)
        ->assertJsonPath('data.movement_in_qty', 7)
        ->assertJsonPath('meta.generated_at', '2026-02-15T20:12:44.000000Z');

    $this->getJson('/api/reports/returns/summary?from=2026-02-01&to=2026-02-28')
        ->assertOk()
        ->assertJsonPath('data.total_returns', 1)
        ->assertJsonPath('data.total_return_items_qty', 2)
        ->assertJsonPath('meta.generated_at', '2026-02-15T20:12:44.000000Z');

    Carbon::setTestNow();
});

test('report endpoints return 403 when user lacks granular report permissions', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/orders/summary')->assertStatus(403);
    $this->getJson('/api/reports/inventory/summary')->assertStatus(403);
    $this->getJson('/api/reports/returns/summary')->assertStatus(403);
});

test('report endpoints validate date range', function () {
    $organization = Organization::factory()->create();
    $user = createReportApiUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/orders/summary?from=2026-02-15&to=2026-02-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to']);
});

test('orders summary groups by current_status only', function () {
    $organization = Organization::factory()->create();
    $user = createReportApiUserWithRole($organization->id, 'Owner');
    [$customer, $channel] = createOrderContext($organization->id);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Draft->value,
        'total_amount' => '10.00',
    ]);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '40.00',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/orders/summary')
        ->assertOk()
        ->assertJsonPath('data.by_status.delivered', 1)
        ->assertJsonPath('data.by_status.draft', 1)
        ->assertJsonMissingPath('data.by_status.pending');
});

test('report endpoints expose enriched summary sections for ranged requests', function () {
    $organization = Organization::factory()->create();
    $user = createReportApiUserWithRole($organization->id, 'Owner');
    [$customer, $channel] = createOrderContext($organization->id);
    $websiteChannel = SalesChannel::factory()->create(['name' => 'Website']);
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Winter Jacket',
        'sku' => 'JKT-301',
    ]);

    $previousOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '120.00',
        'created_at' => '2026-03-03 10:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $previousOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => '120.00',
        'total_price' => '120.00',
    ]);

    $currentOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $websiteChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-101',
        'current_status' => OrderStatus::ReadyToShip->value,
        'total_amount' => '240.00',
        'created_at' => '2026-03-10 10:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $currentOrder->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '120.00',
        'total_price' => '240.00',
    ]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 3,
        'qty_reserved' => 2,
        'reorder_threshold' => 5,
    ]);
    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => 'restock',
        'reference_type' => 'Return',
        'qty_delta' => 7,
        'created_at' => '2026-03-10 11:00:00',
    ]);

    $currentReturn = ReturnOrder::factory()->create([
        'order_id' => $currentOrder->id,
        'reason' => 'Damaged zipper',
        'returned_at' => '2026-03-12 12:00:00',
        'created_at' => '2026-03-12 12:00:00',
    ]);
    ReturnItem::factory()->create([
        'return_id' => $currentReturn->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);
    ReturnItem::factory()->create([
        'return_id' => $currentReturn->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'restockable' => false,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/orders/summary?from=2026-03-10&to=2026-03-16')
        ->assertOk()
        ->assertJsonPath('data.comparison.metrics.total_orders.previous', 1)
        ->assertJsonPath('data.breakdowns.by_channel.0.label', 'Website')
        ->assertJsonPath('data.exceptions.backlog_orders.0.reference', 'ORD-101')
        ->assertJsonPath('data.actions.0.label', 'Open backlog orders');

    $this->getJson('/api/reports/inventory/summary?from=2026-03-10&to=2026-03-16')
        ->assertOk()
        ->assertJsonPath('data.comparison.metrics.total_available.current', 1)
        ->assertJsonPath('data.breakdowns.by_movement_type.0.label', 'Restock')
        ->assertJsonPath('data.exceptions.attention_items.0.name', 'Winter Jacket')
        ->assertJsonPath('data.actions.0.label', 'Open low stock items');

    $this->getJson('/api/reports/returns/summary?from=2026-03-10&to=2026-03-16')
        ->assertOk()
        ->assertJsonPath('data.comparison.metrics.total_returns.current', 1)
        ->assertJsonPath('data.breakdowns.by_reason.0.label', 'Damaged zipper')
        ->assertJsonPath('data.exceptions.pending_restock.0.order_reference', 'ORD-101')
        ->assertJsonPath('data.actions.0.label', 'Open restock queue');
});

test('all-time report summaries remain backward compatible without comparison blocks', function () {
    $organization = Organization::factory()->create();
    $user = createReportApiUserWithRole($organization->id, 'Owner');

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/orders/summary')
        ->assertOk()
        ->assertJsonMissingPath('data.comparison');
});

test('returns summary uses returned_at fallback to created_at for filtering', function () {
    $organization = Organization::factory()->create();
    $user = createReportApiUserWithRole($organization->id, 'Owner');
    [$customer, $channel] = createOrderContext($organization->id);
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    $orderInRange = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Returned->value,
    ]);

    $returnInRange = ReturnOrder::factory()->create([
        'order_id' => $orderInRange->id,
        'returned_at' => null,
        'created_at' => Carbon::parse('2026-02-10 10:00:00'),
    ]);

    ReturnItem::factory()->create([
        'return_id' => $returnInRange->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    $orderOutRange = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Returned->value,
    ]);

    $returnOutRange = ReturnOrder::factory()->create([
        'order_id' => $orderOutRange->id,
        'returned_at' => null,
        'created_at' => Carbon::parse('2026-01-10 10:00:00'),
    ]);

    ReturnItem::factory()->create([
        'return_id' => $returnOutRange->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'restockable' => true,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/returns/summary?from=2026-02-01&to=2026-02-28')
        ->assertOk()
        ->assertJsonPath('data.total_returns', 1)
        ->assertJsonPath('data.total_return_items_qty', 2);
});

test('logistics manager can access orders and returns reports but not inventory report', function () {
    $organization = Organization::factory()->create();
    $user = createReportApiUserWithRole($organization->id, 'Logistics Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/reports/orders/summary')->assertOk();
    $this->getJson('/api/reports/inventory/summary')->assertStatus(403);
    $this->getJson('/api/reports/returns/summary')->assertOk();
});

function createReportApiUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create([
        'organization_id' => $organizationId,
    ]);

    $user->assignRole($role);

    return $user;
}

function createOrderContext(int $organizationId): array
{
    return [
        Customer::factory()->create(['organization_id' => $organizationId]),
        SalesChannel::factory()->create(),
    ];
}
