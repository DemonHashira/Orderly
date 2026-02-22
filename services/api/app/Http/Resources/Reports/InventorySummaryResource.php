<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventorySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'range' => [
                'from' => $this->resource['range']['from'],
                'to' => $this->resource['range']['to'],
                'is_all_time' => (bool) $this->resource['range']['is_all_time'],
            ],
            'total_skus' => (int) $this->resource['total_skus'],
            'total_on_hand' => (int) $this->resource['total_on_hand'],
            'total_reserved' => (int) $this->resource['total_reserved'],
            'total_available' => (int) $this->resource['total_available'],
            'low_stock_count' => (int) $this->resource['low_stock_count'],
            'movement_in_qty' => (int) $this->resource['movement_in_qty'],
            'movement_out_qty' => (int) $this->resource['movement_out_qty'],
        ];
    }
}
