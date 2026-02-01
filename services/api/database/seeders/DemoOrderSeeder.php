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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'otaku-store')->firstOrFail();

        // Get users for different roles
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

        $channels = SalesChannel::query()->get()->keyBy('code');
        $customers = Customer::query()->where('organization_id', $org->id)->inRandomOrder()->take(12)->get();
        $products = Product::query()->where('organization_id', $org->id)->inRandomOrder()->get();

        if ($customers->isEmpty() || $products->isEmpty() || $channels->isEmpty()) {
            throw new \RuntimeException('DemoOrderSeeder requires organizations, users, sales channels, customers and products to exist.');
        }

        $factory = new DemoOrderFactory;
        $statusProjector = new DemoOrderStatusProjector;
        $shipmentFactory = new DemoShipmentFactory;
        $returnFactory = new DemoReturnFactory;
        $inventoryLedger = new DemoInventoryLedger;

        $scenarios = DemoOrderScenarios::make($channels->keys()->all());

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
                $items = $factory->createOrderItems($order, $products, (int) $scenario['items']);
                $factory->updateTotalsAndTimestamps($order, $items, $createdAt);

                // Status history
                $statusProjector->project(
                    order: $order,
                    orderManagerUserId: $orderManager->id,
                    logisticsUserId: $logistics->id,
                    finalStatus: $scenario['status'],
                    createdAt: $createdAt,
                );

                // Shipping and inventory
                if (in_array($scenario['status'], ['shipped', 'delivered', 'returned', 'unpaid'], true)) {
                    $shipmentFactory->createFor($order, $scenario['status'], $createdAt);
                    $inventoryLedger->applySale($org->id, $logistics->id, $order->id, $items);
                } else {
                    $inventoryLedger->reserveItems($org->id, $items);
                }

                // Returns
                if (($scenario['return'] ?? false) === true) {
                    $returnedAt = $createdAt->copy()->addDays(10);

                    [$returnOrder, $returnItems] = $returnFactory->createFor(
                        orderId: $order->id,
                        items: $items,
                        returnedAt: $returnedAt,
                        outcome: $scenario['status'],
                    );

                    $inventoryLedger->applyReturnRestock(
                        organizationId: $org->id,
                        performedByUserId: $inventory->id,
                        returnOrderId: $returnOrder->id,
                        returnItems: $returnItems,
                    );
                }
            });
        }

        // Ensure reserved quantities match open orders
        $inventoryLedger->recalculateReserved($org->id);
    }
}
