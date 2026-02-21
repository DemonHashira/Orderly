<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\AddReturnItemRequest;
use App\Http\Requests\Returns\IndexReturnsRequest;
use App\Http\Requests\Returns\RestockReturnRequest;
use App\Http\Resources\ReturnOrderResource;
use App\Models\Order;
use App\Models\ReturnOrder;
use App\Services\Returns\ReturnService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ReturnController extends Controller
{
    public function __construct(
        private readonly ReturnService $returnService,
    ) {}

    public function index(IndexReturnsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ReturnOrder::class);

        $orgId = (int) $request->user()->organization_id;

        $query = ReturnOrder::query()
            ->whereHas('order', fn ($builder) => $builder->forOrg($orgId))
            ->with([
                'order:id,organization_id,reference,current_status',
                'items.product:id,name,sku',
            ]);

        if ($request->filled('order_id')) {
            $query->where('order_id', (int) $request->query('order_id'));
        }

        $reason = trim((string) $request->query('reason', ''));
        if ($reason !== '') {
            $query->where('reason', 'like', '%'.$reason.'%');
        }

        if ($request->filled('returned_from')) {
            $query->whereDate('returned_at', '>=', (string) $request->query('returned_from'));
        }

        if ($request->filled('returned_to')) {
            $query->whereDate('returned_at', '<=', (string) $request->query('returned_to'));
        }

        if ($request->filled('has_restockable')) {
            $hasRestockable = filter_var($request->query('has_restockable'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($hasRestockable === true) {
                $query->whereHas('items', fn ($builder) => $builder->where('restockable', true));
            }
            if ($hasRestockable === false) {
                $query->whereDoesntHave('items', fn ($builder) => $builder->where('restockable', true));
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return ReturnOrderResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }

    public function show(IndexReturnsRequest $request, int $return): ReturnOrderResource
    {
        $returnOrder = $this->resolveReturnForOrg(
            returnId: $return,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('view', $returnOrder);

        return new ReturnOrderResource($returnOrder);
    }

    public function showByOrder(IndexReturnsRequest $request, int $order): ReturnOrderResource
    {
        $orderModel = Order::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($order);

        $returnOrder = ReturnOrder::query()
            ->where('order_id', $orderModel->id)
            ->with([
                'order:id,organization_id,reference,current_status',
                'items.product:id,name,sku',
            ])
            ->firstOrFail();

        Gate::authorize('view', $returnOrder);

        return new ReturnOrderResource($returnOrder);
    }

    public function addItem(AddReturnItemRequest $request, int $return): ReturnOrderResource
    {
        $returnOrder = $this->resolveReturnForOrg(
            returnId: $return,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('addItem', $returnOrder);

        $data = $request->validated();

        $this->returnService->addReturnItem(
            returnOrderId: (int) $returnOrder->id,
            productId: (int) $data['product_id'],
            quantity: (int) $data['quantity'],
            restockable: (bool) $data['restockable'],
        );

        return new ReturnOrderResource($returnOrder->refresh()->load([
            'order:id,organization_id,reference,current_status',
            'items.product:id,name,sku',
        ]));
    }

    public function restock(RestockReturnRequest $request, int $return): ReturnOrderResource
    {
        $returnOrder = $this->resolveReturnForOrg(
            returnId: $return,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('restock', $returnOrder);

        $returnOrder = $this->returnService->restockReturn(
            returnOrderId: (int) $returnOrder->id,
            actorUserId: (int) $request->user()->id,
        );

        return new ReturnOrderResource($returnOrder->load([
            'order:id,organization_id,reference,current_status',
            'items.product:id,name,sku',
        ]));
    }

    private function resolveReturnForOrg(int $returnId, int $orgId): ReturnOrder
    {
        return ReturnOrder::query()
            ->whereHas('order', fn ($builder) => $builder->forOrg($orgId))
            ->with([
                'order:id,organization_id,reference,current_status',
                'items.product:id,name,sku',
            ])
            ->findOrFail($returnId);
    }
}
