<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\User;
use Database\Seeders\Demo\DemoInventoryLedger;
use Database\Seeders\Demo\DemoOrderFactory;
use Database\Seeders\Demo\DemoOrderScenarios;
use Database\Seeders\Demo\DemoOrderStatusProjector;
use Database\Seeders\Demo\DemoReturnFactory;
use Database\Seeders\Demo\DemoShipmentFactory;
use Database\Seeders\Support\TenantProductCatalogs;
use Database\Seeders\Support\TenantSeedPresets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $channels = SalesChannel::query()->get()->keyBy('code');

        if ($channels->isEmpty()) {
            throw new \RuntimeException('DemoOrderSeeder requires organizations, users, sales channels, customers and products to exist.');
        }

        foreach (TenantSeedPresets::all() as $presetIndex => $preset) {
            $org = Organization::query()->where('slug', $preset['slug'])->firstOrFail();
            fake()->seed(20260304 + $presetIndex);

            $owner = User::query()
                ->where('organization_id', $org->id)
                ->role('Owner')
                ->first();

            $orderManager = User::query()
                ->where('organization_id', $org->id)
                ->role('Order Manager')
                ->first()
                ?? $owner
                ?? User::query()->where('organization_id', $org->id)->firstOrFail();

            $logistics = User::query()
                ->where('organization_id', $org->id)
                ->role('Logistics Manager')
                ->first()
                ?? $orderManager;

            $inventory = User::query()
                ->where('organization_id', $org->id)
                ->role('Inventory Manager')
                ->first()
                ?? $orderManager;

            $customers = Customer::query()->where('organization_id', $org->id)->orderBy('id')->get();
            $products = Product::query()->where('organization_id', $org->id)->orderBy('id')->get();

            if ($customers->isEmpty() || $products->isEmpty()) {
                throw new \RuntimeException('DemoOrderSeeder requires organizations, users, sales channels, customers and products to exist.');
            }

            $factory = new DemoOrderFactory;
            $statusProjector = new DemoOrderStatusProjector;
            $shipmentFactory = new DemoShipmentFactory;
            $returnFactory = new DemoReturnFactory;
            $inventoryLedger = new DemoInventoryLedger;

            $scenarios = DemoOrderScenarios::make(
                $channels->keys()->all(),
                $preset['order_reference_prefix'],
                TenantProductCatalogs::anchorSkusFor($preset['slug']),
            );

            foreach ($scenarios as $idx => $scenario) {
                DB::transaction(function () use (
                    $org,
                    $customers,
                    $channels,
                    $products,
                    $idx,
                    $scenario,
                    $orderManager,
                    $logistics,
                    $inventory,
                    $factory,
                    $statusProjector,
                    $shipmentFactory,
                    $returnFactory,
                    $inventoryLedger,
                ) {
                    $customer = $customers[$idx % $customers->count()];
                    $channel = $channels[$scenario['channel']] ?? $channels->first();
                    $createdAt = now()->subDays((int) $scenario['days_ago']);

                    $order = $factory->createOrUpdateOrder(
                        organizationId: $org->id,
                        customerId: $customer->id,
                        salesChannelId: $channel->id,
                        createdByUserId: $orderManager->id,
                        reference: $scenario['reference'],
                        currentStatus: $scenario['status'],
                    );

                    $factory->resetChildren($order);
                    $items = $factory->createOrderItems(
                        $order,
                        $products,
                        (int) $scenario['items'],
                        $scenario['item_blueprint'] ?? [],
                    );
                    $factory->updateTotalsAndTimestamps($order, $items, $createdAt);

                    $statusProjector->project(
                        order: $order,
                        orderManagerUserId: $orderManager->id,
                        logisticsUserId: $logistics->id,
                        finalStatus: $scenario['status'],
                        createdAt: $createdAt,
                    );

                    if (in_array($scenario['status'], ['shipped', 'delivered', 'returned', 'unpaid'], true)) {
                        $shippedAt = $createdAt->copy()->addDays(3);
                        $shipmentFactory->createFor($order, $scenario['status'], $createdAt);
                        $inventoryLedger->applySale(
                            organizationId: $org->id,
                            performedByUserId: $logistics->id,
                            orderId: $order->id,
                            items: $items,
                            occurredAt: $shippedAt,
                        );
                    } else {
                        $inventoryLedger->reserveItems($org->id, $items);
                    }

                    if (($scenario['return'] ?? false) === true) {
                        $returnedAt = $createdAt->copy()->addDays(
                            (int) ($scenario['return_delay_days'] ?? min(7, max(4, ((int) $scenario['days_ago']) - 1))),
                        );

                        [$returnOrder, $returnItems] = $returnFactory->createFor(
                            orderId: $order->id,
                            items: $items,
                            returnedAt: $returnedAt,
                            outcome: $scenario['status'],
                            reason: $scenario['return_reason'] ?? null,
                            restockableMode: $scenario['restockable_mode'] ?? 'mixed',
                            returnAllItems: (bool) ($scenario['return_all_items'] ?? false),
                        );

                        if (($scenario['apply_restock'] ?? true) === true) {
                            $inventoryLedger->applyReturnRestock(
                                organizationId: $org->id,
                                performedByUserId: $inventory->id,
                                returnOrderId: $returnOrder->id,
                                returnItems: $returnItems,
                                occurredAt: $returnedAt,
                            );

                            $returnOrder->forceFill([
                                'restocked_at' => $returnedAt,
                                'updated_at' => $returnedAt,
                            ])->save();
                        }
                    }
                });
            }

            $inventoryLedger->recalculateReserved($org->id);
        }
    }
}
