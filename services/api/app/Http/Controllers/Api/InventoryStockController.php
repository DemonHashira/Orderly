<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IndexInventoryStocksRequest;
use App\Http\Resources\InventoryStockResource;
use App\Models\InventoryStock;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class InventoryStockController extends Controller
{
    public function index(IndexInventoryStocksRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $orgId = (int) $request->user()->organization_id;

        $query = InventoryStock::query()
            ->forOrg($orgId)
            ->with('product:id,organization_id,sku,name,is_active');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';

            $query->whereHas('product', function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            });
        }

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($isActive !== null) {
                $query->whereHas('product', fn ($builder) => $builder->where('is_active', $isActive));
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return InventoryStockResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }
}
