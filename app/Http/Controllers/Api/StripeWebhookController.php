<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeWebhookService $webhooks) {}

    /** Recibe eventos de Stripe. Se usa el cuerpo crudo para verificar la firma. */
    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->webhooks->handle($request->getContent(), $request->header('Stripe-Signature'));

        return response()->json([
            'received' => true,
            'duplicate' => $result['duplicate'],
            'event_id' => $result['event_id'],
        ]);
    }
}
