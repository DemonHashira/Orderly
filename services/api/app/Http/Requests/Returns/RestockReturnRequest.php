<?php

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

final class RestockReturnRequest extends FormRequest
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
