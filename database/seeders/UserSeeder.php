<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crea un administrador y un cliente de demostración.
 *
 * Las contraseñas se leen de SEED_ADMIN_PASSWORD / SEED_CUSTOMER_PASSWORD (.env).
 * Si están vacías se genera una aleatoria y se muestra una sola vez en consola.
 * Es repetible: no duplica usuarios ni cambia contraseñas existentes.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUser(
            (string) env('SEED_ADMIN_EMAIL', 'admin@ecommerce.test'),
            'Administrador Demo',
            env('SEED_ADMIN_PASSWORD'),
            UserRole::Admin,
        );

        $this->seedUser(
            (string) env('SEED_CUSTOMER_EMAIL', 'cliente@ecommerce.test'),
            'Cliente Demo',
            env('SEED_CUSTOMER_PASSWORD'),
            UserRole::Customer,
        );
    }

    private function seedUser(string $email, string $name, ?string $password, UserRole $role): void
    {
        if (User::where('email', $email)->exists()) {
            $this->command?->info("Usuario {$email} ya existe; se conserva sin cambios.");

            return;
        }

        $generated = blank($password);
        $password = $generated ? Str::password(16, symbols: false) : $password;

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        $this->command?->info("Usuario {$role->value} creado: {$email}");

        if ($generated) {
            $this->command?->warn("Contraseña generada para {$email} (guárdala, no se volverá a mostrar): {$password}");
        }
    }
}
