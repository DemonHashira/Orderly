<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\IndexShipmentsRequest;
use App\Http\Requests\Shipments\MarkDeliveredShipmentRequest;
use App\Http\Requests\Shipments\MarkReturnedShipmentRequest;
use App\Http\Requests\Shipments\MarkUnpaidShipmentRequest;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Resources\ReturnSummaryResource;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Logistics\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentService $shipmentService,
    ) {}

    public function index(IndexShipmentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Shipment::class);

        $orgId = (int) $request->user()->organization_id;

        $query = Shipment::query()
            ->whereHas('order', fn ($builder) => $builder->forOrg($orgId))
            ->with(['order:id,organization_id,reference,current_status']);

        if ($request->filled('order_id')) {
            $query->where('order_id', (int) $request->query('order_id'));
        }

        $courier = trim((string) $request->query('courier', ''));
        if ($courier !== '') {
            $query->where('courier', 'like', '%'.$courier.'%');
        }

        $tracking = trim((string) $request->query('tracking_number', ''));
        if ($tracking !== '') {
            $query->where('tracking_number', 'like', '%'.$tracking.'%');
        }

        $outcome = trim((string) $request->query('outcome', ''));
        if ($outcome !== '') {
            $query->whereHas('order', fn ($builder) => $builder->where('current_status', $outcome));
        }

        if ($request->filled('shipped_from')) {
            $query->whereDate('shipped_at', '>=', (string) $request->query('shipped_from'));
        }

        if ($request->filled('shipped_to')) {
            $query->whereDate('shipped_at', '<=', (string) $request->query('shipped_to'));
        }

        if ($request->filled('delivered')) {
            $delivered = filter_var($request->query('delivered'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($delivered === true) {
                $query->whereNotNull('delivered_at');
            }

            if ($delivered === false) {
                $query->whereNull('delivered_at');
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return ShipmentResource::collection(
            $query->latest('id')->paginate($perPage)->withQueryString(),
        );
    }

    public function show(IndexShipmentsRequest $request, int $shipment): ShipmentResource
    {
        $shipmentModel = $this->resolveShipmentForOrg(
            shipmentId: $shipment,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('view', $shipmentModel);

        return new ShipmentResource($shipmentModel);
    }

    public function store(StoreShipmentRequest $request, int $order): JsonResponse
    {
        $orderModel = Order::query()
            ->forOrg((int) $request->user()->organization_id)
            ->findOrFail($order);

        Gate::authorize('create', Shipment::class);

        $data = $request->validated();

        $shipmentModel = $this->shipmentService->createShipment(
            orderId: (int) $orderModel->id,
            actorUserId: (int) $request->user()->id,
            courier: (string) $data['courier'],
            trackingNumber: (string) ($data['tracking_number'] ?? ''),
            shippedAt: array_key_exists('shipped_at', $data) && $data['shipped_at'] !== null
                ? Carbon::parse((string) $data['shipped_at'])
                : null,
        )->load(['order:id,organization_id,reference,current_status']);

        return new ShipmentResource($shipmentModel)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function markDelivered(MarkDeliveredShipmentRequest $request, int $shipment): ShipmentResource
    {
        $shipmentModel = $this->resolveShipmentForOrg(
            shipmentId: $shipment,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('markDelivered', $shipmentModel);

        $data = $request->validated();

        $shipmentModel = $this->shipmentService->markDelivered(
            shipmentId: (int) $shipmentModel->id,
            actorUserId: (int) $request->user()->id,
            deliveredAt: array_key_exists('delivered_at', $data) && $data['delivered_at'] !== null
                ? Carbon::parse((string) $data['delivered_at'])
                : null,
        )->load(['order:id,organization_id,reference,current_status']);

        return new ShipmentResource($shipmentModel);
    }

    public function markReturned(MarkReturnedShipmentRequest $request, int $shipment): JsonResponse
    {
        $shipmentModel = $this->resolveShipmentForOrg(
            shipmentId: $shipment,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('markReturned', $shipmentModel);

        $data = $request->validated();

        [$updatedShipment, $returnOrder] = $this->shipmentService->markReturned(
            shipmentId: (int) $shipmentModel->id,
            actorUserId: (int) $request->user()->id,
            reason: (string) $data['reason'],
            returnedAt: array_key_exists('returned_at', $data) && $data['returned_at'] !== null
                ? Carbon::parse((string) $data['returned_at'])
                : null,
        );

        return response()->json([
            'shipment' => new ShipmentResource($updatedShipment->load(['order:id,organization_id,reference,current_status'])),
            'return' => new ReturnSummaryResource($returnOrder),
        ]);
    }

    public function markUnpaid(MarkUnpaidShipmentRequest $request, int $shipment): JsonResponse
    {
        $shipmentModel = $this->resolveShipmentForOrg(
            shipmentId: $shipment,
            orgId: (int) $request->user()->organization_id,
        );

        Gate::authorize('markUnpaid', $shipmentModel);

        $data = $request->validated();

        [$updatedShipment, $returnOrder] = $this->shipmentService->markUnpaid(
            shipmentId: (int) $shipmentModel->id,
            actorUserId: (int) $request->user()->id,
            reason: (string) $data['reason'],
            returnedAt: array_key_exists('returned_at', $data) && $data['returned_at'] !== null
                ? Carbon::parse((string) $data['returned_at'])
                : null,
        );

        return response()->json([
            'shipment' => new ShipmentResource($updatedShipment->load(['order:id,organization_id,reference,current_status'])),
            'return' => new ReturnSummaryResource($returnOrder),
        ]);
    }

    private function resolveShipmentForOrg(int $shipmentId, int $orgId): Shipment
    {
        return Shipment::query()
            ->whereHas('order', fn ($builder) => $builder->forOrg($orgId))
            ->with(['order:id,organization_id,reference,current_status'])
            ->findOrFail($shipmentId);
    }
}
