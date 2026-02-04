<?php

namespace App\Services\Orders;

use App\Domain\Orders\Exceptions\InvalidOrderTransition;
use App\Domain\Orders\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class OrderItemService
{
    public function __construct(private OrderPricingService $pricing) {}

    public function addItem(
        int $orderId,
        int $productId,
        int $quantity,
        ?string $unitPrice = null,
    ): OrderItem {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Order item quantity must be > 0. Given: {$quantity}");
        }

        return DB::transaction(function () use ($orderId, $productId, $quantity, $unitPrice) {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            $this->assertItemsEditable($order);

            $product = Product::query()
                ->where('organization_id', $order->organization_id)
                ->findOrFail($productId);

            $price = $unitPrice ?? (string) $product->sale_price;

            $item = OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total_price' => $this->normalizeAmount((float) $price * $quantity),
            ]);

            $this->pricing->recalculateOrderTotals($order->refresh());

            return $item->refresh();
        });
    }

    public function updateItem(
        int $orderItemId,
        int $quantity,
        ?string $unitPrice = null,
    ): OrderItem {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Order item quantity must be > 0. Given: {$quantity}");
        }

        return DB::transaction(function () use ($orderItemId, $quantity, $unitPrice) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($orderItemId);

            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);

            $this->assertItemsEditable($order);

            $item->forceFill([
                'quantity' => $quantity,
                'unit_price' => $unitPrice ?? (string) $item->unit_price,
            ])->save();

            $this->pricing->recalculateOrderTotals($order->refresh());

            return $item->refresh();
        });
    }

    public function removeItem(int $orderItemId): void
    {
        DB::transaction(function () use ($orderItemId) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($orderItemId);
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);

            $this->assertItemsEditable($order);

            $item->delete();

            $this->pricing->recalculateOrderTotals($order->refresh());
        });
    }

    private function assertItemsEditable(Order $order): void
    {
        $status = OrderStatus::from($order->current_status);

        if ($status !== OrderStatus::Draft) {
            throw InvalidOrderTransition::forStatus($status, 'modify order items');
        }
    }

    private function normalizeAmount(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
