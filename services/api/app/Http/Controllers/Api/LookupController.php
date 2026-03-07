<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class LookupController extends Controller
{
    public function orderCreate(): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $organizationId = (int) request()->user()->organization_id;

        $salesChannels = SalesChannel::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (SalesChannel $salesChannel): array => [
                'id' => (int) $salesChannel->id,
                'code' => (string) $salesChannel->code,
                'name' => (string) $salesChannel->name,
            ]);

        $products = Product::query()
            ->forOrg($organizationId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'sale_price'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'sale_price' => (string) $product->sale_price,
            ]);

        return response()->json([
            'data' => [
                'sales_channels' => $salesChannels,
                'products' => $products,
            ],
        ]);
    }
}
