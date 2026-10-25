<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Protección: se comprueba antes de que RefreshDatabase migre o limpie tablas,
     * para que las pruebas nunca toquen la base de desarrollo.
     */
    protected function setUpTraits()
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Pruebas abortadas: la base de datos \"{$database}\" no es de pruebas (*_test).");
        }

        // Claves efímeras solo en memoria si .env.testing no las define.
        if (blank(config('jwt.secret'))) {
            config(['jwt.secret' => bin2hex(random_bytes(32))]);
        }
        if (blank(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        return parent::setUpTraits();
    }

    /** Cabeceras con un JWT real emitido para el usuario. */
    protected function jwtHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    /** Olvida el usuario y el token resueltos entre peticiones de una misma prueba. */
    protected function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
        JWTAuth::unsetToken();
    }
}
