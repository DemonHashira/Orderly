<?php

namespace App\Http\Controllers\Api;

use App\Domain\Orders\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\ConfirmOrderRequest;
use App\Http\Requests\Orders\IndexOrdersRequest;
use App\Http\Requests\Orders\ReadyToShipOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderPricingService;
use App\Services\Orders\OrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderItemService $itemService,
        private readonly OrderPricingService $pricingService,
        private readonly OrderWorkflowService $workflowService,
    ) {}

    public function index(IndexOrdersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Order::class);

        $orgId = (int) $request->user()->organization_id;

        $query = Order::query()->forOrg($orgId)->with(['customer', 'salesChannel']);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = '%'.strtolower($search).'%';

            $query->where(function ($builder) use ($needle): void {
                $builder->whereRaw('LOWER(reference) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(internal_notes) LIKE ?', [$needle]);
            });
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('current_status', $status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->query('customer_id'));
        }

        if ($request->filled('sales_channel_id')) {
            $query->where('sales_channel_id', (int) $request->query('sales_channel_id'));
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', (string) $request->query('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', (string) $request->query('created_to'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return OrderResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }

    public function show(IndexOrdersRequest $request, int $order): OrderResource
    {
        $orderModel = Order::query()
            ->forOrg((int) $request->user()->organization_id)
            ->with([
                'customer',
                'salesChannel',
                'items',
                'statusHistory' => fn ($query) => $query->latest('id'),
            ])
            ->findOrFail($order);

        Gate::authorize('view', $orderModel);

        return new OrderResource($orderModel);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $orgId = (int) $request->user()->organization_id;
        $userId = (int) $request->user()->id;
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $order = DB::transaction(function () use ($data, $items, $orgId, $userId) {
            $order = Order::query()->create([
                'organization_id' => $orgId,
                'customer_id' => (int) $data['customer_id'],
                'sales_channel_id' => (int) $data['sales_channel_id'],
                'created_by' => $userId,
                'reference' => $this->generateReference(),
                'total_amount' => 0,
                'current_status' => OrderStatus::Draft->value,
                'internal_notes' => $data['internal_notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                    ? (string) $item['unit_price']
                    : null;

                $this->itemService->addItem(
                    orderId: (int) $order->id,
                    productId: (int) $item['product_id'],
                    quantity: (int) $item['quantity'],
                    unitPrice: $unitPrice,
                );
            }

            $this->pricingService->recalculateOrderTotals($order->refresh());

            OrderStatusHistory::query()->create([
                'order_id' => (int) $order->id,
                'status' => OrderStatus::Draft->value,
                'changed_by' => $userId,
            ]);

            return $order->refresh()->load([
                'customer',
                'salesChannel',
                'items',
                'statusHistory' => fn ($query) => $query->latest('id'),
            ]);
        });

        return new OrderResource($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateOrderRequest $request, int $order): OrderResource|JsonResponse
    {
        $orderModel = Order::query()
            ->forOrg((int) $request->user()->organization_id)
            ->with(['salesChannel', 'items'])
            ->with('customer')
            ->findOrFail($order);

        Gate::authorize('update', $orderModel);

        $draftGuard = $this->ensureDraftOrder(
            order: $orderModel,
            message: 'Only draft orders can be updated.',
            code: 'order_update_not_allowed',
        );
        if ($draftGuard !== null) {
            return $draftGuard;
        }

        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $orderModel = DB::transaction(function () use ($orderModel, $data, $items) {
            $orderModel->forceFill([
                'customer_id' => (int) $data['customer_id'],
                'sales_channel_id' => (int) $data['sales_channel_id'],
                'internal_notes' => $data['internal_notes'] ?? null,
            ])->save();

            $existingItemIds = $orderModel->items()->pluck('id');
            foreach ($existingItemIds as $itemId) {
                $this->itemService->removeItem((int) $itemId);
            }

            foreach ($items as $item) {
                $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                    ? (string) $item['unit_price']
                    : null;

                $this->itemService->addItem(
                    orderId: (int) $orderModel->id,
                    productId: (int) $item['product_id'],
                    quantity: (int) $item['quantity'],
                    unitPrice: $unitPrice,
                );
            }

            $this->pricingService->recalculateOrderTotals($orderModel->refresh());

            return $orderModel->refresh()->load([
                'customer',
                'salesChannel',
                'items',
                'statusHistory' => fn ($query) => $query->latest('id'),
            ]);
        });

        return new OrderResource($orderModel);
    }

    public function confirm(ConfirmOrderRequest $request, int $order): OrderResource
    {
        return $this->transitionOrder(
            requestOrgId: (int) $request->user()->organization_id,
            actorUserId: (int) $request->user()->id,
            orderId: $order,
            ability: 'confirm',
            toStatus: OrderStatus::Confirmed,
        );
    }

    public function readyToShip(ReadyToShipOrderRequest $request, int $order): OrderResource
    {
        return $this->transitionOrder(
            requestOrgId: (int) $request->user()->organization_id,
            actorUserId: (int) $request->user()->id,
            orderId: $order,
            ability: 'readyToShip',
            toStatus: OrderStatus::ReadyToShip,
        );
    }

    public function cancel(CancelOrderRequest $request, int $order): OrderResource
    {
        return $this->transitionOrder(
            requestOrgId: (int) $request->user()->organization_id,
            actorUserId: (int) $request->user()->id,
            orderId: $order,
            ability: 'cancel',
            toStatus: OrderStatus::Cancelled,
        );
    }

    public function destroy(IndexOrdersRequest $request, int $order): Response
    {
        $orderModel = Order::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($order);

        Gate::authorize('delete', $orderModel);

        $draftGuard = $this->ensureDraftOrder(
            order: $orderModel,
            message: 'Only draft orders can be deleted.',
            code: 'order_delete_not_allowed',
        );
        if ($draftGuard !== null) {
            return $draftGuard;
        }

        if ($orderModel->shipment()->exists() || $orderModel->return()->exists()) {
            return response()->json([
                'message' => 'Orders linked to shipment or return records cannot be deleted.',
                'code' => 'order_delete_not_allowed',
            ], Response::HTTP_CONFLICT);
        }

        DB::transaction(function () use ($orderModel): void {
            $orderModel->delete();
        });

        return response()->noContent();
    }

    private function transitionOrder(
        int $requestOrgId,
        int $actorUserId,
        int $orderId,
        string $ability,
        OrderStatus $toStatus,
    ): OrderResource {
        $orderModel = Order::query()
            ->forOrg($requestOrgId)
            ->findOrFail($orderId);

        Gate::authorize($ability, $orderModel);

        $orderModel = $this->workflowService->transition($orderModel->id, $toStatus, $actorUserId)
            ->load([
                'customer',
                'salesChannel',
                'items',
                'statusHistory' => fn ($query) => $query->latest('id'),
            ]);

        return new OrderResource($orderModel);
    }

    private function generateReference(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

            $exists = Order::query()->where('reference', $reference)->exists();
            if (! $exists) {
                return $reference;
            }
        }

        throw new RuntimeException('Unable to generate a unique order reference.');
    }

    private function ensureDraftOrder(Order $order, string $message, string $code): ?JsonResponse
    {
        if ($order->current_status === OrderStatus::Draft->value) {
            return null;
        }

        return response()->json([
            'message' => $message,
            'code' => $code,
        ], Response::HTTP_CONFLICT);
    }
}
