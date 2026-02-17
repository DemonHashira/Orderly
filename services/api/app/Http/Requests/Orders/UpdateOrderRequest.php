<?php

namespace App\Http\Requests\Orders;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = (int) $this->user()->organization_id;

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                ),
            ],
            'sales_channel_id' => ['required', 'integer', Rule::exists('sales_channels', 'id')],
            'internal_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                ),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],

            'organization_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'reference' => ['prohibited'],
            'current_status' => ['prohibited'],
            'total_amount' => ['prohibited'],
        ];
    }
}
