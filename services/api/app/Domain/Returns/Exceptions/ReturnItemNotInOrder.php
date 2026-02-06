<?php

namespace App\Domain\Returns\Exceptions;

use RuntimeException;

final class ReturnItemNotInOrder extends RuntimeException
{
    public static function forProduct(int $productId): self
    {
        return new self("Product is not part of the original order. product_id={$productId}");
    }
}
