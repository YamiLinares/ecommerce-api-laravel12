<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Estructura uniforme de error: {"message": string, "code": string, "errors"?: object}.
 */
$errorResponse = static function (string $message, string $code, int $status, array $extra = [], array $headers = []): JsonResponse {
    return response()->json(array_merge(['message' => $message, 'code' => $code], $extra), $status, $headers);
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            SecurityHeaders::class,
        ]);
        $middleware->throttleApi('api');
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
        // Nunca existe una ruta "login" HTML: los invitados reciben 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($errorResponse): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // No registrar en logs datos sensibles enviados en peticiones.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', 'card', 'cvc']);

        $exceptions->dontReport([ApiException::class]);

        $exceptions->render(function (Throwable $e, Request $request) use ($errorResponse) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => $errorResponse(
                    $e->getMessage(), $e->errorCode, $e->status, $e->details ? ['errors' => $e->details] : []
                ),
                $e instanceof ValidationException => $errorResponse(
                    'Los datos enviados no son válidos.', 'VALIDATION_ERROR', 422, ['errors' => $e->errors()]
                ),
                $e instanceof AuthenticationException => $errorResponse(
                    'No autenticado. Envíe un token JWT válido en la cabecera Authorization: Bearer <token>.', 'UNAUTHENTICATED', 401
                ),
                // Laravel ya convirtió AuthorizationException / ModelNotFoundException en excepciones HTTP.
                $e instanceof HttpExceptionInterface => match ($e->getStatusCode()) {
                    403 => $errorResponse('No tiene permisos para realizar esta acción.', 'FORBIDDEN', 403),
                    404 => $errorResponse('Recurso no encontrado.', 'NOT_FOUND', 404),
                    405 => $errorResponse('Método HTTP no permitido para esta ruta.', 'METHOD_NOT_ALLOWED', 405, [], $e->getHeaders()),
                    429 => $errorResponse('Demasiadas solicitudes. Intente nuevamente más tarde.', 'TOO_MANY_REQUESTS', 429, [], $e->getHeaders()),
                    default => $errorResponse(
                        $e->getStatusCode() < 500 ? 'La solicitud no pudo procesarse.' : 'Servicio no disponible temporalmente.',
                        'HTTP_ERROR', $e->getStatusCode(), [], $e->getHeaders()
                    ),
                },
                // Nunca se exponen trazas ni mensajes internos al cliente.
                default => $errorResponse('Error interno del servidor.', 'SERVER_ERROR', 500),
            };
        });
    })->create();
