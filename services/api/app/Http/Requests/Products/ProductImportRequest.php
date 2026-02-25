<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

final class ProductImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,xlsx',
                'max:'.(int) config('products.import.max_file_kb', 10240),
            ],
        ];
    }
}
