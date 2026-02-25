<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

final class ProductsImport implements ToArray
{
    public function array(array $array): void {}
}
