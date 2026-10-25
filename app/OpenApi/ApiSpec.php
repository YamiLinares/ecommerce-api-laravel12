<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(openapi: '3.0.0')]
#[OA\Info(
    version: '1.0.0',
    title: 'API de E-commerce Segura',
    description: <<<'MD'
API REST de comercio electrónico construida con **Laravel 12**, **MySQL**, autenticación **JWT** y pagos con **Stripe** (modo de pruebas).

### Niveles de acceso
| Etiqueta | Requisito |
|---|---|
| 🌐 **Público** | No requiere token. |
| 🔒 **Autenticado** | Cabecera `Authorization: Bearer <token>` con un JWT válido. |
| 🛡️ **Administrador** | JWT de un usuario con rol `admin`. |
| ✍️ **Firma Stripe** | Cabecera `Stripe-Signature` válida (solo Stripe). |

### Cómo autenticarse en esta página
1. Ejecute `POST /api/auth/login` (o `register`) y copie `data.access_token`.
2. Pulse **Authorize** (arriba a la derecha), pegue el token **sin** el prefijo `Bearer` y confirme.
3. Los endpoints con candado enviarán el token automáticamente.

El token expira a los 60 minutos (`expires_in` en segundos). `POST /api/auth/logout` lo invalida inmediatamente.

### Formato de errores
Todos los errores usan la misma estructura: `{"message": "...", "code": "CODIGO", "errors": {...}}`.
`errors` aparece solo en validaciones (422) y en algunos conflictos (409).

### Estados de una orden
`pending → paid` (solo mediante webhook firmado de Stripe) · `pending → cancelled` · `paid → shipped` · `shipped → delivered`.

### Importes
Todos los importes son cadenas decimales con 2 decimales (p. ej. `"79.90"`), calculados por el servidor.
MD,
    contact: new OA\Contact(name: 'YamiLinares', url: 'https://github.com/YamiLinares/ecommerce-api-laravel12'),
    license: new OA\License(name: 'MIT', url: 'https://opensource.org/licenses/MIT'),
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Servidor local de desarrollo')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token JWT obtenido en /api/auth/login o /api/auth/register. Pegue solo el token (sin "Bearer ").',
)]
#[OA\Tag(name: 'Autenticación', description: 'Registro, inicio y cierre de sesión con JWT.')]
#[OA\Tag(name: 'Productos', description: 'Catálogo. Lectura pública; creación, edición y borrado solo para administradores.')]
#[OA\Tag(name: 'Órdenes', description: 'Órdenes de compra del usuario autenticado.')]
#[OA\Tag(name: 'Pagos', description: 'Pagos con Stripe (PaymentIntents) asociados a una orden.')]
#[OA\Tag(name: 'Administración', description: 'Gestión de órdenes para administradores.')]
#[OA\Tag(name: 'Webhooks', description: 'Eventos enviados por Stripe, verificados por firma.')]
final class ApiSpec {}
