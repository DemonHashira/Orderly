<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

final class IndexReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'returned_from' => ['nullable', 'date'],
            'returned_to' => ['nullable', 'date'],
            'has_restockable' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
