<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Gestión administrativa de órdenes (protegida con el middleware "admin").
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->withCount('items')
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->validated('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->latest('id')
            ->paginate((int) ($request->validated('per_page') ?? 15))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        return new OrderResource($this->orders->transition($order, $request->status()));
    }
}
