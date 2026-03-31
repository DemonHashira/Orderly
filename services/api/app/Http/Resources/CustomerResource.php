<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
        $address = $this->relationLoaded('defaultAddress') ? $this->defaultAddress : null;

        return [
            'id' => $this->id,
            'name' => $name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $address ? [
                'country' => $address->country,
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
            ] : null,
        ];
    }
}
