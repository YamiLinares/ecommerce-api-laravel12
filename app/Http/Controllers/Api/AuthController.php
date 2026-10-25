<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // Solo campos validados; el rol queda siempre en "customer".
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));

        $token = $this->guard()->login($user);

        return $this->tokenResponse($token, $user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->guard()->attempt($request->validated());

        if (! $token) {
            throw new ApiException('Credenciales incorrectas.', 401, 'INVALID_CREDENTIALS');
        }

        return $this->tokenResponse($token, $this->guard()->user());
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** Invalida el token actual agregándolo a la lista negra. */
    public function logout(): JsonResponse
    {
        $this->guard()->logout();

        return response()->json(['message' => 'Sesión cerrada correctamente. El token fue invalidado.']);
    }

    /** Emite un token nuevo e invalida el anterior. */
    public function refresh(): JsonResponse
    {
        $guard = $this->guard();
        $token = $guard->refresh();

        return $this->tokenResponse($token, $guard->setToken($token)->user());
    }

    private function tokenResponse(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->factory()->getTTL() * 60,
                'user' => new UserResource($user),
            ],
        ], $status);
    }

    private function guard(): JWTGuard
    {
        return auth('api');
    }
}
