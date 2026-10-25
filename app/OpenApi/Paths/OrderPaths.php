<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class OrderPaths
{
    #[OA\Get(
        path: '/api/orders',
        operationId: 'ordersIndex',
        summary: '🔒 Historial de compras',
        description: 'Devuelve únicamente las órdenes del usuario autenticado, de la más reciente a la más antigua.',
        security: [['bearerAuth' => []]],
        tags: ['Órdenes'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filtrar por estado.', schema: new OA\Schema(type: 'string', enum: ['pending', 'paid', 'shipped', 'delivered', 'cancelled'])),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Página de órdenes.', content: new OA\JsonContent(ref: '#/components/schemas/OrderCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/orders',
        operationId: 'ordersStore',
        summary: '🔒 Crear una orden',
        description: <<<'MD'
Crea una orden en estado `pending` para el usuario autenticado y **reserva el stock** dentro de una transacción con bloqueo de filas (`SELECT ... FOR UPDATE`).
Los precios unitarios se copian del catálogo en el momento de la compra; subtotales y total los calcula el servidor.
Campos como `total`, `price`, `user_id` o `status` enviados por el cliente se ignoran. El total máximo es 999,999.99 (límite de Stripe). Límite: 20 órdenes/minuto.
MD,
        security: [['bearerAuth' => []]],
        tags: ['Órdenes'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/OrderInput')),
        responses: [
            new OA\Response(response: 201, description: 'Orden creada.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
            )),
            new OA\Response(response: 400, ref: '#/components/responses/MalformedJson'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 409, description: 'Stock insuficiente, producto no disponible o inventario bloqueado por otra compra.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: [
                    'message' => 'Stock insuficiente para el producto "Laptop 14 pulgadas ultraligera".',
                    'code' => 'INSUFFICIENT_STOCK',
                    'errors' => ['product_id' => 8, 'available' => 8, 'requested' => 99],
                ],
            )),
            new OA\Response(response: 422, description: 'Error de validación (VALIDATION_ERROR) o total superior al máximo (ORDER_TOTAL_TOO_LARGE).', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/orders/{order}',
        operationId: 'ordersShow',
        summary: '🔒 Detalle de una orden',
        description: 'Incluye productos y pagos. Una orden de otro usuario responde 404 (no se revela su existencia); los administradores pueden ver cualquiera.',
        security: [['bearerAuth' => []]],
        tags: ['Órdenes'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        responses: [
            new OA\Response(response: 200, description: 'Orden.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/api/orders/{order}/cancel',
        operationId: 'ordersCancel',
        summary: '🔒 Cancelar una orden pendiente',
        description: 'Solo órdenes `pending`. Devuelve el stock reservado y cancela en Stripe los pagos abiertos. Si Stripe ya está procesando o cobró el pago, responde 409.',
        security: [['bearerAuth' => []]],
        tags: ['Órdenes'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        responses: [
            new OA\Response(response: 200, description: 'Orden cancelada.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Transición no permitida o pago en curso.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: [
                    'message' => 'No se puede cambiar la orden de "paid" a "cancelled".',
                    'code' => 'INVALID_STATUS_TRANSITION',
                    'errors' => ['from' => 'paid', 'to' => 'cancelled'],
                ],
            )),
            new OA\Response(response: 502, description: 'Error al comunicarse con Stripe.', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function cancel(): void {}
}
