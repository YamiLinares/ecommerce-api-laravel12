<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_route_returns_json_404(): void
    {
        $this->get('/api/no-existe')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Recurso no encontrado.', 'code' => 'NOT_FOUND']);
    }

    public function test_wrong_http_method_returns_405(): void
    {
        $this->deleteJson('/api/auth/login')->assertStatus(405)->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
    }

    public function test_malformed_json_returns_400(): void
    {
        $this->call('POST', '/api/auth/login', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"email": ')
            ->assertStatus(400)
            ->assertJsonPath('code', 'MALFORMED_JSON');
    }

    public function test_unauthenticated_request_without_accept_header_still_gets_json_401(): void
    {
        $this->get('/api/orders')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_internal_errors_do_not_leak_details(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->get('/api/_test/boom', fn () => throw new RuntimeException('SQLSTATE secreto: password=xyz'));

        $response = $this->getJson('/api/_test/boom');

        $response->assertStatus(500)->assertExactJson(['message' => 'Error interno del servidor.', 'code' => 'SERVER_ERROR']);
        $this->assertStringNotContainsString('secreto', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_api_responses_include_security_headers(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_user_payload_never_exposes_password_hash(): void
    {
        $user = User::factory()->create();

        $content = $this->getJson('/api/auth/me', $this->jwtHeaders($user))->assertOk()->getContent();

        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString($user->password, $content);
    }

    public function test_cors_does_not_allow_arbitrary_origins_by_default(): void
    {
        $this->withHeaders(['Origin' => 'https://sitio-malicioso.example'])
            ->getJson('/api/products')
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
