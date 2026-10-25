<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Comprueba que Swagger UI responde y que el documento OpenAPI describe
 * exactamente las rutas reales de la API (ni de más ni de menos).
 */
class ApiDocumentationTest extends TestCase
{
    private static ?array $spec = null;

    private function spec(): array
    {
        if (self::$spec === null) {
            Artisan::call('l5-swagger:generate');
            self::$spec = json_decode(file_get_contents(storage_path('api-docs/api-docs.json')), true, flags: JSON_THROW_ON_ERROR);
        }

        return self::$spec;
    }

    public function test_swagger_ui_is_available(): void
    {
        $this->spec();

        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('swagger-ui', false);
    }

    public function test_openapi_document_is_served(): void
    {
        $this->spec();

        $this->get('/docs?api-docs.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.0')
            ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer');
    }

    public function test_every_api_route_is_documented_and_vice_versa(): void
    {
        $documented = [];
        foreach ($this->spec()['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $documented[] = strtoupper($method).' '.$path;
                }
            }
        }

        $actual = [];
        foreach (Route::getRoutes() as $route) {
            $uri = '/'.$route->uri();
            if (! str_starts_with($uri, '/api/') || str_starts_with($route->getName() ?? '', 'l5-swagger')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $actual[] = $method.' '.$uri;
                }
            }
        }

        sort($documented);
        sort($actual);
        $this->assertSame($actual, $documented);
    }

    public function test_protected_operations_declare_bearer_security(): void
    {
        $public = ['POST /api/auth/register', 'POST /api/auth/login', 'GET /api/products', 'GET /api/products/{product}', 'POST /api/webhooks/stripe'];

        foreach ($this->spec()['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $key = strtoupper($method).' '.$path;
                $security = $operation['security'] ?? [];

                if (in_array($key, $public, true)) {
                    $this->assertSame([], $security, "{$key} debería ser público");
                } else {
                    $this->assertSame([['bearerAuth' => []]], $security, "{$key} debería requerir JWT");
                    $this->assertArrayHasKey('401', $operation['responses'], "{$key} debe documentar 401");
                }
            }
        }
    }
}
