<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza respuestas JSON en la API y rechaza cuerpos JSON malformados (400).
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $content = $request->getContent();

        if ($request->isJson() && $content !== '') {
            json_decode($content);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => 'El cuerpo de la petición no es un JSON válido.',
                    'code' => 'MALFORMED_JSON',
                ], 400);
            }
        }

        return $next($request);
    }
}
