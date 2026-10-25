<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

final class AuthPaths
{
    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'authRegister',
        security: [],
        summary: '🌐 Registrar un cliente',
        description: 'Crea una cuenta con rol `customer` y devuelve un JWT. Cualquier campo `role` enviado se ignora. Límite: 5 intentos/minuto por correo e IP.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 120, example: 'Ana Pérez'),
                new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 191, example: 'ana@example.com'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+503 7000-0000'),
                new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, description: 'Mínimo 8 caracteres, con mayúscula, minúscula y número.', example: 'Secreta123'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Secreta123'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado y autenticado.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AuthToken')],
            )),
            new OA\Response(response: 400, ref: '#/components/responses/MalformedJson'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
            new OA\Response(response: 500, ref: '#/components/responses/ServerError'),
        ],
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'authLogin',
        security: [],
        summary: '🌐 Iniciar sesión',
        description: 'Valida las credenciales y emite un JWT (HS256) con expiración. Límite: 5 intentos/minuto por correo e IP.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secreta123'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Token emitido.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AuthToken')],
            )),
            new OA\Response(response: 401, description: 'Credenciales incorrectas.', content: new OA\JsonContent(
                ref: '#/components/schemas/Error',
                example: ['message' => 'Credenciales incorrectas.', 'code' => 'INVALID_CREDENTIALS'],
            )),
            new OA\Response(response: 400, ref: '#/components/responses/MalformedJson'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationFailed'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function login(): void {}

    #[OA\Get(
        path: '/api/auth/me',
        operationId: 'authMe',
        summary: '🔒 Perfil del usuario autenticado',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 200, description: 'Perfil.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function me(): void {}

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'authLogout',
        summary: '🔒 Cerrar sesión',
        description: 'Agrega el token actual a la lista negra: deja de ser válido inmediatamente.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string', example: 'Sesión cerrada correctamente. El token fue invalidado.')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ],
    )]
    public function logout(): void {}

    #[OA\Post(
        path: '/api/auth/refresh',
        operationId: 'authRefresh',
        summary: '🔒 Renovar token',
        description: 'Emite un token nuevo e invalida el actual. Requiere un token todavía vigente.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 200, description: 'Token renovado.', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AuthToken')],
            )),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 429, ref: '#/components/responses/TooManyRequests'),
        ],
    )]
    public function refresh(): void {}
}
