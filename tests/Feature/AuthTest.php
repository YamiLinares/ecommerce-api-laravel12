<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'phone' => '+503 7000-0000',
            'password' => 'Secreta123',
            'password_confirmation' => 'Secreta123',
        ], $overrides);
    }

    public function test_customer_can_register_and_receives_jwt(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email', 'role']]])
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.expires_in', 3600)
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Secreta123', $user->password));
        $this->assertNotSame('Secreta123', $user->password);
        $this->assertSame(3, count(explode('.', $response->json('data.access_token'))));
    }

    public function test_registration_cannot_escalate_to_admin(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload(['role' => 'admin']))
            ->assertCreated()
            ->assertJsonPath('data.user.role', 'customer');

        $this->assertSame(UserRole::Customer, User::where('email', 'ana@example.com')->first()->role);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'ana@example.com',
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/auth/register', $this->registerPayload(['email' => 'no-es-correo']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'Secreta123']);

        $response = $this->postJson('/api/auth/login', ['email' => 'ANA@example.com', 'password' => 'Secreta123']);

        $response->assertOk()->assertJsonStructure(['data' => ['access_token', 'expires_in', 'user']]);

        $payload = JWTAuth::setToken($response->json('data.access_token'))->getPayload();
        $this->assertSame((string) User::first()->id, (string) $payload->get('sub'));
        $this->assertNotNull($payload->get('exp'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'Secreta123']);

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Incorrecta1'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Credenciales incorrectas.', 'code' => 'INVALID_CREDENTIALS']);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'Secreta123']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Mala12345'])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Secreta123'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/auth/me', $this->jwtHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_invalid_and_tampered_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        [$header, $payload, $signature] = explode('.', $token);

        $forgedPayload = rtrim(strtr(base64_encode(json_encode(['sub' => '999', 'role' => 'admin'])), '+/', '-_'), '=');

        foreach (['no-es-un-jwt', "{$header}.{$forgedPayload}.{$signature}", "{$header}.{$payload}.firmaFalsa"] as $bad) {
            $this->resetAuth();
            $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$bad}"])
                ->assertUnauthorized()
                ->assertJsonPath('code', 'UNAUTHENTICATED');
        }
    }

    public function test_expired_token_is_rejected(): void
    {
        $headers = $this->jwtHeaders(User::factory()->create());

        $this->travel(61)->minutes();

        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    public function test_logout_invalidates_token(): void
    {
        $headers = $this->jwtHeaders(User::factory()->create());

        $this->postJson('/api/auth/logout', [], $headers)->assertOk();

        $this->resetAuth();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }

    public function test_refresh_issues_new_token_and_invalidates_previous(): void
    {
        $headers = $this->jwtHeaders(User::factory()->create());

        $this->travel(2)->seconds();
        $newToken = $this->postJson('/api/auth/refresh', [], $headers)->assertOk()->json('data.access_token');

        $this->assertNotSame($headers['Authorization'], 'Bearer '.$newToken);

        $this->resetAuth();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();

        $this->resetAuth();
        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$newToken])->assertOk();
    }
}
