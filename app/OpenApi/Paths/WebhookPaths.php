<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class WebhookPaths
{
    #[OA\Post(
        path: '/api/webhooks/stripe',
        operationId: 'stripeWebhook',
        security: [],
        summary: '✍️ Webhook de Stripe',
        description: <<<'MD'
Endpoint invocado **por Stripe** (no por clientes). No usa JWT: se autentica verificando la cabecera `Stripe-Signature` con `STRIPE_WEBHOOK_SECRET` (HMAC-SHA256, tolerancia de 5 minutos).

Eventos procesados: `payment_intent.succeeded` (pago exitoso → orden `paid`), `payment_intent.payment_failed`, `payment_intent.processing`, `payment_intent.canceled`. Otros eventos se registran y se ignoran.

**Idempotencia:** cada `event.id` se registra en `stripe_webhook_events`; un evento repetido responde 200 con `duplicate: true` sin volver a aplicarse.
Antes de marcar la orden como pagada se verifica que el importe y la moneda cobrados coincidan con el pago registrado.

En local, use Stripe CLI: `stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe`. Desde esta página solo puede comprobarse el rechazo de firmas inválidas.
MD,
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'Stripe-Signature', in: 'header', required: true, description: 'Firma generada por Stripe.', schema: new OA\Schema(type: 'string'), example: 't=1700000000,v1=firma_hex_de_ejemplo'),
        ],
        requestBody: new OA\RequestBody(required: true, description: 'Objeto Event de Stripe.', content: new OA\JsonContent(
            type: 'object',
            example: [
                'id' => 'evt_ejemplo000000000000000',
                'object' => 'event',
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => [
                    'id' => 'pi_3Example000000000000000',
                    'object' => 'payment_intent',
                    'amount' => 19430,
                    'amount_received' => 19430,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'metadata' => ['order_id' => '1', 'user_id' => '3', 'payment_id' => '1'],
                ]],
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Evento recibido (procesado o duplicado).', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'received', type: 'boolean', example: true),
                    new OA\Property(property: 'duplicate', type: 'boolean', example: false),
                    new OA\Property(property: 'event_id', type: 'string', example: 'evt_ejemplo000000000000000'),
                ],
            )),
            new OA\Response(response: 400, description: 'Firma o contenido inválido.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: ['message' => 'Firma del webhook inválida.', 'code' => 'INVALID_SIGNATURE'],
            )),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 503, description: 'STRIPE_WEBHOOK_SECRET no configurado.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: ['message' => 'Webhook de Stripe no configurado.', 'code' => 'WEBHOOK_NOT_CONFIGURED'],
            )),
        ],
    )]
    public function handle(): void {}
}
