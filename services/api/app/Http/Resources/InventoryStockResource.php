<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product' => [
                'id' => (int) $this->product_id,
                'sku' => (string) $this->product?->sku,
                'name' => (string) $this->product?->name,
                'is_active' => (bool) $this->product?->is_active,
            ],
            'qty_on_hand' => (int) $this->qty_on_hand,
            'qty_reserved' => (int) $this->qty_reserved,
            'available' => (int) ($this->qty_on_hand - $this->qty_reserved),
        ];
    }
}
