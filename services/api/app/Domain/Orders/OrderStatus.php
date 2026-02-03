<?php

namespace App\Domain\Orders;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Unpaid = 'unpaid';
    case Cancelled = 'cancelled';

    // Orders that haven't shipped yet
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Confirmed, self::ReadyToShip], true);
    }

    // Orders that are finalized
    public function isFinalized(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Returned,
            self::Unpaid,
            self::Cancelled,
        ], true);
    }

    // Orders that are still in progress
    public function isActive(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Confirmed,
            self::ReadyToShip,
            self::Shipped,
        ], true);
    }
}
