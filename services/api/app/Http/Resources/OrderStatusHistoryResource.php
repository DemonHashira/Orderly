<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'changed_by' => $this->changed_by ? (int) $this->changed_by : null,
            'changed_at' => $this->created_at?->toISOString(),
        ];
    }
}
