<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IndexInventoryMovementsRequest;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\InventoryStockResource;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Services\Inventory\InventoryLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly InventoryLedgerService $inventoryLedgerService,
    ) {}

    public function index(IndexInventoryMovementsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryStock::class);

        $orgId = (int) $request->user()->organization_id;
        $query = InventoryMovement::query()
            ->forOrg($orgId)
            ->with('product:id,organization_id,sku,name');

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->query('product_id'));
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';

            $query->whereHas('product', function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(sku) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            });
        }

        $type = trim((string) $request->query('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($request->filled('from')) {
            $from = Carbon::parse((string) $request->query('from'))->startOfDay();
            $query->where('created_at', '>=', $from);
        }

        if ($request->filled('to')) {
            $to = Carbon::parse((string) $request->query('to'))->endOfDay();
            $query->where('created_at', '<=', $to);
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return InventoryMovementResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }

    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        $orgId = (int) $request->user()->organization_id;
        $data = $request->validated();

        $product = Product::query()
            ->forOrg($orgId)
            ->where('is_active', true)
            ->findOrFail((int) $data['product_id']);

        $stock = InventoryStock::query()
            ->forOrg($orgId)
            ->where('product_id', $product->id)
            ->with('product:id,organization_id,sku,name,is_active')
            ->firstOrFail();

        Gate::authorize('createMovement', $stock);

        [$movement, $updatedStock] = $this->inventoryLedgerService->applyManualMovement(
            organizationId: $orgId,
            productId: (int) $product->id,
            actorUserId: (int) $request->user()->id,
            type: (string) $data['type'],
            quantityDelta: (int) $data['quantity_delta'],
            reason: (string) $data['reason'],
        );

        return response()->json([
            'data' => [
                'movement' => new InventoryMovementResource($movement->loadMissing('product:id,organization_id,sku,name'))
                    ->resolve($request),
                'stock' => new InventoryStockResource($updatedStock->loadMissing('product:id,organization_id,sku,name,is_active'))
                    ->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }
}
