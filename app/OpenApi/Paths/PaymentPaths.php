<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class PaymentPaths
{
    #[OA\Get(
        path: '/api/orders/{order}/payments',
        operationId: 'paymentsIndex',
        summary: '🔒 Pagos de una orden',
        security: [['bearerAuth' => []]],
        tags: ['Pagos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        responses: [
            new OA\Response(response: 200, description: 'Pagos registrados.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment'))],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/orders/{order}/payments',
        operationId: 'paymentsStore',
        summary: '🔒 Iniciar el pago de una orden con Stripe',
        description: <<<'MD'
Crea un **PaymentIntent** de Stripe por el total de la orden (calculado en el servidor) y devuelve su `client_secret` para confirmarlo con Stripe.js.
Si la orden ya tiene un pago abierto, se reutiliza (respuesta 200) en lugar de crear otro: la operación es idempotente.

**Modo de pruebas sin frontend:** envíe `payment_method` con un método de prueba de Stripe (`pm_card_visa`, `pm_card_chargeDeclined`, …) para confirmar desde el servidor.

La orden **no** se marca como pagada aquí: solo cuando llega el webhook firmado `payment_intent.succeeded`.
Solo el dueño de la orden puede pagarla. Límite: 10 solicitudes/minuto.
MD,
        security: [['bearerAuth' => []]],
        tags: ['Pagos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(
            properties: [new OA\Property(property: 'payment_method', type: 'string', nullable: true, pattern: '^pm_[A-Za-z0-9_]+$', example: 'pm_card_visa')],
        )),
        responses: [
            new OA\Response(response: 201, description: 'PaymentIntent creado.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentInit')),
            new OA\Response(response: 200, description: 'Se reutilizó el pago abierto existente.', content: new OA\JsonContent(ref: '#/components/schemas/PaymentInit')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'La orden no está pendiente.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: ['message' => 'Solo se pueden pagar órdenes pendientes.', 'code' => 'ORDER_NOT_PAYABLE', 'errors' => ['status' => 'paid']],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 502, description: 'Stripe no respondió o rechazó la solicitud.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: ['message' => 'No fue posible comunicarse con el proveedor de pagos. Intente más tarde.', 'code' => 'PAYMENT_GATEWAY_ERROR'],
            )),
            new OA\Response(response: 503, description: 'Stripe no está configurado en el servidor (falta STRIPE_SECRET).', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: ['message' => 'El proveedor de pagos no está configurado.', 'code' => 'PAYMENTS_NOT_CONFIGURED'],
            )),
        ],
    )]
    public function store(): void {}
}
