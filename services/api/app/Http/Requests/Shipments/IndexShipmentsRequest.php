<?php

namespace App\Http\Requests\Shipments;

use Illuminate\Foundation\Http\FormRequest;

final class IndexShipmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'min:1'],
            'courier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'outcome' => ['nullable', 'string', 'in:shipped,delivered,returned,unpaid'],
            'shipped_from' => ['nullable', 'date'],
            'shipped_to' => ['nullable', 'date'],
            'delivered' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
