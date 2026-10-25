<?php

/*
| CORS restrictivo: por defecto ningún origen externo puede llamar a la API desde
| un navegador. Si existe un frontend, declare sus orígenes separados por coma en
| CORS_ALLOWED_ORIGINS (p. ej. "https://tienda.example.com,http://localhost:5173").
| Swagger UI se sirve desde el mismo origen y no necesita CORS.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 3600,

    // Se usa JWT en cabecera, no cookies.
    'supports_credentials' => false,

];
