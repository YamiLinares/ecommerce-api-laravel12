<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class ProductPaths
{
    #[OA\Get(
        path: '/api/products',
        operationId: 'productsIndex',
        security: [],
        summary: '🌐 Listar productos',
        description: 'Listado público paginado de productos activos. Un administrador autenticado puede incluir inactivos con `include_inactive=1`.',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Busca en nombre, SKU y descripción.', schema: new OA\Schema(type: 'string', maxLength: 100), example: 'teclado'),
            new OA\Parameter(name: 'min_price', in: 'query', required: false, description: 'Precio mínimo.', schema: new OA\Schema(type: 'number', minimum: 0), example: 10),
            new OA\Parameter(name: 'max_price', in: 'query', required: false, description: 'Precio máximo (≥ min_price).', schema: new OA\Schema(type: 'number', minimum: 0), example: 300),
            new OA\Parameter(name: 'in_stock', in: 'query', required: false, description: '1 = con stock, 0 = agotados.', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'include_inactive', in: 'query', required: false, description: 'Solo administradores (requiere token).', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, description: 'Campo de orden; prefijo "-" para descendente.', schema: new OA\Schema(type: 'string', default: 'name', enum: ['name', '-name', 'price', '-price', 'created_at', '-created_at'])),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Página de productos.', content: new OA\JsonContent(ref: '#/components/schemas/ProductCollection')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function index(): void {}

    #[OA\Get(
        path: '/api/products/{product}',
        operationId: 'productsShow',
        security: [],
        summary: '🌐 Consultar un producto',
        description: 'Los productos inactivos o eliminados devuelven 404 (salvo inactivos para administradores).',
        tags: ['Productos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ProductId')],
        responses: [
            new OA\Response(response: 200, description: 'Producto.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
            )),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/api/products',
        operationId: 'productsStore',
        summary: '🛡️ Crear producto (administrador)',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductInput')),
        responses: [
            new OA\Response(response: 201, description: 'Producto creado.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
            )),
            new OA\Response(response: 400, ref: '#/components/responses/MalformedJson'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function store(): void {}

    #[OA\Put(
        path: '/api/products/{product}',
        operationId: 'productsUpdate',
        summary: '🛡️ Actualizar producto (administrador)',
        description: 'También acepta `PATCH`. Actualización parcial: solo se modifican los campos enviados.',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ProductId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductUpdateInput')),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    #[OA\Patch(
        path: '/api/products/{product}',
        operationId: 'productsPatch',
        summary: '🛡️ Actualizar parcialmente un producto (administrador)',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ProductId')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ProductUpdateInput')),
        responses: [
            new OA\Response(response: 200, description: 'Producto actualizado.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/products/{product}',
        operationId: 'productsDestroy',
        summary: '🛡️ Eliminar producto (administrador)',
        description: 'Borrado lógico (soft delete): el producto deja de mostrarse, pero se conserva el historial de órdenes.',
        security: [['bearerAuth' => []]],
        tags: ['Productos'],
        parameters: [new OA\Parameter(ref: '#/components/parameters/ProductId')],
        responses: [
            new OA\Response(response: 204, description: 'Producto eliminado.'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ],
    )]
    public function destroy(): void {}
}
