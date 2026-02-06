<?php

namespace App\Domain\Returns\Exceptions;

use RuntimeException;

final class InvalidReturnItemQuantity extends RuntimeException
{
    public static function forQuantity(int $quantity): self
    {
        return new self("Return item quantity must be > 0. Given: {$quantity}");
    }
}
