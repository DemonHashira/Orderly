<?php

namespace App\Domain\Orders\Exceptions;

use App\Domain\Orders\OrderStatus;
use RuntimeException;

final class InvalidOrderTransition extends RuntimeException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("Invalid order transition: $from->value -> $to->value");
    }

    public static function forStatus(OrderStatus $status, string $action): self
    {
        return new self("Cannot $action for order in status: $status->value");
    }
}
