<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ReturnsSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'range' => [
                'from' => $this->resource['range']['from'],
                'to' => $this->resource['range']['to'],
                'is_all_time' => (bool) $this->resource['range']['is_all_time'],
            ],
            'total_returns' => (int) $this->resource['total_returns'],
            'total_return_items_qty' => (int) $this->resource['total_return_items_qty'],
            'restockable_items_qty' => (int) $this->resource['restockable_items_qty'],
            'non_restockable_items_qty' => (int) $this->resource['non_restockable_items_qty'],
            'by_order_status' => $this->resource['by_order_status'],
        ];

        if (array_key_exists('comparison', $this->resource)) {
            $payload['comparison'] = $this->resource['comparison'];
        }

        if (array_key_exists('breakdowns', $this->resource)) {
            $payload['breakdowns'] = $this->resource['breakdowns'];
        }

        if (array_key_exists('exceptions', $this->resource)) {
            $payload['exceptions'] = $this->resource['exceptions'];
        }

        if (array_key_exists('actions', $this->resource)) {
            $payload['actions'] = $this->resource['actions'];
        }

        return $payload;
    }
}
