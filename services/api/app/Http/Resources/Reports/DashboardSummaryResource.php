<?php

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = [
            'range' => [
                'from' => $this->resource['range']['from'],
                'to' => $this->resource['range']['to'],
                'is_all_time' => (bool) $this->resource['range']['is_all_time'],
            ],
        ];

        if (array_key_exists('orders', $this->resource)) {
            $payload['orders'] = $this->resource['orders'];
        }

        if (array_key_exists('inventory', $this->resource)) {
            $payload['inventory'] = $this->resource['inventory'];
        }

        if (array_key_exists('returns', $this->resource)) {
            $payload['returns'] = $this->resource['returns'];
        }

        return $payload;
    }
}
