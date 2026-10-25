<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| Esquemas de datos y respuestas reutilizables
|--------------------------------------------------------------------------
*/

#[OA\Components(
    schemas: [
        new OA\Schema(
            schema: 'Error',
            required: ['message', 'code'],
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Recurso no encontrado.'),
                new OA\Property(property: 'code', type: 'string', example: 'NOT_FOUND'),
                new OA\Property(property: 'errors', type: 'object', nullable: true, description: 'Detalles adicionales (opcional).'),
            ],
        ),
        new OA\Schema(
            schema: 'ValidationError',
            required: ['message', 'code', 'errors'],
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Los datos enviados no son válidos.'),
                new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
                new OA\Property(
                    property: 'errors',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
                    example: ['email' => ['El campo correo electrónico es obligatorio.']],
                ),
            ],
        ),
        new OA\Schema(
            schema: 'User',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 3),
                new OA\Property(property: 'name', type: 'string', example: 'Ana Pérez'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana@example.com'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+503 7000-0000'),
                new OA\Property(property: 'role', type: 'string', enum: ['customer', 'admin'], example: 'customer'),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            ],
        ),
        new OA\Schema(
            schema: 'AuthToken',
            properties: [
                new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.ejemplo.firma'),
                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                new OA\Property(property: 'expires_in', type: 'integer', description: 'Segundos de validez del token.', example: 3600),
                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            ],
        ),
        new OA\Schema(
            schema: 'Product',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'sku', type: 'string', example: 'TEC-001'),
                new OA\Property(property: 'name', type: 'string', example: 'Teclado mecánico TKL'),
                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Teclado mecánico con switches rojos.'),
                new OA\Property(property: 'price', type: 'string', pattern: '^\d+\.\d{2}$', example: '79.90'),
                new OA\Property(property: 'stock', type: 'integer', example: 40),
                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            ],
        ),
        new OA\Schema(
            schema: 'ProductInput',
            required: ['sku', 'name', 'price', 'stock'],
            properties: [
                new OA\Property(property: 'sku', type: 'string', maxLength: 64, pattern: '^[A-Z0-9-]+$', description: 'Único. Se convierte a mayúsculas.', example: 'TEC-100'),
                new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 150, example: 'Micrófono USB de condensador'),
                new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true, example: 'Micrófono cardioide con soporte de escritorio.'),
                new OA\Property(property: 'price', type: 'string', description: 'Decimal con máximo 2 decimales, mínimo 0.50.', example: '64.90'),
                new OA\Property(property: 'stock', type: 'integer', minimum: 0, maximum: 1000000, example: 25),
                new OA\Property(property: 'is_active', type: 'boolean', default: true, example: true),
            ],
        ),
        new OA\Schema(
            schema: 'ProductUpdateInput',
            description: 'Actualización parcial: envíe solo los campos a modificar.',
            properties: [
                new OA\Property(property: 'sku', type: 'string', maxLength: 64, example: 'TEC-100'),
                new OA\Property(property: 'name', type: 'string', maxLength: 150, example: 'Micrófono USB profesional'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'string', example: '59.90'),
                new OA\Property(property: 'stock', type: 'integer', example: 30),
                new OA\Property(property: 'is_active', type: 'boolean', example: false),
            ],
        ),
        new OA\Schema(
            schema: 'PaginationLinks',
            properties: [
                new OA\Property(property: 'first', type: 'string', example: 'http://127.0.0.1:8000/api/products?page=1'),
                new OA\Property(property: 'last', type: 'string', example: 'http://127.0.0.1:8000/api/products?page=2'),
                new OA\Property(property: 'prev', type: 'string', nullable: true, example: null),
                new OA\Property(property: 'next', type: 'string', nullable: true, example: 'http://127.0.0.1:8000/api/products?page=2'),
            ],
        ),
        new OA\Schema(
            schema: 'PaginationMeta',
            properties: [
                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'last_page', type: 'integer', example: 2),
                new OA\Property(property: 'path', type: 'string', example: 'http://127.0.0.1:8000/api/products'),
                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
                new OA\Property(property: 'total', type: 'integer', example: 20),
                new OA\Property(property: 'links', type: 'array', items: new OA\Items(type: 'object')),
            ],
        ),
        new OA\Schema(
            schema: 'ProductCollection',
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product')),
                new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ],
        ),
        new OA\Schema(
            schema: 'OrderItem',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                new OA\Property(property: 'product_name', type: 'string', description: 'Copia del nombre al momento de la compra.', example: 'Teclado mecánico TKL'),
                new OA\Property(property: 'product_sku', type: 'string', example: 'TEC-001'),
                new OA\Property(property: 'unit_price', type: 'string', description: 'Precio unitario aplicado en la compra.', example: '79.90'),
                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                new OA\Property(property: 'subtotal', type: 'string', example: '159.80'),
            ],
        ),
        new OA\Schema(
            schema: 'Payment',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'order_id', type: 'integer', example: 1),
                new OA\Property(property: 'provider', type: 'string', example: 'stripe'),
                new OA\Property(property: 'stripe_payment_intent_id', type: 'string', nullable: true, example: 'pi_3Example000000000000000'),
                new OA\Property(property: 'amount', type: 'string', example: '194.30'),
                new OA\Property(property: 'currency', type: 'string', example: 'usd'),
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'succeeded', 'failed', 'canceled'], example: 'pending'),
                new OA\Property(property: 'failure_message', type: 'string', nullable: true, example: null),
                new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            ],
        ),
        new OA\Schema(
            schema: 'Order',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'user_id', type: 'integer', example: 3),
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid', 'shipped', 'delivered', 'cancelled'], example: 'pending'),
                new OA\Property(property: 'currency', type: 'string', example: 'usd'),
                new OA\Property(property: 'subtotal', type: 'string', example: '194.30'),
                new OA\Property(property: 'total', type: 'string', example: '194.30'),
                new OA\Property(property: 'items_count', type: 'integer', description: 'Solo en listados.', example: 2),
                new OA\Property(property: 'items', type: 'array', description: 'Solo en el detalle y al crear.', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
                new OA\Property(property: 'payments', type: 'array', description: 'Solo en el detalle.', items: new OA\Items(ref: '#/components/schemas/Payment')),
                new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
            ],
        ),
        new OA\Schema(
            schema: 'OrderCollection',
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
                new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
            ],
        ),
        new OA\Schema(
            schema: 'OrderInput',
            required: ['items'],
            description: 'Solo se aceptan producto y cantidad. Precios, totales, usuario y estado los calcula el servidor.',
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    minItems: 1,
                    maxItems: 50,
                    items: new OA\Items(
                        required: ['product_id', 'quantity'],
                        properties: [
                            new OA\Property(property: 'product_id', type: 'integer', description: 'Producto activo existente; sin repetir.', example: 1),
                            new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 100, example: 2),
                        ],
                    ),
                ),
            ],
            example: ['items' => [['product_id' => 1, 'quantity' => 2], ['product_id' => 2, 'quantity' => 1]]],
        ),
        new OA\Schema(
            schema: 'PaymentInit',
            properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'payment', ref: '#/components/schemas/Payment'),
                    new OA\Property(property: 'client_secret', type: 'string', nullable: true, description: 'Para confirmar con Stripe.js. No lo almacene ni lo registre en logs.', example: 'pi_3Example000000000000000_secret_ejemplo'),
                    new OA\Property(property: 'publishable_key', type: 'string', nullable: true, description: 'Clave pública de Stripe (pk_test_...).', example: 'pk_test_ejemplo'),
                ], type: 'object'),
                new OA\Property(property: 'message', type: 'string', example: 'Pago iniciado. La orden se marcará como pagada cuando Stripe confirme el cobro mediante webhook.'),
            ],
        ),
    ],
    responses: [
        new OA\Response(
            response: 'Unauthenticated',
            description: 'No autenticado: falta el token, es inválido, expiró o fue invalidado.',
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: [
                'message' => 'No autenticado. Envíe un token JWT válido en la cabecera Authorization: Bearer <token>.',
                'code' => 'UNAUTHENTICATED',
            ]),
        ),
        new OA\Response(
            response: 'Forbidden',
            description: 'Autenticado pero sin permisos (se requiere rol administrador).',
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: [
                'message' => 'No tiene permisos para realizar esta acción.',
                'code' => 'FORBIDDEN',
            ]),
        ),
        new OA\Response(
            response: 'NotFound',
            description: 'Recurso inexistente o perteneciente a otro usuario.',
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Recurso no encontrado.', 'code' => 'NOT_FOUND']),
        ),
        new OA\Response(
            response: 'ValidationFailed',
            description: 'Error de validación.',
            content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
        ),
        new OA\Response(
            response: 'TooManyRequests',
            description: 'Límite de solicitudes excedido. Ver cabecera Retry-After.',
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: [
                'message' => 'Demasiadas solicitudes. Intente nuevamente más tarde.',
                'code' => 'TOO_MANY_REQUESTS',
            ]),
        ),
        new OA\Response(
            response: 'MalformedJson',
            description: 'El cuerpo no es JSON válido.',
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: [
                'message' => 'El cuerpo de la petición no es un JSON válido.',
                'code' => 'MALFORMED_JSON',
            ]),
        ),
        new OA\Response(
            response: 'ServerError',
            description: 'Error interno inesperado (sin detalles internos).',
            content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Error interno del servidor.', 'code' => 'SERVER_ERROR']),
        ),
    ],
    parameters: [
        new OA\Parameter(
            parameter: 'OrderId',
            name: 'order',
            in: 'path',
            required: true,
            description: 'Identificador de la orden.',
            schema: new OA\Schema(type: 'integer', minimum: 1),
            example: 1,
        ),
        new OA\Parameter(
            parameter: 'ProductId',
            name: 'product',
            in: 'path',
            required: true,
            description: 'Identificador del producto.',
            schema: new OA\Schema(type: 'integer', minimum: 1),
            example: 1,
        ),
        new OA\Parameter(
            parameter: 'PerPage',
            name: 'per_page',
            in: 'query',
            required: false,
            description: 'Elementos por página (1-100).',
            schema: new OA\Schema(type: 'integer', default: 15, minimum: 1, maximum: 100),
        ),
        new OA\Parameter(
            parameter: 'Page',
            name: 'page',
            in: 'query',
            required: false,
            description: 'Número de página.',
            schema: new OA\Schema(type: 'integer', default: 1, minimum: 1),
        ),
    ],
)]
final class Schemas {}
