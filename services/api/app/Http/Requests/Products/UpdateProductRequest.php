<?php

namespace App\Http\Requests\Products;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge([
                'sku' => strtoupper(trim((string) $this->input('sku'))),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = (int) $this->user()->organization_id;
        $productId = (int) $this->route('product');

        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where(fn (Builder $query): Builder => $query->where('organization_id', $organizationId))
                    ->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'organization_id' => ['prohibited'],
        ];
    }
}
