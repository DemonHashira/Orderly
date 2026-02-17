<?php

namespace App\Http\Requests\Orders;

use App\Domain\Orders\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_map(fn (OrderStatus $status): string => $status->value, OrderStatus::cases()))],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'sales_channel_id' => ['nullable', 'integer', 'min:1'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
