<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'quantity' => (int) $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'total_price' => (string) $this->total_price,
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => (int) $this->product->id,
                'name' => (string) $this->product->name,
                'sku' => (string) $this->product->sku,
            ]),
        ];
    }
}
