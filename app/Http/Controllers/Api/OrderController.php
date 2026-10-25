<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** Historial de compras del usuario autenticado (solo sus órdenes). */
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->withCount('items')
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate((int) ($request->validated('per_page') ?? 15))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user(), $request->items());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(['items', 'payments']));
    }

    public function cancel(Order $order): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($this->orders->cancel($order));
    }
}
