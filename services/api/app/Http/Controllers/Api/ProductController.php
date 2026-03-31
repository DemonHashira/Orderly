<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\IndexProductsRequest;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\InventoryStock;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class ProductController extends Controller
{
    public function index(IndexProductsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);

        $orgId = (int) $request->user()->organization_id;
        $query = Product::query()->forOrg($orgId);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';

            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            });
        }

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return ProductResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }

    public function show(IndexProductsRequest $request, int $product): ProductResource
    {
        $productModel = Product::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($product);

        Gate::authorize('view', $productModel);

        return new ProductResource($productModel);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $data = $request->validated();

        $product = DB::transaction(function () use ($request, $data): Product {
            $product = Product::query()->create([
                'organization_id' => (int) $request->user()->organization_id,
                'sku' => (string) $data['sku'],
                'name' => (string) $data['name'],
                'sale_price' => (string) $data['sale_price'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            InventoryStock::query()->updateOrCreate(
                [
                    'organization_id' => (int) $request->user()->organization_id,
                    'product_id' => (int) $product->id,
                ],
                [
                    'qty_on_hand' => 0,
                    'qty_reserved' => 0,
                    'reorder_threshold' => null,
                ],
            );

            return $product;
        });

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateProductRequest $request, int $product): ProductResource
    {
        $productModel = Product::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($product);

        Gate::authorize('update', $productModel);

        $productModel->update($request->validated());

        return new ProductResource($productModel->refresh());
    }

    public function archive(IndexProductsRequest $request, int $product): ProductResource
    {
        $productModel = Product::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($product);

        Gate::authorize('archive', $productModel);

        if ($productModel->is_active) {
            $productModel->forceFill(['is_active' => false])->save();
        }

        return new ProductResource($productModel->refresh());
    }
}
