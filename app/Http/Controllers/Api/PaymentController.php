<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Order $order): AnonymousResourceCollection
    {
        Gate::authorize('view', $order);

        return PaymentResource::collection($order->payments()->latest('id')->get());
    }

    /**
     * Crea (o reutiliza) el PaymentIntent de Stripe. Devuelve el client_secret para
     * confirmar el pago con Stripe.js; la orden se marca pagada solo vía webhook.
     */
    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        $result = $this->payments->initiate($order, $request->validated('payment_method'));

        return response()->json([
            'data' => [
                'payment' => new PaymentResource($result['payment']),
                'client_secret' => $result['client_secret'],
                'publishable_key' => config('services.stripe.key') ?: null,
            ],
            'message' => 'Pago iniciado. La orden se marcará como pagada cuando Stripe confirme el cobro mediante webhook.',
        ], $result['created'] ? 201 : 200);
    }
}
