<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

final class ProductExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:csv,xlsx'],
            'is_active' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
