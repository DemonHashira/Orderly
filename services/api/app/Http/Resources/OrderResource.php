<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'reference' => (string) $this->reference,
            'customer_id' => (int) $this->customer_id,
            'sales_channel_id' => (int) $this->sales_channel_id,
            'created_by' => (int) $this->created_by,
            'current_status' => (string) $this->current_status,
            'total_amount' => (string) $this->total_amount,
            'internal_notes' => $this->internal_notes,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
