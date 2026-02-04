<?php

namespace App\Services\Orders;

use App\Models\Order;

final class OrderPricingService
{
    public function recalculateOrderTotals(Order $order): Order
    {
        $order->loadMissing(['items']);

        $total = 0.0;

        foreach ($order->items as $item) {
            $quantity = (int) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $lineTotal = $this->normalizeAmount($unitPrice * $quantity);

            if ((string) $item->total_price !== $lineTotal) {
                $item->forceFill(['total_price' => $lineTotal])->save();
            }

            $total += (float) $lineTotal;
        }

        $orderTotal = $this->normalizeAmount($total);

        if ((string) $order->total_amount !== $orderTotal) {
            $order->forceFill(['total_amount' => $orderTotal])->save();
        }

        return $order->refresh();
    }

    public function validateItemPricing(Order $order): array
    {
        $order->loadMissing(['items.product']);

        $mismatches = [];

        foreach ($order->items as $item) {
            $expected = (string) $item->product->sale_price;
            $actual = (string) $item->unit_price;

            if ($expected !== $actual) {
                $mismatches[] = [
                    'order_item_id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'unit_price' => $actual,
                    'expected_price' => $expected,
                ];
            }
        }

        return $mismatches;
    }

    private function normalizeAmount(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
