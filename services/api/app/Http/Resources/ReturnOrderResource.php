<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReturnOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'reason' => $this->reason,
            'returned_at' => $this->returned_at?->toISOString(),
            'restocked_at' => $this->restocked_at?->toISOString(),
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => (int) $this->order->id,
                'reference' => (string) $this->order->reference,
                'current_status' => (string) $this->order->current_status,
                'customer_id' => (int) $this->order->customer_id,
                'customer_name' => $this->order->relationLoaded('customer')
                    ? trim(implode(' ', array_filter([
                        $this->order->customer->first_name,
                        $this->order->customer->middle_name,
                        $this->order->customer->last_name,
                    ])))
                    : null,
                'items' => $this->order->relationLoaded('items')
                    ? OrderItemResource::collection($this->order->items)->resolve($request)
                    : [],
            ]),
            'items' => ReturnItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
