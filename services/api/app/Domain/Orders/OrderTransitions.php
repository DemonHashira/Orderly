<?php

namespace App\Domain\Orders;

final class OrderTransitions
{
    // Valid status transitions for the order lifecycle
    private const array MAP = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned', 'unpaid'],
        'delivered' => [],
        'returned' => [],
        'unpaid' => [],
        'cancelled' => [],
    ];

    // Check if transition from one status to another is allowed
    public static function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, self::MAP[$from->value] ?? [], true);
    }
}
