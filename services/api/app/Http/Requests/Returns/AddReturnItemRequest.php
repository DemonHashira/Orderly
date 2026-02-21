<?php

namespace App\Http\Requests\Returns;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddReturnItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = (int) $this->user()->organization_id;

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                ),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'restockable' => ['required', 'boolean'],
        ];
    }
}
