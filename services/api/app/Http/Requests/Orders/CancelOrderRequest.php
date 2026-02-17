<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

final class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
