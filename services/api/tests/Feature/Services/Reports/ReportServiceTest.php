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
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('report service aggregates orders and statuses from current_status', function () {
    $organization = Organization::factory()->create();
    [$user, $customer, $channel] = createServiceOrderContext($organization->id);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Draft->value,
        'total_amount' => '10.00',
        'created_at' => '2026-02-01 10:00:00',
    ]);

    Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '30.00',
        'created_at' => '2026-02-02 10:00:00',
    ]);

    $service = app(ReportService::class);
    $summary = $service->getOrdersSummary($organization->id, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));

    expect($summary['total_orders'])->toBe(2)
        ->and($summary['total_revenue'])->toBe('40.00')
        ->and($summary['avg_order_value'])->toBe('20.00')
        ->and($summary['by_status']['draft'])->toBe(1)
        ->and($summary['by_status']['delivered'])->toBe(1);
});

test('report service aggregates inventory and movement totals', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_on_hand' => 15,
        'qty_reserved' => 4,
        'reorder_threshold' => 20,
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_delta' => 10,
        'created_at' => '2026-02-05 10:00:00',
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'qty_delta' => -3,
        'created_at' => '2026-02-06 10:00:00',
    ]);

    $service = app(ReportService::class);
    $summary = $service->getInventorySummary($organization->id, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));

    expect($summary['total_skus'])->toBe(1)
        ->and($summary['total_on_hand'])->toBe(15)
        ->and($summary['total_reserved'])->toBe(4)
        ->and($summary['total_available'])->toBe(11)
        ->and($summary['low_stock_count'])->toBe(1)
        ->and($summary['movement_in_qty'])->toBe(10)
        ->and($summary['movement_out_qty'])->toBe(3);
});

test('report service filters returns by returned_at fallback to created_at', function () {
    $organization = Organization::factory()->create();
    [$user, $customer, $channel] = createServiceOrderContext($organization->id);
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    $order1 = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Returned->value,
    ]);

    $return1 = ReturnOrder::factory()->create([
        'order_id' => $order1->id,
        'returned_at' => null,
        'created_at' => '2026-02-10 11:00:00',
    ]);

    ReturnItem::factory()->create([
        'return_id' => $return1->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'restockable' => true,
    ]);

    $order2 = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $channel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Unpaid->value,
    ]);

    $return2 = ReturnOrder::factory()->create([
        'order_id' => $order2->id,
        'returned_at' => null,
        'created_at' => '2026-01-10 11:00:00',
    ]);

    ReturnItem::factory()->create([
        'return_id' => $return2->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'restockable' => false,
    ]);

    $service = app(ReportService::class);
    $summary = $service->getReturnsSummary($organization->id, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28'));

    expect($summary['total_returns'])->toBe(1)
        ->and($summary['total_return_items_qty'])->toBe(2)
        ->and($summary['restockable_items_qty'])->toBe(2)
        ->and($summary['non_restockable_items_qty'])->toBe(0)
        ->and($summary['by_order_status']['returned'])->toBe(1)
        ->and(array_key_exists('unpaid', $summary['by_order_status']))->toBeFalse();
});

function createServiceOrderContext(int $organizationId): array
{
    $user = User::factory()->create(['organization_id' => $organizationId]);

    return [
        $user,
        Customer::factory()->create(['organization_id' => $organizationId]),
        SalesChannel::factory()->create(),
    ];
}
