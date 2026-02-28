<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'type' => (string) $this->type,
            'quantity_delta' => (int) $this->qty_delta,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
            'product' => [
                'id' => (int) $this->product_id,
                'sku' => (string) $this->product?->sku,
                'name' => (string) $this->product?->name,
            ],
        ];
    }
}
