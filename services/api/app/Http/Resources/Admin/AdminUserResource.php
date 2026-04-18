<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->getRoleNames()->values()->all();

        return [
            'id' => (int) $this->id,
            'organization_id' => (int) $this->organization_id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'name' => [
                $this->first_name,
                $this->middle_name,
                $this->last_name,
            ]
                    |> array_filter(...)
                    |> (fn ($x) => implode(' ', $x))
                    |> trim(...),
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'role' => $roles[0] ?? null,
            'roles' => $roles,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
