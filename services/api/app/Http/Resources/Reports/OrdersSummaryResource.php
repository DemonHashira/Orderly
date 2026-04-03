<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrdersSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'range' => [
                'from' => $this->resource['range']['from'],
                'to' => $this->resource['range']['to'],
                'is_all_time' => (bool) $this->resource['range']['is_all_time'],
            ],
            'total_orders' => (int) $this->resource['total_orders'],
            'total_revenue' => (string) $this->resource['total_revenue'],
            'avg_order_value' => (string) $this->resource['avg_order_value'],
            'by_status' => $this->resource['by_status'],
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
