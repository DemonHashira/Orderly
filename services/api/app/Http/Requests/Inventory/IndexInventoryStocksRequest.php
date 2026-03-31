<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

final class IndexInventoryStocksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'stock_condition' => ['nullable', 'string', 'in:low_stock,out_of_stock,reserved,available'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
