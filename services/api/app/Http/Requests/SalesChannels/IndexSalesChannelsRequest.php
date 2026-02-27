<?php

namespace App\Http\Requests\SalesChannels;

use Illuminate\Foundation\Http\FormRequest;

final class IndexSalesChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
