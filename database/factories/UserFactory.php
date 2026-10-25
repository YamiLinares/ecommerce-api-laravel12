<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+503 7###-####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123!'),
        ];
    }

    /** El rol se asigna fuera de la asignación masiva, igual que en la aplicación. */
    public function admin(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->role = UserRole::Admin;
        });
    }
}
