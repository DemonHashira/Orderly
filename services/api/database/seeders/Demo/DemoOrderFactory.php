<?php

namespace Database\Seeders\Demo;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class DemoOrderFactory
{
    // Create or update order with basic info
    public function createOrUpdateOrder(
        int $organizationId,
        int $customerId,
        int $salesChannelId,
        int $createdByUserId,
        string $reference,
        string $currentStatus,
    ): Order {
        return Order::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'organization_id' => $organizationId,
                'customer_id' => $customerId,
                'sales_channel_id' => $salesChannelId,
                'created_by' => $createdByUserId,
                'reference' => $reference,
                'total_amount' => 0,
                'current_status' => $currentStatus,
                'internal_notes' => fake()->boolean(30) ? 'Seeded demo order.' : null,
            ],
        );
    }

    // Remove all child records
    public function resetChildren(Order $order): void
    {
        $returnOrderId = $order->return()->value('id');

        InventoryMovement::query()
            ->where('reference_type', 'Order')
            ->where('reference_id', $order->id)
            ->delete();

        if ($returnOrderId) {
            InventoryMovement::query()
                ->where('reference_type', 'Return')
                ->where('reference_id', $returnOrderId)
                ->delete();
        }

        $order->items()->delete();
        $order->statusHistory()->delete();
        $order->shipment()->delete();
        $order->return()->delete();
    }

    // Create order items from random products or a deterministic blueprint
    public function createOrderItems(Order $order, Collection $products, int $itemCount, array $itemBlueprint = []): Collection
    {
        if ($itemBlueprint !== []) {
            return $this->createItemsFromBlueprint($order, $products, $itemBlueprint);
        }

        $picked = $products->shuffle()->take(max(1, $itemCount));
        $rows = collect();

        foreach ($picked as $product) {
            $qty = fake()->numberBetween(1, 3);
            $unitPrice = (float) $product->sale_price;

            $rows->push(OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $qty * $unitPrice,
            ]));
        }

        return $rows;
    }

    private function createItemsFromBlueprint(Order $order, Collection $products, array $itemBlueprint): Collection
    {
        $rows = collect();

        foreach ($itemBlueprint as $entry) {
            $sku = (string) ($entry['sku'] ?? '');
            $quantity = max(1, (int) ($entry['quantity'] ?? 1));
            $product = $products->firstWhere('sku', $sku);

            if ($product === null) {
                throw new RuntimeException("DemoOrderFactory blueprint SKU [{$sku}] was not found.");
            }

            $unitPrice = (float) $product->sale_price;

            $rows->push(OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
            ]));
        }

        return $rows;
    }

    // Calculate order total and set timestamps
    public function updateTotalsAndTimestamps(Order $order, Collection $items, Carbon $createdAt): void
    {
        $total = (float) $items->sum('total_price');

        $order->forceFill([
            'total_amount' => $total,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
