<?php

namespace App\Domain\Inventory\Exceptions;

use RuntimeException;

final class InsufficientStock extends RuntimeException
{
    public static function available(int $productId, int $available, int $required): self
    {
        return new self(
            "Insufficient available stock for product_id=$productId. ".
            "Available: $available, Required: $required"
        );
    }

    public static function onHand(int $productId, int $onHand, int $required): self
    {
        return new self(
            "Insufficient stock for product_id=$productId. ".
            "On hand: $onHand, Required: $required"
        );
    }
}
