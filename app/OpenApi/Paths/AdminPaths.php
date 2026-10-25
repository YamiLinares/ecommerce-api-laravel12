<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class AdminPaths
{
    #[OA\Get(
        path: '/api/admin/orders',
        operationId: 'adminOrdersIndex',
        summary: '🛡️ Listar todas las órdenes (administrador)',
        security: [['bearerAuth' => []]],
        tags: ['Administración'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'paid', 'shipped', 'delivered', 'cancelled'])),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, description: 'Filtrar por cliente.', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Página de órdenes.', content: new OA\JsonContent(ref: '#/components/schemas/OrderCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function index(): void {}

    #[OA\Patch(
        path: '/api/admin/orders/{order}/status',
        operationId: 'adminOrdersUpdateStatus',
        summary: '🛡️ Cambiar el estado de una orden (administrador)',
        description: 'Transiciones permitidas: `paid → shipped`, `shipped → delivered`, `pending → cancelled`. El estado `paid` no puede asignarse manualmente: solo lo establece el webhook firmado de Stripe.',
        security: [['bearerAuth' => []]],
        tags: ['Administración'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/OrderId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['status'],
            properties: [new OA\Property(property: 'status', type: 'string', enum: ['shipped', 'delivered', 'cancelled'], example: 'shipped')],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 409, description: 'Transición no permitida.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: [
                    'message' => 'No se puede cambiar la orden de "pending" a "shipped".',
                    'code' => 'INVALID_STATUS_TRANSITION',
                    'errors' => ['from' => 'pending', 'to' => 'shipped'],
                ],
            )),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function updateStatus(): void {}
}
