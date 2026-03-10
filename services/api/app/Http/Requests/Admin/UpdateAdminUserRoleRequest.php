<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminUserRoleRequest extends FormRequest
{
    private const array ALLOWED_ROLES = [
        'Owner',
        'Order Manager',
        'Logistics Manager',
        'Inventory Manager',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('roles.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }
}
