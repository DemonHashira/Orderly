<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'in:adjustment,damage,restock'],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],

            // These values are derived server-side.
            'organization_id' => ['prohibited'],
            'performed_by_user_id' => ['prohibited'],
            'qty_delta' => ['prohibited'],
            'reference_type' => ['prohibited'],
            'reference_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Enforce the expected sign for each movement type.
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('type') || ! $this->filled('quantity_delta')) {
                return;
            }

            $type = (string) $this->input('type');
            $delta = (int) $this->input('quantity_delta');

            if ($type === 'restock' && $delta < 1) {
                $validator->errors()->add('quantity_delta', 'The quantity_delta must be greater than 0 for restock.');
            }

            if ($type === 'damage' && $delta > -1) {
                $validator->errors()->add('quantity_delta', 'The quantity_delta must be less than 0 for damage.');
            }
        });
    }
}
