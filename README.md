# API de E-commerce Segura con Swagger Completo

API REST de comercio electrónico construida con **Laravel 12**, **MySQL**, autenticación **JWT**, pagos con **Stripe** (modo de pruebas) y documentación interactiva **Swagger / OpenAPI 3**.

Incluye registro e inicio de sesión de clientes, catálogo de productos con permisos de administrador, órdenes de compra con control de inventario transaccional, pagos con PaymentIntents confirmados por webhooks firmados, y una batería de pruebas automatizadas sobre MySQL.

---

## Índice

1. [Objetivo académico](#1-objetivo-académico)
2. [Tecnologías](#2-tecnologías)
3. [Características implementadas](#3-características-implementadas)
4. [Requisitos previos](#4-requisitos-previos)
5. [Estructura del proyecto](#5-estructura-del-proyecto)
6. [Instalación paso a paso](#6-instalación-paso-a-paso)
7. [Configuración de Stripe y webhooks](#7-configuración-de-stripe-y-webhooks)
8. [Ejecutar el servidor y abrir Swagger UI](#8-ejecutar-el-servidor-y-abrir-swagger-ui)
9. [Ejemplos de uso](#9-ejemplos-de-uso)
10. [Lista de endpoints](#10-lista-de-endpoints)
11. [Modelo de datos](#11-modelo-de-datos)
12. [Seguridad](#12-seguridad)
13. [Pruebas automatizadas](#13-pruebas-automatizadas)
14. [Solución de problemas](#14-solución-de-problemas)
15. [Recomendaciones para producción](#15-recomendaciones-para-producción)
16. [Credenciales y archivos sensibles](#16-credenciales-y-archivos-sensibles)

---

## 1. Objetivo académico

Diseñar, implementar, probar y documentar una API de e-commerce que aplique buenas prácticas profesionales de backend:

- autenticación stateless con JWT y autorización por rol y por recurso;
- integridad de inventario y de importes ante compras simultáneas;
- integración segura con un proveedor de pagos real (Stripe) y verificación de webhooks;
- documentación completa y ejecutable con Swagger;
- pruebas automatizadas que demuestren cada requisito.

## 2. Tecnologías

| Componente | Versión / paquete |
|---|---|
| PHP | 8.2 o superior (probado con 8.4) |
| Framework | Laravel 12 |
| Base de datos | MySQL 8 / 9 (probado con 9.1) |
| JWT | [`php-open-source-saver/jwt-auth`](https://github.com/PHP-Open-Source-Saver/jwt-auth) 2.x — fork mantenido de `tymon/jwt-auth` con soporte para Laravel 12 |
| Swagger / OpenAPI | [`darkaonline/l5-swagger`](https://github.com/DarkaOnLine/L5-Swagger) 11.x (swagger-php 6, atributos PHP 8) |
| Pagos | [`stripe/stripe-php`](https://github.com/stripe/stripe-php) (biblioteca oficial), API PaymentIntents |
| Pruebas | PHPUnit 11 |
| Estilo de código | Laravel Pint |

> `tymon/jwt-auth` original no publica versiones compatibles con Laravel 12; por eso se usa su fork mantenido, que conserva la misma API.

## 3. Características implementadas

**Usuarios y autenticación**
- Registro de clientes, login, logout (invalidación del token en lista negra), renovación de token y perfil.
- Tokens JWT HS256 con expiración de 60 minutos; contraseñas con bcrypt.
- Roles `customer` y `admin`. El rol nunca se acepta desde la petición (no hay escalada de privilegios).

**Catálogo**
- CRUD completo. Lectura pública; creación, edición y borrado solo para administradores.
- Paginación, búsqueda, filtros por precio y stock, ordenamiento.
- Borrado lógico (soft delete) para conservar el historial de órdenes.

**Órdenes**
- Creación con reserva de stock en una transacción con bloqueo de filas (`SELECT ... FOR UPDATE`).
- El servidor calcula precios, subtotales y total (en centavos enteros, sin `float`).
- Copia histórica del nombre, SKU y precio unitario en `order_items`.
- Historial del usuario, detalle protegido contra IDOR, cancelación con devolución de stock.
- Transiciones de estado validadas (ver [diagrama](#estados-de-una-orden)).

**Pagos con Stripe**
- PaymentIntent por el total de la orden, con clave de idempotencia y metadatos (orden, usuario, pago).
- La orden se marca `paid` **solo** al recibir el webhook firmado `payment_intent.succeeded`, tras verificar importe y moneda.
- Webhooks con verificación de firma, idempotencia por `event.id` y manejo de pagos pendientes, en proceso, exitosos, fallidos y cancelados.
- No se almacena ningún dato de tarjeta.

**Transversal**
- Form Requests para todas las entradas, mensajes de validación en español.
- Errores JSON uniformes, sin trazas ni detalles internos.
- Rate limiting, CORS restrictivo, cabeceras de seguridad.
- Swagger UI completo en `/api/documentation`.

## 4. Requisitos previos

- PHP 8.2+ con extensiones `pdo_mysql`, `openssl`, `mbstring`, `bcmath`, `curl`.
- Composer 2.
- MySQL 8 o superior (por ejemplo, el de WampServer o XAMPP).
- Git.
- Opcional: [Stripe CLI](https://docs.stripe.com/stripe-cli) para reenviar webhooks a tu máquina.

Comprobación rápida en **PowerShell** (por ejemplo, la terminal integrada de VS Code: *Terminal → New Terminal*):

```powershell
php -v
composer --version
mysql --version
git --version
```

## 5. Estructura del proyecto

```text
app/
├── Enums/                 # UserRole, OrderStatus (transiciones), PaymentStatus
├── Exceptions/            # ApiException: errores de dominio con código HTTP y código propio
├── Http/
│   ├── Controllers/Api/   # Auth, Product, Order, Payment, StripeWebhook, Admin/Order
│   ├── Middleware/        # ForceJsonResponse, SecurityHeaders, EnsureUserIsAdmin
│   ├── Requests/          # Form Requests (Auth, Product, Order, Payment)
│   └── Resources/         # Transformación de respuestas JSON
├── Models/                # User, Product, Order, OrderItem, Payment, StripeWebhookEvent
├── OpenApi/               # Especificación Swagger (atributos PHP), separada de los controladores
├── Policies/              # OrderPolicy, ProductPolicy
├── Services/              # OrderService, PaymentService, StripeWebhookService
│   └── Payments/          # PaymentGateway (contrato) + StripePaymentGateway
└── Support/Money.php      # Aritmética monetaria exacta en centavos
bootstrap/app.php          # Middleware y manejo centralizado de errores JSON
config/                    # jwt.php, l5-swagger.php, cors.php, services.php (Stripe)
database/
├── migrations/            # users, products, orders, order_items, payments, stripe_webhook_events
├── factories/
└── seeders/               # Usuarios demo y 21 productos de ejemplo
lang/es/validation.php     # Mensajes de validación en español
routes/api.php             # Rutas de la API
storage/api-docs/          # Documento OpenAPI generado (api-docs.json)
tests/
├── Feature/               # Pruebas HTTP de extremo a extremo sobre MySQL
├── Unit/                  # Dinero y transiciones de estado
└── Fakes/                 # Doble de Stripe para pruebas sin red
```

## 6. Instalación paso a paso

Todos los comandos son para **Windows PowerShell** y se ejecutan en la carpeta del proyecto (en VS Code: *File → Open Folder* y luego la terminal integrada).

### 6.1 Clonar el repositorio

```powershell
git clone https://github.com/YamiLinares/ecommerce-api-laravel12.git
cd ecommerce-api-laravel12
```

### 6.2 Instalar dependencias

```powershell
composer install
```

### 6.3 Crear la base de datos MySQL

Inicia MySQL (en WampServer: icono verde). Luego abre el cliente de MySQL como administrador:

```powershell
mysql -u root -p
```

Dentro de MySQL, crea las bases y un usuario exclusivo para el proyecto. **Reemplaza `TU_CONTRASEÑA_SEGURA`** por una contraseña propia:

```sql
CREATE DATABASE ecommerce_api_laravel12 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ecommerce_api_laravel12_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ecommerce_app'@'localhost' IDENTIFIED BY 'TU_CONTRASEÑA_SEGURA';
GRANT ALL PRIVILEGES ON ecommerce_api_laravel12.* TO 'ecommerce_app'@'localhost';
GRANT ALL PRIVILEGES ON ecommerce_api_laravel12_test.* TO 'ecommerce_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> La segunda base (`_test`) la usan las pruebas automatizadas y se borra en cada ejecución. Nunca apuntes las pruebas a la base de desarrollo: el proyecto se niega a ejecutarlas si el nombre no termina en `_test`.

### 6.4 Configurar el archivo `.env`

```powershell
Copy-Item .env.example .env
Copy-Item .env.testing.example .env.testing
```

Edita `.env` (y `.env.testing`) y completa `DB_PASSWORD` con la contraseña del paso anterior. Ambos archivos están ignorados por Git.

### 6.5 Generar la clave de la aplicación y la clave JWT

```powershell
php artisan key:generate
php artisan jwt:secret
```

`jwt:secret` escribe `JWT_SECRET` en `.env`. Es la clave con la que se firman los tokens: no la compartas.

### 6.6 Migraciones y datos de ejemplo

Opcionalmente define en `.env` las contraseñas de los usuarios demo (`SEED_ADMIN_PASSWORD`, `SEED_CUSTOMER_PASSWORD`). Si las dejas vacías, el seeder genera contraseñas aleatorias y las muestra **una sola vez** en la consola.

```powershell
php artisan migrate --seed
```

Se crean:
- `admin@ecommerce.test` (rol `admin`) y `cliente@ecommerce.test` (rol `customer`);
- 21 productos de ejemplo (uno agotado y uno inactivo para probar filtros).

Los seeders son repetibles: volver a ejecutarlos no duplica datos ni cambia contraseñas existentes.

### 6.7 Generar la documentación Swagger

```powershell
php artisan l5-swagger:generate
```

## 7. Configuración de Stripe y webhooks

La API funciona sin Stripe (los pagos responden `503 PAYMENTS_NOT_CONFIGURED`). Para probar pagos reales en **modo de pruebas**:

### 7.1 Obtener las claves de prueba

1. Crea una cuenta gratuita o inicia sesión en <https://dashboard.stripe.com>.
2. Activa el interruptor **Test mode / Modo de prueba**.
3. Ve a **Developers → API keys** y copia:
   - *Publishable key* (`pk_test_...`) → `STRIPE_KEY`
   - *Secret key* (`sk_test_...`) → `STRIPE_SECRET`
4. Pégalas en tu `.env` local (nunca en el código ni en el chat).

### 7.2 Reenviar webhooks a tu máquina con Stripe CLI

1. Descarga Stripe CLI para Windows desde <https://github.com/stripe/stripe-cli/releases> (archivo `stripe_X.Y.Z_windows_x86_64.zip`) y descomprímelo.
2. Inicia sesión (abre el navegador para autorizar):
   ```powershell
   stripe login
   ```
3. En una terminal aparte, deja escuchando el reenvío:
   ```powershell
   stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe --events payment_intent.succeeded,payment_intent.payment_failed,payment_intent.processing,payment_intent.canceled
   ```
4. La CLI muestra `Your webhook signing secret is whsec_...`. Cópialo en `.env` como `STRIPE_WEBHOOK_SECRET`.
5. Limpia la caché de configuración si la usaste: `php artisan config:clear`.

### 7.3 Probar un pago completo

1. Inicia sesión como cliente y crea una orden (ver [ejemplos](#9-ejemplos-de-uso)).
2. Inicia el pago confirmándolo con una tarjeta de prueba de Stripe:
   ```json
   POST /api/orders/{id}/payments
   { "payment_method": "pm_card_visa" }
   ```
3. En la terminal de `stripe listen` verás `payment_intent.succeeded → 200`.
4. `GET /api/orders/{id}` muestra la orden en estado `paid` y el pago `succeeded`.

Métodos de prueba útiles: `pm_card_visa` (aprobado), `pm_card_chargeDeclined` (rechazado), `pm_card_visa_chargeDeclinedInsufficientFunds` (fondos insuficientes).

Con un frontend real, en lugar de `payment_method` se usa el `client_secret` devuelto con Stripe.js / Payment Element.

> Reenviar un mismo evento (`stripe events resend evt_...`) responde `duplicate: true` y no lo aplica dos veces.

## 8. Ejecutar el servidor y abrir Swagger UI

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

- **Swagger UI:** <http://127.0.0.1:8000/api/documentation>
- Documento OpenAPI (JSON): <http://127.0.0.1:8000/docs?api-docs.json>
- La raíz `http://127.0.0.1:8000/` redirige a Swagger.

### Autenticarse en Swagger UI

1. Abre **Autenticación → POST /api/auth/login**, pulsa *Try it out*, escribe el correo y la contraseña y pulsa *Execute*.
2. Copia `data.access_token` de la respuesta.
3. Pulsa **Authorize** (arriba a la derecha), pega el token **sin** la palabra `Bearer` y confirma.
4. Los endpoints con candado ya envían `Authorization: Bearer <token>` automáticamente.

Los resúmenes indican el nivel de acceso: 🌐 público, 🔒 autenticado, 🛡️ administrador, ✍️ firma de Stripe.

## 9. Ejemplos de uso

Ejemplos con PowerShell (`Invoke-RestMethod`). También puedes usarlos desde Swagger UI o Postman importando `http://127.0.0.1:8000/docs?api-docs.json`.

```powershell
$api = "http://127.0.0.1:8000/api"

# Registro (devuelve un token)
$body = @{ name = "Ana Pérez"; email = "ana@example.com"; password = "Secreta123"; password_confirmation = "Secreta123" } | ConvertTo-Json
$reg = Invoke-RestMethod -Method Post -Uri "$api/auth/register" -ContentType "application/json; charset=utf-8" -Body $body

# Login
$body = @{ email = "ana@example.com"; password = "Secreta123" } | ConvertTo-Json
$login = Invoke-RestMethod -Method Post -Uri "$api/auth/login" -ContentType "application/json" -Body $body
$headers = @{ Authorization = "Bearer $($login.data.access_token)" }

# Perfil
Invoke-RestMethod -Uri "$api/auth/me" -Headers $headers

# Catálogo público con filtros
Invoke-RestMethod -Uri "$api/products?search=teclado&min_price=10&sort=-price&per_page=5"

# Crear una orden (el servidor calcula precios y total)
$body = @{ items = @(@{ product_id = 1; quantity = 2 }, @{ product_id = 2; quantity = 1 }) } | ConvertTo-Json -Depth 3
$order = Invoke-RestMethod -Method Post -Uri "$api/orders" -Headers $headers -ContentType "application/json" -Body $body

# Historial y detalle
Invoke-RestMethod -Uri "$api/orders" -Headers $headers
Invoke-RestMethod -Uri "$api/orders/$($order.data.id)" -Headers $headers

# Iniciar el pago (requiere Stripe configurado)
$body = @{ payment_method = "pm_card_visa" } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$api/orders/$($order.data.id)/payments" -Headers $headers -ContentType "application/json" -Body $body

# Cerrar sesión (el token deja de funcionar)
Invoke-RestMethod -Method Post -Uri "$api/auth/logout" -Headers $headers
```

Operaciones de administrador (inicia sesión con `admin@ecommerce.test`):

```powershell
# Use la contraseña de SEED_ADMIN_PASSWORD (o la que mostró el seeder)
$body = @{ email = "admin@ecommerce.test"; password = "CONTRASEÑA_DEL_ADMIN" } | ConvertTo-Json
$admin = Invoke-RestMethod -Method Post -Uri "$api/auth/login" -ContentType "application/json" -Body $body
$adminHeaders = @{ Authorization = "Bearer $($admin.data.access_token)" }

$body = @{ sku = "TEC-100"; name = "Micrófono USB"; price = "64.90"; stock = 25 } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$api/products" -Headers $adminHeaders -ContentType "application/json" -Body $body
Invoke-RestMethod -Method Patch -Uri "$api/products/1" -Headers $adminHeaders -ContentType "application/json" -Body '{"price":"75.00"}'
Invoke-RestMethod -Method Delete -Uri "$api/products/1" -Headers $adminHeaders
Invoke-RestMethod -Method Patch -Uri "$api/admin/orders/1/status" -Headers $adminHeaders -ContentType "application/json" -Body '{"status":"shipped"}'
```

### Formato de respuestas

Éxito: `{"data": {...}}` (los listados agregan `links` y `meta` de paginación).

Error (siempre la misma estructura):

```json
{
  "message": "Los datos enviados no son válidos.",
  "code": "VALIDATION_ERROR",
  "errors": { "email": ["El campo correo electrónico es obligatorio."] }
}
```

| HTTP | `code` | Cuándo |
|---|---|---|
| 400 | `MALFORMED_JSON`, `INVALID_SIGNATURE`, `INVALID_PAYLOAD` | JSON inválido; firma de webhook incorrecta |
| 401 | `UNAUTHENTICATED`, `INVALID_CREDENTIALS` | Sin token, token inválido/expirado/invalidado; credenciales erróneas |
| 403 | `FORBIDDEN` | Cliente intentando una operación de administrador |
| 404 | `NOT_FOUND` | Recurso inexistente **o perteneciente a otro usuario** |
| 405 | `METHOD_NOT_ALLOWED` | Método HTTP no soportado |
| 409 | `INSUFFICIENT_STOCK`, `PRODUCT_UNAVAILABLE`, `INVENTORY_BUSY`, `INVALID_STATUS_TRANSITION`, `ORDER_NOT_PAYABLE`, `PAYMENT_IN_PROGRESS` | Conflictos de inventario o de estado |
| 422 | `VALIDATION_ERROR`, `AMOUNT_TOO_SMALL`, `ORDER_TOTAL_TOO_LARGE` | Datos inválidos |
| 429 | `TOO_MANY_REQUESTS` | Límite de solicitudes (cabecera `Retry-After`) |
| 500 | `SERVER_ERROR` | Error inesperado (sin detalles internos) |
| 502 / 503 | `PAYMENT_GATEWAY_ERROR` / `PAYMENTS_NOT_CONFIGURED`, `WEBHOOK_NOT_CONFIGURED` | Stripe no disponible o sin configurar |

## 10. Lista de endpoints

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/api/auth/register` | 🌐 Público | Registro de cliente (devuelve JWT) |
| POST | `/api/auth/login` | 🌐 Público | Inicio de sesión (devuelve JWT) |
| GET | `/api/auth/me` | 🔒 JWT | Perfil del usuario |
| POST | `/api/auth/logout` | 🔒 JWT | Invalida el token |
| POST | `/api/auth/refresh` | 🔒 JWT | Nuevo token; invalida el anterior |
| GET | `/api/products` | 🌐 Público | Listado paginado con filtros |
| GET | `/api/products/{id}` | 🌐 Público | Detalle de producto |
| POST | `/api/products` | 🛡️ Admin | Crear producto |
| PUT/PATCH | `/api/products/{id}` | 🛡️ Admin | Actualizar producto |
| DELETE | `/api/products/{id}` | 🛡️ Admin | Eliminar (soft delete) |
| GET | `/api/orders` | 🔒 JWT | Historial propio |
| POST | `/api/orders` | 🔒 JWT | Crear orden |
| GET | `/api/orders/{id}` | 🔒 Dueño o admin | Detalle con productos y pagos |
| POST | `/api/orders/{id}/cancel` | 🔒 Dueño o admin | Cancelar orden pendiente |
| GET | `/api/orders/{id}/payments` | 🔒 Dueño o admin | Pagos de la orden |
| POST | `/api/orders/{id}/payments` | 🔒 Dueño | Iniciar pago con Stripe |
| GET | `/api/admin/orders` | 🛡️ Admin | Todas las órdenes (filtros `status`, `user_id`) |
| PATCH | `/api/admin/orders/{id}/status` | 🛡️ Admin | Cambiar estado (`shipped`, `delivered`, `cancelled`) |
| POST | `/api/webhooks/stripe` | ✍️ Firma Stripe | Eventos de Stripe |

Filtros de `GET /api/products`: `search`, `min_price`, `max_price`, `in_stock`, `include_inactive` (solo admin), `sort` (`name`, `price`, `created_at`, con `-` para descendente), `per_page` (1-100), `page`.

### Estados de una orden

```text
            webhook payment_intent.succeeded
 pending ───────────────────────────────────► paid ──admin──► shipped ──admin──► delivered
    │
    └── cliente o admin (devuelve stock, cancela PaymentIntent) ──► cancelled
```

- `paid` **no** puede asignarse manualmente; solo lo establece el webhook firmado.
- `delivered` y `cancelled` son estados finales.
- Si el cobro está en proceso o ya se completó en Stripe, la cancelación responde `409 PAYMENT_IN_PROGRESS`.

Estados de pago: `pending` → `processing` → `succeeded`; o `failed` (se puede reintentar con otro método sobre el mismo PaymentIntent) / `canceled`.

## 11. Modelo de datos

```text
users 1───* orders 1───* order_items *───1 products
  │            │
  └──────* payments *──1┘          stripe_webhook_events (idempotencia)
```

| Tabla | Campos clave | Restricciones |
|---|---|---|
| `users` | `email` único, `password` (bcrypt), `role` enum | `role` por defecto `customer` |
| `products` | `sku` único, `price DECIMAL(10,2)`, `stock UNSIGNED INT`, `is_active`, `deleted_at` | Índices por `is_active+price` y `name` |
| `orders` | `user_id`, `status` enum, `subtotal`/`total DECIMAL(12,2)`, `currency`, `paid_at`, `cancelled_at` | FK `user_id` RESTRICT; índice `user_id+status` |
| `order_items` | `unit_price` (copia histórica), `product_name`, `product_sku`, `quantity`, `subtotal` | FK orden CASCADE, producto RESTRICT; único `order_id+product_id` |
| `payments` | `stripe_payment_intent_id` único, `amount`, `currency`, `status` enum, `failure_message`, `paid_at` | FK a orden y usuario RESTRICT |
| `stripe_webhook_events` | `stripe_event_id` único, `type`, `processed_at` | Garantiza procesar cada evento una sola vez |

Los importes se guardan como `DECIMAL` y se calculan en centavos enteros (`App\Support\Money`, con `bcmath`), nunca con `float`.

## 12. Seguridad

| Riesgo | Medida aplicada |
|---|---|
| Robo/falsificación de token | JWT HS256 firmado con `JWT_SECRET`, expiración de 60 min, lista negra al cerrar sesión o renovar |
| Fuerza bruta | Rate limiting: login/registro 5/min por correo+IP y 20/min por IP; órdenes 20/min; pagos 10/min; API 60/min |
| Escalada de privilegios | `role` fuera de `$fillable`; el registro siempre crea `customer`; middleware `admin` y policies |
| IDOR | `OrderPolicy`: solo dueño o admin; las órdenes ajenas responden 404 para no revelar su existencia |
| Asignación masiva | `$fillable` explícito; los controladores usan solo datos validados |
| Manipulación de precios/estado | Precios y totales calculados en el servidor; `total`, `user_id`, `status`, `price` del cliente se ignoran |
| Condiciones de carrera | Transacción + `SELECT ... FOR UPDATE` ordenado por id; reintentos ante deadlock; `409 INVENTORY_BUSY` |
| Webhooks falsos | Firma `Stripe-Signature` verificada con la biblioteca oficial y tolerancia de 5 minutos |
| Eventos repetidos | Tabla `stripe_webhook_events` con `UNIQUE` + bloqueo; respuesta `duplicate: true` |
| Pago marcado por el cliente | `paid` solo vía webhook, verificando importe y moneda contra el pago registrado |
| Pagos duplicados | Clave de idempotencia de Stripe por pago y reutilización del PaymentIntent abierto |
| Inyección SQL | Eloquent / consultas parametrizadas; comodines `LIKE` escapados |
| Fuga de información | Errores JSON genéricos; nunca trazas; `password` oculto en modelos |
| Datos de tarjeta | No se reciben ni guardan; solo IDs de Stripe (`pm_...`, `pi_...`) |
| Logs | Sin contraseñas, tokens ni claves; los errores de Stripe registran solo código y request id |
| CORS | Ningún origen permitido por defecto (`CORS_ALLOWED_ORIGINS`) |
| Cabeceras | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Cache-Control: no-store`, HSTS bajo HTTPS |

## 13. Pruebas automatizadas

Las pruebas usan la base **MySQL** `ecommerce_api_laravel12_test` (necesaria para verificar bloqueos reales de InnoDB) y un doble de Stripe, por lo que no requieren claves.

```powershell
php artisan test
```

Resultado verificado: **93 pruebas, 439 aserciones, todas aprobadas**.

| Archivo | Qué verifica |
|---|---|
| `AuthTest` | Registro, hash de contraseña, no escalada de rol, validaciones, login y emisión de JWT, rate limiting, tokens inválidos/manipulados/expirados, logout y refresh |
| `ProductTest` | Listado público, paginación, filtros, inactivos, CRUD de admin, 401/403, validaciones, soft delete |
| `OrderTest` | Totales calculados en servidor, copia histórica del precio, stock insuficiente sin efectos parciales, última unidad, `FOR UPDATE`, validaciones, separación entre usuarios, cancelación y transiciones |
| `OrderConcurrencyTest` | Bloqueo real de fila con una segunda conexión MySQL concurrente |
| `PaymentTest` | Importe del servidor, idempotencia, orden no pagada sin webhook, tarjeta rechazada, errores de Stripe 502/503, acceso ajeno |
| `StripeWebhookTest` | Firma válida/inválida/expirada/manipulada, idempotencia, éxito, fallo, cancelación, importe incorrecto, eventos fuera de orden |
| `StripeGatewayIntegrationTest` | Cliente oficial `stripe-php` contra [stripe-mock](https://github.com/stripe/stripe-mock) (se omite si no está en ejecución) |
| `ErrorResponseTest` | 400/404/405/500 en JSON, sin fugas, cabeceras de seguridad, CORS |
| `ApiDocumentationTest` | Swagger UI responde y el OpenAPI documenta exactamente todas las rutas reales con su seguridad |
| `Unit/*` | Aritmética monetaria exacta y máquina de estados de órdenes |

Para ejecutar también la integración con stripe-mock (opcional):

```powershell
stripe-mock.exe -http-port 12111   # en otra terminal
php artisan test --group=stripe-mock
```

## 14. Solución de problemas

| Problema | Solución |
|---|---|
| `SQLSTATE[HY000] [2002]` / no conecta a MySQL | Inicia MySQL (WampServer en verde) y revisa `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` |
| `Access denied for user` | Repite el `GRANT` del paso 6.3 o corrige la contraseña en `.env` |
| `Secret is not set` / tokens no se generan | Ejecuta `php artisan jwt:secret` |
| Siempre `401` en Swagger | Pulsa *Authorize* y pega solo el token (sin `Bearer`); si expiró (60 min), vuelve a hacer login |
| Swagger UI vacío o desactualizado | `php artisan l5-swagger:generate` |
| Cambios en `.env` no se aplican | `php artisan config:clear` |
| Pagos responden `503 PAYMENTS_NOT_CONFIGURED` | Configura `STRIPE_SECRET` (sección 7.1) |
| Webhook responde `400 INVALID_SIGNATURE` | `STRIPE_WEBHOOK_SECRET` debe ser el `whsec_...` que muestra la sesión actual de `stripe listen` |
| La orden sigue `pending` tras pagar | Verifica que `stripe listen` esté corriendo y responda `200` |
| `429 TOO_MANY_REQUESTS` | Espera el tiempo indicado en `Retry-After` |
| Las pruebas se abortan por la base de datos | `.env.testing` debe apuntar a una base cuyo nombre termine en `_test` |
| Caracteres acentuados se ven mal con `curl` en Windows | Envía el cuerpo desde un archivo UTF-8 o usa `Invoke-RestMethod` / Swagger |

## 15. Recomendaciones para producción

- `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`.
- Servir solo por **HTTPS** (activa HSTS automáticamente).
- Usar claves de Stripe **live** únicamente en el servidor y registrar el endpoint de webhook en *Dashboard → Developers → Webhooks* con su propio `whsec_...`.
- Usuario MySQL con privilegios mínimos (no `root`) y contraseña robusta.
- `CACHE_STORE=redis` para la lista negra de JWT y el rate limiting si hay varios servidores.
- Optimizar: `composer install --no-dev --optimize-autoloader`, `php artisan config:cache`, `php artisan route:cache`.
- Restringir o desactivar Swagger UI público (`L5_SWAGGER_GENERATE_ALWAYS=false` y middleware en `config/l5-swagger.php`).
- Definir `CORS_ALLOWED_ORIGINS` solo con los dominios del frontend.
- Rotar `JWT_SECRET` y claves de Stripe periódicamente y ante cualquier sospecha de exposición.
- Monitorear los logs `critical` de pagos (cobros sobre órdenes canceladas requieren reembolso manual).

## 16. Credenciales y archivos sensibles

- **Nunca** subas `.env`, `.env.testing` ni ningún archivo con claves. Están en `.gitignore`.
- `.env.example` y `.env.testing.example` contienen solo nombres de variables y valores vacíos o ficticios.
- No pegues claves en el código, en issues ni en mensajes. Usa solo claves de **prueba** (`sk_test_...`) en desarrollo.
- Si una clave se expone accidentalmente: revócala/rótala de inmediato (Stripe Dashboard → API keys → *Roll key*; `php artisan jwt:secret --force` para JWT) y elimínala del historial.
- Antes de cada commit se ejecutó un escaneo con [Gitleaks](https://github.com/gitleaks/gitleaks):
  ```powershell
  gitleaks git -v .
  gitleaks dir -v .
  ```

---

Proyecto académico desarrollado por [YamiLinares](https://github.com/YamiLinares). Licencia MIT.
