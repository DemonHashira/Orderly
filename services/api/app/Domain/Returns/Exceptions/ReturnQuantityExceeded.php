<?php

namespace App\Domain\Returns\Exceptions;

use RuntimeException;

final class ReturnQuantityExceeded extends RuntimeException
{
    public static function forProduct(int $productId, int $maxAllowed, int $requested): self
    {
        return new self(
            'Return quantity exceeds ordered quantity. '.
            "product_id={$productId}, Max allowed: {$maxAllowed}, Requested: {$requested}"
        );
    }
}
