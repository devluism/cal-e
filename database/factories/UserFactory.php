<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /*
             * Un usuario SIEMPRE nace con su negocio.
             *
             * La invariante «no existe un usuario sin negocio» la impone la base
             * (`users.tenant_id` es NOT NULL), pero una factory que no la respeta rompe
             * `migrate:fresh --seed`. Y peor: como los tests creaban sus usuarios a mano,
             * la factory inválida pasaba desapercibida y las pruebas seguían verdes.
             * Una factory tiene que producir un modelo que la base acepte.
             *
             * Cada usuario de factory estrena negocio propio, que es lo correcto en la V1:
             * una cuenta es un negocio. Los tests que necesitan varios usuarios en el mismo
             * negocio pasan `tenant_id` explícito y este valor no llega a usarse.
             */
            'tenant_id' => Tenant::factory(),

            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'owner',

            // Explícito aunque la columna tenga default: `actingAs()` usa la instancia en
            // memoria, y un modelo que no sabe su propio `active` lo deja en null — que el
            // middleware leería como desactivado.
            'active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }
}
