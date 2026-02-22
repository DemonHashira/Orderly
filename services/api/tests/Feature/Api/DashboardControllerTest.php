<?php

use App\Domain\Orders\OrderStatus;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ReturnItem;
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

test('owner dashboard returns consolidated response and metadata', function () {
    $organization = Organization::factory()->create();
    $user = createDashboardApiUserWithRole($organization->id, 'Owner');
    $customer = Customer::factory()->create(['organization_id' => $organization->id]);
    $channel = SalesChannel::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    $order = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '99.00',
    ]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 30,
        'qty_reserved' => 5,
        'reorder_threshold' => 8,
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_delta' => 12,
    ]);

    $return = ReturnOrder::factory()->create([
        'order_id' => $order->id,
    ]);

    ReturnItem::factory()->create([
        'return_id' => $return->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'restockable' => true,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'range',
                'orders',
                'inventory',
                'returns',
            ],
            'meta' => ['generated_at'],
        ])
        ->assertJsonPath('data.orders.total_orders', 1)
        ->assertJsonPath('data.inventory.total_skus', 1)
        ->assertJsonPath('data.returns.total_returns', 1);
});

test('dashboard is tenant scoped', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();

    $user = createDashboardApiUserWithRole($organization->id, 'Owner');
    $otherUser = createDashboardApiUserWithRole($otherOrganization->id, 'Owner');

    [$customer, $channel] = createDashboardOrderContext($organization->id);
    [$otherCustomer, $otherChannel] = createDashboardOrderContext($otherOrganization->id);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '10.00',
    ]);

    Order::factory()->create([
        'organization_id' => $otherOrganization->id,
        'customer_id' => $otherCustomer->id,
        'sales_channel_id' => $otherChannel->id,
        'created_by' => $otherUser->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '100.00',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.orders.total_orders', 1)
        ->assertJsonPath('data.orders.total_revenue', '10.00');
});

test('dashboard requires dashboard.view permission', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')->assertStatus(403);
});

test('logistics dashboard hides inventory section', function () {
    $organization = Organization::factory()->create();
    $user = createDashboardApiUserWithRole($organization->id, 'Logistics Manager');

    Sanctum::actingAs($user);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'range',
                'orders',
                'returns',
            ],
            'meta' => ['generated_at'],
        ])
        ->assertJsonMissingPath('data.inventory');
});

test('dashboard values match report endpoints for the same range', function () {
    $organization = Organization::factory()->create();
    $user = createDashboardApiUserWithRole($organization->id, 'Owner');
    [$customer, $channel] = createDashboardOrderContext($organization->id);
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    $order = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Returned->value,
        'total_amount' => '77.00',
        'created_at' => '2026-02-10 08:00:00',
    ]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 8,
        'qty_reserved' => 1,
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_delta' => 4,
        'created_at' => '2026-02-11 08:00:00',
    ]);

    $return = ReturnOrder::factory()->create([
        'order_id' => $order->id,
        'returned_at' => '2026-02-12 09:00:00',
    ]);

    ReturnItem::factory()->create([
        'return_id' => $return->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    Sanctum::actingAs($user);

    $orders = $this->getJson('/api/reports/orders/summary?from=2026-02-01&to=2026-02-28')->json('data');
    $inventory = $this->getJson('/api/reports/inventory/summary?from=2026-02-01&to=2026-02-28')->json('data');
    $returns = $this->getJson('/api/reports/returns/summary?from=2026-02-01&to=2026-02-28')->json('data');

    $dashboard = $this->getJson('/api/dashboard?from=2026-02-01&to=2026-02-28');

    $dashboard
        ->assertOk()
        ->assertJsonPath('data.orders.total_orders', $orders['total_orders'])
        ->assertJsonPath('data.inventory.total_skus', $inventory['total_skus'])
        ->assertJsonPath('data.returns.total_returns', $returns['total_returns']);
});

function createDashboardApiUserWithRole(int $organizationId, string $role): User
{
    $user = User::factory()->create(['organization_id' => $organizationId]);
    $user->assignRole($role);

    return $user;
}

function createDashboardOrderContext(int $organizationId): array
{
    return [
        Customer::factory()->create(['organization_id' => $organizationId]),
        SalesChannel::factory()->create(),
    ];
}
