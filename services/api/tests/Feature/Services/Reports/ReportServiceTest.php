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
use App\Services\Reports\ReportService;
use Database\Seeders\DatabaseSeeder;
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

test('report service enriches orders summary with comparison breakdowns exceptions and actions', function () {
    $organization = Organization::factory()->create();
    [$user, $customer, $retailChannel] = createServiceOrderContext($organization->id);
    $websiteChannel = SalesChannel::factory()->create(['name' => 'Website']);
    $jacket = Product::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Winter Jacket',
        'sku' => 'JKT-301',
    ]);
    $pants = Product::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Cargo Pants',
        'sku' => 'PNT-302',
    ]);

    $previousOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $retailChannel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '120.00',
        'created_at' => '2026-03-03 09:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $previousOrder->id,
        'product_id' => $jacket->id,
        'quantity' => 1,
        'unit_price' => '120.00',
        'total_price' => '120.00',
    ]);

    $backlogOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $websiteChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-101',
        'current_status' => OrderStatus::ReadyToShip->value,
        'total_amount' => '420.00',
        'created_at' => '2026-03-10 08:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $backlogOrder->id,
        'product_id' => $jacket->id,
        'quantity' => 2,
        'unit_price' => '120.00',
        'total_price' => '240.00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $backlogOrder->id,
        'product_id' => $pants->id,
        'quantity' => 3,
        'unit_price' => '60.00',
        'total_price' => '180.00',
    ]);

    $deliveredOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $retailChannel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Delivered->value,
        'total_amount' => '180.00',
        'created_at' => '2026-03-15 10:00:00',
    ]);
    OrderItem::factory()->create([
        'order_id' => $deliveredOrder->id,
        'product_id' => $pants->id,
        'quantity' => 2,
        'unit_price' => '90.00',
        'total_price' => '180.00',
    ]);

    $service = app(ReportService::class);
    $summary = $service->getOrdersSummary(
        $organization->id,
        Carbon::parse('2026-03-10'),
        Carbon::parse('2026-03-16'),
    );
    $ordersByChannel = collect($summary['breakdowns']['by_channel'])
        ->mapWithKeys(fn (array $channel): array => [$channel['label'] => $channel['value']])
        ->all();

    expect($summary['comparison']['previous_range'])->toBe([
        'from' => '2026-03-03',
        'to' => '2026-03-09',
    ])
        ->and($summary['comparison']['metrics']['total_orders']['previous'])->toBe(1)
        ->and($summary['comparison']['metrics']['total_orders']['current'])->toBe(2)
        ->and($summary['comparison']['metrics']['total_orders']['direction'])->toBe('up')
        ->and($ordersByChannel)->toMatchArray([
            $retailChannel->name => 1,
            $websiteChannel->name => 1,
        ])
        ->and($summary['breakdowns']['top_products'][0])->toMatchArray([
            'name' => 'Cargo Pants',
            'sku' => 'PNT-302',
            'quantity' => 5,
        ])
        ->and($summary['exceptions']['backlog_orders'][0])->toMatchArray([
            'reference' => 'ORD-101',
            'status' => OrderStatus::ReadyToShip->value,
            'customer_name' => [
                $customer->first_name,
                $customer->middle_name,
                $customer->last_name,
            ]
                    |> array_filter(...)
                    |> (fn ($x) => implode(' ', $x))
                    |> trim(...),
        ])
        ->and($summary['actions'][0])->toMatchArray([
            'id' => 'open-orders-backlog',
            'label' => 'Open backlog orders',
        ]);

    $allTimeSummary = $service->getOrdersSummary($organization->id);

    expect(array_key_exists('comparison', $allTimeSummary))->toBeFalse();
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

test('report service enriches inventory summary with comparison breakdowns exceptions and actions', function () {
    $organization = Organization::factory()->create();
    $hoodie = Product::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Archive Hoodie',
        'sku' => 'HD-401',
    ]);

    InventoryStock::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $hoodie->id,
        'qty_on_hand' => 3,
        'qty_reserved' => 2,
        'reorder_threshold' => 5,
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $hoodie->id,
        'type' => 'restock',
        'reference_type' => 'Return',
        'qty_delta' => 7,
        'created_at' => '2026-03-10 10:00:00',
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $hoodie->id,
        'type' => 'damage',
        'reference_type' => 'Order',
        'qty_delta' => -4,
        'created_at' => '2026-03-12 10:00:00',
    ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $hoodie->id,
        'type' => 'restock',
        'reference_type' => 'Return',
        'qty_delta' => 5,
        'created_at' => '2026-03-03 10:00:00',
    ]);

    $service = app(ReportService::class);
    $summary = $service->getInventorySummary(
        $organization->id,
        Carbon::parse('2026-03-10'),
        Carbon::parse('2026-03-16'),
    );

    expect($summary['comparison']['metrics']['total_available']['previous'])->toBe(1)
        ->and($summary['comparison']['metrics']['movement_in_qty']['previous'])->toBe(5)
        ->and($summary['breakdowns']['by_movement_type'][0])->toMatchArray([
            'label' => 'Restock',
            'value' => 7,
        ])
        ->and($summary['breakdowns']['by_reference_type'][0])->toMatchArray([
            'label' => 'Return',
            'value' => 7,
        ])
        ->and($summary['exceptions']['attention_items'][0])->toMatchArray([
            'name' => 'Archive Hoodie',
            'sku' => 'HD-401',
            'status' => 'low_stock',
        ])
        ->and($summary['actions'][0])->toMatchArray([
            'id' => 'open-low-stock-items',
            'label' => 'Open low stock items',
        ]);
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

test('report service enriches returns summary with comparison breakdowns exceptions and actions', function () {
    $organization = Organization::factory()->create();
    [$user, $customer, $retailChannel] = createServiceOrderContext($organization->id);
    $websiteChannel = SalesChannel::factory()->create(['name' => 'Website']);
    $pants = Product::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Cargo Pants',
        'sku' => 'PNT-302',
    ]);

    $previousOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $retailChannel->id,
        'created_by' => $user->id,
        'current_status' => OrderStatus::Returned->value,
        'created_at' => '2026-03-03 08:00:00',
    ]);
    $previousReturn = ReturnOrder::factory()->create([
        'order_id' => $previousOrder->id,
        'reason' => 'Wrong size',
        'returned_at' => '2026-03-05 10:00:00',
        'created_at' => '2026-03-05 10:00:00',
    ]);
    ReturnItem::factory()->create([
        'return_id' => $previousReturn->id,
        'product_id' => $pants->id,
        'quantity' => 1,
        'restockable' => true,
    ]);

    $currentOrder = Order::factory()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'sales_channel_id' => $websiteChannel->id,
        'created_by' => $user->id,
        'reference' => 'ORD-7001',
        'current_status' => OrderStatus::Returned->value,
        'created_at' => '2026-03-10 08:00:00',
    ]);
    $currentReturn = ReturnOrder::factory()->create([
        'order_id' => $currentOrder->id,
        'reason' => 'Damaged zipper',
        'returned_at' => '2026-03-12 10:00:00',
        'created_at' => '2026-03-12 10:00:00',
    ]);
    ReturnItem::factory()->create([
        'return_id' => $currentReturn->id,
        'product_id' => $pants->id,
        'quantity' => 3,
        'restockable' => true,
    ]);
    ReturnItem::factory()->create([
        'return_id' => $currentReturn->id,
        'product_id' => $pants->id,
        'quantity' => 2,
        'restockable' => false,
    ]);

    $service = app(ReportService::class);
    $summary = $service->getReturnsSummary(
        $organization->id,
        Carbon::parse('2026-03-10'),
        Carbon::parse('2026-03-16'),
    );

    expect($summary['comparison']['metrics']['total_returns']['previous'])->toBe(1)
        ->and($summary['breakdowns']['by_reason'][0])->toMatchArray([
            'label' => 'Damaged zipper',
            'value' => 1,
        ])
        ->and($summary['breakdowns']['by_channel'][0])->toMatchArray([
            'label' => 'Website',
            'value' => 1,
        ])
        ->and($summary['breakdowns']['top_products'][0])->toMatchArray([
            'name' => 'Cargo Pants',
            'sku' => 'PNT-302',
            'quantity' => 5,
        ])
        ->and($summary['exceptions']['pending_restock'][0])->toMatchArray([
            'order_reference' => 'ORD-7001',
            'customer_name' => [
                $customer->first_name,
                $customer->middle_name,
                $customer->last_name,
            ]
                    |> array_filter(...)
                    |> (fn ($x) => implode(' ', $x))
                    |> trim(...),
            'restockable_qty' => 3,
        ])
        ->and($summary['exceptions']['write_off_products'][0])->toMatchArray([
            'name' => 'Cargo Pants',
            'quantity' => 2,
        ])
        ->and($summary['actions'][0])->toMatchArray([
            'id' => 'open-restock-queue',
            'label' => 'Open restock queue',
        ]);

    InventoryMovement::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $pants->id,
        'type' => 'return',
        'reference_type' => 'Return',
        'reference_id' => $currentReturn->id,
        'qty_delta' => 3,
    ]);

    $restockedSummary = $service->getReturnsSummary(
        $organization->id,
        Carbon::parse('2026-03-10'),
        Carbon::parse('2026-03-16'),
    );

    expect($restockedSummary['exceptions']['pending_restock'])->toHaveCount(0);
});

test('database seeder populates report comparisons breakdowns and exceptions for the demo organization', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-30 12:00:00'));

    try {
        $this->seed(DatabaseSeeder::class);

        $organization = Organization::query()->where('slug', 'otaku-store')->firstOrFail();
        $service = app(ReportService::class);
        $from = Carbon::parse('2026-03-01');
        $to = Carbon::parse('2026-03-30');

        $ordersSummary = $service->getOrdersSummary($organization->id, $from, $to);
        $inventorySummary = $service->getInventorySummary($organization->id, $from, $to);
        $returnsSummary = $service->getReturnsSummary($organization->id, $from, $to);

        expect($ordersSummary['comparison']['metrics']['total_orders']['previous'])->toBeGreaterThan(0)
            ->and($ordersSummary['breakdowns']['by_channel'])->not->toBeEmpty()
            ->and($ordersSummary['breakdowns']['top_products'])->not->toBeEmpty()
            ->and($ordersSummary['exceptions']['backlog_orders'])->not->toBeEmpty()
            ->and($inventorySummary['comparison']['metrics']['movement_in_qty']['previous'])->toBeGreaterThanOrEqual(0)
            ->and($inventorySummary['breakdowns']['by_movement_type'])->not->toBeEmpty()
            ->and($inventorySummary['breakdowns']['by_reference_type'])->not->toBeEmpty()
            ->and($inventorySummary['exceptions']['attention_items'])->not->toBeEmpty()
            ->and($returnsSummary['comparison']['metrics']['total_returns']['previous'])->toBeGreaterThan(0)
            ->and($returnsSummary['breakdowns']['by_reason'])->not->toBeEmpty()
            ->and($returnsSummary['breakdowns']['by_channel'])->not->toBeEmpty()
            ->and($returnsSummary['breakdowns']['top_products'])->not->toBeEmpty()
            ->and($returnsSummary['exceptions']['pending_restock'])->not->toBeEmpty()
            ->and($returnsSummary['exceptions']['write_off_products'])->not->toBeEmpty();
    } finally {
        Carbon::setTestNow();
    }
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
